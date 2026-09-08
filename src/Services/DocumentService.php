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

            // Obtener datos de documento y billing de la orden
            $documento_tipo = get_post_meta($order_id, 'sm_documento_tipo', true) ?: 'factura';
            $rut = get_post_meta($order_id, 'sm_rut', true) ?: '';
            $razon_social = get_post_meta($order_id, 'sm_razon_social', true) ?: '';
            $giro = get_post_meta($order_id, 'sm_giro', true) ?: '';

            // Obtener notas de la orden
            $observacion = $order->get_customer_note() ?: '';

            // Obtener método de pago
            $payment_method = $order->get_payment_method_title() ?: 'Pago a crédito';

            // Construir texto2: RUT + Tipo de documento + Razón social + Giro
            $texto2_parts = [];
            if (!empty($rut)) {
                $texto2_parts[] = $rut;
            }
            $texto2_parts[] = ucfirst($documento_tipo); // Tipo (Factura o Boleta)
            if (!empty($razon_social)) {
                $texto2_parts[] = $razon_social;
            }
            if (!empty($giro)) {
                $texto2_parts[] = $giro;
            }

            $document_data = [
                'datos' => [
                    'empresa'       => $company_code,
                    'codigoEntidad' => $entity_code,
                    'tido'          => $tido,
                    'modalidad'     => $modalidad,
                    'lineas'        => $lines,
                    
                    'observacion'   => $observacion,
                    'kobo'          => $this->get_warehouse_kobo_from_order($order),
                    'texto1'        => $payment_method . ' - Orden #' . $order_id,
                    'texto2'        => implode(' - ', $texto2_parts),
                    'texto3'        => 'Origen: ' . get_bloginfo('name'),
                    'texto4'        => 'Orden: #' . $order_id,
                    'texto5'        => 'Fecha de pedido: ' . $order->get_date_created()->date('Y-m-d H:i:s'),
                ]
            ];

            if (!empty($funcionario)) {
                $document_data['datos']['funcionario'] = $funcionario;
            }
            
            $result = $this->create_document($document_data);

            if ($result) {
                $idmaeedo = isset($result['idmaeedo']) ? $result['idmaeedo'] : '';
                $numero = isset($result['numero']) ? $result['numero'] : '';
                $note_message = 'Documento creado en Random ERP exitosamente';
                if ($idmaeedo) {
                    $note_message .= ' - idmaeedo: ' . $idmaeedo;
                }
                if ($numero) {
                    $note_message .= ' - numero: ' . $numero;
                }
                $order->add_order_note($note_message);

                update_post_meta($order_id, 'created_document', 1);
                if ($idmaeedo) {
                    update_post_meta($order_id, 'idmaeedo', $idmaeedo);
                }
                if ($numero) {
                    update_post_meta($order_id, 'numero_documento', $numero);
                }

                $this->log("Document created successfully for order: $order_id - idmaeedo: $idmaeedo - numero: $numero");
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
    
    /**
     * Obtiene el código de entidad ERP para el pedido.
     *
     * Flujo de resolución:
     *  1. Si el usuario está logueado → user_meta random_erp_entity_code.
     *  2. Si es invitado → busca por RUT (sm_rut del pedido) en user_meta.
     *  3. Si el RUT está registrado en WP → usa su entity_code y lo vincula al pedido.
     *  4. Si no está registrado → genera un código temporal "GUEST-{order_id}" y
     *     lo guarda en el pedido para futura vinculación.
     *  5. Fallback → default entity code de configuración.
     */
    private function get_entity_code_from_order($order) {
        // 1. Usuario logueado
        $user = $order->get_user();
        if ($user) {
            $entity_code = get_user_meta($user->ID, 'random_erp_entity_code', true);
            if ($entity_code) {
                $this->log("Entidad resuelta por usuario logueado ({$user->ID}): {$entity_code}");
                return $entity_code;
            }
        }

        // 2. Invitado con RUT en el pedido → buscar si está registrado en WP/ERP
        $order_id = $order->get_id();
        $rut      = get_post_meta($order_id, 'sm_rut', true);

        if (!empty($rut)) {
            $existing_users = get_users([
                'meta_key'   => 'rut',
                'meta_value' => $rut,
                'number'     => 1,
                'fields'     => ['ID'],
            ]);

            if (!empty($existing_users)) {
                $matched_user_id = $existing_users[0]->ID;
                $entity_code     = get_user_meta($matched_user_id, 'random_erp_entity_code', true);

                if ($entity_code) {
                    // 3. RUT encontrado en WP y tiene entidad ERP → vincular al pedido
                    update_post_meta($order_id, '_sm_guest_resolved_user_id', $matched_user_id);
                    update_post_meta($order_id, '_sm_guest_resolved_entity_code', $entity_code);
                    $this->log("Invitado con RUT {$rut} resuelto al usuario WP #{$matched_user_id}, entidad: {$entity_code}");
                    return $entity_code;
                }
            }

            // 4. RUT no registrado en el ERP → usar entidad por defecto
            $default = get_option('sm_default_entity_code', '5');
            $this->log("Invitado con RUT {$rut} no encontrado en el ERP. Usando entidad por defecto: {$default}");
            return $default;
        }

        // 5. Fallback: sin RUT ni usuario
        $default = get_option('sm_default_entity_code', '5');
        $this->log("Sin RUT ni usuario registrado para pedido #{$order_id}. Usando entidad por defecto: {$default}");
        return $default;
    }

    /**
     * Obtiene el código KOBO (random_erp_warehouse_code) de la bodega
     * asociada al pedido a través del sm_pickup_store_id.
     * Si no hay bodega asociada, retorna cadena vacía.
     */
    private function get_warehouse_kobo_from_order($order): string {
        $order_id = $order->get_id();
        $store_id = (int) $order->get_meta('_sm_order_warehouse_id', true);

        if (empty($store_id)) {
            $store_id = (int) $order->get_meta('sm_pickup_store_id', true);
        }

        if (empty($store_id)) {
            // Intentar resolver por ciudad de envío
            $commune = $order->get_shipping_city() ?: $order->get_billing_city();
            if (!empty($commune)) {
                $store_id = $this->resolve_store_id_by_commune($commune);
            }
        }

        if (empty($store_id)) {
            $this->log("No se encontró bodega para el pedido #{$order_id}. KOBO vacío.");
            return '';
        }

        $kobo = get_term_meta($store_id, 'random_erp_warehouse_code', true);
        $this->log("Pedido #{$order_id}: Bodega term_id={$store_id}, KOBO=" . ($kobo ?: '(vacío)'));
        return (string) ($kobo ?: '');
    }

    /**
     * Resuelve el term_id de la location usando el mapeo de comunas.
     */
    private function resolve_store_id_by_commune(string $commune): int {
        $mapping      = get_option('sm_location_mapping', []);
        $communeLower = strtolower(trim($commune));

        if (!is_array($mapping)) {
            return 0;
        }

        foreach ($mapping as $region) {
            if (empty($region['comunas']) || !is_array($region['comunas'])) {
                continue;
            }
            foreach ($region['comunas'] as $comunaData) {
                if (empty($comunaData['name']) || empty($comunaData['warehouse_id'])) {
                    continue;
                }
                if (strtolower(trim($comunaData['name'])) === $communeLower) {
                    return (int) $comunaData['warehouse_id'];
                }
            }
        }
        return 0;
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