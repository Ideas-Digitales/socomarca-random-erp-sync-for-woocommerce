<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\CronSyncService;

class CronSyncAjaxHandler extends BaseAjaxHandler {
    
    private $cronSyncService;
    
    public function __construct() {
        parent::__construct();
        $this->cronSyncService = new CronSyncService();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_manual_sync', [$this, 'handleManualSync']);
    }
    
    public function handleManualSync() {
        $this->logAction('Ejecutando sincronización manual completa');
        
        try {
            // Ejecutar la sincronización manual
            $this->cronSyncService->executeSynchronization();
            
            // Obtener información de la última sincronización
            $lastSync = $this->cronSyncService->getLastSyncInfo();
            
            if ($lastSync && $lastSync['status'] === 'success') {
                $this->sendSuccessResponse([
                    'message' => 'Sincronización completada exitosamente',
                    'execution_time' => $lastSync['execution_time'],
                    'results' => $lastSync['results']
                ]);
            } else {
                $this->sendErrorResponse('Error en la sincronización: ' . ($lastSync['error'] ?? 'Error desconocido'));
            }
            
        } catch (\Exception $e) {
            $this->sendErrorResponse('Error: ' . $e->getMessage());
        }
        
        wp_die();
    }
}