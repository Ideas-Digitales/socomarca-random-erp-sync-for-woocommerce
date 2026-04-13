<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\EntityService;
use Socomarca\RandomERP\Services\CategoryService;
use Socomarca\RandomERP\Services\ProductService;

class CombinedAjaxHandler extends BaseAjaxHandler {
    
    public function __construct() {
        $this->registerHooks();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_delete_all_data', [$this, 'initDeleteAllData']);
        add_action('wp_ajax_sm_delete_batch_data', [$this, 'deleteBatchData']);
    }
    
    public function initDeleteAllData() {
        $this->requireAdminPermissions();
        $this->requireConfirmation('DELETE_ALL_DATA');
        $this->logAction('Iniciando preparación para eliminación masiva por lotes');
        
        try {
            // Get totals for each type to initialize progress bar
            $product_count   = $this->getProductCount();
            $category_count  = $this->getCategoryCount();
            $user_count      = $this->getUserCount();
            $brand_count     = $this->getBrandCount();
            $warehouse_count = $this->getWarehouseCount();
            $b2bking_count   = $this->getB2BKingGroupCount();

            $total_items = $product_count + $category_count + $user_count + $brand_count + $warehouse_count + $b2bking_count;

            delete_option('sm_batch_delete_progress');
            delete_option('sm_batch_delete_totals');

            // products -> categories -> brands -> warehouses -> b2bking -> users
            update_option('sm_batch_delete_totals', [
                'products_total'     => $product_count,
                'categories_total'   => $category_count,
                'users_total'        => $user_count,
                'brands_total'       => $brand_count,
                'warehouses_total'   => $warehouse_count,
                'b2bking_total'      => $b2bking_count,
                'products_deleted'   => 0,
                'categories_deleted' => 0,
                'users_deleted'      => 0,
                'brands_deleted'     => 0,
                'warehouses_deleted' => 0,
                'b2bking_deleted'    => 0,
                'total_items'        => $total_items,
                'total_deleted'      => 0,
                'current_phase'      => 'products',
            ]);

            error_log("CombinedAjaxHandler: Inicializado - $product_count productos, $category_count categorías, $user_count usuarios, $brand_count marcas, $warehouse_count bodegas, $b2bking_count grupos B2B King");

            $this->sendJsonResponse(true, [
                'success'          => true,
                'message'          => "Iniciando eliminación por lotes: $total_items elementos total",
                'total_items'      => $total_items,
                'products_total'   => $product_count,
                'categories_total' => $category_count,
                'users_total'      => $user_count,
                'brands_total'     => $brand_count,
                'warehouses_total' => $warehouse_count,
                'b2bking_total'    => $b2bking_count,
            ]);
            
        } catch (Exception $e) {
            error_log('CombinedAjaxHandler: Error inicializando eliminación masiva - ' . $e->getMessage());
            $this->sendJsonResponse(false, [
                'success' => false,
                'message' => 'Error preparando eliminación masiva: ' . $e->getMessage()
            ]);
        }
        
        wp_die();
    }
    
    public function deleteBatchData() {
        $this->requireAdminPermissions();
        
        try {
            $totals = get_option('sm_batch_delete_totals', []);
            if (empty($totals)) {
                throw new Exception('No se encontraron datos de progreso de eliminación');
            }
            
            $batch_size = 10;
            $phase = $totals['current_phase'];
            $result = null;
            
            switch ($phase) {
                case 'products':
                    $result = $this->deleteBatchProducts($batch_size, $totals);
                    break;
                case 'categories':
                    $result = $this->deleteBatchCategories($batch_size, $totals);
                    break;
                case 'brands':
                    $result = $this->deleteBatchBrands($batch_size, $totals);
                    break;
                case 'warehouses':
                    $result = $this->deleteBatchWarehouses($batch_size, $totals);
                    break;
                case 'b2bking':
                    $result = $this->deleteBatchB2BKingGroups($batch_size, $totals);
                    break;
                case 'users':
                    $result = $this->deleteBatchUsers($batch_size, $totals);
                    break;
                default:
                    throw new Exception('Fase de eliminación desconocida: ' . $phase);
            }
            
            $this->sendJsonResponse(true, $result);
            
        } catch (Exception $e) {
            error_log('CombinedAjaxHandler: Error en lote de eliminación - ' . $e->getMessage());
            $this->sendJsonResponse(false, [
                'success' => false,
                'message' => 'Error procesando lote: ' . $e->getMessage()
            ]);
        }
        
        wp_die();
    }
    
