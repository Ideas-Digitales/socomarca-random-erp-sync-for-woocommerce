<?php

namespace Socomarca\RandomERP\Services;

class PriceListService extends BaseApiService {
    
    public function getPriceLists() {
        
        $company_code = get_option('sm_company_code', '01');
        $endpoint = "/web32/precios/pidelistaprecio?empresa={$company_code}";
        
        
        $priceLists = $this->makeApiRequest($endpoint);
        


        
        if ($priceLists !== false && is_array($priceLists)) {

            

            $post_data = [
                'post_title'   => $priceLists['nombre'],
                'post_content' => '', // Puedes agregar contenido si es necesario
                'post_status'  => 'publish',
                'post_type'    => 'b2bking_group',
            ];

            
            $existing_post = get_page_by_title($post_data['post_title'], OBJECT, 'b2bking_group');
            if (!$existing_post) {
                $post_id = wp_insert_post($post_data);
            } else {
                $post_id = $existing_post->ID;
            }

            foreach ($priceLists as $priceList) {
                foreach ($priceLists['datos'] as $data) {

                    $products = wc_get_products( array( 'sku' => $data['kopr'] ) );
                    foreach ($products as $product) {
                        //Agrega meta a producto segun b2b king: https://woocommerce-b2b-plugin.com/docs/b2bking-tiered-pricing-setup-auto-generated-tiered-pricing-table/#2-toc-title
                        $meta_name = "b2bking_product_pricetiers_group_".$post_id;

                        $principal_unit = $data['venderen']; // Unidad principal publicada; 1=primera, 2=segunda, 0=ambas
                        
                        // Filtrar unidades según venderen
                        $filtered_unidades = [];
                        foreach ($data['unidades'] as $index => $unidad) {
                            // 1=primera,
                            if ($principal_unit == 1 && $index != 0) {
                                continue; 
                            }
                            // 2=segunda
                            if ($principal_unit == 2 && $index != 1) {
                                continue;
                            }
                            // 0=ambas - no filtrar
                            
                            $filtered_unidades[] = $unidad;
                        }

                        // Recopilar nombres de las unidades filtradas
                        $unidades_nombres = [];
                        foreach ($filtered_unidades as $unidad) {
                            $unidades_nombres[] = $unidad['nombre'];
                        }

                        // Actualizar el atributo "Unidad" del producto con todas las unidades
                        $attributes = $product->get_attributes();
                        $unidad_attribute = new \WC_Product_Attribute();
                        $unidad_attribute->set_id(0);
                        $unidad_attribute->set_name('Unidad');
                        $unidad_attribute->set_options($unidades_nombres);
                        $unidad_attribute->set_visible(true);
                        $unidad_attribute->set_variation(true); // Para que pueda usarse en variaciones
                        
                        $attributes['pa_unidad'] = $unidad_attribute; // pa_ prefix para atributos globales
                        $product->set_attributes($attributes);
                        $product->save();

                        $variations = $this->get_product_variations($product->get_id());
                        
                        // Procesar cada variación individualmente
                        foreach ($variations as $variation) {
                            $variation_object = wc_get_product($variation['id']);
                            $variation_object->set_manage_stock(true);
                            
                            // Encontrar la unidad que corresponde a esta variación
                            $matching_unidad = null;
                            foreach ($filtered_unidades as $unidad) {
                                // Comparar el nombre de la unidad con los atributos de la variación
                                foreach ($variation['attributes'] as $attribute) {
                                    foreach ($attribute as $attribute_name => $attribute_value) {
                                        if ($attribute_value == $unidad['nombre']) {
                                            $matching_unidad = $unidad;
                                            break 3; // Salir de todos los loops
                                        }
                                    }
                                }
                            }
                            
                            // Si encontramos la unidad correspondiente, actualizar stock y precios
                            if ($matching_unidad) {
                                // Actualizar stock con el valor correcto de esta unidad
                                $variation_object->set_stock_quantity($matching_unidad['stockventa']);
                                
                                // Configurar precios B2B King
                                update_post_meta($variation['id'], 'b2bking_regular_product_price_group_'.$post_id, $matching_unidad['prunneto'][0]['f']);
                                
                                $high_price = '';
                                $b2b_king_values = "";
                                foreach ($matching_unidad['prunneto'] as $prunneto) {
                                    $b2b_king_values .= $prunneto['min'].':'.$prunneto['f'].';';
                                    $high_price = $prunneto['f'];
                                    if ($high_price >= $prunneto['f']) {
                                        $high_price = $prunneto['f'];
                                    }
                                }
                                
                                // Precio por defecto de la variación
                                $variation_object->set_regular_price($high_price);
                                $variation_object->save();

                                // Agregar precios a la variación según la lista de precios
                                update_post_meta($variation['id'], $meta_name, $b2b_king_values);
                            } else {
                                // Si no se encuentra unidad correspondiente, al menos guardar la variación
                                $variation_object->save();
                            }
                        }
                    }
                }
            }
            return [
                'success' => true,
                'message' => count($priceLists) . ' listas de precios obtenidas exitosamente',
                'data' => $priceLists,
                'quantity' => count($priceLists)
            ];
        }
        
        return [
            'success' => false,
            'message' => 'No se pudieron obtener las listas de precios del ERP'
        ];
    }

    public function get_product_variations( $product_id ) {
        $product = wc_get_product( $product_id );
    
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return []; // No es un producto variable o no existe.
        }
    
        $variations = [];

        $attributes = $product->get_attributes();
        $attribute_values = [];
        foreach ($attributes as $attribute_name => $attribute) {
            $attribute_values[$attribute_name] = $attribute->get_options();
        }

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            $variations[] = [
                'id' => $variation_id,
                'attributes' => $attribute_values
            ];
        }
        return $variations; // Devuelve un array con el id de las variaciones, su nombre y los valores de los atributos.
    }
}