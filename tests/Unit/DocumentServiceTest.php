<?php

use Socomarca\RandomERP\Services\DocumentService;
use Tests\TestCase;

beforeEach(function () {
    if (!defined('SOCOMARCA_ERP_PLUGIN_DIR')) {
        define('SOCOMARCA_ERP_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
    }
    
    $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
    if (file_exists($logs_dir . '/documents.log')) {
        unlink($logs_dir . '/documents.log');
    }
    
    delete_option('sm_invoice_on_completion');
    delete_option('random_erp_token');
    delete_option('sm_default_entity_code');
    
    require_once dirname(__DIR__) . '/Stubs/DocumentServiceMockFunctions.php';
    
    mockDocumentServiceWordPressOrderFunctions();
    mockDocumentServiceWordPressUserFunctions();
    mockDocumentServiceWordPressFileFunctions();
});

afterEach(function () {
    global $mock_orders, $mock_users, $mock_order_notes;
    $mock_orders = [];
    $mock_users = [];
    $mock_order_notes = [];
});

describe('Pruebas Unitarias DocumentService', function () {
    
    it('puede ser instanciado', function () {
        $service = new TestableDocumentService();
        expect($service)->toBeInstanceOf(DocumentService::class);
    });
    
    it('crea archivo de log cuando es instanciado', function () {
        new TestableDocumentService();
        $log_file = SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log';
        expect(file_exists(dirname($log_file)))->toBeTrue();
    });
    
    it('maneja orden con items válidos y código de entidad', function () {
        // Create mock order with items
        createDocumentServiceMockOrder(123, [
            'user_id' => 1,
            'status' => 'completed',
            'items' => [
                ['sku' => 'BEBIDA', 'quantity' => 2, 'product_id' => 100],
                ['sku' => 'COMIDA', 'quantity' => 1, 'product_id' => 101]
            ]
        ]);
        
        // Create mock user with entity code
        createDocumentServiceMockUser(1, [
            'meta' => ['random_erp_entity_code' => '5']
        ]);
        
        // Mock successful API response
        $service = new TestableDocumentService();
        $service->setMockApiResponse([
            'numero' => '0000001039',
            'tido' => 'BLV',
            'empresa' => '01',
            'estado' => ['codigo' => '1', 'mensaje' => 'Grabación exitosa']
        ]);
        
        $result = $service->create_invoice_on_order_completion(123);
        
        expect($result)->not()->toBeFalse();
        
        // Check that order note was added
        global $mock_order_notes;
        expect($mock_order_notes[123])->toContain('Factura creada en Random ERP exitosamente');
        
        // Check log file contains success message
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Invoice created successfully for order: 123');
        expect($log_content)->toContain('Document API response received');
    });
    
    it('maneja orden faltante de manera elegante', function () {
        $service = new TestableDocumentService();
        $result = $service->create_invoice_on_order_completion(999);
        
        expect($result)->toBeFalse();
        
        // Check log contains error message
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Order not found for ID: 999');
    });
    
    it('maneja código de entidad faltante usando valor por defecto', function () {
        // Create user without entity code
        createDocumentServiceMockUser(2, ['meta' => []]);
        createDocumentServiceMockOrder(124, ['user_id' => 2, 'items' => [['sku' => 'TEST', 'quantity' => 1]]]);
        
        update_option('sm_default_entity_code', '10');
        
        // Mock successful API response
        $service = new TestableDocumentService();
        $service->setMockApiResponse([
            'numero' => '0000001040',
            'estado' => ['codigo' => '1', 'mensaje' => 'Grabación exitosa']
        ]);
        
        $result = $service->create_invoice_on_order_completion(124);
        
        expect($result)->not()->toBeFalse();
        
        // Check that default entity code was used in the request
        $lastRequest = $service->getLastApiRequest();
        $request_data = json_decode($lastRequest['body'], true);
        expect($request_data['datos']['codigoEntidad'])->toBe('10');
    });
    
    it('maneja orden sin items válidos', function () {
        // Create order with items that have no SKU
        createDocumentServiceMockOrder(125, [
            'user_id' => 1,
            'items' => [['sku' => '', 'quantity' => 1]]
        ]);
        
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
        
        $service = new TestableDocumentService();
        $result = $service->create_invoice_on_order_completion(125);
        
        expect($result)->toBeFalse();
        
        // Check log contains appropriate error
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('No valid lines found for order: 125');
    });
    
    it('procesa variaciones de productos correctamente', function () {
        // Create order with variation product (SKU with |)
        createDocumentServiceMockOrder(126, [
            'user_id' => 1,
            'items' => [['sku' => 'PRODUCTO|001', 'quantity' => 1]]
        ]);
        
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
        
        // Mock successful API response
        $service = new TestableDocumentService();
        $service->setMockApiResponse([
            'numero' => '0000001041',
            'estado' => ['codigo' => '1', 'mensaje' => 'Grabación exitosa']
        ]);
        
        $result = $service->create_invoice_on_order_completion(126);
        
        expect($result)->not()->toBeFalse();
        
        // Check that parent SKU was used (before |)
        $lastRequest = $service->getLastApiRequest();
        $request_data = json_decode($lastRequest['body'], true);
        expect($request_data['datos']['lineas'][0]['codigoProducto'])->toBe('PRODUCTO');
    });
    
    it('maneja errores de API con logging detallado', function () {
        createDocumentServiceMockOrder(127, [
            'user_id' => 1,
            'items' => [['sku' => 'INVALID', 'quantity' => 1]]
        ]);
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
        
        // Mock API error response
        $service = new TestableDocumentService();
        $service->setMockApiError(400, [
            'message' => 'EA345F0B-C5A0-4C14-AE6B-4D59824BAE6B| El código de producto o descuento no es válido: INVALID',
            'errorId' => 'VQcrsKm8',
            'logUrl' => 'http://seguimiento.random.cl:3003/xlogger?reqId=7b6dQu8n'
        ]);
        
        $result = $service->create_invoice_on_order_completion(127);
        
        expect($result)->toBeFalse();
        
        // Check error order note was added
        global $mock_order_notes;
        expect($mock_order_notes[127])->toContain('Error al crear factura en Random ERP');
        
        // Check detailed error logging
        $log_content = file_get_contents(SOCOMARCA_ERP_PLUGIN_DIR . 'logs/documents.log');
        expect($log_content)->toContain('Failed to create invoice for order: 127');
        expect($log_content)->toContain('Request body sent to API:');
        expect($log_content)->toContain('API Response:');
        expect($log_content)->toContain('Request failed with status 400');
        expect($log_content)->toContain('API Error Message: EA345F0B-C5A0-4C14-AE6B-4D59824BAE6B| El código de producto o descuento no es válido: INVALID');
        expect($log_content)->toContain('API Error ID: VQcrsKm8');
        expect($log_content)->toContain('API Log URL: http://seguimiento.random.cl:3003/xlogger?reqId=7b6dQu8n');
    });
    
    it('agrega mensajes de log como notas privadas de orden', function () {
        createDocumentServiceMockOrder(128, [
            'user_id' => 1,
            'items' => [['sku' => 'TEST', 'quantity' => 1]]
        ]);
        createDocumentServiceMockUser(1, ['meta' => ['random_erp_entity_code' => '5']]);
        
        // Mock successful API response
        $service = new TestableDocumentService();
        $service->setMockApiResponse([
            'numero' => '0000001043',
            'estado' => ['codigo' => '1', 'mensaje' => 'Grabación exitosa']
        ]);
        
        $service->create_invoice_on_order_completion(128);
        
        // Check that private order notes were added
        global $mock_order_notes;
        $notes = $mock_order_notes[128];
        
        // Should contain both public and private notes
        expect($notes)->toContain('Factura creada en Random ERP exitosamente'); // Public note
        
        // Check if any private notes with DocumentService prefix were added
        $hasPrivateNotes = false;
        foreach ($notes as $note) {
            if (strpos($note, 'DocumentService:') !== false) {
                $hasPrivateNotes = true;
                break;
            }
        }
        expect($hasPrivateNotes)->toBeTrue();
    });
});

// Testable DocumentService class that allows mocking API responses
class TestableDocumentService extends DocumentService
{
    private $mockApiResponse = null;
    private $mockApiError = null;
    private $lastApiRequest = null;
    
    public function setMockApiResponse($response)
    {
        $this->mockApiResponse = $response;
        $this->mockApiError = null;
    }
    
    public function setMockApiError($statusCode, $errorResponse)
    {
        $this->mockApiError = ['status' => $statusCode, 'response' => $errorResponse];
        $this->mockApiResponse = null;
    }
    
    public function getLastApiRequest()
    {
        return $this->lastApiRequest;
    }
    
    // Override the create_document method to use mocked responses
    public function create_document($document_data) {
        // Store the request details for testing
        $this->lastApiRequest = [
            'endpoint' => '/web32/documento',
            'method' => 'POST',
            'body' => json_encode($document_data)
        ];
        
        // Log the request details as the real method would
        $this->log("Sending document to Random ERP API");
        $this->log("Request payload: " . json_encode($document_data));
        
        // Return mock error response if set
        if ($this->mockApiError) {
            $this->log("Request failed with status " . $this->mockApiError['status']);
            
            $error_body = $this->mockApiError['response'];
            if (isset($error_body['message'])) {
                $this->log("API Error Message: " . $error_body['message']);
            }
            if (isset($error_body['errorId'])) {
                $this->log("API Error ID: " . $error_body['errorId']);
            }
            if (isset($error_body['logUrl'])) {
                $this->log("API Log URL: " . $error_body['logUrl']);
            }
            
            $this->log("Document API returned false - request failed");
            return false;
        }
        
        // Return mock success response if set
        if ($this->mockApiResponse) {
            $this->log("Document API response received: " . json_encode($this->mockApiResponse));
            return $this->mockApiResponse;
        }
        
        // Default mock response
        $this->log("Document API response received: {\"success\": true}");
        return ['success' => true];
    }
    
    // Make the log method accessible for testing
    public function testLog($message)
    {
        $this->log($message);
    }
}