    private function deleteBatchProducts($batch_size, &$totals) {
        $products = wc_get_products([
            'status' => ['publish', 'private', 'draft', 'pending', 'trash'],
            'limit' => $batch_size,
            'return' => 'ids'
        ]);
        
        $deleted_this_batch = 0;
        $errors = [];
        
        foreach ($products as $product_id) {
            try {
                $result = wp_delete_post($product_id, true);
                if ($result) {
                    $deleted_this_batch++;
                }
            } catch (Exception $e) {
                $errors[] = "Error eliminando producto ID $product_id: " . $e->getMessage();
            }
        }
        
        $totals['products_deleted'] += $deleted_this_batch;
        $totals['total_deleted'] += $deleted_this_batch;
        
        // Check if products phase is complete
        $remaining_products = wc_get_products([
            'status' => ['publish', 'private', 'draft', 'pending', 'trash'],
            'limit' => 1,
            'return' => 'ids'
        ]);
        
        if (empty($remaining_products)) {
            $totals['current_phase'] = 'categories';
            error_log('CombinedAjaxHandler: Fase productos completada, pasando a categorías');
        }
        
        update_option('sm_batch_delete_totals', $totals);
        
        return [
            'success' => true,
            'phase' => 'products',
            'deleted_this_batch' => $deleted_this_batch,
            'total_deleted' => $totals['total_deleted'],
            'total_items' => $totals['total_items'],
            'phase_complete' => empty($remaining_products),
            'next_phase' => empty($remaining_products) ? 'categories' : 'products',
            'message' => "Eliminados $deleted_this_batch productos en este lote",
            'errors' => $errors
        ];
    }
    
