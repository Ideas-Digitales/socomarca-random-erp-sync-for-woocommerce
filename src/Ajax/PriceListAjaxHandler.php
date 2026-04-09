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
        add_action('wp_ajax_sm_process_batch_price_lists', [$this, 'processBatchPriceLists']);
    }

    /**
     * Obtiene las listas de precios y las guarda en cache
     */
    public function getPriceLists() {
        $this->logAction('Iniciando obtencion de listas de precios');

        $result = $this->priceListService->getPriceLists();

        $this->logAction('Resultado - ' . print_r($result, true));

        if ($result['success']) {
            $this->sendSuccessResponse([
                'message' => $result['message'],
                'total' => $result['total'] ?? 0,
                'group_id' => $result['group_id'] ?? 0
            ]);
        } else {
            $this->sendErrorResponse($result['message']);
        }

        wp_die();
    }

    /**
     * Procesa un lote de productos con precios
     */
    public function processBatchPriceLists() {
        $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
        $batch_size = intval(isset($_POST['batch_size']) ? $_POST['batch_size'] : 10);

        $this->logAction("Procesando lote de precios - offset=$offset, batch_size=$batch_size");

        $result = $this->priceListService->processBatchPriceLists($offset, $batch_size);

        $this->logAction('Resultado del lote - ' . print_r($result, true));

        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
}
