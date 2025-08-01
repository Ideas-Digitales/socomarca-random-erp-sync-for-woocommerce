<?php

/**
 * Mock WordPress HTTP functions specifically for DocumentService testing
 * This file overrides the real HTTP functions to provide controlled responses for testing
 */

// Global variables to store mock responses and track API calls
global $mock_api_responses, $mock_api_errors, $mock_api_exceptions, $mock_api_response_sequences;
global $last_api_request_body, $last_api_request_url, $last_api_request_headers;
global $wp_actions;

$mock_api_responses = [];
$mock_api_errors = [];
$mock_api_exceptions = [];
$mock_api_response_sequences = [];
$wp_actions = [];

// Note: Cannot override existing functions in PHP without runkit extension
// These functions will only be defined if they don't already exist

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        global $mock_api_responses, $mock_api_errors, $mock_api_exceptions, $mock_api_response_sequences;
        global $last_api_request_body, $last_api_request_url, $last_api_request_headers;
        
        // Store request details for verification
        $last_api_request_url = $url;
        $last_api_request_body = $args['body'] ?? '';
        $last_api_request_headers = $args['headers'] ?? [];
        
        // Extract endpoint from URL
        $parsed_url = parse_url($url);
        $endpoint = $parsed_url['path'] ?? '';
        
        // Check for exceptions first
        if (isset($mock_api_exceptions[$endpoint])) {
            throw new Exception($mock_api_exceptions[$endpoint]);
        }
        
        // Check for errors
        if (isset($mock_api_errors[$endpoint])) {
            return new WP_Error('http_request_failed', $mock_api_errors[$endpoint]);
        }
        
        // Check for response sequences (for testing retries)
        if (isset($mock_api_response_sequences[$endpoint])) {
            $response = array_shift($mock_api_response_sequences[$endpoint]);
            if (empty($mock_api_response_sequences[$endpoint])) {
                unset($mock_api_response_sequences[$endpoint]);
            }
            
            return [
                'response' => ['code' => $response['status']],
                'body' => json_encode($response['body']),
                'headers' => ['content-type' => 'application/json']
            ];
        }
        
        // Check for single responses
        if (isset($mock_api_responses[$endpoint])) {
            $response = $mock_api_responses[$endpoint];
            return [
                'response' => ['code' => $response['status']],
                'body' => json_encode($response['body']),
                'headers' => ['content-type' => 'application/json']
            ];
        }
        
        // Default successful response for unknown endpoints
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true]),
            'headers' => ['content-type' => 'application/json']
        ];
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return wp_remote_request($url, array_merge($args, ['method' => 'POST']));
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

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $errors = [];
        
        public function __construct($code = '', $message = '', $data = '')
        {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
            }
        }
        
        public function get_error_message($code = '')
        {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            
            return '';
        }
        
        public function get_error_code()
        {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}