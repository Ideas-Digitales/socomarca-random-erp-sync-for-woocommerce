<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\BrandService;

class BrandAjaxHandler extends BaseAjaxHandler {
    
    private $brandService;
    
    public function __construct() {
        $this->brandService = new BrandService();
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_get_brands', [$this, 'getBrands']);
        add_action('wp_ajax_sm_delete_all_brands', [$this, 'deleteAllBrands']);
    }
    
    public function getBrands() {
        $this->logAction('Iniciando sincronización de marcas');
        
        try {
            $result = $this->brandService->syncBrands();
            
            $this->logAction('Resultado sincronización - ' . print_r($result, true));
            
            if ($result['success']) {
                $this->sendSuccessResponse([
                    'message' => $result['message'],
                    'stats' => $result['stats']
                ]);
            } else {
                $this->sendErrorResponse($result['message']);
            }
            
        } catch (\Exception $e) {
            $this->logAction('Error sincronizando marcas: ' . $e->getMessage());
            $this->sendErrorResponse('Error sincronizando marcas: ' . $e->getMessage());
        }
        
        wp_die();
    }
    
    public function deleteAllBrands() {
        $this->requireAdminPermissions();
        $this->requireConfirmation('DELETE_ALL_BRANDS');
        
        $this->logAction('Iniciando eliminación masiva de marcas');
        
        try {
            $result = $this->brandService->deleteAllBrands();
            
            $this->logAction('Resultado eliminación - ' . print_r($result, true));
            
            $this->sendJsonResponse($result['success'], $result);
            
        } catch (\Exception $e) {
            $this->logAction('Error eliminando marcas: ' . $e->getMessage());
            $this->sendErrorResponse('Error eliminando marcas: ' . $e->getMessage());
        }
        
        wp_die();
    }
}