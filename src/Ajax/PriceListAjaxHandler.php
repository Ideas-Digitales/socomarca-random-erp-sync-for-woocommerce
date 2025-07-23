<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\PriceListService;

class PriceListAjaxHandler extends BaseAjaxHandler {
    
    private $priceListService;
    
    public function __construct() {
        $this->priceListService = new PriceListService();
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_get_price_lists', [$this, 'getPriceLists']);
    }
    
    public function getPriceLists() {
        $this->logAction('Iniciando proceso de obtención de listas de precios');
        
        $result = $this->priceListService->getPriceLists();
        
        $this->logAction('Resultado completo - ' . print_r($result, true));
        
        if ($result['success']) {
            $this->sendSuccessResponse([
                'message' => $result['message'],
                'data' => $result['data'] ?? []
            ]);
        } else {
            $this->sendErrorResponse($result['message']);
        }
        
        wp_die();
    }
}