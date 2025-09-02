<?php

namespace Socomarca\RandomERP\Services;

class BrandService extends BaseApiService {
    
    private $log_file;
    
    public function __construct() {
        parent::__construct();
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        $this->log_file = $logs_dir . '/brands.log';
        $this->registerTaxonomyHooks();
    }
    
    protected function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] BrandService: $message" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function registerTaxonomyHooks() {
        add_action('init', [$this, 'registerTaxonomies']);
        add_action('product_brand_add_form_fields', [$this, 'addBrandFields']);
        add_action('product_brand_edit_form_fields', [$this, 'editBrandFields']);
        add_action('create_product_brand', [$this, 'saveBrandFields']);
        add_action('edit_product_brand', [$this, 'saveBrandFields']);
        add_filter('manage_edit-product_brand_columns', [$this, 'addBrandColumns']);
        add_action('manage_product_brand_custom_column', [$this, 'addBrandColumnContent'], 10, 3);
    }
    
    public function registerTaxonomies() {
        // Only register if WooCommerce Brands plugin is not active
        if (!taxonomy_exists('product_brand')) {
            register_taxonomy('product_brand', ['product'], [
                'labels' => [
                    'name' => 'Marcas',
                    'singular_name' => 'Marca',
                    'menu_name' => 'Marcas',
                    'all_items' => 'Todas las Marcas',
                    'edit_item' => 'Editar Marca',
                    'view_item' => 'Ver Marca',
                    'update_item' => 'Actualizar Marca',
                    'add_new_item' => 'Agregar Nueva Marca',
                    'new_item_name' => 'Nuevo Nombre de Marca',
                    'parent_item' => 'Marca Padre',
                    'parent_item_colon' => 'Marca Padre:',
                    'search_items' => 'Buscar Marcas',
                    'not_found' => 'No se encontraron marcas'
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'show_tagcloud' => true,
                'hierarchical' => false,
                'rewrite' => ['slug' => 'marca'],
                'capabilities' => [
                    'manage_terms' => 'manage_product_terms',
                    'edit_terms' => 'edit_product_terms',
                    'delete_terms' => 'delete_product_terms',
                    'assign_terms' => 'assign_product_terms',
                ]
            ]);
        }
    }
    
    public function addBrandFields($taxonomy) {
        ?>
        <div class="form-field">
            <label for="random_erp_code">Código ERP</label>
            <input type="text" name="random_erp_code" id="random_erp_code" value="" />
            <p class="description">Código de la marca en Random ERP</p>
        </div>
        <?php
    }
    
    public function editBrandFields($term) {
        $random_erp_code = get_term_meta($term->term_id, 'random_erp_code', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="random_erp_code">Código ERP</label>
            </th>
            <td>
                <input type="text" name="random_erp_code" id="random_erp_code" value="<?php echo esc_attr($random_erp_code); ?>" />
                <p class="description">Código de la marca en Random ERP</p>
            </td>
        </tr>
        <?php
    }
    
    public function saveBrandFields($term_id) {
        if (isset($_POST['random_erp_code'])) {
            $random_erp_code = sanitize_text_field($_POST['random_erp_code']);
            update_term_meta($term_id, 'random_erp_code', $random_erp_code);
        }
    }
    
    public function addBrandColumns($columns) {
        $columns['random_erp_code'] = 'Código ERP';
        return $columns;
    }
    
    public function addBrandColumnContent($content, $column_name, $term_id) {
        if ($column_name === 'random_erp_code') {
            $random_erp_code = get_term_meta($term_id, 'random_erp_code', true);
            $content = !empty($random_erp_code) ? esc_html($random_erp_code) : '-';
        }
        return $content;
    }
    
    public function getBrands() {
        $company_code = get_option('sm_company_code', '');
        $modalidad = get_option('sm_modalidad', '');
        
        if (empty($company_code)) {
            throw new \Exception('Código de empresa no configurado');
        }
        
        $params = [
            'empresa' => $company_code,
            'fields' => 'KOPR,MRPR,NOKOMR'
        ];
        
        if (!empty($modalidad)) {
            $params['modalidad'] = $modalidad;
        }
        
        // For brands, we need to make a custom request since makeApiRequest doesn't support params properly
        $token = $this->getAuthToken();
        if (!$token) {
            throw new \Exception('No se pudo obtener el token de autenticación');
        }
        
        $url = $this->api_url . '/productos?' . http_build_query($params);
        
        $args = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception('Error en la petición HTTP: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new \Exception('Error HTTP ' . $status_code . ': ' . $body_raw);
        }
        
        $body = json_decode($body_raw, true);
        
        if (!$body || !isset($body['data'])) {
            throw new \Exception('Respuesta inválida del API');
        }
        
        return $body;
    }
    
    public function syncBrands() {
        try {
            $this->log('Iniciando sincronización de marcas');
            
            $response = $this->getBrands();
            
            if (!isset($response['data']) || !is_array($response['data'])) {
                throw new \Exception('Respuesta inválida del API: ' . json_encode($response));
            }
            
            $brands = $response['data'];
            $processed_brands = [];
            $created = 0;
            $updated = 0;
            $errors = 0;
            
            foreach ($brands as $product) {
                try {
                    if (!empty($product['MRPR'])) {
                        $brand_code = $product['MRPR'];
                        
                        // Evitar duplicados en el mismo batch
                        if (in_array($brand_code, $processed_brands)) {
                            continue;
                        }
                        $processed_brands[] = $brand_code;
                        
                        $brand_name = !empty($product['NOKOMR']) ? $product['NOKOMR'] : $brand_code;
                        
                        // Verificar si la marca ya existe
                        $existing_term = $this->findBrandByCode($brand_code);
                        
                        if ($existing_term) {
                            // Actualizar marca existente
                            $result = wp_update_term($existing_term->term_id, 'product_brand', [
                                'name' => $brand_name,
                                'slug' => sanitize_title($brand_name)
                            ]);
                            
                            if (!is_wp_error($result)) {
                                $updated++;
                                $this->log("Marca actualizada: {$brand_name} (código: {$brand_code})");
                            } else {
                                $errors++;
                                $this->log("Error actualizando marca {$brand_name}: " . $result->get_error_message());
                            }
                        } else {
                            // Crear nueva marca
                            $result = wp_insert_term($brand_name, 'product_brand', [
                                'slug' => sanitize_title($brand_name)
                            ]);
                            
                            if (!is_wp_error($result)) {
                                // Guardar el código ERP como meta
                                update_term_meta($result['term_id'], 'random_erp_code', $brand_code);
                                $created++;
                                $this->log("Marca creada: {$brand_name} (código: {$brand_code})");
                            } else {
                                $errors++;
                                $this->log("Error creando marca {$brand_name}: " . $result->get_error_message());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->log('Error procesando marca: ' . $e->getMessage());
                }
            }
            
            $this->log("Sincronización de marcas completada: {$created} creadas, {$updated} actualizadas, {$errors} errores");
            
            return [
                'success' => true,
                'message' => "Sincronización completada: {$created} creadas, {$updated} actualizadas",
                'stats' => [
                    'total' => count($processed_brands),
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors
                ]
            ];
            
        } catch (\Exception $e) {
            $this->log('Error sincronizando marcas: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function findBrandByCode($code) {
        $terms = get_terms([
            'taxonomy' => 'product_brand',
            'meta_query' => [
                [
                    'key' => 'random_erp_code',
                    'value' => $code,
                    'compare' => '='
                ]
            ],
            'hide_empty' => false
        ]);
        
        return !empty($terms) ? $terms[0] : null;
    }
    
    public function deleteAllBrands() {
        try {
            $this->log('Iniciando eliminación de todas las marcas');
            
            $terms = get_terms([
                'taxonomy' => 'product_brand',
                'hide_empty' => false
            ]);
            
            $deleted = 0;
            $errors = 0;
            
            foreach ($terms as $term) {
                $result = wp_delete_term($term->term_id, 'product_brand');
                
                if (!is_wp_error($result) && $result !== false) {
                    $deleted++;
                    $this->log("Marca eliminada: {$term->name}");
                } else {
                    $errors++;
                    $error_message = is_wp_error($result) ? $result->get_error_message() : 'Error desconocido';
                    $this->log("Error eliminando marca {$term->name}: {$error_message}");
                }
            }
            
            $this->log("Eliminación de marcas completada: {$deleted} eliminadas, {$errors} errores");
            
            return [
                'success' => true,
                'message' => "Eliminación completada: {$deleted} marcas eliminadas",
                'stats' => [
                    'deleted' => $deleted,
                    'errors' => $errors
                ]
            ];
            
        } catch (\Exception $e) {
            $this->log('Error eliminando marcas: ' . $e->getMessage());
            throw $e;
        }
    }
}