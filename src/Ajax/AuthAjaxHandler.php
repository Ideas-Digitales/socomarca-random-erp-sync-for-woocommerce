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
            
            public function validateWithCategories() {
                error_log('SOCOMARCA ERP: Iniciando validateWithCategories()');
                
                try {
                    // Usar getAuthToken que maneja automáticamente producción vs desarrollo
                    $token = $this->getAuthToken();
                    if (!$token) {
                        error_log('SOCOMARCA ERP: No se pudo obtener token (producción o desarrollo)');
                        return ['success' => false, 'error' => 'No se pudo obtener token de autenticación. Verifica la configuración de producción.'];
                    }
                    
                    error_log('SOCOMARCA ERP: Token obtenido exitosamente: ' . substr($token, 0, 20) . '...');
                    
                    // Hacer petición a categorías para validar conectividad completa
                    $categories = $this->makeApiRequest('/familias');
                    
                    if ($categories === false) {
                        error_log('SOCOMARCA ERP: Fallo en petición a /familias');
                        return ['success' => false, 'error' => 'El endpoint de categorías no respondió correctamente'];
                    }
                    
                    error_log('SOCOMARCA ERP: Petición a /familias exitosa');
                    return ['success' => true, 'data' => $categories];
                    
                } catch (Exception $e) {
                    error_log('SOCOMARCA ERP: Excepción en validateWithCategories: ' . $e->getMessage());
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            }
        };
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_validate_connection', [$this, 'validateConnection']);
    }
    
    public function validateConnection() {
        $this->logAction('Validando conexión con el ERP');
        
        // Obtener el modo de operación
        $operation_mode = get_option('sm_operation_mode', 'development');
        
        if ($operation_mode === 'production') {
            // En producción, validar con petición a categorías
            $this->logAction('Modo producción: Validando con endpoint de categorías');
            $result = $this->apiService->validateWithCategories();
            
            if ($result['success']) {
                $this->sendSuccessResponse([
                    'message' => 'Conexión correcta.'
                ]);
            } else {
                $this->sendErrorResponse('Error de conexión: ' . $result['error']);
            }
        } else {
            // En desarrollo, usar validación de token solamente
            $this->logAction('Modo desarrollo: Validando solo autenticación');
            $token = $this->apiService->validateConnection();
            
            if ($token) {
                $this->sendSuccessResponse([
                    'message' => 'Conexión correcta. Token creado exitosamente.'
                ]);
            } else {
                $this->sendErrorResponse('Error al autenticar con el ERP');
            }
        }
        
        wp_die();
    }
}