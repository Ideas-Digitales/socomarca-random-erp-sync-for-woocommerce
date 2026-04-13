<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\StockService;

class StockAjaxHandler extends BaseAjaxHandler {

    private $stockService;

    public function __construct() {
        $this->stockService = new StockService();
        parent::__construct();
    }

    protected function registerHooks(): void {
        add_action('wp_ajax_sm_fetch_stock',         [$this, 'fetchStock']);
        add_action('wp_ajax_sm_process_batch_stock', [$this, 'processBatchStock']);
    }

    /**
     * Llama al endpoint del ERP, guarda en cache y devuelve el total.
     */
    public function fetchStock(): void {
        $this->logAction('Iniciando obtencion de stock desde ERP');

        try {
            $result = $this->stockService->fetchStock();

            if ($result['success']) {
                $this->sendSuccessResponse([
                    'message' => $result['message'],
                    'total'   => $result['total'],
                ]);
            } else {
                $this->sendErrorResponse($result['message']);
            }
        } catch (\Exception $e) {
            $this->logAction('Error obteniendo stock: ' . $e->getMessage());
            $this->sendErrorResponse('Error obteniendo stock: ' . $e->getMessage());
        }

        wp_die();
    }

    /**
     * Procesa un lote del cache de stock.
     */
    public function processBatchStock(): void {
        $offset     = intval($_POST['offset']     ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 20);

        try {
            $result = $this->stockService->processBatch($offset, $batch_size);

            if ($result['success']) {
                $this->sendSuccessResponse($result);
            } else {
                $this->sendErrorResponse($result['message']);
            }
        } catch (\Exception $e) {
            $this->logAction('Error procesando lote de stock: ' . $e->getMessage());
            $this->sendErrorResponse('Error procesando lote de stock: ' . $e->getMessage());
        }

        wp_die();
    }
}
