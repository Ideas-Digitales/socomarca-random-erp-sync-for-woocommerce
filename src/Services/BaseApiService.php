<?php

namespace Socomarca\RandomERP\Services;

use Exception;

abstract class BaseApiService {
    
    protected $api_url;
    protected $api_user;
    protected $api_password;
    protected $operation_mode;
    protected $production_token;
    
    public function __construct() {
        $this->operation_mode = get_option('sm_operation_mode', 'development');
        $this->production_token = get_option('sm_production_token', '');
        
        // Configurar URL según el modo
        if ($this->operation_mode === 'production') {
            $this->api_url = get_option('sm_prod_api_url', '');
        } else {
            $this->api_url = get_option('sm_dev_api_url', 'http://seguimiento.random.cl:3003');
        }
        
        // Credenciales para modo desarrollo
        $this->api_user = get_option('sm_api_user', 'demo@random.cl');
        $this->api_password = get_option('sm_api_password', 'd3m0r4nd0m3RP');
    }
    
    protected function getAuthToken() {
        // Validar que la URL esté configurada
        if (empty($this->api_url)) {
            $mode_text = ($this->operation_mode === 'production') ? 'producción' : 'desarrollo';
            throw new Exception("URL del API para modo {$mode_text} no configurada");
        }
        
        if ($this->operation_mode === 'production') {
            // En modo producción, usar el token manual
            if (empty($this->production_token)) {
                throw new Exception('Token de producción no configurado');
            }
            return $this->production_token;
        } else {
            // En modo desarrollo, generar token automáticamente
            $token = get_option('random_erp_token');
            if (empty($token)) {
                $token = $this->authenticate();
            }
            return $token;
        }
    }
    
    protected function authenticate() {
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
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $data = json_decode($body, true);
        
        if (isset($data['token'])) {
            update_option('random_erp_token', $data['token']);
            return $data['token'];
        }
        
        return false;
    }
    
    protected function makeApiRequest($endpoint, $method = 'GET', $data = null) {
        $token = $this->getAuthToken();
        if (!$token) {
            return false;
        }
        
        $url = $this->api_url . $endpoint;
        
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
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $body = json_decode($body_raw, true);
            
            if (isset($body['data']) && is_array($body['data'])) {
                return $body['data'];
            }
            
            if (is_array($body)) {
                return $body;
            }
            
            return false;
        }
        
        if ($status_code === 401) {
            // Solo intentar re-autenticación en modo desarrollo
            if ($this->operation_mode === 'development') {
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
            } else {
                // En modo producción, el token debe ser válido
                throw new Exception('Token de producción no válido o expirado (401 Unauthorized)');
            }
        }
        
        return false;
    }
}