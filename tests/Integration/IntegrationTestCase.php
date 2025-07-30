<?php

namespace Tests\Integration;

use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up API credentials for integration tests
        $this->setupApiCredentials();
        
        // Mock WordPress functions needed for services
        $this->mockWordPressServiceFunctions();
        
        // Clear mock terms storage for each test
        $this->clearMockTermsStorage();
    }
    
    protected function clearMockTermsStorage()
    {
        global $mock_terms_storage;
        $mock_terms_storage = [];
    }
    
    protected function setupApiCredentials()
    {
        // Use real API credentials for integration tests
        // These should be set as environment variables or in a config file
        update_option('sm_api_url', $_ENV['RANDOM_ERP_API_URL'] ?? 'http://seguimiento.random.cl:3003');
        update_option('sm_api_user', $_ENV['RANDOM_ERP_API_USER'] ?? 'demo@random.cl');
        update_option('sm_api_password', $_ENV['RANDOM_ERP_API_PASSWORD'] ?? 'd3m0r4nd0m3RP');
        update_option('sm_company_code', $_ENV['RANDOM_ERP_COMPANY_CODE'] ?? '01');
        update_option('sm_company_rut', $_ENV['RANDOM_ERP_COMPANY_RUT'] ?? '134549696');
    }
    
    protected function mockWordPressServiceFunctions()
    {
        // Load mock functions from separate file to avoid redeclaration
        if (!function_exists('get_terms')) {
            require_once dirname(__DIR__) . '/Stubs/MockWordPressServiceFunctions.php';
        }
    }
    
    protected function skipIfNoApiCredentials()
    {
        $api_url = get_option('sm_api_url');
        $api_user = get_option('sm_api_user');
        $api_password = get_option('sm_api_password');
        
        if (empty($api_url) || empty($api_user) || empty($api_password)) {
            $this->markTestSkipped('API credentials not configured for integration tests');
        }
    }
}