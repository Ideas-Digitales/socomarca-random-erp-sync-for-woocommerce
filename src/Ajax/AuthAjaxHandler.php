<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\BaseApiService;

class AuthAjaxHandler extends BaseAjaxHandler {
    
    private $apiService;
    
    public function __construct() {
        $this->apiService = new class extends BaseApiService {
            public function validateConnection() {
                return $this->authenticate();
            }
        };
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_validate_connection', [$this, 'validateConnection']);
    }
    
    public function validateConnection() {
        $this->logAction('Validando conexión con el ERP');
        
        $token = $this->apiService->validateConnection();
        
        if ($token) {
            $this->sendSuccessResponse([
                'message' => 'Conexion correcta. Token creado exitosamente.'
            ]);
        } else {
            $this->sendErrorResponse('Error al autenticar con el ERP');
        }
        
        wp_die();
    }
}