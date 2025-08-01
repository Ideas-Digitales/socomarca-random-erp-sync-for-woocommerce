<?php

/**
 * Shared mock functions for DocumentService testing
 * This file contains common mock functions used by both Unit and Integration tests
 */

// Helper functions for mocking WordPress order and user functions
function mockDocumentServiceWordPressOrderFunctions()
{
    global $mock_orders, $mock_users, $mock_order_notes;
    $mock_orders = [];
    $mock_users = [];
    $mock_order_notes = [];
    
    if (!function_exists('wc_get_order')) {
        function wc_get_order($order_id) {
            global $mock_orders;
            if (!isset($mock_orders[$order_id])) {
                return false;
            }
            return new MockWCOrder($mock_orders[$order_id], $order_id);
        }
    }
}

function mockDocumentServiceWordPressUserFunctions()
{
    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key, $single = false) {
            global $mock_users;
            if (!isset($mock_users[$user_id]['meta'][$key])) {
                return $single ? '' : [];
            }
            return $single ? $mock_users[$user_id]['meta'][$key] : [$mock_users[$user_id]['meta'][$key]];
        }
    }
}

function mockDocumentServiceWordPressFileFunctions()
{
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($target) {
            return mkdir($target, 0755, true);
        }
    }
    
    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
            // Mock implementation
            return true;
        }
    }
    
    if (!function_exists('do_action')) {
        function do_action($hook, ...$args) {
            // Mock implementation
            return true;
        }
    }
    
    if (!function_exists('has_action')) {
        function has_action($hook, $callback = false) {
            // Mock implementation
            return false;
        }
    }
    
    if (!function_exists('wp_remote_retrieve_headers')) {
        function wp_remote_retrieve_headers($response) {
            $headers = isset($response['headers']) ? $response['headers'] : [];
            
            // Return an object that has getAll method for compatibility
            return new class($headers) {
                private $headers;
                
                public function __construct($headers) {
                    $this->headers = $headers;
                }
                
                public function getAll() {
                    return $this->headers;
                }
            };
        }
    }
    
    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body($response) {
            return isset($response['body']) ? $response['body'] : '';
        }
    }
    
    if (!function_exists('wp_remote_retrieve_response_code')) {
        function wp_remote_retrieve_response_code($response) {
            return isset($response['response']['code']) ? $response['response']['code'] : 200;
        }
    }
}

function createDocumentServiceMockOrder($order_id, $data)
{
    global $mock_orders;
    $mock_orders[$order_id] = $data;
}

function createDocumentServiceMockUser($user_id, $data)
{
    global $mock_users;
    $mock_users[$user_id] = $data;
}

// Mock WooCommerce Order class
if (!class_exists('MockWCOrder')) {
    class MockWCOrder
    {
        private $data;
        private $order_id;
        
        public function __construct($data, $order_id)
        {
            $this->data = $data;
            $this->order_id = $order_id;
        }
        
        public function get_user()
        {
            if (!isset($this->data['user_id'])) {
                return false;
            }
            return new MockWPUser($this->data['user_id']);
        }
        
        public function get_items()
        {
            $items = [];
            foreach ($this->data['items'] ?? [] as $item_data) {
                $items[] = new MockWCOrderItem($item_data);
            }
            return $items;
        }
        
        public function add_order_note($note, $is_customer_note = 0, $added_by_user = false)
        {
            global $mock_order_notes;
            if (!isset($mock_order_notes[$this->order_id])) {
                $mock_order_notes[$this->order_id] = [];
            }
            $mock_order_notes[$this->order_id][] = $note;
        }
    }
}

if (!class_exists('MockWPUser')) {
    class MockWPUser
    {
        public $ID;
        
        public function __construct($user_id)
        {
            $this->ID = $user_id;
        }
    }
}

if (!class_exists('MockWCOrderItem')) {
    class MockWCOrderItem
    {
        private $data;
        
        public function __construct($data)
        {
            $this->data = $data;
        }
        
        public function get_product()
        {
            return new MockWCProduct($this->data);
        }
        
        public function get_quantity()
        {
            return $this->data['quantity'] ?? 1;
        }
    }
}

if (!class_exists('MockWCProduct')) {
    class MockWCProduct
    {
        private $data;
        
        public function __construct($data)
        {
            $this->data = $data;
        }
        
        public function get_sku()
        {
            return $this->data['sku'] ?? '';
        }
    }
}