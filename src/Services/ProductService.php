<?php

namespace Socomarca\RandomERP\Services;

use Exception;

class ProductService extends BaseApiService {

    private $brandCodeMap = null;

    public function getProducts() {
                
        $products = $this->makeApiRequest('/productos?tipr=FPN');
        
        if ($products !== false) {
            return [
                'quantity' => count($products),
                'items' => $products
            ];
        }
        
        return false;
    }
    
    public function processProducts() {
        
        $products = $this->getProducts();
        
        if (!$products || !isset($products['items'])) {
            return [
                'success' => false,
                'message' => 'No se pudieron obtener los productos del ERP'
            ];
        }
        
        
        $items = $products['items'];
        $product_limit = intval(\get_option('sm_product_limit', -1));
        if ($product_limit > 0) {
            $items = array_slice($items, 0, $product_limit);
        }

        \update_option('sm_products_cache', $items);
        $total = count($items);

        return [
            'success' => true,
            'message' => $total . ' productos obtenidos. Iniciando creación de productos...',
            'total' => $total
        ];
    }
    
    public function processBatchProducts($offset = 0, $batch_size = 10) {
        $cached_products = \get_option('sm_products_cache', []);
        
        if (empty($cached_products)) {
            return [
                'success' => false,
                'message' => 'No hay productos en cache'
            ];
        }
        
        $batch = array_slice($cached_products, $offset, $batch_size);
        $created_products = 0;
        $updated_products = 0;
        $errors = [];
        
        
        $total_created = intval(\get_option('sm_total_created_products', 0));
        $total_updated = intval(\get_option('sm_total_updated_products', 0));
        
        foreach ($batch as $product) {
            try {
                
                $result = $this->processProduct($product);
                
                if ($result['success']) {
                    if ($result['action'] === 'created') {
                        $created_products++;
                    } else {
                        $updated_products++;
                    }
                } else {
                    $errors[] = $result['error'];
                }
                
            } catch (Exception $e) {
                $errors[] = 'Error procesando producto ' . $product['KOPR'] . ': ' . $e->getMessage();
            }
        }
        
        $processed = $offset + count($batch);
        $total = count($cached_products);
        $is_complete = $processed >= $total;
        
        
        $total_created += $created_products;
        $total_updated += $updated_products;
        \update_option('sm_total_created_products', $total_created);
        \update_option('sm_total_updated_products', $total_updated);
        
        
        if ($is_complete) {
            \delete_option('sm_products_cache');
            \delete_option('sm_total_created_products');
            \delete_option('sm_total_updated_products');
        }
        
        
        return [
            'success' => true,
            'created' => $created_products,
            'updated' => $updated_products,
            'total_created' => $total_created,
            'total_updated' => $total_updated,
            'errors' => $errors,
            'processed' => $processed,
            'total' => $total,
            'is_complete' => $is_complete,
            'message' => "Lote procesado: $created_products productos creados, $updated_products actualizados"
        ];
    }
    
    private function processProduct($product) {

        $category_ids = $this->findProductCategories($product);
        $brand_term_id = $this->getBrandTermId(isset($product['MRPR']) ? $product['MRPR'] : '');


        $existing_product_id = \wc_get_product_id_by_sku($product['KOPR']);

        if ($existing_product_id) {
            return $this->updateExistingProduct($existing_product_id, $product, $category_ids, $brand_term_id);
        } else {
            return $this->createNewProduct($product, $category_ids, $brand_term_id);
        }
    }

    /**
     * Busca el term_id de 'pwb-brand' asociado al código de marca del ERP (MRPR).
     * El mapa código->term_id se construye una sola vez por instancia (por lote).
     */
    private function getBrandTermId($brand_code) {
        if (empty($brand_code)) {
            return null;
        }

        if ($this->brandCodeMap === null) {
            $this->brandCodeMap = $this->loadBrandCodeMap();
        }

        return isset($this->brandCodeMap[$brand_code]) ? $this->brandCodeMap[$brand_code] : null;
    }

