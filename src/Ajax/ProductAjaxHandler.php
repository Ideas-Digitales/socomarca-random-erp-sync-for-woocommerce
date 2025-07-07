<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\ProductService;

class ProductAjaxHandler extends BaseAjaxHandler {
    
    private $productService;
    
    public function __construct() {
        $this->productService = new ProductService();
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_get_products', [$this, 'getProducts']);
        add_action('wp_ajax_sm_process_products', [$this, 'processProducts']);
        add_action('wp_ajax_sm_process_batch_products', [$this, 'processBatchProducts']);
        add_action('wp_ajax_sm_delete_all_products', [$this, 'deleteAllProducts']);
    }
    
    public function getProducts() {
        $this->logAction('Iniciando proceso de obtención de productos');
        
        $products = $this->productService->getProducts();
        
        $this->logAction('Resultado - ' . print_r($products, true));
        
        if ($products && isset($products['items'])) {
            $this->sendSuccessResponse([
                'message' => $products['quantity'] . ' productos obtenidos del ERP',
                'total' => $products['quantity'],
                'products' => $products['items']
            ]);
        } else {
            $this->sendErrorResponse('No se pudieron obtener los productos del ERP');
        }
        
        wp_die();
    }
    
    public function processProducts() {
        $this->logAction('Iniciando proceso de productos');
        
        $result = $this->productService->processProducts();
        
        $this->logAction('Resultado - ' . print_r($result, true));
        
        if ($result['success']) {
            $this->sendSuccessResponse([
                'message' => $result['message'],
                'total' => $result['total']
            ]);
        } else {
            $this->sendErrorResponse($result['message']);
        }
        
        wp_die();
    }
    
    public function processBatchProducts() {
        $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
        $batch_size = intval(isset($_POST['batch_size']) ? $_POST['batch_size'] : 10);
        
        $this->logAction("Procesando lote de productos - offset=$offset, batch_size=$batch_size");
        
        $result = $this->productService->processBatchProducts($offset, $batch_size);
        
        $this->logAction('Resultado del lote - ' . print_r($result, true));
        
        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
    
    public function deleteAllProducts() {
        $this->requireAdminPermissions();
        $this->requireConfirmation('DELETE_ALL_PRODUCTS');
        
        $this->logAction('Iniciando eliminación masiva de productos');
        
        $result = $this->productService->deleteAllProducts();
        
        $this->logAction('Resultado eliminación - ' . print_r($result, true));
        
        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
}