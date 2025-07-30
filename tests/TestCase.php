<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize global options array for WordPress functions
        global $wp_options;
        $wp_options = [];
        
        // Mock WordPress functions
        mockWordPressFunctions();
        
        // Mock additional WordPress functions that may be needed
        $this->mockWordPressHttpFunctions();
    }
    
    protected function tearDown(): void
    {
        // Clean up global options
        global $wp_options;
        $wp_options = [];
        
        parent::tearDown();
    }
    
    /**
     * Mock WordPress HTTP functions for testing
     */
    protected function mockWordPressHttpFunctions()
    {
        if (!class_exists('WP_Error')) {
            require_once dirname(__DIR__) . '/tests/Stubs/WP_Error.php';
        }
        
        // Load mock functions from separate file to avoid redeclaration
        if (!function_exists('wp_remote_post')) {
            require_once dirname(__DIR__) . '/tests/Stubs/MockWordPressFunctions.php';
        }
    }
}
