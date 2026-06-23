<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class CheckoutBillingFields {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_before_checkout_billing_form', [$this, 'renderFields']);
        add_action('woocommerce_checkout_process', [$this, 'validateFields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveFields']);
        add_action('woocommerce_email_after_order_table', [$this, 'displayInEmail']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayInAdmin']);
    }

    public function enqueueAssets(): void {
        if (!is_checkout()) {
            return;
        }

        $plugin_dir = SOCOMARCA_ERP_PLUGIN_DIR;
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;

        wp_enqueue_script(
            'jquery-rut',
            $plugin_url . 'assets/js/jquery.rut.min.js',
            ['jquery'],
            filemtime($plugin_dir . 'assets/js/jquery.rut.min.js'),
            true
        );

        wp_enqueue_script(
            'socomarca-checkout-billing',
            $plugin_url . 'assets/js/checkout.js',
            ['jquery', 'jquery-rut'],
            filemtime($plugin_dir . 'assets/js/checkout.js'),
            true
        );

        wp_enqueue_style(
            'socomarca-checkout',
            $plugin_url . 'assets/css/checkout.css',
            [],
            filemtime($plugin_dir . 'assets/css/checkout.css')
        );
    }

    public function renderFields($checkout = null): void {
        ?>
        <div id="sm-documento-tipo-block">
            <div class="sm-doc-type">
                <label>
                    <input type="radio" name="sm_documento_tipo" value="factura" checked>
                    Factura
                </label>
                <label>
                    <input type="radio" name="sm_documento_tipo" value="boleta">
                    Boleta
                </label>
            </div>

            <div class="sm-billing-row">
                <p class="form-row form-row-first">
                    <label for="sm_rut">RUT <abbr title="requerido">*</abbr></label>
                    <input
                        type="text"
                        class="input-text"
                        name="sm_rut"
                        id="sm_rut"
                        placeholder="12.345.678-9"
                    >
                    <span id="sm_rut_error" class="sm-rut-error"></span>
                </p>

                <p class="form-row form-row-last" id="sm-razon-social-wrapper">
                    <label for="sm_razon_social">Razón Social <abbr title="requerido">*</abbr></label>
                    <input
                        type="text"
                        class="input-text"
                        name="sm_razon_social"
                        id="sm_razon_social"
                    >
                </p>
            </div>

            <div id="sm-factura-fields">
                <p class="form-row form-row-wide">
                    <label for="sm_giro">Giro <abbr title="requerido">*</abbr></label>
                    <input
                        type="text"
                        class="input-text"
                        name="sm_giro"
                        id="sm_giro"
                    >
                </p>
            </div>
        </div>
        <?php
    }

    public function validateFields(): void {
        $documento_tipo = isset($_POST['sm_documento_tipo']) ? sanitize_text_field(wp_unslash($_POST['sm_documento_tipo'])) : 'factura';
        $rut = isset($_POST['sm_rut']) ? sanitize_text_field(wp_unslash($_POST['sm_rut'])) : '';
        $razon_social = isset($_POST['sm_razon_social']) ? sanitize_text_field(wp_unslash($_POST['sm_razon_social'])) : '';
        $giro = isset($_POST['sm_giro']) ? sanitize_text_field(wp_unslash($_POST['sm_giro'])) : '';

        if (empty($rut)) {
            wc_add_notice('El RUT es requerido.', 'error');
        } elseif (!$this->validateRut($rut)) {
            wc_add_notice('El RUT ingresado es inválido.', 'error');
        }

        if ($documento_tipo === 'factura') {
            if (empty($razon_social)) {
                wc_add_notice('La Razón Social es requerida para factura.', 'error');
            }
            if (empty($giro)) {
                wc_add_notice('El Giro es requerido para factura.', 'error');
            }
        }
    }

    private function validateRut(string $rut): bool {
        $rut = preg_replace('/[^0-9k]/i', '', $rut);
        $rut_body = substr($rut, 0, -1);
        $rut_check = strtoupper(substr($rut, -1));

        if (empty($rut_body) || !is_numeric($rut_body)) {
            return false;
        }

        $sum = 0;
        $multiplier = 2;

        for ($i = strlen($rut_body) - 1; $i >= 0; $i--) {
            $sum += (int)$rut_body[$i] * $multiplier;
            $multiplier++;
            if ($multiplier > 7) {
                $multiplier = 2;
            }
        }

        $check_digit = 11 - ($sum % 11);

        if ($check_digit == 11) {
            $check_digit = '0';
        } elseif ($check_digit == 10) {
            $check_digit = 'K';
        } else {
            $check_digit = (string)$check_digit;
        }

        return $check_digit === $rut_check;
    }

    public function saveFields(int $order_id): void {
        $documento_tipo = isset($_POST['sm_documento_tipo']) ? sanitize_text_field(wp_unslash($_POST['sm_documento_tipo'])) : 'factura';
        $rut = isset($_POST['sm_rut']) ? sanitize_text_field(wp_unslash($_POST['sm_rut'])) : '';
        $razon_social = isset($_POST['sm_razon_social']) ? sanitize_text_field(wp_unslash($_POST['sm_razon_social'])) : '';
        $giro = isset($_POST['sm_giro']) ? sanitize_text_field(wp_unslash($_POST['sm_giro'])) : '';

        if (!empty($rut)) {
            update_post_meta($order_id, 'sm_documento_tipo', $documento_tipo);
            update_post_meta($order_id, 'sm_rut', $rut);

            if ($documento_tipo === 'factura') {
                update_post_meta($order_id, 'sm_razon_social', $razon_social);
                update_post_meta($order_id, 'sm_giro', $giro);
            }
        }
    }

    public function displayInEmail($order): void {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $documento_tipo = get_post_meta($order->get_id(), 'sm_documento_tipo', true);
        $rut = get_post_meta($order->get_id(), 'sm_rut', true);

        if (empty($rut)) {
            return;
        }

        echo '<h3>Información de Documento</h3>';
        echo '<p><strong>Tipo:</strong> ' . esc_html(ucfirst($documento_tipo)) . '</p>';
        echo '<p><strong>RUT:</strong> ' . esc_html($rut) . '</p>';

        if ($documento_tipo === 'factura') {
            $razon_social = get_post_meta($order->get_id(), 'sm_razon_social', true);
            $giro = get_post_meta($order->get_id(), 'sm_giro', true);

            if (!empty($razon_social)) {
                echo '<p><strong>Razón Social:</strong> ' . esc_html($razon_social) . '</p>';
            }
            if (!empty($giro)) {
                echo '<p><strong>Giro:</strong> ' . esc_html($giro) . '</p>';
            }
        }
    }

    public function displayInAdmin($order): void {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $documento_tipo = get_post_meta($order->get_id(), 'sm_documento_tipo', true);
        $rut = get_post_meta($order->get_id(), 'sm_rut', true);

        if (empty($rut)) {
            return;
        }

        echo '<p><strong>Tipo Documento:</strong> ' . esc_html(ucfirst($documento_tipo)) . '</p>';
        echo '<p><strong>RUT:</strong> ' . esc_html($rut) . '</p>';

        if ($documento_tipo === 'factura') {
            $razon_social = get_post_meta($order->get_id(), 'sm_razon_social', true);
            $giro = get_post_meta($order->get_id(), 'sm_giro', true);

            if (!empty($razon_social)) {
                echo '<p><strong>Razón Social:</strong> ' . esc_html($razon_social) . '</p>';
            }
            if (!empty($giro)) {
                echo '<p><strong>Giro:</strong> ' . esc_html($giro) . '</p>';
            }
        }
    }
}
