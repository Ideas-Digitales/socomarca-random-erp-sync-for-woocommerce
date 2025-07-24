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
            $product_count = $this->getProductCount();
            $category_count = $this->getCategoryCount();
            $user_count = $this->getUserCount();
            
            $total_items = $product_count + $category_count + $user_count;
            
            // Reset batch tracking options
            delete_option('sm_batch_delete_progress');
            delete_option('sm_batch_delete_totals');
            
            // Initialize tracking
            update_option('sm_batch_delete_totals', [
                'products_total' => $product_count,
                'categories_total' => $category_count,
                'users_total' => $user_count,
                'products_deleted' => 0,
                'categories_deleted' => 0,
                'users_deleted' => 0,
                'total_items' => $total_items,
                'total_deleted' => 0,
                'current_phase' => 'products' // products -> categories -> users
            ]);
            
            error_log("CombinedAjaxHandler: Inicializado - $product_count productos, $category_count categorías, $user_count usuarios");
            
            $this->sendJsonResponse(true, [
                'success' => true,
                'message' => "Iniciando eliminación por lotes: $total_items elementos total",
                'total_items' => $total_items,
                'products_total' => $product_count,
                'categories_total' => $category_count,
                'users_total' => $user_count
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
            $totals['current_phase'] = 'users';
            error_log('CombinedAjaxHandler: Fase categorías completada, pasando a usuarios');
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
                'products_deleted' => $totals['products_deleted'],
                'categories_deleted' => $totals['categories_deleted'],
                'users_deleted' => $totals['users_deleted'],
                'total_deleted' => $totals['total_deleted']
            ] : null,
            'errors' => $errors
        ];
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