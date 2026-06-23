<?php

namespace Socomarca\RandomERP\Services;

use Exception;

class ProductService extends BaseApiService {
    
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
        
        
        $existing_product_id = \wc_get_product_id_by_sku($product['KOPR']);
        
        if ($existing_product_id) {
            return $this->updateExistingProduct($existing_product_id, $product, $category_ids);
        } else {
            return $this->createNewProduct($product, $category_ids);
        }
    }
    
    private function findProductCategories($product) {
        $category_ids = [];
        
        if (!empty($product['FMPR'])) {
            
            $main_category = \get_terms([
                'taxonomy' => 'product_cat',
                'meta_query' => [
                    [
                        'key' => 'erp_code',
                        'value' => $product['FMPR'],
                        'compare' => '='
                    ]
                ],
                'hide_empty' => false,
                'number' => 1
            ]);
            
            if (!empty($main_category)) {
                $category_ids[] = $main_category[0]->term_id;
            } else {
            }
        }
        
        if (!empty($product['PFPR'])) {
            
            $subcategory = \get_terms([
                'taxonomy' => 'product_cat',
                'meta_query' => [
                    [
                        'key' => 'erp_code',
                        'value' => $product['PFPR'],
                        'compare' => '='
                    ]
                ],
                'hide_empty' => false,
                'number' => 1
            ]);
            
            if (!empty($subcategory)) {
                $category_ids[] = $subcategory[0]->term_id;
            } else {
            }
        }
        
        return $category_ids;
    }
    
    private function updateExistingProduct($product_id, $product, $category_ids) {
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
        $wc_product->set_stock_quantity(0);
        $wc_product->set_regular_price(0);

        if (!empty($category_ids)) {
            $wc_product->set_category_ids($category_ids);
        }

        $wc_product->save();

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
    
    
    private function createNewProduct($product, $category_ids) {
        return $this->createSimpleProduct($product, $category_ids);
    }
    
    private function createSimpleProduct($product, $category_ids) {
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