<?php

/**
 * Mock WordPress HTTP functions for testing
 */

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

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
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