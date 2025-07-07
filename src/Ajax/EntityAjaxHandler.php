<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\EntityService;

class EntityAjaxHandler extends BaseAjaxHandler {
    
    private $entityService;
    
    public function __construct() {
        $this->entityService = new EntityService();
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_get_entities', [$this, 'getEntities']);
        add_action('wp_ajax_sm_process_batch_users', [$this, 'processBatchUsers']);
        add_action('wp_ajax_sm_delete_all_users', [$this, 'deleteAllUsers']);
    }
    
    public function getEntities() {
        $this->logAction('Iniciando proceso de obtención de entidades');
        
        $result = $this->entityService->createUsersFromEntities();
        
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
    
    public function processBatchUsers() {
        $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
        $batch_size = intval(isset($_POST['batch_size']) ? $_POST['batch_size'] : 10);
        
        $this->logAction("Procesando lote de usuarios - offset=$offset, batch_size=$batch_size");
        
        $result = $this->entityService->processBatchUsers($offset, $batch_size);
        
        $this->logAction('Resultado del lote - ' . print_r($result, true));
        
        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
    
    public function deleteAllUsers() {
        $this->requireAdminPermissions();
        $this->requireConfirmation('DELETE_ALL_USERS');
        
        $this->logAction('Iniciando eliminación masiva de usuarios');
        
        $result = $this->entityService->deleteAllUsersExceptAdmin();
        
        $this->logAction('Resultado eliminación - ' . print_r($result, true));
        
        $this->sendJsonResponse($result['success'], $result);
        wp_die();
    }
}