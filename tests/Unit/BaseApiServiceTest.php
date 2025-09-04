<?php

use Socomarca\RandomERP\Services\BaseApiService;

class TestableBaseApiService extends BaseApiService
{
    public function getAuthTokenPublic()
    {
        return $this->getAuthToken();
    }

    public function authenticatePublic()
    {
        return $this->authenticate();
    }

    public function makeApiRequestPublic($endpoint, $method = 'GET', $data = null)
    {
        return $this->makeApiRequest($endpoint, $method, $data);
    }

    public function getApiUrl()
    {
        return $this->api_url;
    }

    public function getApiUser()
    {
        return $this->api_user;
    }

    public function getApiPassword()
    {
        return $this->api_password;
    }
}

beforeEach(function () {
    delete_option('sm_dev_api_url');
    delete_option('sm_prod_api_url');
    delete_option('sm_api_user');
    delete_option('sm_api_password');
    delete_option('sm_operation_mode');
    delete_option('sm_production_token');
    delete_option('random_erp_token');
});

describe('BaseApiService', function () {
    
    describe('Constructor', function () {
        
        it('inicializa con configuración API por defecto', function () {
            $service = new TestableBaseApiService();
            
            expect($service->getApiUrl())->toBe('http://seguimiento.random.cl:3003');
            expect($service->getApiUser())->toBe('demo@random.cl');
            expect($service->getApiPassword())->toBe('d3m0r4nd0m3RP');
        });
        
        it('usa configuración API personalizada desde opciones de WordPress', function () {
            update_option('sm_dev_api_url', 'https://custom.api.com');
            update_option('sm_api_user', 'custom@user.com');
            update_option('sm_api_password', 'custompassword');
            
            $service = new TestableBaseApiService();
            
            expect($service->getApiUrl())->toBe('https://custom.api.com');
            expect($service->getApiUser())->toBe('custom@user.com');
            expect($service->getApiPassword())->toBe('custompassword');
        });
        
    });
    
    describe('getAuthToken', function () {
        
        it('retorna token existente desde opciones de WordPress', function () {
            $expectedToken = 'existing_token_123';
            update_option('random_erp_token', $expectedToken);
            
            $service = new TestableBaseApiService();
            $token = $service->getAuthTokenPublic();
            
            expect($token)->toBe($expectedToken);
        });
        
    });
    
    describe('Funcionalidad básica', function () {
        
        it('puede instanciar el servicio', function () {
            $service = new TestableBaseApiService();
            expect($service)->toBeInstanceOf(TestableBaseApiService::class);
        });
        
        it('tiene métodos de configuración API apropiados', function () {
            $service = new TestableBaseApiService();
            expect($service->getApiUrl())->toBeString();
            expect($service->getApiUser())->toBeString();
            expect($service->getApiPassword())->toBeString();
        });
        
        it('puede acceder a métodos protegidos a través de wrappers públicos', function () {
            $service = new TestableBaseApiService();
            expect(method_exists($service, 'getAuthTokenPublic'))->toBe(true);
            expect(method_exists($service, 'authenticatePublic'))->toBe(true);
            expect(method_exists($service, 'makeApiRequestPublic'))->toBe(true);
        });
        
        it('inicializa correctamente las credenciales API desde valores por defecto', function () {
            $service = new TestableBaseApiService();
            
            expect($service->getApiUrl())->not()->toBeEmpty();
            expect($service->getApiUser())->not()->toBeEmpty();
            expect($service->getApiPassword())->not()->toBeEmpty();
        });
        
        it('maneja correctamente las opciones de WordPress para configuración API', function () {
            // Test with custom configuration
            update_option('sm_dev_api_url', 'https://test.example.com');
            update_option('sm_api_user', 'testuser');
            update_option('sm_api_password', 'testpass');
            
            $service = new TestableBaseApiService();
            
            expect($service->getApiUrl())->toBe('https://test.example.com');
            expect($service->getApiUser())->toBe('testuser');
            expect($service->getApiPassword())->toBe('testpass');
            
            // Clean up and test defaults
            delete_option('sm_dev_api_url');
            delete_option('sm_api_user');
            delete_option('sm_api_password');
            
            $service2 = new TestableBaseApiService();
            
            expect($service2->getApiUrl())->toBe('http://seguimiento.random.cl:3003');
            expect($service2->getApiUser())->toBe('demo@random.cl');
            expect($service2->getApiPassword())->toBe('d3m0r4nd0m3RP');
        });
        
        it('puede manejar la recuperación de tokens desde opciones de WordPress', function () {
            // Test with existing token
            update_option('random_erp_token', 'test_token_123');
            $service = new TestableBaseApiService();
            $token = $service->getAuthTokenPublic();
            expect($token)->toBe('test_token_123');
            
            // Clear token to test authentication behavior
            delete_option('random_erp_token');
            $service2 = new TestableBaseApiService();
            $token2 = $service2->getAuthTokenPublic();
            // In unit tests with real API, it might get a real token or false depending on network
            expect($token2 === false || is_string($token2))->toBe(true);
        });
        
    });
    
    describe('Manejo de errores', function () {
        
        it('maneja configuración faltante de manera elegante', function () {
            // Even with missing options, should use defaults
            delete_option('sm_dev_api_url');
            delete_option('sm_prod_api_url');
            delete_option('sm_api_user');
            delete_option('sm_api_password');
            
            $service = new TestableBaseApiService();
            
            expect($service->getApiUrl())->toBeString();
            expect($service->getApiUser())->toBeString();
            expect($service->getApiPassword())->toBeString();
        });
        
        it('maneja correctamente opción de token vacía', function () {
            delete_option('random_erp_token');
            
            // Should attempt authentication when no token exists
            $service = new TestableBaseApiService();
            $result = $service->getAuthTokenPublic();
            
            // In unit tests with real API, it might get a real token or false depending on network
            expect($result === false || is_string($result))->toBe(true);
        });
        
    });
    
});