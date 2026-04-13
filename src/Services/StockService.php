<?php

namespace Socomarca\RandomERP\Services;

class StockService extends BaseApiService {

    /**
     * Llama al endpoint de stock, agrupa por KOPR y guarda en cache para batch.
     *
     * El endpoint devuelve una fila por KOPR+KOBO. Agrupamos por KOPR
     * para procesar todas las bodegas de un producto juntas.
     */
    public function fetchStock(): array {
        $modalidad = get_option('sm_modalidad', 'ADMIN');
        $endpoint  = '/stock/detalle?modalidad=' . rawurlencode($modalidad);

        $response = $this->makeApiRequest($endpoint, 'GET', null, 120);

        if (!is_array($response) || empty($response)) {
            return [
                'success' => false,
                'message' => 'No se pudieron obtener datos de stock desde el ERP',
            ];
        }

        // makeApiRequest ya retorna $body['data'] directamente
        $grouped = [];
        foreach ($response as $entry) {
            $kopr = $entry['KOPR'] ?? '';
            if (empty($kopr)) {
                continue;
            }
            $grouped[$kopr][] = $entry;
        }

        $items = array_values($grouped);

        update_option('sm_stock_cache', $items);
        update_option('sm_stock_total_processed', 0);
        update_option('sm_stock_total_updated', 0);

        return [
            'success' => true,
            'message' => count($items) . ' productos con stock obtenidos. Iniciando procesamiento...',
            'total'   => count($items),
        ];
    }

    /**
     * Procesa un lote desde el cache.
     */
    public function processBatch(int $offset = 0, int $batch_size = 20): array {
        $cached = get_option('sm_stock_cache', []);

        if (empty($cached)) {
            return [
                'success' => false,
                'message' => 'No hay datos de stock en cache',
            ];
        }

        $batch     = array_slice($cached, $offset, $batch_size);
        $processed = 0;
        $updated   = 0;
        $errors    = [];

        $total_processed = intval(get_option('sm_stock_total_processed', 0));
        $total_updated   = intval(get_option('sm_stock_total_updated', 0));

        foreach ($batch as $product_entries) {
            try {
                $result = $this->processProductStock($product_entries);

                if ($result['success']) {
                    $processed++;
                    if ($result['updated']) {
                        $updated++;
                    }
                } else {
                    $errors[] = $result['error'];
                }
            } catch (\Exception $e) {
                $kopr     = $product_entries[0]['KOPR'] ?? 'desconocido';
                $errors[] = "Error procesando stock de {$kopr}: " . $e->getMessage();
            }
        }

        $done       = $offset + count($batch);
        $total      = count($cached);
        $is_complete = $done >= $total;

        $total_processed += $processed;
        $total_updated   += $updated;

        update_option('sm_stock_total_processed', $total_processed);
        update_option('sm_stock_total_updated', $total_updated);

        if ($is_complete) {
            delete_option('sm_stock_cache');
            delete_option('sm_stock_total_processed');
            delete_option('sm_stock_total_updated');
        }

        return [
            'success'         => true,
            'processed_batch' => $processed,
            'updated_batch'   => $updated,
            'total_processed' => $total_processed,
            'total_updated'   => $total_updated,
            'errors'          => $errors,
            'processed'       => $done,
            'total'           => $total,
            'is_complete'     => $is_complete,
            'message'         => "Lote procesado: {$processed} productos, {$updated} actualizados",
        ];
    }

    /**
     * Procesa todas las entradas de stock de un mismo producto.
     *
     * Cada entrada corresponde a una bodega (KOBO) distinta.
     * Por cada entrada:
     *   - Se asocia el producto a la taxonomia "locations" de esa bodega.
     *   - Se actualiza el stock de la variacion con STOCNV1.
     *
     * Si el producto tiene multiples variaciones, todas reciben el mismo
     * valor de stock porque el endpoint no distingue por unidad.
     */
    private function processProductStock(array $entries): array {
        $kopr = $entries[0]['KOPR'] ?? '';

        $product_id = wc_get_product_id_by_sku($kopr);
        if (!$product_id) {
            return ['success' => true, 'updated' => false, 'error' => null]; // no existe, saltar
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return ['success' => false, 'updated' => false, 'error' => "Producto {$kopr} no encontrado"];
        }

        $term_ids = [];
        $updated  = false;

        foreach ($entries as $entry) {
            $kobo   = $entry['KOBO'] ?? '';
            $stock  = isset($entry['STOCNV1']) ? intval($entry['STOCNV1']) : 0;

            if (empty($kobo)) {
                continue;
            }

            $term = $this->findWarehouseTermByCode($kobo);
            if ($term) {
                $term_ids[] = (int) $term->term_id;
            }

            // Actualizar stock en las variaciones del producto
            $term_id = $term ? (int) $term->term_id : null;
            $this->setVariationStock($product, $stock, $term_id);
            $updated = true;
        }

        // Asociar el producto a todas las bodegas encontradas
        if (!empty($term_ids)) {
            $existing = wp_get_object_terms($product_id, 'locations', ['fields' => 'ids']);
            if (is_wp_error($existing)) {
                $existing = [];
            }
            $merged = array_unique(array_merge($existing, $term_ids));
            wp_set_object_terms($product_id, $merged, 'locations');
        }

        return ['success' => true, 'updated' => $updated, 'error' => null];
    }

    /**
     * Establece el stock en todas las variaciones de un producto variable,
     * o directamente en el producto si es simple.
     *
     * Ademas de actualizar el stock global de WooCommerce, escribe los metas
     * que usa Multiloca Lite para stock por ubicacion:
     *   - wcmlim_stock_at_{term_id}
     *   - wcmlim_product_availability_at_{term_id}
     */
    private function setVariationStock(\WC_Product $product, int $stock, ?int $term_id = null): void {
        $stock_status = $stock > 0 ? 'instock' : 'outofstock';

        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation) {
                    continue;
                }
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity($stock);
                $variation->set_stock_status($stock_status);
                $variation->save();

                // Metas de Multiloca Lite (por ubicacion)
                if ($term_id) {
                    update_post_meta($variation_id, 'wcmlim_stock_at_' . $term_id, $stock);
                    update_post_meta($variation_id, 'wcmlim_product_availability_at_' . $term_id, $stock > 0 ? 'yes' : 'no');
                }
            }
            wc_delete_product_transients($product->get_id());
            $product->get_data_store()->update_lookup_table($product->get_id(), 'wc_product_meta_lookup');
        } else {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->set_stock_status($stock_status);
            $product->save();

            if ($term_id) {
                update_post_meta($product->get_id(), 'wcmlim_stock_at_' . $term_id, $stock);
                update_post_meta($product->get_id(), 'wcmlim_product_availability_at_' . $term_id, $stock > 0 ? 'yes' : 'no');
            }

            wc_delete_product_transients($product->get_id());
            $product->get_data_store()->update_lookup_table($product->get_id(), 'wc_product_meta_lookup');
        }
    }

    /**
     * Busca el termino de la taxonomia "locations" cuyo meta
     * "random_erp_warehouse_code" coincide con el codigo de bodega.
     */
    private function findWarehouseTermByCode(string $code): ?\WP_Term {
        $terms = get_terms([
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => 'random_erp_warehouse_code',
                    'value'   => $code,
                    'compare' => '=',
                ],
            ],
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }

        return $terms[0];
    }
}
