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
        
        // General address fields overrides
        add_filter('woocommerce_default_address_fields', [$this, 'filterDefaultAddressFields'], 9999, 1);
        add_filter('woocommerce_get_country_locale', [$this, 'filterCountryLocale'], 9999, 1);

        // My Account Edit Address integration (using high priority 9999 to override country-specific overrides)
        add_filter('woocommerce_billing_fields', [$this, 'addBillingAddressFields'], 9999, 1);
        add_filter('woocommerce_address_to_edit', [$this, 'addFieldsToAddressEditor'], 9999, 2);
        add_action('woocommerce_after_save_address_validation', [$this, 'validateAddressFields'], 10, 3);
        add_action('woocommerce_customer_save_address', [$this, 'syncFieldsAfterSave'], 10, 2);

        // Formatted address integration (displays RUT, Giro, Doc Type in My Account address preview, admin, and emails)
        add_filter('woocommerce_localisation_address_formats', [$this, 'customAddressFormats'], 10, 1);
        add_filter('woocommerce_order_formatted_billing_address', [$this, 'injectOrderAddressFields'], 10, 2);
        add_filter('woocommerce_my_account_my_address_formatted_address', [$this, 'injectMyAccountAddressFields'], 10, 3);
        add_filter('woocommerce_formatted_address_replacements', [$this, 'customAddressReplacements'], 10, 2);
    }

    public function enqueueAssets(): void {
        if (!is_checkout() && !is_account_page()) {
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
        $user_id = get_current_user_id();
        $documento_tipo = 'factura'; // default
        $rut = '';
        $razon_social = '';
        $giro = '';

        if ($user_id > 0) {
            $documento_tipo = get_user_meta($user_id, 'billing_documento_tipo', true) ?: get_user_meta($user_id, 'sm_documento_tipo', true) ?: 'factura';
            $rut            = get_user_meta($user_id, 'billing_rut', true) ?: get_user_meta($user_id, 'sm_rut', true);
            $razon_social   = get_user_meta($user_id, 'billing_company', true) ?: get_user_meta($user_id, 'sm_razon_social', true);
            $giro           = get_user_meta($user_id, 'billing_giro', true) ?: get_user_meta($user_id, 'sm_giro', true);
        }
        ?>
        <div id="sm-documento-tipo-block">
            <div class="sm-doc-type">
                <label>
                    <input type="radio" name="sm_documento_tipo" value="factura" <?php checked($documento_tipo, 'factura'); ?>>
                    Factura
                </label>
                <label>
                    <input type="radio" name="sm_documento_tipo" value="boleta" <?php checked($documento_tipo, 'boleta'); ?>>
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
                        value="<?php echo esc_attr($rut); ?>"
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
                        value="<?php echo esc_attr($razon_social); ?>"
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
                        value="<?php echo esc_attr($giro); ?>"
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

            // Save to user profile if user is logged in
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                update_user_meta($user_id, 'sm_documento_tipo', $documento_tipo);
                update_user_meta($user_id, 'billing_documento_tipo', $documento_tipo);
                update_user_meta($user_id, 'sm_rut', $rut);
                update_user_meta($user_id, 'billing_rut', $rut);
                
                if ($documento_tipo === 'factura') {
                    update_user_meta($user_id, 'sm_razon_social', $razon_social);
                    update_user_meta($user_id, 'billing_company', $razon_social);
                    update_user_meta($user_id, 'sm_giro', $giro);
                    update_user_meta($user_id, 'billing_giro', $giro);
                    update_user_meta($user_id, 'tipo_cliente', 'empresa');
                } else {
                    update_user_meta($user_id, 'tipo_cliente', 'persona');
                }
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

    public function addBillingAddressFields($fields) {
        // If we are on checkout, do not add them to standard fields to avoid duplication with our manual rendering
        if (is_checkout() && !is_wc_endpoint_url('order-pay')) {
            return $fields;
        }

        // 1. Re-prioritize standard name fields to place them after our custom block
        if (isset($fields['billing_first_name'])) {
            $fields['billing_first_name']['priority'] = 10;
        }
        if (isset($fields['billing_last_name'])) {
            $fields['billing_last_name']['priority'] = 20;
        }

        // 2. Define custom fields and override company field with priorities from 2 to 8
        $fields['billing_documento_tipo'] = [
            'type'        => 'select',
            'label'       => 'Tipo de documento',
            'options'     => [
                'factura' => 'Factura',
                'boleta'  => 'Boleta',
            ],
            'required'    => true,
            'class'       => ['form-row-wide'],
            'priority'    => 2,
        ];

        $fields['billing_rut'] = [
            'type'        => 'text',
            'label'       => 'RUT',
            'required'    => true,
            'placeholder' => '12.345.678-9',
            'class'       => ['form-row-wide'],
            'priority'    => 4,
        ];

        // Ensure Razón Social (Company) is present, configured, and labeled correctly
        if (!isset($fields['billing_company'])) {
            $fields['billing_company'] = [
                'type'        => 'text',
                'label'       => 'Razón Social',
                'required'    => false, // Handled conditionally in validation hook
                'class'       => ['form-row-wide'],
                'priority'    => 6,
            ];
        } else {
            $fields['billing_company']['label'] = 'Razón Social';
            $fields['billing_company']['priority'] = 6;
            $fields['billing_company']['required'] = false;
            $fields['billing_company']['class'] = ['form-row-wide'];
        }

        $fields['billing_giro'] = [
            'type'        => 'text',
            'label'       => 'Giro',
            'required'    => false, // Handled conditionally in validation hook
            'class'       => ['form-row-wide'],
            'priority'    => 8,
        ];

        // 3. Mark always-required fields as required, and customize labels for Chile
        if (isset($fields['billing_city'])) {
            $fields['billing_city']['label'] = 'Comuna';
        }
        if (isset($fields['billing_state'])) {
            $fields['billing_state']['label'] = 'Región';
        }

        $always_required = [
            'billing_first_name',
            'billing_last_name',
            'billing_country',
            'billing_address_1',
            'billing_city',
            'billing_state',
            'billing_phone',
            'billing_email'
        ];

        foreach ($always_required as $key) {
            if (isset($fields[$key])) {
                $fields[$key]['required'] = true;
            }
        }

        return $fields;
    }

    public function validateAddressFields($user_id, $load_address, $address) {
        if ($load_address !== 'billing') {
            return;
        }

        $rut = isset($_POST['billing_rut']) ? sanitize_text_field(wp_unslash($_POST['billing_rut'])) : '';
        $documento_tipo = isset($_POST['billing_documento_tipo']) ? sanitize_text_field(wp_unslash($_POST['billing_documento_tipo'])) : 'factura';
        $company = isset($_POST['billing_company']) ? sanitize_text_field(wp_unslash($_POST['billing_company'])) : '';
        $giro = isset($_POST['billing_giro']) ? sanitize_text_field(wp_unslash($_POST['billing_giro'])) : '';

        $first_name = isset($_POST['billing_first_name']) ? sanitize_text_field(wp_unslash($_POST['billing_first_name'])) : '';
        $last_name  = isset($_POST['billing_last_name']) ? sanitize_text_field(wp_unslash($_POST['billing_last_name'])) : '';
        $address_1  = isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '';
        $city       = isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '';
        $state      = isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '';
        $phone      = isset($_POST['billing_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '';
        $email      = isset($_POST['billing_email']) ? sanitize_text_field(wp_unslash($_POST['billing_email'])) : '';

        // Validate RUT
        if (empty($rut)) {
            wc_add_notice('El RUT es requerido.', 'error');
        } elseif (!$this->validateRut($rut)) {
            wc_add_notice('El RUT ingresado es inválido.', 'error');
        }

        // Validate document type fields
        if ($documento_tipo === 'factura') {
            if (empty($company)) {
                wc_add_notice('La Razón Social es requerida para factura.', 'error');
            }
            if (empty($giro)) {
                wc_add_notice('El Giro es requerido para factura.', 'error');
            }
        }

        // Validate standard fields
        if (empty($first_name)) {
            wc_add_notice('El Nombre es requerido.', 'error');
        }
        if (empty($last_name)) {
            wc_add_notice('El Apellido es requerido.', 'error');
        }
        if (empty($address_1)) {
            wc_add_notice('La Dirección de la calle es requerida.', 'error');
        }
        if (empty($city)) {
            wc_add_notice('La Comuna es requerida.', 'error');
        }
        if (empty($state)) {
            wc_add_notice('La Región es requerida.', 'error');
        }
        if (empty($phone)) {
            wc_add_notice('El Teléfono es requerido.', 'error');
        }
        if (empty($email)) {
            wc_add_notice('El Correo electrónico es requerido.', 'error');
        }
    }

    public function syncFieldsAfterSave($user_id, $load_address) {
        if ($load_address !== 'billing') {
            return;
        }
        $documento_tipo = get_user_meta($user_id, 'billing_documento_tipo', true);
        $rut = get_user_meta($user_id, 'billing_rut', true);
        $razon_social = get_user_meta($user_id, 'billing_company', true);
        $giro = get_user_meta($user_id, 'billing_giro', true);

        // Sync to sm_ keys and standard registration keys
        update_user_meta($user_id, 'sm_documento_tipo', $documento_tipo);
        update_user_meta($user_id, 'sm_rut', $rut);
        update_user_meta($user_id, 'sm_razon_social', $razon_social);
        update_user_meta($user_id, 'sm_giro', $giro);

        if ($documento_tipo === 'factura') {
            update_user_meta($user_id, 'tipo_cliente', 'empresa');
            update_user_meta($user_id, 'business_name', $razon_social);
            update_user_meta($user_id, 'giro', $giro);
        } else {
            update_user_meta($user_id, 'tipo_cliente', 'persona');
        }
    }

    public function filterDefaultAddressFields($fields) {
        if (isset($fields['city'])) {
            $fields['city']['label'] = 'Comuna';
            $fields['city']['required'] = true;
        }
        if (isset($fields['state'])) {
            $fields['state']['label'] = 'Región';
            $fields['state']['required'] = true;
        }
        if (isset($fields['address_1'])) {
            $fields['address_1']['required'] = true;
        }
        return $fields;
    }

    public function filterCountryLocale($locale) {
        if (isset($locale['CL'])) {
            $locale['CL']['state']['required'] = true;
            $locale['CL']['state']['label'] = 'Región';
            $locale['CL']['city']['required'] = true;
            $locale['CL']['city']['label'] = 'Comuna';
            $locale['CL']['address_1']['required'] = true;
        }
        return $locale;
    }

    public function addFieldsToAddressEditor($fields, $load_address) {
        error_log("addFieldsToAddressEditor called! load_address=" . $load_address);
        if ($load_address !== 'billing') {
            return $fields;
        }

        $always_required = [
            'billing_first_name',
            'billing_last_name',
            'billing_address_1',
            'billing_city',
            'billing_state',
            'billing_phone',
            'billing_email'
        ];

        foreach ($always_required as $key) {
            if (isset($fields[$key])) {
                $fields[$key]['required'] = true;
                error_log("Setting required=true for " . $key);
            }
        }

        if (isset($fields['billing_city'])) {
            $fields['billing_city']['label'] = 'Comuna';
        }
        if (isset($fields['billing_state'])) {
            $fields['billing_state']['label'] = 'Región';
        }

        return $fields;
    }

    public function customAddressFormats($formats) {
        if (isset($formats['CL'])) {
            $formats['CL'] .= "\n{documento_tipo_label}\n{rut_label}\n{giro_label}";
        }
        return $formats;
    }

    public function injectOrderAddressFields($args, $order) {
        $order_id = $order->get_id();
        $documento_tipo = get_post_meta($order_id, 'sm_documento_tipo', true);
        $rut = get_post_meta($order_id, 'sm_rut', true);
        $razon_social = get_post_meta($order_id, 'sm_razon_social', true);
        $giro = get_post_meta($order_id, 'sm_giro', true);

        if (!empty($rut)) {
            $args['documento_tipo_label'] = 'Tipo documento: ' . ucfirst($documento_tipo);
            $args['rut_label'] = 'RUT: ' . $rut;
            if ($documento_tipo === 'factura') {
                $args['company'] = $razon_social;
                $args['giro_label'] = 'Giro: ' . $giro;
            } else {
                $args['giro_label'] = '';
            }
        } else {
            $args['documento_tipo_label'] = '';
            $args['rut_label'] = '';
            $args['giro_label'] = '';
        }

        return $args;
    }

    public function injectMyAccountAddressFields($args, $customer_id, $name) {
        if ($name !== 'billing') {
            return $args;
        }

        $documento_tipo = get_user_meta($customer_id, 'billing_documento_tipo', true) ?: get_user_meta($customer_id, 'sm_documento_tipo', true) ?: 'boleta';
        $rut = get_user_meta($customer_id, 'billing_rut', true) ?: get_user_meta($customer_id, 'sm_rut', true);
        $razon_social = get_user_meta($customer_id, 'billing_company', true) ?: get_user_meta($customer_id, 'sm_razon_social', true);
        $giro = get_user_meta($customer_id, 'billing_giro', true) ?: get_user_meta($customer_id, 'sm_giro', true);

        if (!empty($rut)) {
            $args['documento_tipo_label'] = 'Tipo documento: ' . ucfirst($documento_tipo);
            $args['rut_label'] = 'RUT: ' . $rut;
            if ($documento_tipo === 'factura') {
                $args['company'] = $razon_social;
                $args['giro_label'] = 'Giro: ' . $giro;
            } else {
                $args['giro_label'] = '';
            }
        } else {
            $args['documento_tipo_label'] = '';
            $args['rut_label'] = '';
            $args['giro_label'] = '';
        }

        return $args;
    }

    public function customAddressReplacements($replacements, $args) {
        $replacements['{documento_tipo_label}'] = isset($args['documento_tipo_label']) ? $args['documento_tipo_label'] : '';
        $replacements['{rut_label}']           = isset($args['rut_label']) ? $args['rut_label'] : '';
        $replacements['{giro_label}']          = isset($args['giro_label']) ? $args['giro_label'] : '';
        return $replacements;
    }
}
