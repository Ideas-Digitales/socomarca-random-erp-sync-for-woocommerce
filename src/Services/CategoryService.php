<?php

namespace Socomarca\RandomERP\Services;

class CategoryService extends BaseApiService {
    
    public function getCategories() {
        $endpoint = "/familias";
        $categories = $this->makeApiRequest($endpoint);
        
        if ($categories !== false && is_array($categories)) {
            return [
                'quantity' => count($categories),
                'items' => $categories
            ];
        }
        
        return false;
    }
    
    public function processCategories() {
        $categories = $this->getCategories();
        
        if (!$categories || !isset($categories['items'])) {
            return [
                'success' => false,
                'message' => 'No se pudieron obtener las categorías del ERP'
            ];
        }
        
        $created_categories = 0;
        $updated_categories = 0;
        $errors = [];
        
        for ($nivel = 1; $nivel <= 3; $nivel++) {
            $level_count = 0;
            
            foreach ($categories['items'] as $category) {
                try {
                    $categoria_nivel = isset($category['NIVEL']) ? intval($category['NIVEL']) : 0;
                    
                    if ($categoria_nivel != $nivel) {
                        continue;
                    }
                    
                    $level_count++;
                    
                    if ($nivel == 1) {
                        $result = $this->processLevelOneCategory($category);
                    } else {
                        $result = $this->processSubCategory($category, $nivel);
                    }
                    
                    if ($result['success']) {
                        if ($result['action'] === 'created') {
                            $created_categories++;
                        } else {
                            $updated_categories++;
                        }
                    } else {
                        $errors[] = $result['error'];
                    }
                    
                } catch (Exception $e) {
                    $errors[] = 'Error procesando categoría ' . $category['CODIGO'] . ': ' . $e->getMessage();
                }
            }
            
        }
        
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
        
        $existing_term = get_term_by('slug', $term_data['slug'], 'product_cat');
        
        if ($existing_term) {
            $result = wp_update_term($existing_term->term_id, 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($existing_term->term_id, $category);
                return ['success' => true, 'action' => 'updated'];
            } else {
                return ['success' => false, 'error' => 'Error actualizando categoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        } else {
            $result = wp_insert_term($term_data['name'], 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($result['term_id'], $category);
                return ['success' => true, 'action' => 'created'];
            } else {
                return ['success' => false, 'error' => 'Error creando categoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        }
    }
    
    private function processSubCategory($category, $nivel) {
        $parent_key_parts = explode("/", $category['LLAVE']);
        
        
        
        if ($nivel == 2) {
            $parent_code = $parent_key_parts[0];
        } else { 
            $parent_code = isset($parent_key_parts[1]) ? $parent_key_parts[1] : $parent_key_parts[0];
        }
        
        
        
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
        
        $existing_term = get_term_by('slug', $term_data['slug'], 'product_cat');
        
        if ($existing_term) {
            $result = wp_update_term($existing_term->term_id, 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($existing_term->term_id, $category);
                return ['success' => true, 'action' => 'updated'];
            } else {
                return ['success' => false, 'error' => 'Error actualizando subcategoría ' . $category['CODIGO'] . ': ' . $result->get_error_message()];
            }
        } else {
            $result = wp_insert_term($term_data['name'], 'product_cat', $term_data);
            if (!is_wp_error($result)) {
                $this->saveTermMeta($result['term_id'], $category);
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
        try {
            
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
            
            return [
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar categorías: ' . $e->getMessage()
            ];
        }
    }
}