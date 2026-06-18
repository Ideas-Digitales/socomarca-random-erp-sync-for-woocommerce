<?php

namespace Socomarca\RandomERP\Services;

use Exception;

class DocumentService extends BaseApiService {
    
    private $log_file;
    private $current_order_id = null;
    
    public function __construct() {
        parent::__construct();
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        $this->log_file = $logs_dir . '/documents.log';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'check_invoice_on_completion_setting']);
    }
    
    protected function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] DocumentService: $message" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    protected function logDebug($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] DocumentService: $message" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public function check_invoice_on_completion_setting() {
        $invoice_on_completion = get_option('sm_invoice_on_completion', false);
        
        if ($invoice_on_completion) {
            add_action('woocommerce_order_status_completed', [$this, 'create_invoice_on_order_completion']);
        }
    }
    
    public function create_invoice_on_order_completion($order_id) {
        // Establecer contexto de orden actual para logging
        $this->current_order_id = $order_id;
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                $this->log("Order not found for ID: $order_id");
                return false;
            }
            
            $company_code = get_option('sm_company_code', '01');
            $entity_code = $this->get_entity_code_from_order($order);
            
            if (!$entity_code) {
                $this->log("Could not determine entity code for order: $order_id");
                return false;
            }
            
            $lines = $this->build_order_lines($order);
            if (empty($lines)) {
                $this->log("No valid lines found for order: $order_id");
                return false;
            }

            $tido = get_option('sm_tido', 'BLV');
            $modalidad = get_option('sm_modalidad', 'WEB');
            $funcionario = get_option('sm_funcionario', '');

            $document_data = [
                'datos' => [
                    'empresa' => $company_code,
                    'codigoEntidad' => $entity_code,
                    'tido' => $tido,
                    'modalidad' => $modalidad,
                    'lineas' => $lines
                ]
            ];

            if (!empty($funcionario)) {
                $document_data['datos']['funcionario'] = $funcionario;
            }
            
            $result = $this->create_document($document_data);

            if ($result) {
                $idmaeedo = isset($result['idmaeedo']) ? $result['idmaeedo'] : '';
                $note_message = 'Documento creado en Random ERP exitosamente';
                if ($idmaeedo) {
                    $note_message .= ' - idmaeedo: ' . $idmaeedo;
                }
                $order->add_order_note($note_message);

                update_post_meta($order_id, 'created_document', 1);
                if ($idmaeedo) {
                    update_post_meta($order_id, 'idmaeedo', $idmaeedo);
                }

                $this->log("Document created successfully for order: $order_id - idmaeedo: $idmaeedo");
            } else {
                $order->add_order_note('Error al crear documento en Random ERP');
                update_post_meta($order_id, 'created_document', 0);
                $this->log("Failed to create document for order: $order_id");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage());
            return false;
        } finally {
            // Limpiar contexto de orden después del procesamiento
            $this->current_order_id = null;
        }
    }
    
    private function get_entity_code_from_order($order) {
        $user = $order->get_user();
        if ($user) {
            $entity_code = get_user_meta($user->ID, 'random_erp_entity_code', true);
            if ($entity_code) {
                return $entity_code;
            }
        }
        
        return get_option('sm_default_entity_code', '5');
    }
    
    private function build_order_lines($order) {
        $lines = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            
            $sku = $product->get_sku();
            if (empty($sku)) {
                continue;
            }

            //Si tiene | en el sku, es una variación, entonces se debe obtener el sku del padre y el número de variación
            if (strpos($sku, '|') !== false) {
                $sku_parts = explode('|', $sku);
                $sku = $sku_parts[0];
                $variation_number = $sku_parts[1];
            }
            
            $lines[] = [
                'cantidad' => $item->get_quantity(),
                'codigoProducto' => $sku
            ];
        }
        
        return $lines;
    }
    
    public function create_document($document_data) {
        try {
            $this->logDebug("Sending document to Random ERP API");
            $this->logDebug("Request payload: " . json_encode($document_data));

            $result = $this->makeApiRequestWithDetails('/web32/documento', 'POST', $document_data);

            if ($result !== false) {
                $this->logDebug("Document API response received");
                return $result;
            }

            $this->log("Document API returned false - request failed");
            return false;

        } catch (Exception $e) {
            $this->log("API Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function makeApiRequestWithDetails($endpoint, $method = 'GET', $data = null) {
        $token = $this->getAuthToken();
        if (!$token) {
            $this->log("No auth token available");
            return false;
        }

        if (get_option('sm_dry_run', false)) {
            $endpoint .= (strpos($endpoint, '?') !== false ? '&' : '?') . 'dryRun=true';
        }

        $url = $this->api_url . $endpoint;
        $this->logDebug("Making request to: " . $url);

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

        $this->logDebug("Request headers: " . json_encode($args['headers']));
        $this->logDebug("Request body: " . ($args['body'] ?? 'empty'));
        
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log("WP Error: " . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        $this->logDebug("Response status code: " . $status_code);
        $this->logDebug("Response headers: " . json_encode(is_object($response_headers) ? $response_headers->getAll() : $response_headers));
        $this->logDebug("Response body: " . $body_raw);
        
        // Códigos de estado exitosos para creación de documentos (200, 201)
        if ($status_code === 200 || $status_code === 201) {
            $body = json_decode($body_raw, true);

            if (is_array($body)) {
                return $body;
            }

            // Para creación de documentos, a veces obtenemos el cuerpo de respuesta crudo
            if (!empty($body_raw)) {
                return json_decode($body_raw, true) ?: $body_raw;
            }

            $this->log("Success status " . $status_code . " but could not parse response body");
            return false;
        }
        
        if ($status_code === 401) {
            $this->log("401 Unauthorized - attempting to refresh token");
            delete_option('random_erp_token');
            $new_token = $this->authenticate();
            if ($new_token) {
                $this->log("New token obtained, retrying request");
                $args['headers']['Authorization'] = 'Bearer ' . $new_token;
                $retry_response = wp_remote_request($url, $args);
                
                if (!is_wp_error($retry_response)) {
                    $retry_status = wp_remote_retrieve_response_code($retry_response);
                    $retry_body_raw = wp_remote_retrieve_body($retry_response);
                    $this->log("Retry response status: " . $retry_status);
                    $this->log("Retry response body: " . $retry_body_raw);
                    
                    if ($retry_status === 200) {
                        $retry_body = json_decode($retry_body_raw, true);

                        if (is_array($retry_body)) {
                            return $retry_body;
                        }
                    }
                } else {
                    $this->log("Retry request failed: " . $retry_response->get_error_message());
                }
            } else {
                $this->log("Failed to obtain new token");
            }
        }

        $this->log("Request failed with status " . $status_code);

        $error_body = json_decode($body_raw, true);
        if ($error_body && isset($error_body['message'])) {
            $this->log("API Error: " . $error_body['message']);
            if (isset($error_body['errorId'])) {
                $this->logDebug("API Error ID: " . $error_body['errorId']);
            }
            if (isset($error_body['logUrl'])) {
                $this->logDebug("API Log URL: " . $error_body['logUrl']);
            }
        } else {
            $this->log("Raw error response: " . $body_raw);
        }
        
        return false;
    }
}