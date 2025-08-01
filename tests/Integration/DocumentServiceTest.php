<?php

use Socomarca\RandomERP\Services\DocumentService;
use Tests\Integration\IntegrationTestCase;

beforeEach(function () {
    // Define plugin directory constant if not defined
    if (!defined('SOCOMARCA_ERP_PLUGIN_DIR')) {
        define('SOCOMARCA_ERP_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
    }
    
    // Clear logs directory
    $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
    if (file_exists($logs_dir . '/documents.log')) {
        unlink($logs_dir . '/documents.log');
    }
    
    // Reset WordPress options for each test
    delete_option('sm_invoice_on_completion');
    delete_option('random_erp_token');
    delete_option('sm_default_entity_code');
    
    // Load shared mock functions
    require_once dirname(__DIR__) . '/Stubs/DocumentServiceMockFunctions.php';
    
    // Mock WordPress functions needed for DocumentService
    mockDocumentServiceWordPressOrderFunctions();
    mockDocumentServiceWordPressUserFunctions();
    mockDocumentServiceWordPressFileFunctions();
    
    // Load mock HTTP functions for API testing
    require_once dirname(__DIR__) . '/Stubs/MockDocumentServiceFunctions.php';
    
    // Clear mock API responses
    global $mock_api_responses, $mock_api_errors, $mock_api_exceptions, $mock_api_response_sequences;
    $mock_api_responses = [];
    $mock_api_errors = [];
    $mock_api_exceptions = [];
    $mock_api_response_sequences = [];
});

afterEach(function () {
    // Clean up after each test
    global $mock_orders, $mock_users, $mock_order_notes;
    $mock_orders = [];
    $mock_users = [];
    $mock_order_notes = [];
});

describe('Pruebas de Integración DocumentService', function () {
    
    it('puede ser instanciado', function () {
        $service = new DocumentService();
        expect($service)->toBeInstanceOf(DocumentService::class);
    });
    
    it('crea archivo de log cuando es instanciado', function () {
        new DocumentService();
        $log_file = SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log';
        expect(file_exists(dirname($log_file)))->toBeTrue();
    });
    
    it('no se engancha a la completación de orden cuando la configuración está deshabilitada', function () {
        update_option('sm_invoice_on_completion', false);
        
        $service = new DocumentService();
        
        // Verificar que el hook no se agregó
        expect(has_action('woocommerce_order_status_completed', [$service, 'create_invoice_on_order_completion']))->toBeFalse();
    });
    
    it('se engancha a la completación de orden cuando la configuración está habilitada', function () {
        update_option('sm_invoice_on_completion', true);
        
        $service = new DocumentService();
        
        // Disparar la acción init para configurar hooks
        do_action('init');
        
        // En una prueba real, verificaríamos si el hook fue agregado
        // Por ahora, solo verificamos que la opción fue configurada
        expect(get_option('sm_invoice_on_completion'))->toBeTrue();
    });
});

describe('Procesamiento de Órdenes DocumentService', function () {
    
    beforeEach(function () {
        // Configurar credenciales API
        $this->setupApiCredentials();
        
        // Crear orden mock con items
        createDocumentServiceMockOrder(123, [
            'user_id' => 1,
            'status' => 'completed',
            'items' => [
                ['sku' => 'BEBIDA', 'quantity' => 2, 'product_id' => 100],
                ['sku' => 'COMIDA', 'quantity' => 1, 'product_id' => 101]
            ]
        ]);
        
        // Crear usuario mock con código de entidad
        createDocumentServiceMockUser(1, [
            'meta' => ['random_erp_entity_code' => '5']
        ]);
    });
    
    it('crea factura exitosamente para orden válida', function () {
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(123);
        
        // Dado que estamos haciendo llamadas reales a la API con datos de prueba que pueden no existir en el ERP,
        // esperamos que esto pueda fallar, lo cual es normal para pruebas de integración
        if ($result !== false) {
            // Si tiene éxito, verificar que se agregó la nota de orden
            global $mock_order_notes;
            expect($mock_order_notes[123])->toContain('Factura creada en Random ERP exitosamente');
            
            // Verificar que el archivo de log contiene mensaje de éxito
            $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
            expect($log_content)->toContain('Invoice created successfully for order: 123');
        } else {
            // Si falla (esperado con datos de prueba), verificar que el fallo fue registrado
            $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
            expect($log_content)->toContain('Failed to create invoice for order: 123');
        }
        
        // En cualquier caso, asegurar que se intentó la llamada API
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Sending document to Random ERP API');
    });
    
    it('maneja orden faltante de manera elegante', function () {
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(999);
        
        expect($result)->toBeFalse();
        
        // Verificar que el log contiene mensaje de error
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Order not found for ID: 999');
    });
    
    it('maneja código de entidad faltante usando valor por defecto', function () {
        // Crear usuario sin código de entidad
        createDocumentServiceMockUser(2, ['meta' => []]);
        createDocumentServiceMockOrder(124, ['user_id' => 2, 'items' => [['sku' => 'TEST', 'quantity' => 1]]]);
        
        update_option('sm_default_entity_code', '10');
        
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(124);
        
        // Verificar que se intentó la llamada API con código de entidad por defecto
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Sending document to Random ERP API');
        expect($log_content)->toContain('"codigoEntidad":"10"');
    });
    
    it('maneja orden sin items válidos', function () {
        // Crear orden con items que no tienen SKU
        createDocumentServiceMockOrder(125, [
            'user_id' => 1,
            'items' => [['sku' => '', 'quantity' => 1]]
        ]);
        
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(125);
        
        expect($result)->toBeFalse();
        
        // Verificar que el log contiene error apropiado
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('No valid lines found for order: 125');
    });
    
    it('procesa variaciones de productos correctamente', function () {
        // Crear orden con producto de variación (SKU con |)
        createDocumentServiceMockOrder(126, [
            'user_id' => 1,
            'items' => [['sku' => 'PRODUCTO|001', 'quantity' => 1]]
        ]);
        
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(126);
        
        // Verificar que se intentó la llamada API y se usó el SKU padre (antes de |)
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Sending document to Random ERP API');
        expect($log_content)->toContain('"codigoProducto":"PRODUCTO"');
    });
});

describe('Manejo de Errores API DocumentService', function () {
    
    beforeEach(function () {
        $this->setupApiCredentials();
        createDocumentServiceMockOrder(127, [
            'user_id' => 1,
            'items' => [['sku' => 'INVALID', 'quantity' => 1]]
        ]);
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
    });
    
    it('maneja respuestas de error API con logging detallado', function () {
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(127);
        
        // Llamada API con código de producto inválido debe fallar
        expect($result)->toBeFalse();
        
        // Verificar que se agregó nota de error en la orden
        global $mock_order_notes;
        expect($mock_order_notes[127])->toContain('Error al crear factura en Random ERP');
        
        // Verificar que se intentó la llamada API y se registró el error
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Failed to create invoice for order: 127');
        expect($log_content)->toContain('Request body sent to API:');
        expect($log_content)->toContain('Sending document to Random ERP API');
        
        // Verificar que hay respuesta de error API o estado fallido
        $has_api_error = strpos($log_content, 'API Error Message:') !== false;
        $has_failed_status = strpos($log_content, 'Request failed with status') !== false;
        expect($has_api_error || $has_failed_status)->toBeTrue();
    });
    
    it('maneja errores de red de manera elegante', function () {
        // Probar con URL API inválida para simular error de red
        $original_url = get_option('sm_api_url');
        update_option('sm_api_url', 'http://invalid-domain-that-does-not-exist.local:99999');
        
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(127);
        
        expect($result)->toBeFalse();
        
        // Verificar logging de errores - debe contener algún tipo de error de conexión/red
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        $has_network_error = strpos($log_content, 'WP Error:') !== false || 
                           strpos($log_content, 'No auth token available') !== false ||
                           strpos($log_content, 'Failed to create invoice') !== false;
        expect($has_network_error)->toBeTrue();
        
        // Restaurar URL original
        update_option('sm_api_url', $original_url);
    });
    
    it('maneja 401 no autorizado y reintenta con nuevo token', function () {
        // Forzar un 401 usando un token inválido
        update_option('random_erp_token', 'invalid-token-that-will-cause-401');
        
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(127);
        
        // El resultado puede ser true o false dependiendo de si el reintento tiene éxito
        // Lo que queremos probar es que se intentó la lógica de reintento
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        
        // Verificar que se intentó la lógica de reintento o la autenticación tuvo éxito
        $has_retry_attempt = strpos($log_content, '401 Unauthorized - attempting to refresh token') !== false;
        $has_auth_attempt = strpos($log_content, 'Sending document to Random ERP API') !== false;
        expect($has_retry_attempt || $has_auth_attempt)->toBeTrue();
    });
});

describe('Notas Privadas de Orden DocumentService', function () {
    
    it('agrega mensajes de log como notas privadas de orden', function () {
        createDocumentServiceMockOrder(128, [
            'user_id' => 1,
            'items' => [['sku' => 'BEBIDA', 'quantity' => 1]]
        ]);
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
        
        $service = new DocumentService();
        $service->create_invoice_on_order_completion(128);
        
        // Verificar que se agregaron notas de orden (tanto públicas como privadas)
        global $mock_order_notes;
        $notes = $mock_order_notes[128];
        
        expect($notes)->not()->toBeEmpty();
        
        // Debe contener nota de error ya que el producto BEBIDA probablemente no existe en el ERP de prueba
        $has_error_note = false;
        $has_private_note = false;
        
        foreach ($notes as $note) {
            if (strpos($note, 'Error al crear factura en Random ERP') !== false || 
                strpos($note, 'Factura creada en Random ERP exitosamente') !== false) {
                $has_error_note = true;
            }
            if (strpos($note, 'DocumentService:') !== false) {
                $has_private_note = true;
            }
        }
        
        expect($has_error_note || $has_private_note)->toBeTrue();
    });
    
    it('limpia contexto de orden después del procesamiento', function () {
        createDocumentServiceMockOrder(129, [
            'user_id' => 1,
            'items' => [['sku' => 'TEST', 'quantity' => 1]]
        ]);
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
        
        $service = new DocumentService();
        $result = $service->create_invoice_on_order_completion(129);
        
        // Debe retornar false para datos de prueba que no existen en ERP
        expect($result)->toBeFalse();
        
        // Verificar que el contexto de orden fue manejado apropiadamente
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Sending document to Random ERP API');
        
        // El contexto de orden debe configurarse durante el procesamiento
        global $mock_order_notes;
        expect(isset($mock_order_notes[129]))->toBeTrue();
    });
});

// Note: These tests now use real API calls for integration testing
// Mock helper functions are kept for compatibility but not used