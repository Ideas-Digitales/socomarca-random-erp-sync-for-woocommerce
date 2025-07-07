<?php

namespace Socomarca\RandomERP\Services;

class CategoryService extends BaseApiService {
    
    public function getCategories() {
        error_log('CategoryService: Obteniendo categorías...');
        
        // Las categorías no necesitan filtrar por empresa/rut
        $endpoint = "/familias";
        $categories = $this->makeApiRequest($endpoint);
        
        error_log('CategoryService: Respuesta raw de API: ' . substr(print_r($categories, true), 0, 1000));
        
        if ($categories !== false && is_array($categories)) {
            error_log('CategoryService: ' . count($categories) . ' categorías obtenidas');
            return [
                'quantity' => count($categories),
                'items' => $categories
            ];
        }
        
        error_log('CategoryService: Error - No se pudieron obtener categorías válidas');
        return false;
    }
    
    public function processCategories() {
        error_log('CategoryService: Iniciando procesamiento de categorías');
        
        $categories = $this->getCategories();
        
        error_log('CategoryService: Respuesta de getCategories: ' . print_r($categories, true));
        
        if (!$categories || !isset($categories['items'])) {
            error_log('CategoryService: Error - No hay categorías válidas para procesar');
            return [
                'success' => false,
                'message' => 'No se pudieron obtener las categorías del ERP'
            ];
        }
        
        error_log('CategoryService: Procesando ' . count($categories['items']) . ' categorías...');
        
        $created_categories = 0;
        $updated_categories = 0;
        $errors = [];
        
        // Procesar categorías por niveles para asegurar que los padres existan antes que los hijos
        for ($nivel = 1; $nivel <= 3; $nivel++) {
            error_log("CategoryService: Iniciando procesamiento nivel $nivel");
            $level_count = 0;
            
            foreach ($categories['items'] as $category) {
                try {
                    $categoria_nivel = isset($category['NIVEL']) ? intval($category['NIVEL']) : 0;
                    
                    if ($categoria_nivel != $nivel) {
                        continue; // Saltar si no es el nivel actual
                    }
                    
                    $level_count++;
                    error_log("CategoryService: Procesando nivel $nivel - {$category['CODIGO']}: {$category['NOMBRE']}");
                    
                    if ($nivel == 1) {
                        $result = $this->processLevelOneCategory($category);
                    } else {
                        $result = $this->processSubCategory($category, $nivel);
                    }
                    
                    error_log("CategoryService: Resultado para {$category['CODIGO']}: " . print_r($result, true));
                    
                    if ($result['success']) {
                        if ($result['action'] === 'created') {
                            $created_categories++;
                            error_log("CategoryService: ✓ Creada - {$category['CODIGO']}");
                        } else {
                            $updated_categories++;
                            error_log("CategoryService: ✓ Actualizada - {$category['CODIGO']}");
                        }
                    } else {
                        $errors[] = $result['error'];
                        error_log("CategoryService: ✗ Error - {$category['CODIGO']}: {$result['error']}");
                    }
                    
                } catch (Exception $e) {
                    $errors[] = 'Error procesando categoría ' . $category['CODIGO'] . ': ' . $e->getMessage();
                }
            }
            
            error_log("CategoryService: Nivel $nivel completado. Procesadas: $level_count categorías");
        }
        
        // Verificar cuántas categorías hay en WooCommerce
        $wc_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'count' => true
        ]);
        $total_wc_categories = is_wp_error($wc_categories) ? 0 : count($wc_categories);
        
        $message = "Proceso completado: $created_categories categorías creadas, $updated_categories actualizadas";
        $message .= ". Total categorías en WooCommerce: $total_wc_categories";
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
        }
        
        error_log("CategoryService: $message");
        
        return [
            'success' => true,
            'message' => $message,
            'created' => $created_categories,
            'updated' => $updated_categories,
            'errors' => $errors
        ];
    }
    
    private function processLevelOneCategory($category) {
        $term_data = [
            'name' => $category['NOMBRE'],
            'slug' => sanitize_title($category['CODIGO']),
            'description' => ''
        ];
        
        // Buscar si ya existe
        $existing_term = get_term_by('slug', $term_data['slug'], 'product_cat');
        
        if ($existing_term) {
            // Actualizar categoría existente
            $result = wp_update_term($existing_term->term_id, 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($existing_term->term_id, $category);
                error_log("CategoryService: Actualizada categoría nivel 1 - {$category['CODIGO']}");
                return ['success' => true, 'action' => 'updated'];
            } else {
                return ['success' => false, 'error' => 'Error actualizando categoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        } else {
            // Crear nueva categoría
            $result = wp_insert_term($term_data['name'], 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($result['term_id'], $category);
                error_log("CategoryService: Creada categoría nivel 1 - {$category['CODIGO']}");
                return ['success' => true, 'action' => 'created'];
            } else {
                return ['success' => false, 'error' => 'Error creando categoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        }
    }
    
    private function processSubCategory($category, $nivel) {
        $parent_key_parts = explode("/", $category['LLAVE']);
        
        // Para nivel 2: buscar por el primer segmento
        // Para nivel 3: buscar por los primeros dos segmentos
        if ($nivel == 2) {
            $parent_code = $parent_key_parts[0];
        } else { // nivel 3
            $parent_code = isset($parent_key_parts[1]) ? $parent_key_parts[1] : $parent_key_parts[0];
        }
        
        error_log("CategoryService: Buscando padre '$parent_code' para {$category['CODIGO']}");
        
        // Buscar la categoría padre
        $parent_term = get_terms([
            'taxonomy' => 'product_cat',
            'meta_query' => [
                [
                    'key' => 'erp_code',
                    'value' => $parent_code,
                    'compare' => '='
                ]
            ],
            'hide_empty' => false,
            'number' => 1
        ]);
        
        if (empty($parent_term)) {
            return ['success' => false, 'error' => "No se encontró categoría padre '$parent_code' para {$category['CODIGO']} (LLAVE: {$category['LLAVE']})"];
        }
        
        $parent_id = $parent_term[0]->term_id;
        
        $term_data = [
            'name' => $category['NOMBRE'],
            'slug' => sanitize_title($category['CODIGO']),
            'parent' => $parent_id,
            'description' => ''
        ];
        
        // Buscar si ya existe
        $existing_term = get_term_by('slug', $term_data['slug'], 'product_cat');
        
        if ($existing_term) {
            // Actualizar subcategoría existente
            $result = wp_update_term($existing_term->term_id, 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($existing_term->term_id, $category);
                error_log("CategoryService: Actualizada subcategoría nivel $nivel - {$category['CODIGO']}");
                return ['success' => true, 'action' => 'updated'];
            } else {
                return ['success' => false, 'error' => 'Error actualizando subcategoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        } else {
            // Crear nueva subcategoría
            $result = wp_insert_term($term_data['name'], 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($result['term_id'], $category);
                error_log("CategoryService: Creada subcategoría nivel $nivel - {$category['CODIGO']}");
                return ['success' => true, 'action' => 'created'];
            } else {
                return ['success' => false, 'error' => 'Error creando subcategoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        }
    }
    
    private function saveTermMeta($term_id, $category) {
        update_term_meta($term_id, 'erp_code', $category['CODIGO']);
        update_term_meta($term_id, 'erp_level', $category['NIVEL']);
        update_term_meta($term_id, 'erp_key', $category['LLAVE']);
    }
    
    public function deleteAllCategories() {
        error_log('CategoryService: Iniciando eliminación masiva de categorías');
        
        try {
            // Obtener todas las categorías de productos
            $categories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'fields' => 'ids'
            ]);
            
            if (is_wp_error($categories)) {
                throw new Exception('Error al obtener categorías: ' . $categories->get_error_message());
            }
            
            $deleted_count = 0;
            $errors = [];
            
            foreach ($categories as $category_id) {
                $result = wp_delete_term($category_id, 'product_cat');
                
                if (is_wp_error($result)) {
                    $errors[] = "Error al eliminar categoría ID $category_id: " . $result->get_error_message();
                } else {
                    $deleted_count++;
                }
            }
            
            $message = "Se eliminaron $deleted_count categorías exitosamente.";
            if (!empty($errors)) {
                $message .= " Errores encontrados: " . implode(', ', $errors);
            }
            
            error_log("CategoryService: $message");
            
            return [
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log('CategoryService: Error - ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al eliminar categorías: ' . $e->getMessage()
            ];
        }
    }
}