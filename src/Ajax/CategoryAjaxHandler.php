<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\CategoryService;

class CategoryAjaxHandler extends BaseAjaxHandler {
    
    private $categoryService;
    
    public function __construct() {
        $this->categoryService = new CategoryService();
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_get_categories', [$this, 'getCategories']);
        add_action('wp_ajax_sm_process_categories', [$this, 'processCategories']);
        add_action('wp_ajax_sm_delete_all_categories', [$this, 'deleteAllCategories']);
    }
    
    public function getCategories() {
        $this->logAction('Iniciando proceso de obtención y procesamiento de categorías');
        
        
        $result = $this->categoryService->processCategories();
        
        $this->logAction('Resultado completo - ' . print_r($result, true));
        
        if ($result['success']) {
            $this->sendSuccessResponse([
                'message' => $result['message'],
                'total' => isset($result['created']) ? $result['created'] + $result['updated'] : 0,
                'created' => isset($result['created']) ? $result['created'] : 0,
                'updated' => isset($result['updated']) ? $result['updated'] : 0
            ]);
        } else {
            $this->sendErrorResponse($result['message']);
        }
        
        wp_die();
    }
    
    public function processCategories() {
        $this->logAction('Iniciando procesamiento de categorías');
        
        $result = $this->categoryService->processCategories();
        
        $this->logAction('Resultado procesamiento - ' . print_r($result, true));
        
        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
    
    public function deleteAllCategories() {
        $this->requireAdminPermissions();
        $this->requireConfirmation('DELETE_ALL_CATEGORIES');
        
        $this->logAction('Iniciando eliminación masiva de categorías');
        
        $result = $this->categoryService->deleteAllCategories();
        
        $this->logAction('Resultado eliminación - ' . print_r($result, true));
        
        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
}