<?php

/**
 * Mock WordPress service functions for integration testing
 */

// Mock WordPress taxonomy functions with simulated category storage
global $mock_terms_storage;
if (!isset($mock_terms_storage)) {
    $mock_terms_storage = [];
}

if (!function_exists('get_terms')) {
    function get_terms($args = []) {
        global $mock_terms_storage;
        
        // If meta_query is specified, try to find matching terms
        if (isset($args['meta_query']) && is_array($args['meta_query'])) {
            foreach ($args['meta_query'] as $meta_condition) {
                if (isset($meta_condition['key']) && isset($meta_condition['value'])) {
                    $key = $meta_condition['key'];
                    $value = $meta_condition['value'];
                    
                    // Search for terms with matching meta
                    foreach ($mock_terms_storage as $term) {
                        if (isset($term->meta[$key]) && $term->meta[$key] === $value) {
                            return [$term];
                        }
                    }
                }
            }
        }
        
        // For taxonomy queries without meta_query, return stored terms
        if (isset($args['taxonomy'])) {
            $taxonomy = $args['taxonomy'];
            $matching_terms = [];
            
            foreach ($mock_terms_storage as $term) {
                if ($term->taxonomy === $taxonomy) {
                    $matching_terms[] = $term;
                }
            }
            
            return $matching_terms;
        }
        
        return [];
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = []) {
        global $mock_terms_storage;
        
        $term_id = rand(1, 1000);
        $term_taxonomy_id = rand(1, 1000);
        
        // Create mock term object
        $mock_term = (object) [
            'term_id' => $term_id,
            'term_taxonomy_id' => $term_taxonomy_id,
            'name' => $term,
            'slug' => isset($args['slug']) ? $args['slug'] : sanitize_title($term),
            'taxonomy' => $taxonomy,
            'parent' => isset($args['parent']) ? $args['parent'] : 0,
            'description' => isset($args['description']) ? $args['description'] : '',
            'meta' => []
        ];
        
        // Store the term
        $mock_terms_storage[] = $mock_term;
        
        return ['term_id' => $term_id, 'term_taxonomy_id' => $term_taxonomy_id];
    }
}

if (!function_exists('wp_update_term')) {
    function wp_update_term($term_id, $taxonomy, $args = []) {
        global $mock_terms_storage;
        
        // Find existing term and update it
        foreach ($mock_terms_storage as $index => $term) {
            if ($term->term_id == $term_id) {
                if (isset($args['name'])) $mock_terms_storage[$index]->name = $args['name'];
                if (isset($args['slug'])) $mock_terms_storage[$index]->slug = $args['slug'];
                if (isset($args['parent'])) $mock_terms_storage[$index]->parent = $args['parent'];
                if (isset($args['description'])) $mock_terms_storage[$index]->description = $args['description'];
                break;
            }
        }
        
        return ['term_id' => $term_id, 'term_taxonomy_id' => rand(1, 1000)];
    }
}

if (!function_exists('wp_delete_term')) {
    function wp_delete_term($term, $taxonomy) {
        return true;
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy) {
        global $mock_terms_storage;
        
        foreach ($mock_terms_storage as $term) {
            if ($term->taxonomy === $taxonomy) {
                if ($field === 'slug' && $term->slug === $value) {
                    return $term;
                }
                if ($field === 'id' && $term->term_id == $value) {
                    return $term;
                }
                if ($field === 'name' && $term->name === $value) {
                    return $term;
                }
            }
        }
        
        return null;
    }
}

if (!function_exists('update_term_meta')) {
    function update_term_meta($term_id, $meta_key, $meta_value) {
        global $mock_terms_storage;
        
        // Find the term and update its meta
        foreach ($mock_terms_storage as $index => $term) {
            if ($term->term_id == $term_id) {
                $mock_terms_storage[$index]->meta[$meta_key] = $meta_value;
                break;
            }
        }
        
        return true;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(str_replace([' ', '/'], ['-', '-'], $title));
    }
}

// Mock user functions
if (!function_exists('username_exists')) {
    function username_exists($username) {
        return false;
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email) {
        return false;
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email = '') {
        return rand(1, 1000);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12) {
        return 'generated_password_123';
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value) {
        return true;
    }
}

// Mock WooCommerce functions
if (!function_exists('wc_get_products')) {
    function wc_get_products($args = []) {
        return [];
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr) {
        return rand(1, 1000);
    }
}

// Define WordPress constants
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!function_exists('get_page_by_title')) {
    function get_page_by_title($page_title, $output = OBJECT, $post_type = 'page') {
        return null;
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy) {
        return true;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        return true;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr) {
        return rand(1, 1000);
    }
}

// Mock WordPress user functions
if (!function_exists('get_users')) {
    function get_users($args = []) {
        return []; // Return empty array for tests
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user($userdata) {
        return rand(1, 1000); // Return random user ID
    }
}

if (!function_exists('wp_update_user')) {
    function wp_update_user($userdata) {
        return rand(1, 1000); // Return user ID
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user($id, $reassign = null) {
        return true;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        return false; // No user found
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12) {
        return 'test_password_123';
    }
}

if (!function_exists('user_can')) {
    function user_can($user, $capability) {
        return false; // Safe default for tests
    }
}

if (!function_exists('retrieve_password')) {
    function retrieve_password($user_login) {
        return true; // Simulate successful password reset
    }
}

// Mock WordPress HTTP functions for real API calls
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return wp_remote_request($url, array_merge($args, ['method' => 'POST']));
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        
        $method = $args['method'] ?? 'GET';
        $timeout = $args['timeout'] ?? 30;
        $headers = $args['headers'] ?? [];
        $body = $args['body'] ?? null;
        
        // Use cURL for real HTTP requests
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Set headers
        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = "$key: $value";
        }
        if (!empty($curl_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        }
        
        // Set method and body
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                if (is_array($body)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
        
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return new WP_Error('http_request_failed', $error);
        }
        
        return [
            'response' => ['code' => $http_code],
            'body' => $response_body
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response)) {
            return 0;
        }
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = array_keys($this->errors)[0] ?? '';
            }
            return $this->errors[$code][0] ?? '';
        }
    }
}