<?php

namespace Socomarca\RandomERP\Services;

use Exception;

class PriceListService extends BaseApiService {

    /**
     * Obtiene las listas de precios del ERP y las guarda en cache
     */
    public function getPriceLists() {
        $company_code = get_option('sm_company_code', '01');
        $endpoint = "/web32/precios/pidelistaprecio?empresa={$company_code}";

        // Usar timeout de 120 segundos para listas de precios
        $priceLists = $this->makeApiRequest($endpoint, 'GET', null, 120);

        if ($priceLists === false || !is_array($priceLists)) {
            return [
                'success' => false,
                'message' => 'No se pudieron obtener las listas de precios del ERP'
            ];
        }

        // Crear grupo B2B King si no existe
        $group_id = $this->createB2BKingGroup($priceLists);

        // Guardar datos en cache para procesamiento por lotes
        $datos = isset($priceLists['datos']) ? $priceLists['datos'] : [];
        update_option('sm_price_lists_cache', $datos);
        update_option('sm_price_lists_group_id', $group_id);
        update_option('sm_total_processed_prices', 0);
        update_option('sm_total_updated_prices', 0);

        return [
            'success' => true,
            'message' => count($datos) . ' productos con precios obtenidos. Iniciando procesamiento...',
            'total' => count($datos),
            'group_id' => $group_id
        ];
    }

    /**
     * Crea el grupo B2B King si no existe
     */
    private function createB2BKingGroup($priceLists) {
        $post_data = [
            'post_title'   => $priceLists['nombre'] ?? 'Lista de Precios',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'b2bking_group',
        ];

        $existing_post = get_page_by_title($post_data['post_title'], OBJECT, 'b2bking_group');
        if (!$existing_post) {
            return wp_insert_post($post_data);
        }
        return $existing_post->ID;
    }

    /**
     * Procesa un lote de productos con precios
     */
    public function processBatchPriceLists($offset = 0, $batch_size = 10) {
        $cached_data = get_option('sm_price_lists_cache', []);
        $group_id = get_option('sm_price_lists_group_id', 0);

        if (empty($cached_data)) {
            return [
                'success' => false,
                'message' => 'No hay datos de precios en cache'
            ];
        }

        if (empty($group_id)) {
            return [
                'success' => false,
                'message' => 'No se encontro el grupo B2B King'
            ];
        }

        $batch = array_slice($cached_data, $offset, $batch_size);
        $processed_count = 0;
        $updated_count = 0;
        $errors = [];

        $total_processed = intval(get_option('sm_total_processed_prices', 0));
        $total_updated = intval(get_option('sm_total_updated_prices', 0));

        foreach ($batch as $data) {
            try {
                $result = $this->processProductPrice($data, $group_id);

                if ($result['success']) {
                    $processed_count++;
                    if ($result['updated']) {
                        $updated_count++;
                    }
                } else {
                    $errors[] = $result['error'];
                }
            } catch (Exception $e) {
                $errors[] = 'Error procesando producto ' . ($data['kopr'] ?? 'desconocido') . ': ' . $e->getMessage();
            }
        }

        $processed = $offset + count($batch);
        $total = count($cached_data);
        $is_complete = $processed >= $total;

        $total_processed += $processed_count;
        $total_updated += $updated_count;
        update_option('sm_total_processed_prices', $total_processed);
        update_option('sm_total_updated_prices', $total_updated);

        if ($is_complete) {
            delete_option('sm_price_lists_cache');
            delete_option('sm_price_lists_group_id');
            delete_option('sm_total_processed_prices');
            delete_option('sm_total_updated_prices');
        }

        return [
            'success' => true,
            'processed_batch' => $processed_count,
            'updated_batch' => $updated_count,
            'total_processed' => $total_processed,
            'total_updated' => $total_updated,
            'errors' => $errors,
            'processed' => $processed,
            'total' => $total,
            'is_complete' => $is_complete,
            'message' => "Lote procesado: $processed_count productos, $updated_count actualizados"
        ];
    }