    private function loadBrandCodeMap() {
        $map = [];

        if (!\taxonomy_exists('pwb-brand')) {
            return $map;
        }

        global $wpdb;

        $results = $wpdb->get_results("
            SELECT tm.meta_value AS code, tt.term_id AS term_id
            FROM {$wpdb->termmeta} tm
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
            WHERE tm.meta_key = 'random_erp_code' AND tt.taxonomy = 'pwb-brand'
        ");

        foreach ($results as $row) {
            if ($row->code !== '') {
                $map[$row->code] = (int) $row->term_id;
            }
        }

        return $map;
    }

    private function assignBrand($product_id, $brand_term_id) {
        if ($brand_term_id) {
            \wp_set_object_terms($product_id, $brand_term_id, 'pwb-brand');
        }
    }
    
    private function findProductCategories($product) {
        $category_ids = [];

        if (empty($product['FMPR'])) {
            return $category_ids;
        }

        $fmpr = $product['FMPR'];
        $pfpr = !empty($product['PFPR']) ? $product['PFPR'] : '';
        $hfpr = !empty($product['HFPR']) ? $product['HFPR'] : '';

        // Caso normal: FMPR = familia (nivel 1), PFPR = subfamilia (nivel 2), HFPR = sub-subfamilia (nivel 3).
        if ($this->isNivelUnoCode($fmpr)) {
            $key_parts = array_filter([$fmpr, $pfpr, $hfpr], fn($p) => $p !== '');
        } else {
            // El ERP omite el nivel 1 en algunos productos (rama SECOS): FMPR viene con el
            // código de nivel 2 directo y PFPR es el nivel 3; HFPR no se usa en este caso.
            $nivel_uno_code = $this->findParentNivelUnoCode($fmpr);

            if ($nivel_uno_code === null) {
                return $category_ids;
            }

            $key_parts = array_filter([$nivel_uno_code, $fmpr, $pfpr], fn($p) => $p !== '');
        }

        $erp_key = '';

        foreach ($key_parts as $part) {
            $erp_key = ($erp_key === '') ? $part : $erp_key . '/' . $part;

            $term = $this->findTermByErpKey($erp_key);

            if ($term) {
                $category_ids[] = $term->term_id;
            }
        }

        return $category_ids;
    }

    private function isNivelUnoCode($code) {
        $term = $this->findTermByErpKey($code);

        if (!$term) {
            return false;
        }

        return \get_term_meta($term->term_id, 'erp_level', true) == 1;
    }

    private function findParentNivelUnoCode($nivel_dos_code) {
        $nivel_uno_terms = \get_terms([
            'taxonomy' => 'product_cat',
            'meta_query' => [
                [
                    'key' => 'erp_level',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'hide_empty' => false
        ]);

        if (empty($nivel_uno_terms) || \is_wp_error($nivel_uno_terms)) {
            return null;
        }

        foreach ($nivel_uno_terms as $term) {
            $code = \get_term_meta($term->term_id, 'erp_key', true);

            if ($code !== '' && $this->findTermByErpKey($code . '/' . $nivel_dos_code)) {
                return $code;
            }
        }

        return null;
    }

    private function findTermByErpKey($llave) {
        $terms = \get_terms([
            'taxonomy' => 'product_cat',
            'meta_query' => [
                [
                    'key' => 'erp_key',
                    'value' => $llave,
                    'compare' => '='
                ]
            ],
            'hide_empty' => false,
            'number' => 1
        ]);

        return (!empty($terms) && !\is_wp_error($terms)) ? $terms[0] : null;
    }
    
    private function updateExistingProduct($product_id, $product, $category_ids, $brand_term_id = null) {
        $wc_product = \wc_get_product($product_id);
        if (!$wc_product) {
            return ['success' => false, 'error' => "Error obteniendo producto existente: {$product['KOPR']}"];
        }

        if ($wc_product->is_type('variable')) {
            $this->convertVariableToSimple($product_id);
            $wc_product = \wc_get_product($product_id);
        }

        $wc_product->set_name($product['NOKOPR']);
        $wc_product->set_sku($product['KOPR']);
        $wc_product->set_status('publish');
        $wc_product->set_catalog_visibility('visible');
        $wc_product->set_manage_stock(true);

        if (!empty($category_ids)) {
            $wc_product->set_category_ids($category_ids);
        }

        $wc_product->save();

        $this->assignBrand($product_id, $brand_term_id);

        $this->saveProductMeta($product_id, $product);

        return ['success' => true, 'action' => 'updated'];
    }

    private function convertVariableToSimple($product_id) {
        $variations = \wc_get_products([
            'type' => 'variation',
            'parent' => $product_id,
            'limit' => -1,
            'return' => 'ids'
        ]);

        foreach ($variations as $variation_id) {
            \wp_delete_post($variation_id, true);
        }

        \delete_post_meta($product_id, 'attribute_pa_unidad');
        \delete_post_meta($product_id, 'attribute_pa_talla');
        \delete_post_meta($product_id, 'attribute_pa_color');
        \delete_post_meta($product_id, '_product_attributes');

        \update_post_meta($product_id, '_type', 'simple');

        $product = \wc_get_product($product_id);
        if ($product) {
            $product->save();
        }

        \delete_transient('wc_product_type_' . $product_id);
        \wp_cache_delete($product_id, 'post');
        \wp_cache_delete('product_id_' . $product_id, 'posts');
    }
    
    
    private function createNewProduct($product, $category_ids, $brand_term_id = null) {
        return $this->createSimpleProduct($product, $category_ids, $brand_term_id);
    }

    private function createSimpleProduct($product, $category_ids, $brand_term_id = null) {
        $new_product = new \WC_Product_Simple();
        $new_product->set_name($product['NOKOPR']);
        $new_product->set_sku($product['KOPR']);
        $new_product->set_status('publish');
        $new_product->set_catalog_visibility('visible');
        $new_product->set_manage_stock(true); // Habilitar gestión de stock para productos simples
        $new_product->set_stock_quantity(0);
        $new_product->set_regular_price(0); // Precio inicial 0 para que sea "comprable"

        if (!empty($category_ids)) {
            $new_product->set_category_ids($category_ids);
        }

        $product_id = $new_product->save();

        if (!$product_id) {
            return ['success' => false, 'error' => "Error creando producto simple: {$product['KOPR']}"];
        }

        $this->assignBrand($product_id, $brand_term_id);

        $this->saveProductMeta($product_id, $product);

        return ['success' => true, 'action' => 'created'];
    }
    
    
    
    
    
    
    
    private function saveProductMeta($product_id, $product) {
        \update_post_meta($product_id, '_erp_product_id', $product['KOPR']);
        \update_post_meta($product_id, '_erp_brand', isset($product['NMARCA']) ? $product['NMARCA'] : '');
        \update_post_meta($product_id, '_erp_alternative_code', isset($product['KOPRAL']) ? $product['KOPRAL'] : '');
    }
    
    public function convertVariablesToSimpleBatch($offset = 0, $batch_size = 10) {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        ];

        $query = new \WP_Query($args);
        $total = $query->found_posts;

        if (empty($query->posts)) {
            return [
                'success' => true,
                'converted' => 0,
                'total' => $total,
                'processed' => $offset,
                'is_complete' => true,
                'message' => 'Todos los productos han sido procesados'
            ];
        }

        $cleaned = 0;
        foreach ($query->posts as $post) {
            try {
                $this->convertVariableToSimple($post->ID);
                $cleaned++;
            } catch (Exception $e) {
            }
        }

        $processed = $offset + count($query->posts);
        $is_complete = $processed >= $total;

        if ($is_complete) {
            \wp_cache_flush();
        }

        return [
            'success' => true,
            'converted' => $cleaned,
            'processed' => $processed,
            'total' => $total,
            'is_complete' => $is_complete,
            'message' => "$cleaned productos procesados en este lote"
        ];
    }

    public function deleteAllProducts() {
        
        try {
            
            $products = \wc_get_products([
                'status' => ['publish', 'private', 'draft', 'pending', 'trash'],
                'limit' => -1,
                'return' => 'ids'
            ]);
            
            if (empty($products)) {
                return [
                    'success' => true,
                    'message' => 'No hay productos para eliminar',
                    'deleted_count' => 0,
                    'errors' => []
                ];
            }
            
            $deleted_count = 0;
            $errors = [];
            
            foreach ($products as $product_id) {
                try {
                    
                    $result = \wp_delete_post($product_id, true);
                    
                    if ($result) {
                        $deleted_count++;
                    } else {
                        $errors[] = "Error al eliminar producto ID: $product_id";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error eliminando producto ID $product_id: " . $e->getMessage();
                }
            }
            
            
            $this->cleanupOrphanedData();
            
            $message = "Se eliminaron $deleted_count productos exitosamente.";
            if (!empty($errors)) {
                $message .= " Errores encontrados: " . implode(', ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= " y " . (count($errors) - 5) . " errores más.";
                }
            }
            
            
            return [
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            
            return [
                'success' => false,
                'message' => 'Error al eliminar productos: ' . $e->getMessage()
            ];
        }
    }
    
    private function cleanupOrphanedData() {
        try {
            global $wpdb;
            
            
            $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL");
            
            
            $wpdb->query("DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL");
            
        } catch (Exception $e) {
        }
    }
}