    private function deleteBatchCategories($batch_size, &$totals) {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => $batch_size,
            'fields' => 'ids'
        ]);
        
        $deleted_this_batch = 0;
        $errors = [];
        
        foreach ($categories as $category_id) {
            try {
                $result = wp_delete_term($category_id, 'product_cat');
                if (!is_wp_error($result) && $result) {
                    $deleted_this_batch++;
                }
            } catch (Exception $e) {
                $errors[] = "Error eliminando categoría ID $category_id: " . $e->getMessage();
            }
        }
        
        $totals['categories_deleted'] += $deleted_this_batch;
        $totals['total_deleted'] += $deleted_this_batch;
        
        // Check if categories phase is complete
        $remaining_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => 1,
            'fields' => 'ids'
        ]);
        
        if (empty($remaining_categories)) {
            $totals['current_phase'] = 'brands';
            error_log('CombinedAjaxHandler: Fase categorías completada, pasando a marcas');
        }
        
        update_option('sm_batch_delete_totals', $totals);
        
        return [
            'success' => true,
            'phase' => 'categories',
            'deleted_this_batch' => $deleted_this_batch,
            'total_deleted' => $totals['total_deleted'],
            'total_items' => $totals['total_items'],
            'phase_complete' => empty($remaining_categories),
            'next_phase' => empty($remaining_categories) ? 'users' : 'categories',
            'message' => "Eliminadas $deleted_this_batch categorías en este lote",
            'errors' => $errors
        ];
    }
    
    private function deleteBatchBrands($batch_size, &$totals) {
        $brands = get_terms([
            'taxonomy'   => 'product_brand',
            'hide_empty' => false,
            'number'     => $batch_size,
            'fields'     => 'ids',
        ]);

        $deleted_this_batch = 0;
        $errors = [];

        if (!is_wp_error($brands)) {
            foreach ($brands as $term_id) {
                $result = wp_delete_term($term_id, 'product_brand');
                if (!is_wp_error($result) && $result) {
                    $deleted_this_batch++;
                } else {
                    $errors[] = "Error eliminando marca ID $term_id";
                }
            }
        }

        $totals['brands_deleted'] += $deleted_this_batch;
        $totals['total_deleted']  += $deleted_this_batch;

        $remaining = get_terms(['taxonomy' => 'product_brand', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids']);

        if (empty($remaining) || is_wp_error($remaining)) {
            $totals['current_phase'] = 'warehouses';
            error_log('CombinedAjaxHandler: Fase marcas completada, pasando a bodegas');
        }

        update_option('sm_batch_delete_totals', $totals);

        return [
            'success'            => true,
            'phase'              => 'brands',
            'deleted_this_batch' => $deleted_this_batch,
            'total_deleted'      => $totals['total_deleted'],
            'total_items'        => $totals['total_items'],
            'phase_complete'     => empty($remaining) || is_wp_error($remaining),
            'next_phase'         => (empty($remaining) || is_wp_error($remaining)) ? 'warehouses' : 'brands',
            'message'            => "Eliminadas $deleted_this_batch marcas en este lote",
            'errors'             => $errors,
        ];
    }

    private function deleteBatchWarehouses($batch_size, &$totals) {
        $terms = get_terms([
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'number'     => $batch_size,
            'fields'     => 'ids',
        ]);

        $deleted_this_batch = 0;
        $errors = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                $result = wp_delete_term($term_id, 'locations');
                if (!is_wp_error($result) && $result) {
                    $deleted_this_batch++;
                } else {
                    $errors[] = "Error eliminando bodega ID $term_id";
                }
            }
        }

        $totals['warehouses_deleted'] += $deleted_this_batch;
        $totals['total_deleted']      += $deleted_this_batch;

        $remaining = get_terms(['taxonomy' => 'locations', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids']);

        if (empty($remaining) || is_wp_error($remaining)) {
            $totals['current_phase'] = 'b2bking';
            error_log('CombinedAjaxHandler: Fase bodegas completada, pasando a precios B2B King');
        }

        update_option('sm_batch_delete_totals', $totals);

        return [
            'success'            => true,
            'phase'              => 'warehouses',
            'deleted_this_batch' => $deleted_this_batch,
            'total_deleted'      => $totals['total_deleted'],
            'total_items'        => $totals['total_items'],
            'phase_complete'     => empty($remaining) || is_wp_error($remaining),
            'next_phase'         => (empty($remaining) || is_wp_error($remaining)) ? 'users' : 'warehouses',
            'message'            => "Eliminadas $deleted_this_batch bodegas en este lote",
            'errors'             => $errors,
        ];
    }

    private function deleteBatchB2BKingGroups($batch_size, &$totals) {
        $posts = get_posts([
            'post_type'      => 'b2bking_group',
            'post_status'    => 'any',
            'numberposts'    => $batch_size,
            'fields'         => 'ids',
        ]);

        $deleted_this_batch = 0;
        $errors = [];

        foreach ($posts as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                $deleted_this_batch++;
            } else {
                $errors[] = "Error eliminando grupo B2B King ID $post_id";
            }
        }

        $totals['b2bking_deleted'] = ($totals['b2bking_deleted'] ?? 0) + $deleted_this_batch;
        $totals['total_deleted']  += $deleted_this_batch;

        $remaining = get_posts(['post_type' => 'b2bking_group', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids']);

        if (empty($remaining)) {
            $totals['current_phase'] = 'users';
            error_log('CombinedAjaxHandler: Fase B2B King completada, pasando a usuarios');
        }

        update_option('sm_batch_delete_totals', $totals);

        return [
            'success'            => true,
            'phase'              => 'b2bking',
            'deleted_this_batch' => $deleted_this_batch,
            'total_deleted'      => $totals['total_deleted'],
            'total_items'        => $totals['total_items'],
            'phase_complete'     => empty($remaining),
            'next_phase'         => empty($remaining) ? 'users' : 'b2bking',
            'message'            => "Eliminados $deleted_this_batch grupos B2B King en este lote",
            'errors'             => $errors,
        ];
    }

    private function deleteBatchUsers($batch_size, &$totals) {
        $users = get_users([
            'role__not_in' => ['administrator', 'super_admin'],
            'number' => $batch_size,
            'fields' => 'ID'
        ]);
        
        $deleted_this_batch = 0;
        $errors = [];
        
        foreach ($users as $user_id) {
            try {
                if (!user_can($user_id, 'manage_options')) {
                    $result = wp_delete_user($user_id);
                    if ($result) {
                        $deleted_this_batch++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error eliminando usuario ID $user_id: " . $e->getMessage();
            }
        }
        
        $totals['users_deleted'] += $deleted_this_batch;
        $totals['total_deleted'] += $deleted_this_batch;
        
        // Check if users phase is complete
        $remaining_users = get_users([
            'role__not_in' => ['administrator', 'super_admin'],
            'number' => 1,
            'fields' => 'ID'
        ]);
        
        $is_complete = empty($remaining_users);
        
        if ($is_complete) {
            // Cleanup options when completely done
            delete_option('sm_batch_delete_progress');
            delete_option('sm_batch_delete_totals');
            error_log('CombinedAjaxHandler: Eliminación masiva completada');
        }
        
        update_option('sm_batch_delete_totals', $totals);
        
        return [
            'success' => true,
            'phase' => 'users',
            'deleted_this_batch' => $deleted_this_batch,
            'total_deleted' => $totals['total_deleted'],
            'total_items' => $totals['total_items'],
            'phase_complete' => $is_complete,
            'all_complete' => $is_complete,
            'message' => $is_complete ? 
                "Eliminación masiva completada: {$totals['total_deleted']} elementos eliminados" :
                "Eliminados $deleted_this_batch usuarios en este lote",
            'final_summary' => $is_complete ? [
                'products_deleted'   => $totals['products_deleted'],
                'categories_deleted' => $totals['categories_deleted'],
                'brands_deleted'     => $totals['brands_deleted'],
                'warehouses_deleted' => $totals['warehouses_deleted'],
                'b2bking_deleted'    => $totals['b2bking_deleted'] ?? 0,
                'users_deleted'      => $totals['users_deleted'],
                'total_deleted'      => $totals['total_deleted'],
            ] : null,
            'errors' => $errors
        ];
    }
    
    private function getB2BKingGroupCount() {
        return (int) wp_count_posts('b2bking_group')->publish
             + (int) (wp_count_posts('b2bking_group')->private ?? 0);
    }

    private function getBrandCount() {
        $terms = get_terms(['taxonomy' => 'product_brand', 'hide_empty' => false, 'fields' => 'ids']);
        return (!is_wp_error($terms) && is_array($terms)) ? count($terms) : 0;
    }

    private function getWarehouseCount() {
        $terms = get_terms(['taxonomy' => 'locations', 'hide_empty' => false, 'fields' => 'ids']);
        return (!is_wp_error($terms) && is_array($terms)) ? count($terms) : 0;
    }

    private function getProductCount() {
        $products = wc_get_products([
            'status' => ['publish', 'private', 'draft', 'pending', 'trash'],
            'limit' => -1,
            'return' => 'ids'
        ]);
        return count($products);
    }
    
    private function getCategoryCount() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'fields' => 'ids'
        ]);
        return is_array($categories) ? count($categories) : 0;
    }
    
    private function getUserCount() {
        $users = get_users([
            'role__not_in' => ['administrator', 'super_admin'],
            'fields' => 'ID'
        ]);
        return count($users);
    }
}