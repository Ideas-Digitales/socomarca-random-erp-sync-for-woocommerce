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
        
        // Actualizar información básica del producto
        $wc_product->set_name($product['NOKOPR']);
        $wc_product->set_sku($product['KOPR']);
        $wc_product->set_status('publish');
        $wc_product->set_catalog_visibility('visible');
        
        if (!empty($category_ids)) {
            $wc_product->set_category_ids($category_ids);
        }
        
        // Manejar diferentes tipos de productos
        if ($wc_product->is_type('simple')) {
            // Convertir producto simple a producto variable
            
            // Eliminar el producto simple y crear un nuevo producto variable
            \wp_delete_post($product_id, true);
            
            // Crear nuevo producto variable
            $variations_data = $this->createDefaultVariations($product);
            return $this->createVariableProduct($product, $category_ids, $variations_data);
            
        } elseif ($wc_product->is_type('variable')) {
            // Para productos variables, actualizar variaciones si es necesario
            $this->updateProductVariations($product_id, null);
        }
        
        $wc_product->save();
        
        // Guardar meta del producto
        $this->saveProductMeta($product_id, $product);
        
        return ['success' => true, 'action' => 'updated'];
    }
    
    private function updateProductVariations($parent_id, $variations_data) {
        // Obtener variaciones existentes
        $existing_variations = \wc_get_products([
            'type' => 'variation',
            'parent' => $parent_id,
            'limit' => -1,
            'return' => 'ids'
        ]);
        
        // Eliminar todas las variaciones existentes y recrear con "UN"
        foreach ($existing_variations as $variation_id) {
            \wp_delete_post($variation_id, true);
        }
        
        // Obtener producto padre para actualizar atributos
        $parent_product = \wc_get_product($parent_id);
        if ($parent_product) {
            // Crear nuevas variaciones por defecto
            $new_variations_data = $this->createDefaultVariations(['KOPR' => $parent_product->get_sku()]);
            
            // Actualizar atributos del producto padre
            $attributes = $this->createProductAttributes($new_variations_data);
            $parent_product->set_attributes($attributes);
            $parent_product->save();
            
            // Crear nuevas variaciones
            $created_count = $this->createProductVariations($parent_id, $new_variations_data);
        }
    }
    
    private function createNewProduct($product, $category_ids) {
        // Siempre crear productos variables con variación "Unidad" = "UN"
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
        
        // Crear el producto variable padre
        $variable_product = new \WC_Product_Variable();
        $variable_product->set_name($product['NOKOPR']);
        $variable_product->set_sku($product['KOPR']);
        $variable_product->set_status('publish');
        $variable_product->set_catalog_visibility('visible');
        $variable_product->set_manage_stock(false);
        
        if (!empty($category_ids)) {
            $variable_product->set_category_ids($category_ids);
        }
        
        // Crear atributos de producto para variaciones
        $attributes = $this->createProductAttributes($variations_data);
        if (!empty($attributes)) {
            $variable_product->set_attributes($attributes);
        }
        
        // Guardar el producto padre
        $parent_id = $variable_product->save();
        
        if (!$parent_id) {
            return ['success' => false, 'error' => "Error creando producto variable: {$product['KOPR']}"];
        }
        
        // Guardar meta del producto padre
        $this->saveProductMeta($parent_id, $product);
        
        // Crear variaciones individuales
        $variations_created = $this->createProductVariations($parent_id, $variations_data);
        
        return ['success' => true, 'action' => 'created'];
    }
    
    private function createDefaultVariations($product) {
        // Crear estructura de variación por defecto con "Unidad" = "UN" como única variación
        $variations_data = [
            'units' => ['UN'],
            'combinations' => []
        ];
        
        // Generar combinación única para "UN"
        $this->generateVariationCombinations($variations_data, $product);
        
        
        return $variations_data;
    }
    
    private function extractVariationsFromErpData($product) {
        // Por ahora, crearemos una estructura de variación por defecto
        // Esto puede modificarse basado en la estructura real de datos del ERP
        
        // Ejemplo: Si el producto tiene tallas o colores en los datos del ERP
        $variations_data = null;
        
        // Verificar si el producto tiene indicadores de variación en su nombre o código
        if (isset($product['NOKOPR'])) {
            $name = strtoupper($product['NOKOPR']);
            
            // Buscar indicadores de talla en el nombre del producto
            $sizes = [];
            if (preg_match('/\b(XS|S|M|L|XL|XXL|XXXL)\b/', $name, $matches)) {
                $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
            }
            
            // Buscar indicadores de color o crear colores por defecto
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
            
            // Si no se encuentran colores específicos, agregar los por defecto para productos tipo ropa
            if (empty($colors) && !empty($sizes)) {
                $colors = ['Negro', 'Blanco', 'Azul'];
            }
            
            // Crear variaciones si tenemos atributos
            if (!empty($sizes) || !empty($colors)) {
                $variations_data = [
                    'sizes' => $sizes,
                    'colors' => $colors,
                    'combinations' => []
                ];
                
                // Generar combinaciones
                $this->generateVariationCombinations($variations_data, $product);
            }
        }
        
        return $variations_data;
    }
    
    private function generateVariationCombinations(&$variations_data, $product) {
        $base_sku = $product['KOPR'];
        $base_price = 0; // Precio por defecto
        $counter = 1;
        
        // Manejar diferentes tipos de variaciones
        if (!empty($variations_data['units'])) {
            // Para variaciones de "Unidad" (por defecto)
            foreach ($variations_data['units'] as $unit) {
                $variation_sku = $base_sku . '|' . str_pad($counter, 2, '0', STR_PAD_LEFT);
                
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
            // Manejar combinaciones de talla/color (para variaciones auto-detectadas)
            $sizes = !empty($variations_data['sizes']) ? $variations_data['sizes'] : ['Único'];
            $colors = !empty($variations_data['colors']) ? $variations_data['colors'] : ['Único'];
            
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    // Omitir si ambos son "Único" (crearía variación redundante)
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
        
        // Crear atributo de Unidad si existen unidades (para variaciones de "Unidad")
        if (!empty($variations_data['units'])) {
            $unit_attribute = new \WC_Product_Attribute();
            $unit_attribute->set_id(0); // Atributo personalizado
            $unit_attribute->set_name('Unidad');
            $unit_attribute->set_options($variations_data['units']);
            $unit_attribute->set_position(0);
            $unit_attribute->set_visible(true);
            $unit_attribute->set_variation(true);
            $attributes['pa_unidad'] = $unit_attribute;
        }
        
        // Crear atributo de Talla si existen tallas
        if (!empty($variations_data['sizes'])) {
            $size_attribute = new \WC_Product_Attribute();
            $size_attribute->set_id(0); // Atributo personalizado
            $size_attribute->set_name('Talla');
            $size_attribute->set_options($variations_data['sizes']);
            $size_attribute->set_position(0);
            $size_attribute->set_visible(true);
            $size_attribute->set_variation(true);
            $attributes['pa_talla'] = $size_attribute;
        }
        
        // Crear atributo de Color si existen colores
        if (!empty($variations_data['colors'])) {
            $color_attribute = new \WC_Product_Attribute();
            $color_attribute->set_id(0); // Atributo personalizado
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
                
                // Establecer atributos de variación
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
                
                // Establecer propiedades de variación
                $variation->set_sku($combination['sku']);
                $variation->set_regular_price($combination['price']);
                $variation->set_stock_status($combination['stock_status']);
                $variation->set_manage_stock(false);
                $variation->set_status('publish');
                
                // Guardar la variación
                $variation_id = $variation->save();
                
                if ($variation_id) {
                    // Guardar meta datos personalizados para la variación
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