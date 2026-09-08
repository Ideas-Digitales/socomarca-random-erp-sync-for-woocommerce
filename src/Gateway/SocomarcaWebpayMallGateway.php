<?php

namespace Socomarca\RandomERP\Gateway;

use WC_Payment_Gateway;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class SocomarcaWebpayMallGateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'socomarca_webpay_mall';
        $this->icon               = 'https://socofood.cl/wp-content/plugins/transbank-webpay-plus-rest/images/webpay.png';
        $this->has_fields         = false;
        $this->method_title       = 'Webpay Plus Mall';
        $this->method_description = 'Pasarela de pago Webpay Plus Mall de Transbank para tarjetas de crédito, débito y prepago.';

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title', 'Webpay Plus Mall');
        $this->description  = $this->get_option('description', 'Paga con tarjetas de crédito/débito/prepago a través de Webpay Plus Mall.');
        $this->order_status = $this->get_option('order_status', 'processing');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('woocommerce_review_order_before_submit', [$this, 'display_selected_commerce_code']);
        
        // WooCommerce API listener/callback
        add_action('woocommerce_api_' . $this->id, [$this, 'handle_callback']);
        
        $this->log("Pasarela de Webpay Mall instanciada.");
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Activar/Desactivar',
                'type'    => 'checkbox',
                'label'   => 'Activar Webpay Plus Mall',
                'default' => 'no',
            ],
            'title' => [
                'title'       => 'Título en Checkout',
                'type'        => 'text',
                'description' => 'Título que verá el cliente durante el proceso de pago.',
                'default'     => 'Webpay Plus Mall',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Descripción en Checkout',
                'type'        => 'textarea',
                'description' => 'Descripción que verá el cliente durante el proceso de pago.',
                'default'     => 'Paga con tarjetas de crédito/débito/prepago a través de Webpay Plus Mall.',
                'desc_tip'    => true,
            ],
            'environment' => [
                'title'       => 'Ambiente',
                'type'        => 'select',
                'options'     => [
                    'TEST'      => 'Integración (TEST)',
                    'PRODUCCION' => 'Producción',
                ],
                'description' => 'Selecciona el ambiente de Transbank. En integración se usan credenciales de prueba automáticamente.',
                'default'     => 'TEST',
                'desc_tip'    => true,
            ],
            'mall_commerce_code' => [
                'title'       => 'Código de Comercio Mall (padre)',
                'type'        => 'text',
                'description' => 'Código de comercio Mall (padre) entregado por Transbank. Empieza con 597 y tiene 12 dígitos. Solo se usa en ambiente Producción.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'mall_api_key' => [
                'title'       => 'API Key Mall',
                'type'        => 'password',
                'description' => 'API Key (Tbk-Api-Key-Secret) entregada por Transbank para el comercio Mall. Solo se usa en ambiente Producción.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'default_child_commerce_code' => [
                'title'       => 'Código de Comercio Hijo por Defecto',
                'type'        => 'text',
                'description' => 'Código de comercio hijo (12 dígitos, empieza con 597) utilizado como fallback si la bodega asociada al pedido no tiene uno configurado.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'order_status' => [
                'title'       => 'Estado del Pedido (Pago Exitoso)',
                'type'        => 'select',
                'options'     => wc_get_order_statuses(),
                'description' => 'Estado en el que quedará el pedido una vez que el pago sea acreditado exitosamente.',
                'default'     => 'wc-processing',
                'desc_tip'    => true,
            ],
        ];
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return [
                'result' => 'fail',
                'redirect' => '',
            ];
        }

        $this->log("Procesando pago para Orden ID: {$order_id}");

        // 1. Determine the Warehouse/Location ID
        $store_id = $order->get_meta('_sm_order_warehouse_id', true);
        if (empty($store_id)) {
            $store_id = $order->get_meta('sm_pickup_store_id', true);
        }
        if (empty($store_id)) {
            $commune = $order->get_shipping_city() ?: $order->get_billing_city();
            $store_id = $this->getWarehouseIdByCommune($commune);
        }

        // 2. Get the Child Commerce Code
        $child_commerce_code = '';
        if ($store_id) {
            $child_commerce_code = get_term_meta($store_id, 'sm_child_commerce_code', true);
        }

        $environment = $this->get_environment();

        if (empty($child_commerce_code)) {
            if ($environment === 'PRODUCCION') {
                $child_commerce_code = $this->get_option('default_child_commerce_code');
            } else {
                $child_commerce_code = '597055555536'; // Integration default child
            }
        }

        $this->log("Ambiente: {$environment} | Bodega ID: " . ($store_id ?: 'N/A') . " | Código hijo a usar: {$child_commerce_code}");

        if (empty($child_commerce_code)) {
            $this->log("Error: No se encontró código de comercio hijo para la bodega/sucursal.");
            wc_add_notice('Error en la configuración de la bodega de despacho/retiro. Por favor, contacta con soporte.', 'error');
            return [
                'result' => 'fail',
                'redirect' => '',
            ];
        }

        // 3. Prepare parent and child identifiers
        $parent_buy_order = 'M-' . $order_id . '-' . time();
        $child_buy_order  = 'C-' . $order_id . '-' . time();
        $session_id       = 'S-' . $order_id . '-' . time();
        $amount           = floatval($order->get_total());

        // Return URL
        $return_url = WC()->api_request_url($this->id);

        $details = [
            [
                'commerce_code' => $child_commerce_code,
                'buy_order'     => $child_buy_order,
                'amount'        => $amount
            ]
        ];

        $payload = [
            'buy_order'  => $parent_buy_order,
            'session_id' => $session_id,
            'details'    => $details,
            'return_url' => $return_url
        ];

        $this->log("Enviando payload a Transbank: " . wp_json_encode($payload));

        $response = $this->makeTransbankRequest('POST', 'rswebpaytransaction/api/webpay/v1.2/transactions', $payload);

        if ($response && isset($response['token']) && isset($response['url'])) {
            $token = $response['token'];
            $url   = $response['url'];

            // Save details to order
            $order->update_meta_data('_sm_webpay_token', $token);
            $order->update_meta_data('_sm_webpay_url', $url);
            $order->update_meta_data('_sm_webpay_buy_order', $parent_buy_order);
            $order->update_meta_data('_sm_webpay_child_buy_order', $child_buy_order);
            $order->update_meta_data('_sm_webpay_child_commerce_code', $child_commerce_code);
            $order->save();

            $this->log("Transacción creada con éxito. Token: {$token}. Redirigiendo a receipt page.");

            return [
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url(true),
            ];
        } else {
            $this->log("Fallo al crear la transacción en Transbank.");
            wc_add_notice('Error al iniciar la transacción con Webpay Mall. Por favor, intenta de nuevo.', 'error');
            return [
                'result' => 'fail',
                'redirect' => '',
            ];
        }
    }

    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        $token = $order->get_meta('_sm_webpay_token');
        $buy_order = $order->get_meta('_sm_webpay_buy_order');

        $transbank_url = $order->get_meta('_sm_webpay_url');
        
        if (empty($transbank_url)) {
            // Fallback just in case
            $settings = get_option('woocommerce_transbank_webpay_plus_rest_settings');
            $environment = $settings['webpay_rest_environment'] ?? 'TEST';
            
            $transbank_url = ($environment === 'PRODUCCION') 
                ? 'https://webpay3g.transbank.cl/webpay-external-ws-war/webpay/'
                : 'https://webpay3gint.transbank.cl/webpay-external-ws-war/webpay/';
        }

        ?>
        <form method="post" id="socomarca_webpay_form" action="<?php echo esc_url($transbank_url); ?>">
            <input type="hidden" name="token_ws" value="<?php echo esc_attr($token); ?>" />
            <p>Redirigiendo a la pasarela de pagos de Webpay Plus Mall...</p>
            <button type="submit" class="button alt" id="socomarca_pay_button">Pagar ahora</button>
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('socomarca_webpay_form').submit();
            });
        </script>
        <?php
    }

    public function handle_callback() {
        $this->log("Callback de Transbank recibido. Petición: " . print_r($_REQUEST, true));

        // 1. Check for aborted/canceled payment
        if (isset($_REQUEST['TBK_ORDEN_COMPRA']) && isset($_REQUEST['TBK_ID_SESION'])) {
            $parent_buy_order = sanitize_text_field(wp_unslash($_REQUEST['TBK_ORDEN_COMPRA']));
            $order = $this->getOrderByBuyOrder($parent_buy_order);
            
            if ($order) {
                $this->log("Transacción cancelada por el usuario en Transbank. Orden ID: " . $order->get_id());
                $order->add_order_note('Webpay Plus Mall: El pago fue cancelado por el usuario en el portal de Transbank.');
                $order->update_status('failed', 'Pago cancelado por el usuario en Webpay.');
                wc_add_notice('El pago fue cancelado en el portal de Webpay. Intenta nuevamente.', 'error');
                wp_redirect($order->get_checkout_payment_url());
            } else {
                wp_redirect(wc_get_checkout_url());
            }
            exit;
        }

        // 2. Process Token
        $token = isset($_REQUEST['token_ws']) ? sanitize_text_field(wp_unslash($_REQUEST['token_ws'])) : '';
        if (empty($token)) {
            $this->log("Error: Token vacío en callback.");
            wc_add_notice('Error en la transacción: Token inválido.', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $order = $this->getOrderByToken($token);
        if (!$order) {
            $this->log("Error: No se encontró orden asociada al token: {$token}");
            wc_add_notice('No se encontró el pedido correspondiente al pago realizado.', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Prevent double processing
        if ($order->is_paid()) {
            $this->log("La orden {$order->get_id()} ya se encuentra pagada. Redirigiendo a gracias.");
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }

        // 3. Commit Transaction
        $response = $this->makeTransbankRequest('PUT', 'rswebpaytransaction/api/webpay/v1.2/transactions/' . $token);

        $this->log("Respuesta Commit Transbank para Orden {$order->get_id()}: " . wp_json_encode($response));

        if ($response && isset($response['details']) && is_array($response['details'])) {
            $detail = $response['details'][0] ?? [];
            $status = $detail['status'] ?? '';
            $response_code = isset($detail['response_code']) ? (int)$detail['response_code'] : -1;

            if ($status === 'AUTHORIZED' && $response_code === 0) {
                // Payment Success!
                $note = $this->render_transaction_details($response, $token);
                $order->add_order_note($note);
                
                // Set custom order status
                $custom_status = $this->order_status;
                if (strpos($custom_status, 'wc-') === 0) {
                    $custom_status = substr($custom_status, 3);
                }
                $order->set_status($custom_status);
                
                // Complete payment
                $authorization_code = $detail['authorization_code'] ?? '';
                $order->payment_complete($authorization_code);
                
                // Save meta fields
                $order->update_meta_data('_sm_webpay_authorization_code', $authorization_code);
                $order->update_meta_data('_sm_webpay_payment_type', $detail['payment_type_code'] ?? '');
                $order->update_meta_data('_sm_webpay_installments', $detail['installments_number'] ?? '0');
                $order->save();

                $this->log("Pago exitoso y acreditado para Orden ID: " . $order->get_id());

                wp_redirect($order->get_checkout_order_received_url());
                exit;
            }
        }

        // Payment Failed/Rejected
        $this->log("El pago fue rechazado o falló para Orden ID: " . $order->get_id());
        $order->add_order_note('Webpay Plus Mall: El pago fue rechazado o falló.');
        $order->update_status('failed', 'El pago fue rechazado en Webpay.');
        $order->save();

        wc_add_notice('El pago fue rechazado o falló en el portal de Webpay. Por favor, intenta de nuevo.', 'error');
        wp_redirect($order->get_checkout_payment_url());
        exit;
    }

    private function getWarehouseIdByCommune(string $commune): ?int {
        $mapping = get_option('sm_location_mapping', []);
        if (empty($mapping) || !is_array($mapping)) {
            return null;
        }
        $communeLower = strtolower(trim($commune));
        foreach ($mapping as $region) {
            if (!isset($region['comunas']) || !is_array($region['comunas'])) {
                continue;
            }
            foreach ($region['comunas'] as $comunaData) {
                if (!isset($comunaData['name']) || !isset($comunaData['warehouse_id'])) {
                    continue;
                }
                if (strtolower(trim($comunaData['name'])) === $communeLower) {
                    return (int) $comunaData['warehouse_id'];
                }
            }
        }
        return null;
    }

    private function getOrderByToken(string $token) {
        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_key'   => '_sm_webpay_token',
            'meta_value' => $token,
        ]);
        return !empty($orders) ? $orders[0] : null;
    }

    private function getOrderByBuyOrder(string $buy_order) {
        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_key'   => '_sm_webpay_buy_order',
            'meta_value' => $buy_order,
        ]);
        return !empty($orders) ? $orders[0] : null;
    }

    private function get_environment(): string {
        // Primero lee el campo propio del gateway
        $own_env = $this->get_option('environment', '');
        if (!empty($own_env)) {
            return $own_env;
        }
        // Fallback al plugin oficial de Transbank
        $settings = get_option('woocommerce_transbank_webpay_plus_rest_settings');
        return $settings['webpay_rest_environment'] ?? 'TEST';
    }

    private function makeTransbankRequest($method, $endpoint, $body = null) {
        $environment = $this->get_environment();
        
        if ($environment === 'PRODUCCION') {
            $api_url = 'https://webpay3g.transbank.cl/';
            // Prioridad: campos propios del gateway > plugin oficial
            $commerce_code = $this->get_option('mall_commerce_code', '');
            $api_key       = $this->get_option('mall_api_key', '');
            if (empty($commerce_code) || empty($api_key)) {
                $settings      = get_option('woocommerce_transbank_webpay_plus_rest_settings');
                $commerce_code = $commerce_code ?: ($settings['webpay_rest_commerce_code'] ?? '');
                $api_key       = $api_key       ?: ($settings['webpay_rest_api_key'] ?? '');
            }
        } else {
            // Integration Mall
            $api_url       = 'https://webpay3gint.transbank.cl/';
            $commerce_code = '597055555535'; // Mall Integration Commerce Code
            $api_key       = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
        }
        
        $this->log("makeTransbankRequest: Ambiente={$environment} | URL={$api_url} | Mall CC={$commerce_code}");

        $url = $api_url . $endpoint;
        
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type'       => 'application/json',
                'Tbk-Api-Key-Id'     => $commerce_code,
                'Tbk-Api-Key-Secret' => $api_key
            ]
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log("Error en petición HTTP a Transbank: " . $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw    = wp_remote_retrieve_body($response);
        
        $data = json_decode($body_raw, true);

        if ($status_code < 200 || $status_code >= 300) {
            $this->log("Error de respuesta Transbank (Código {$status_code}): " . $body_raw);
        }

        return $data;
    }

    private function render_transaction_details($response, $token) {
        $detail = $response['details'][0] ?? [];
        $authorization = $detail['authorization_code'] ?? 'N/A';
        $installments = $detail['installments_number'] ?? '0';
        $payment_type = $detail['payment_type_code'] ?? 'N/A';
        $response_code = isset($detail['response_code']) ? $detail['response_code'] : '-1';
        $amount = $detail['amount'] ?? '0';
        $status = $detail['status'] ?? 'N/A';
        
        $payment_types = [
            'VD' => 'Redcompra (Débito)',
            'VN' => 'Tarjeta de Crédito (Sin cuotas)',
            'VC' => 'Tarjeta de Crédito (Cuotas normales)',
            'SI' => 'Tarjeta de Crédito (Cuotas sin interés)',
            'S2' => 'Tarjeta de Crédito (3 cuotas sin interés)',
            'NC' => 'Tarjeta de Crédito (Cuotas comercio)',
            'VP' => 'Tarjeta Prepago'
        ];
        $payment_type_text = $payment_types[$payment_type] ?? $payment_type;

        return sprintf(
            "<h3>Webpay Plus Mall: Pago exitoso</h3>
            <strong>Estado:</strong> %s<br>
            <strong>Código de autorización:</strong> %s<br>
            <strong>Monto:</strong> $%s<br>
            <strong>Código de respuesta:</strong> %s<br>
            <strong>Tipo de pago:</strong> %s<br>
            <strong>Cuotas:</strong> %s<br>
            <strong>Token:</strong> %s",
            esc_html($status),
            esc_html($authorization),
            esc_html(number_format($amount, 0, ',', '.')),
            esc_html($response_code),
            esc_html($payment_type_text),
            esc_html($installments),
            esc_html($token)
        );
    }

    public function display_selected_commerce_code() {
        // 1. Get warehouse ID
        $store_id = isset($_POST['sm_pickup_store_id']) ? sanitize_text_field(wp_unslash($_POST['sm_pickup_store_id'])) : '';
        
        if (empty($store_id)) {
            $shipping_city = isset($_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '';
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '';
            $commune = !empty($shipping_city) ? $shipping_city : $billing_city;
            
            if (empty($commune)) {
                // If not in POST (e.g. first load), check session
                $store_id = $_SESSION['multiloca_selected_location_id'] ?? 0;
            } else {
                $store_id = $this->getWarehouseIdByCommune($commune);
            }
        }

        // 2. Get Child Commerce Code
        $child_commerce_code = '';
        if ($store_id) {
            $child_commerce_code = get_term_meta($store_id, 'sm_child_commerce_code', true);
        }

        $environment = $this->get_environment();

        if (empty($child_commerce_code)) {
            if ($environment === 'PRODUCCION') {
                $child_commerce_code = $this->get_option('default_child_commerce_code');
            } else {
                $child_commerce_code = '597055555536'; // Integration default child
            }
        }

        if (empty($child_commerce_code)) {
            $child_commerce_code = 'No configurado';
        }

        //echo '<pre style="background: #f5f5f5; border: 1px solid #ddd; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 13px; border-radius: 4px; color: #333;">Codigo de comercio: ' . esc_html($child_commerce_code) . '</pre>';
    }

    private function log($message) {
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        $log_file = $logs_dir . '/documents.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] WebpayMallGateway: $message" . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
