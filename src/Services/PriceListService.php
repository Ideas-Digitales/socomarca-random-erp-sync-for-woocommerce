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

        // Guardar datos en cache para procesamiento por lotes - FILTRAR SOLO EXISTENTES
        $datos = isset($priceLists['datos']) ? $priceLists['datos'] : [];
        
        global $wpdb;
        $existing_skus = $wpdb->get_col("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_sku' AND meta_value != ''");
        $existing_skus_map = array_flip($existing_skus);

        $filtered_datos = [];
        foreach ($datos as $item) {
            $sku = $item['kopr'] ?? '';
            if (!empty($sku) && isset($existing_skus_map[$sku])) {
                $filtered_datos[] = $item;
            }
        }

        update_option('sm_price_lists_cache', $filtered_datos);
        update_option('sm_price_lists_group_id', $group_id);
        update_option('sm_total_processed_prices', 0);
        update_option('sm_total_updated_prices', 0);

        return [
            'success' => true,
            'message' => count($filtered_datos) . ' productos encontrados en la tienda para actualizar precios. Iniciando...',
            'total' => count($filtered_datos),
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
            delete_transient('sm_hidden_product_ids');

            // Purge general al cerrar el sync completo: cubre paginas de
            // shop/categoria paginadas y fragmentos (widgets, productos
            // relacionados) que el purge por producto individual no alcanza.
            do_action('litespeed_purge_all');
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
            return ['success' => true, 'error' => null, 'updated' => false];
        }

        $product = $products[0];
        $updated = false;

        // Obtener el precio desde los datos del ERP
        $unidades = $data['unidades'] ?? [];
        $price = 0; // Por defecto 0 si no hay datos
        
        if (!empty($unidades) && isset($unidades[0]['prunneto'][0]['f'])) {
            $price = $unidades[0]['prunneto'][0]['f'];
        }

        $product->set_regular_price($price);
        $product->set_price($price);

        if (!empty($unidades) && isset($unidades[0]['stockventa'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($unidades[0]['stockventa']);
        }

        $product->save();
        $updated = true;

        // Sin esto, LiteSpeed Cache (TTL de hasta 7 dias) sigue sirviendo el
        // HTML cacheado con el precio anterior en category/shop mientras el
        // single product page (purgado por otro camino o visitado despues)
        // ya muestra el precio nuevo, mostrando precios distintos para el
        // mismo producto segun la pagina.
        $this->purgeProductCache($product->get_id());

        return ['success' => true, 'error' => null, 'updated' => $updated];
    }

    /**
     * Purga la cache de LiteSpeed para un producto (incluye sus archivos de
     * categoria asociados, segun el comportamiento por defecto de LiteSpeed
     * al purgar un post). No-op si LiteSpeed Cache no esta activo.
     */
    private function purgeProductCache($product_id) {
        do_action('litespeed_purge_post', $product_id);
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
