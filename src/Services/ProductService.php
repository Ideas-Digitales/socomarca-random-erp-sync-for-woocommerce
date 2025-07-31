<?php

namespace Socomarca\RandomERP\Services;

use Exception;

class ProductService extends BaseApiService {
    
    public function getProducts() {
                
        $products = $this->makeApiRequest('/productos');
        
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
        
        
        \update_option('sm_products_cache', $products['items']);
        
        
        return [
            'success' => true,
            'message' => $products['quantity'] . ' productos obtenidos. Iniciando creación de productos...',
            'total' => $products['quantity']
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
        
        // Update basic product information
        $wc_product->set_name($product['NOKOPR']);
        $wc_product->set_sku($product['KOPR']);
        $wc_product->set_status('publish');
        $wc_product->set_catalog_visibility('visible');
        
        if (!empty($category_ids)) {
            $wc_product->set_category_ids($category_ids);
        }
        
        // Handle different product types
        if ($wc_product->is_type('simple')) {
            // Convert simple product to variable product
            
            // Delete the simple product and create a new variable product
            \wp_delete_post($product_id, true);
            
            // Create new variable product
            $variations_data = $this->createDefaultVariations($product);
            return $this->createVariableProduct($product, $category_ids, $variations_data);
            
        } elseif ($wc_product->is_type('variable')) {
            // For variable products, update variations if needed
            $this->updateProductVariations($product_id, null);
        }
        
        $wc_product->save();
        
        // Save product meta
        $this->saveProductMeta($product_id, $product);
        
        return ['success' => true, 'action' => 'updated'];
    }
    
    private function updateProductVariations($parent_id, $variations_data) {
        // Get existing variations
        $existing_variations = \wc_get_products([
            'type' => 'variation',
            'parent' => $parent_id,
            'limit' => -1,
            'return' => 'ids'
        ]);
        
        // Delete all existing variations and recreate with "UN"
        foreach ($existing_variations as $variation_id) {
            \wp_delete_post($variation_id, true);
        }
        
        // Get parent product to update attributes
        $parent_product = \wc_get_product($parent_id);
        if ($parent_product) {
            // Create new default variations
            $new_variations_data = $this->createDefaultVariations(['KOPR' => $parent_product->get_sku()]);
            
            // Update parent product attributes
            $attributes = $this->createProductAttributes($new_variations_data);
            $parent_product->set_attributes($attributes);
            $parent_product->save();
            
            // Create new variations
            $created_count = $this->createProductVariations($parent_id, $new_variations_data);
        }
    }
    
    private function createNewProduct($product, $category_ids) {
        // Always create variable products with "Unidad" = "UN" variation
        $variations_data = $this->createDefaultVariations($product);
        return $this->createVariableProduct($product, $category_ids, $variations_data);
    }
    
    private function createSimpleProduct($product, $category_ids) {
        $new_product = new \WC_Product_Simple();
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
            return ['success' => false, 'error' => "Error creando producto simple: {$product['KOPR']}"];
        }
        
        $this->saveProductMeta($product_id, $product);
        
        return ['success' => true, 'action' => 'created'];
    }
    
    private function createVariableProduct($product, $category_ids, $variations_data) {
        
        // Create the parent variable product
        $variable_product = new \WC_Product_Variable();
        $variable_product->set_name($product['NOKOPR']);
        $variable_product->set_sku($product['KOPR']);
        $variable_product->set_status('publish');
        $variable_product->set_catalog_visibility('visible');
        $variable_product->set_manage_stock(false);
        
        if (!empty($category_ids)) {
            $variable_product->set_category_ids($category_ids);
        }
        
        // Create product attributes for variations
        $attributes = $this->createProductAttributes($variations_data);
        if (!empty($attributes)) {
            $variable_product->set_attributes($attributes);
        }
        
        // Save the parent product
        $parent_id = $variable_product->save();
        
        if (!$parent_id) {
            return ['success' => false, 'error' => "Error creando producto variable: {$product['KOPR']}"];
        }
        
        // Save parent product meta
        $this->saveProductMeta($parent_id, $product);
        
        // Create individual variations
        $variations_created = $this->createProductVariations($parent_id, $variations_data);
        
        return ['success' => true, 'action' => 'created'];
    }
    
    private function createDefaultVariations($product) {
        // Create default variation structure with "Unidad" = "UN" as the only variation
        $variations_data = [
            'units' => ['UN'],
            'combinations' => []
        ];
        
        // Generate single combination for "UN"
        $this->generateVariationCombinations($variations_data, $product);
        
        
        return $variations_data;
    }
    
    private function extractVariationsFromErpData($product) {
        // For now, we'll create a default variation structure
        // This can be modified based on actual ERP data structure
        
        // Example: If product has sizes or colors in ERP data
        $variations_data = null;
        
        // Check if product has variation indicators in its name or code
        if (isset($product['NOKOPR'])) {
            $name = strtoupper($product['NOKOPR']);
            
            // Look for size indicators in product name
            $sizes = [];
            if (preg_match('/\b(XS|S|M|L|XL|XXL|XXXL)\b/', $name, $matches)) {
                $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
            }
            
            // Look for color indicators or create default colors
            $colors = [];
            if (strpos($name, 'NEGRO') !== false || strpos($name, 'BLACK') !== false) {
                $colors[] = 'Negro';
            }
            if (strpos($name, 'BLANCO') !== false || strpos($name, 'WHITE') !== false) {
                $colors[] = 'Blanco';
            }
            if (strpos($name, 'AZUL') !== false || strpos($name, 'BLUE') !== false) {
                $colors[] = 'Azul';
            }
            
            // If no specific colors found, add default ones for clothing-like products
            if (empty($colors) && !empty($sizes)) {
                $colors = ['Negro', 'Blanco', 'Azul'];
            }
            
            // Create variations if we have attributes
            if (!empty($sizes) || !empty($colors)) {
                $variations_data = [
                    'sizes' => $sizes,
                    'colors' => $colors,
                    'combinations' => []
                ];
                
                // Generate combinations
                $this->generateVariationCombinations($variations_data, $product);
            }
        }
        
        return $variations_data;
    }
    
