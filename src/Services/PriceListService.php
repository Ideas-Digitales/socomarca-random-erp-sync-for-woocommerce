<?php

namespace Socomarca\RandomERP\Services;

class PriceListService extends BaseApiService {
    
    public function getPriceLists() {
        error_log('PriceListService: Obteniendo listas de precios...');
        
        $company_code = get_option('sm_company_code', '01');
        $endpoint = "/web32/precios/pidelistaprecio?empresa={$company_code}";
        
        error_log("PriceListService: Endpoint: {$endpoint}");
        
        $priceLists = $this->makeApiRequest($endpoint);
        
        error_log('PriceListService: Respuesta raw de API: ' . substr(print_r($priceLists, true), 0, 1000));


        
        if ($priceLists !== false && is_array($priceLists)) {
            error_log('PriceListService: ' . count($priceLists) . ' listas de precios obtenidas');

            

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

                        $variations = $this->get_product_variations($product->get_id());
                        foreach ($variations as $variation) {
                            foreach ($data['unidades'] as $unidad) {

                                //Actualizar stock de la variacion
                                $variation_object = wc_get_product($variation['id']);
                                $variation_object->set_manage_stock(true); 
                                $variation_object->set_stock_quantity($unidad['stockventa']);

                                //Comparar el nombre de la unidad con los atributos de la variacion para ver si existen
                                $found = false;
                                foreach ($variation['attributes'] as $attribute) {
                                    foreach ($attribute as $attribute_name => $attribute_value) {
                                        if($attribute_value == $unidad['nombre']){ 
                                            $found = true;
                                        } else {
                                            //Si no existe, agrega el valor al atributo
                                            //TODO: Agregar los valores de los atributos a la variacion.. quizas no es necesario
                                            //$variation_object->set_attribute($attribute_name, $unidad['nombre']);
                                        }
                                    }
                                }
                                if ($found) {
                                    $low_price = ''; //Para guardar el precio mas bajo y asignar la variacion sin grupo b2b
                                    $b2b_king_values = "";
                                    foreach ($unidad['prunneto'] as $prunneto) {
                                        $b2b_king_values .= $prunneto['f'].':'.$prunneto['max'].';';
                                        $low_price = $prunneto['f'];
                                        if($low_price <= $prunneto['f']){
                                            $low_price = $prunneto['f'];
                                        }
                                    }
                                }
                            }
                            
                            //Precio por defecto de la variacion
                            $variation_object->set_regular_price($low_price);
                            $variation_object->save();

                            //Agregar precios a la variacion segun la lista de precios
                            update_post_meta($variation['id'], $meta_name, $b2b_king_values);
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
        
        error_log('PriceListService: Error - No se pudieron obtener listas de precios válidas');
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