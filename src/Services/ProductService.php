<?php

namespace Socomarca\RandomERP\Services;

class ProductService extends BaseApiService {
    
    public function getProducts() {
        error_log('ProductService: Obteniendo productos...');
        
        $products = $this->makeApiRequest('/productos');
        
        if ($products !== false) {
            error_log('ProductService: ' . count($products) . ' productos obtenidos');
            return [
                'quantity' => count($products),
                'items' => $products
            ];
        }
        
        return false;
    }
    
    public function processProducts() {
        error_log('ProductService: Iniciando proceso');
        
        $products = $this->getProducts();
        
        if (!$products || !isset($products['items'])) {
            return [
                'success' => false,
                'message' => 'No se pudieron obtener los productos del ERP'
            ];
        }
        
        
        update_option('sm_products_cache', $products['items']);
        
        error_log('ProductService: Éxito - ' . $products['quantity'] . ' productos guardados en cache');
        
        return [
            'success' => true,
            'message' => $products['quantity'] . ' productos obtenidos. Iniciando creación de productos...',
            'total' => $products['quantity']
        ];
    }
    
    public function processBatchProducts($offset = 0, $batch_size = 10) {
        $cached_products = get_option('sm_products_cache', []);
        
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
        
        
        $total_created = intval(get_option('sm_total_created_products', 0));
        $total_updated = intval(get_option('sm_total_updated_products', 0));
        
        foreach ($batch as $product) {
            try {
                error_log("ProductService: Procesando producto {$product['KOPR']}: {$product['NOKOPR']}");
                
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
                error_log("ProductService: Exception - {$product['KOPR']}: " . $e->getMessage());
            }
        }
        
        $processed = $offset + count($batch);
        $total = count($cached_products);
        $is_complete = $processed >= $total;
        
        
        $total_created += $created_products;
        $total_updated += $updated_products;
        update_option('sm_total_created_products', $total_created);
        update_option('sm_total_updated_products', $total_updated);
        
        
        if ($is_complete) {
            delete_option('sm_products_cache');
            delete_option('sm_total_created_products');
            delete_option('sm_total_updated_products');
        }
        
        error_log("ProductService: Lote procesado - $created_products creados, $updated_products actualizados");
        
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
        
        
        $existing_product_id = wc_get_product_id_by_sku($product['KOPR']);
        
        if ($existing_product_id) {
            return $this->updateExistingProduct($existing_product_id, $product, $category_ids);
        } else {
            return $this->createNewProduct($product, $category_ids);
        }
    }
    
    private function findProductCategories($product) {
        $category_ids = [];
        
        if (!empty($product['FMPR'])) {
            
            $main_category = get_terms([
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
                error_log("ProductService: Categoría principal encontrada: {$product['FMPR']} -> ID {$main_category[0]->term_id}");
            } else {
                error_log("ProductService: Categoría principal no encontrada: {$product['FMPR']}");
            }
        }
        
        if (!empty($product['PFPR'])) {
            
            $subcategory = get_terms([
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
                error_log("ProductService: Subcategoría encontrada: {$product['PFPR']} -> ID {$subcategory[0]->term_id}");
            } else {
                error_log("ProductService: Subcategoría no encontrada: {$product['PFPR']}");
            }
        }
        
        return $category_ids;
    }
    
    private function updateExistingProduct($product_id, $product, $category_ids) {
        $wc_product = wc_get_product($product_id);
        if (!$wc_product) {
            return ['success' => false, 'error' => "Error obteniendo producto existente: {$product['KOPR']}"];
        }
        
        $wc_product->set_name($product['NOKOPR']);
        $wc_product->set_sku($product['KOPR']);
        $wc_product->set_status('publish');
        $wc_product->set_catalog_visibility('visible');
        $wc_product->set_regular_price(10000); 
        
        if (!empty($category_ids)) {
            $wc_product->set_category_ids($category_ids);
        }
        
        $wc_product->save();
        
        
        $this->saveProductMeta($product_id, $product);
        
        error_log("ProductService: Producto actualizado - {$product['KOPR']}");
        return ['success' => true, 'action' => 'updated'];
    }
    
    private function createNewProduct($product, $category_ids) {
        $new_product = new WC_Product_Simple();
        $new_product->set_name($product['NOKOPR']);
        $new_product->set_sku($product['KOPR']);
        $new_product->set_status('publish');
        $new_product->set_catalog_visibility('visible');
        $new_product->set_regular_price(10000); 
        $new_product->set_manage_stock(false);
        
        if (!empty($category_ids)) {
            $new_product->set_category_ids($category_ids);
        }
        
        $product_id = $new_product->save();
        
        if (!$product_id) {
            return ['success' => false, 'error' => "Error creando producto: {$product['KOPR']}"];
        }
        
        
        $this->saveProductMeta($product_id, $product);
        
        error_log("ProductService: Producto creado - {$product['KOPR']} -> ID $product_id");
        return ['success' => true, 'action' => 'created'];
    }
    
    private function saveProductMeta($product_id, $product) {
        update_post_meta($product_id, '_erp_product_id', $product['KOPR']);
        update_post_meta($product_id, '_erp_brand', isset($product['NMARCA']) ? $product['NMARCA'] : '');
        update_post_meta($product_id, '_erp_alternative_code', isset($product['KOPRAL']) ? $product['KOPRAL'] : '');
    }
    
    public function deleteAllProducts() {
        error_log('ProductService: Iniciando eliminación masiva de productos');
        
        try {
            
            $products = wc_get_products([
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
                    
                    $result = wp_delete_post($product_id, true);
                    
                    if ($result) {
                        $deleted_count++;
                        error_log("ProductService: Producto eliminado ID: $product_id");
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
            
            error_log("ProductService: $message");
            
            return [
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log('ProductService: Error - ' . $e->getMessage());
            
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
            
            error_log('ProductService: Limpieza de metadatos y relaciones completada');
        } catch (Exception $e) {
            error_log('ProductService: Error en limpieza: ' . $e->getMessage());
        }
    }
}