    private function generateVariationCombinations(&$variations_data, $product) {
        $base_sku = $product['KOPR'];
        $base_price = 0; // Default price
        $counter = 1;
        
        // Handle different variation types
        if (!empty($variations_data['units'])) {
            // For "Unidad" variations (default)
            foreach ($variations_data['units'] as $unit) {
                $variation_sku = $base_sku . '-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
                
                $combination = [
                    'sku' => $variation_sku,
                    'price' => $base_price,
                    'stock_status' => 'instock',
                    'erp_id' => $base_sku . '_' . $counter,
                    'unit' => $unit
                ];
                
                $variations_data['combinations'][] = $combination;
                $counter++;
            }
        } else {
            // Handle size/color combinations (for auto-detected variations)
            $sizes = !empty($variations_data['sizes']) ? $variations_data['sizes'] : ['Único'];
            $colors = !empty($variations_data['colors']) ? $variations_data['colors'] : ['Único'];
            
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    // Skip if both are "Único" (would create redundant variation)
                    if ($size === 'Único' && $color === 'Único') {
                        continue;
                    }
                    
                    $variation_sku = $base_sku . '-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
                    
                    $combination = [
                        'sku' => $variation_sku,
                        'price' => $base_price,
                        'stock_status' => 'instock',
                        'erp_id' => $base_sku . '_' . $counter
                    ];
                    
                    if ($size !== 'Único') {
                        $combination['size'] = $size;
                    }
                    if ($color !== 'Único') {
                        $combination['color'] = $color;
                    }
                    
                    $variations_data['combinations'][] = $combination;
                    $counter++;
                }
            }
        }
    }
    
    private function createProductAttributes($variations_data) {
        $attributes = [];
        
        // Create Unit attribute if units exist (for "Unidad" variations)
        if (!empty($variations_data['units'])) {
            $unit_attribute = new \WC_Product_Attribute();
            $unit_attribute->set_id(0); // Custom attribute
            $unit_attribute->set_name('Unidad');
            $unit_attribute->set_options($variations_data['units']);
            $unit_attribute->set_position(0);
            $unit_attribute->set_visible(true);
            $unit_attribute->set_variation(true);
            $attributes['pa_unidad'] = $unit_attribute;
        }
        
        // Create Size attribute if sizes exist
        if (!empty($variations_data['sizes'])) {
            $size_attribute = new \WC_Product_Attribute();
            $size_attribute->set_id(0); // Custom attribute
            $size_attribute->set_name('Talla');
            $size_attribute->set_options($variations_data['sizes']);
            $size_attribute->set_position(0);
            $size_attribute->set_visible(true);
            $size_attribute->set_variation(true);
            $attributes['pa_talla'] = $size_attribute;
        }
        
        // Create Color attribute if colors exist
        if (!empty($variations_data['colors'])) {
            $color_attribute = new \WC_Product_Attribute();
            $color_attribute->set_id(0); // Custom attribute
            $color_attribute->set_name('Color');
            $color_attribute->set_options($variations_data['colors']);
            $color_attribute->set_position(1);
            $color_attribute->set_visible(true);
            $color_attribute->set_variation(true);
            $attributes['pa_color'] = $color_attribute;
        }
        
        return $attributes;
    }
    
    private function createProductVariations($parent_id, $variations_data) {
        $created_count = 0;
        
        foreach ($variations_data['combinations'] as $combination) {
            try {
                $variation = new \WC_Product_Variation();
                $variation->set_parent_id($parent_id);
                
                // Set variation attributes
                $attributes = [];
                if (isset($combination['unit'])) {
                    $attributes['attribute_pa_unidad'] = \sanitize_title($combination['unit']);
                }
                if (isset($combination['size'])) {
                    $attributes['attribute_pa_talla'] = \sanitize_title($combination['size']);
                }
                if (isset($combination['color'])) {
                    $attributes['attribute_pa_color'] = \sanitize_title($combination['color']);
                }
                
                $variation->set_attributes($attributes);
                
                // Set variation properties
                $variation->set_sku($combination['sku']);
                $variation->set_regular_price($combination['price']);
                $variation->set_stock_status($combination['stock_status']);
                $variation->set_manage_stock(false);
                $variation->set_status('publish');
                
                // Save the variation
                $variation_id = $variation->save();
                
                if ($variation_id) {
                    // Save custom meta data for the variation
                    \update_post_meta($variation_id, '_erp_variation_id', $combination['erp_id']);
                    $created_count++;
                    
                } else {
                }
                
            } catch (Exception $e) {
            }
        }
        
        return $created_count;
    }
    
    private function saveProductMeta($product_id, $product) {
        \update_post_meta($product_id, '_erp_product_id', $product['KOPR']);
        \update_post_meta($product_id, '_erp_brand', isset($product['NMARCA']) ? $product['NMARCA'] : '');
        \update_post_meta($product_id, '_erp_alternative_code', isset($product['KOPRAL']) ? $product['KOPRAL'] : '');
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