    /**
     * Procesa los precios de un producto individual
     */
    private function processProductPrice($data, $group_id) {
        $sku = $data['kopr'] ?? '';

        if (empty($sku)) {
            return ['success' => false, 'error' => 'SKU vacio', 'updated' => false];
        }

        $products = wc_get_products(['sku' => $sku]);

        if (empty($products)) {
            return ['success' => true, 'error' => null, 'updated' => false]; // Producto no existe, saltar
        }

        $product = $products[0];
        $updated = false;

        // Recopilar todas las unidades del producto
        $unidades_nombres = [];
        $unidades = $data['unidades'] ?? [];

        foreach ($unidades as $unidad) {
            if (!empty($unidad['nombre'])) {
                $unidades_nombres[] = $unidad['nombre'];
            }
        }

        // Actualizar el atributo "Unidad" del producto
        if (!empty($unidades_nombres)) {
            $attributes = $product->get_attributes();
            $unidad_attribute = new \WC_Product_Attribute();
            $unidad_attribute->set_id(0);
            $unidad_attribute->set_name('Unidad');
            $unidad_attribute->set_options($unidades_nombres);
            $unidad_attribute->set_visible(true);
            $unidad_attribute->set_variation(true);

            $attributes['pa_unidad'] = $unidad_attribute;
            $product->set_attributes($attributes);
            $product->save();
        }

        // Procesar variaciones
        $variations = $this->get_product_variations($product->get_id());
        $meta_name = "b2bking_product_pricetiers_group_" . $group_id;

        foreach ($variations as $variation) {
            $variation_object = wc_get_product($variation['id']);
            if (!$variation_object) {
                continue;
            }

            $variation_object->set_manage_stock(true);

            // Encontrar la unidad que corresponde a esta variacion
            $matching_unidad = $this->findMatchingUnit($variation, $unidades);

            if ($matching_unidad) {
                // Actualizar stock
                $variation_object->set_stock_quantity($matching_unidad['stockventa'] ?? 0);

                // Configurar precios B2B King
                $prunneto = $matching_unidad['prunneto'] ?? [];
                if (!empty($prunneto[0]['f'])) {
                    update_post_meta($variation['id'], 'b2bking_regular_product_price_group_' . $group_id, $prunneto[0]['f']);
                }

                // Construir valores de precios escalonados
                $b2b_king_values = "";
                $high_price = 0;

                foreach ($prunneto as $precio) {
                    $min = $precio['min'] ?? 0;
                    $f = $precio['f'] ?? 0;
                    $b2b_king_values .= $min . ':' . $f . ';';

                    if ($f > 0 && ($high_price == 0 || $f < $high_price)) {
                        $high_price = $f;
                    }
                }

                // Precio por defecto de la variacion
                if ($high_price > 0) {
                    $variation_object->set_regular_price($high_price);
                }

                $variation_object->save();

                // Agregar precios escalonados B2B King
                if (!empty($b2b_king_values)) {
                    update_post_meta($variation['id'], $meta_name, $b2b_king_values);
                }

                $updated = true;
            } else {
                $variation_object->save();
            }
        }

        return ['success' => true, 'error' => null, 'updated' => $updated];
    }

    /**
     * Encuentra la unidad que corresponde a una variacion
     */
    private function findMatchingUnit($variation, $unidades) {
        foreach ($unidades as $unidad) {
            foreach ($variation['attributes'] as $attribute) {
                foreach ($attribute as $attribute_name => $attribute_value) {
                    if ($attribute_value == ($unidad['nombre'] ?? '')) {
                        return $unidad;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Obtiene las variaciones de un producto
     */
    public function get_product_variations($product_id) {
        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            return [];
        }

        $variations = [];
        $attributes = $product->get_attributes();
        $attribute_values = [];

        foreach ($attributes as $attribute_name => $attribute) {
            $attribute_values[$attribute_name] = $attribute->get_options();
        }

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variations[] = [
                    'id' => $variation_id,
                    'attributes' => $attribute_values
                ];
            }
        }

        return $variations;
    }
}
