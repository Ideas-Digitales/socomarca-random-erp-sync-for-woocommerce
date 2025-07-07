<?php

namespace Socomarca\RandomERP\Services;

use Exception;

abstract class BaseApiService {
    
    protected $api_url;
    protected $api_user;
    protected $api_password;
    
    public function __construct() {
        $this->api_url = get_option('sm_api_url', 'http://seguimiento.random.cl:3003');
        $this->api_user = get_option('sm_api_user', 'demo@random.cl');
        $this->api_password = get_option('sm_api_password', 'd3m0r4nd0m3RP');
    }
    
    protected function getAuthToken() {
        $token = get_option('random_erp_token');
        if (empty($token)) {
            $token = $this->authenticate();
        }
        return $token;
    }
    
    protected function authenticate() {
        error_log('BaseApiService: Autenticando...');
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'username' => $this->api_user,
                'password' => $this->api_password,
                'ttl' => '36000000'
            ]
        ];
        
        $response = wp_remote_post($this->api_url . '/login', $args);
        
        if (is_wp_error($response)) {
            error_log('BaseApiService: Error de autenticación - ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['token'])) {
            update_option('random_erp_token', $data['token']);
            error_log('BaseApiService: Token obtenido exitosamente');
            return $data['token'];
        }
        
        error_log('BaseApiService: Error - No se encontró token en la respuesta');
        return false;
    }
    
    protected function makeApiRequest($endpoint, $method = 'GET', $data = null) {
        $token = $this->getAuthToken();
        if (!$token) {
            error_log('BaseApiService: No se pudo obtener token de autenticación');
            return false;
        }
        
        $url = $this->api_url . $endpoint;
        error_log("BaseApiService: Realizando petición {$method} a {$url}");
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ];
        
        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
            $args['headers']['Content-Type'] = 'application/json';
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('BaseApiService: Error en petición - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        
        error_log("BaseApiService: Status Code = {$status_code}");
        
        if ($status_code === 200) {
            $body = json_decode($body_raw, true);
            
            if (isset($body['data']) && is_array($body['data'])) {
                return $body['data'];
            }
            
            if (is_array($body)) {
                return $body;
            }
            
            error_log('BaseApiService: Estructura de respuesta inesperada: ' . substr(print_r($body, true), 0, 500));
            return false;
        }
        
        if ($status_code === 401) {
            error_log('BaseApiService: Token expirado, re-autenticando...');
            delete_option('random_erp_token');
            $new_token = $this->authenticate();
            if ($new_token) {
                $args['headers']['Authorization'] = 'Bearer ' . $new_token;
                $retry_response = wp_remote_request($url, $args);
                
                if (!is_wp_error($retry_response) && wp_remote_retrieve_response_code($retry_response) === 200) {
                    $retry_body = json_decode(wp_remote_retrieve_body($retry_response), true);
                    
                    if (isset($retry_body['data']) && is_array($retry_body['data'])) {
                        return $retry_body['data'];
                    }
                    
                    if (is_array($retry_body)) {
                        return $retry_body;
                    }
                }
            }
        }
        
        error_log("BaseApiService: Error HTTP {$status_code}: {$body_raw}");
        return false;
    }
}