<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\WarehouseService;

class WarehouseAjaxHandler extends BaseAjaxHandler {

    private $warehouseService;

    public function __construct() {
        $this->warehouseService = new WarehouseService();
        parent::__construct();
    }

    protected function registerHooks() {
        add_action('wp_ajax_sm_get_warehouses', [$this, 'getWarehouses']);
        add_action('wp_ajax_sm_delete_all_warehouses', [$this, 'deleteAllWarehouses']);
    }

    public function getWarehouses() {
        $this->logAction('Iniciando sincronización de bodegas');

        try {
            $result = $this->warehouseService->syncWarehouses();

            $this->logAction('Resultado sincronización bodegas - ' . print_r($result, true));

            if ($result['success']) {
                $this->sendSuccessResponse([
                    'message' => $result['message'],
                    'stats' => $result['stats'],
                ]);
            } else {
                $this->sendErrorResponse($result['message']);
            }
        } catch (\Exception $e) {
            $this->logAction('Error sincronizando bodegas: ' . $e->getMessage());
            $this->sendErrorResponse('Error sincronizando bodegas: ' . $e->getMessage());
        }

        wp_die();
    }

    public function deleteAllWarehouses() {
        $this->requireAdminPermissions();
        $this->requireConfirmation('DELETE_ALL_WAREHOUSES');

        $this->logAction('Iniciando eliminación masiva de bodegas');

        try {
            $result = $this->warehouseService->deleteAllWarehouses();

            $this->logAction('Resultado eliminación bodegas - ' . print_r($result, true));

            $this->sendJsonResponse($result['success'], $result);
        } catch (\Exception $e) {
            $this->logAction('Error eliminando bodegas: ' . $e->getMessage());
            $this->sendErrorResponse('Error eliminando bodegas: ' . $e->getMessage());
        }

        wp_die();
    }
}
