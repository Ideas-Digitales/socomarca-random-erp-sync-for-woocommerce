<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Restringe el envio en el checkout a la ubicacion seleccionada en el modal
 * (cookie sm_selected_location). Los campos de region y ciudad se bloquean
 * para reflejar la bodega elegida. Solo aparecen metodos de envio de esa zona.
 */
class CheckoutShippingFilter {

    public function __construct() {
        // Inyecta la ubicacion en WC_Customer — tanto en campos de envio como de facturacion.
        // WooCommerce usa billing como shipping cuando "enviar a direccion diferente" esta desmarcado,
        // por lo que ambos conjuntos de getters deben reflejar la bodega seleccionada.
        add_filter('woocommerce_customer_get_shipping_country', [$this, 'filterCustomerCountry']);
        add_filter('woocommerce_customer_get_shipping_state',   [$this, 'filterCustomerState']);
        add_filter('woocommerce_customer_get_shipping_city',    [$this, 'filterCustomerCity']);
        add_filter('woocommerce_customer_get_billing_country',  [$this, 'filterCustomerCountry']);
        add_filter('woocommerce_customer_get_billing_state',    [$this, 'filterCustomerState']);
        add_filter('woocommerce_customer_get_billing_city',     [$this, 'filterCustomerCity']);

        // Override del paquete de calculo.
        add_filter('woocommerce_cart_shipping_packages', [$this, 'overridePackageDestination']);

        // Bloquea y pre-rellena los campos del formulario de checkout.
        add_filter('woocommerce_checkout_fields', [$this, 'lockCheckoutFields']);
        add_filter('woocommerce_checkout_get_value', [$this, 'prefillCheckoutFields'], 10, 2);

        // Agrega inputs ocultos para que los selects deshabilitados se envien igual.
        add_action('woocommerce_checkout_after_customer_details', [$this, 'outputHiddenFields']);

        // Sincroniza la sesion WC con el cookie durante el refresco AJAX del checkout.
        // Necesario para que el calculo de metodos de envio use la bodega correcta
        // incluso cuando WC tiene datos anteriores en sesion.
        add_action('woocommerce_checkout_update_order_review', [$this, 'updateSessionFromCookie']);

        // Bloquea los Select2 via JS (el plugin de estados los reinicializa y borra disabled).
        add_action('wp_footer', [$this, 'outputLockScript']);
    }

    // -------------------------------------------------------------------------
    // Filtros WC_Customer — se ejecutan antes del calculo de zonas
    // -------------------------------------------------------------------------

    public function filterCustomerCountry($value): string {
        $location = $this->getSelectedLocation();
        return !empty($location) ? 'CL' : $value;
    }

    public function filterCustomerState($value): string {
        $location = $this->getSelectedLocation();
        return !empty($location['region_id']) ? $location['region_id'] : $value;
    }

    public function filterCustomerCity($value): string {
        $location = $this->getSelectedLocation();
        return !empty($location['comuna_name']) ? $location['comuna_name'] : $value;
    }

    // -------------------------------------------------------------------------
    // Override del paquete de envio
    // -------------------------------------------------------------------------

    public function overridePackageDestination(array $packages): array {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return $packages;
        }

        foreach ($packages as &$package) {
            $package['destination']['country'] = 'CL';
            $package['destination']['state']   = $location['region_id'];
            $package['destination']['city']    = $location['comuna_name'];
        }

        return $packages;
    }

    // -------------------------------------------------------------------------
    // Formulario de checkout
    // -------------------------------------------------------------------------

    public function lockCheckoutFields(array $fields): array {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return $fields;
        }

        $lock_shipping = ['shipping_state', 'shipping_city', 'shipping_country'];
        $lock_billing  = ['billing_state',  'billing_city',  'billing_country'];

        foreach ($lock_shipping as $key) {
            if (!isset($fields['shipping'][$key])) continue;
            $fields['shipping'][$key]['custom_attributes']['disabled'] = 'disabled';
            if ($key !== 'shipping_country') {
                $fields['shipping'][$key]['description'] = 'Segun la bodega seleccionada.';
            }
        }

        foreach ($lock_billing as $key) {
            if (!isset($fields['billing'][$key])) continue;
            $fields['billing'][$key]['custom_attributes']['disabled'] = 'disabled';
            if ($key !== 'billing_country') {
                $fields['billing'][$key]['description'] = 'Segun la bodega seleccionada.';
            }
        }

        return $fields;
    }

    public function prefillCheckoutFields($value, string $input) {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return $value;
        }

        switch ($input) {
            case 'shipping_country':
            case 'billing_country':
                return 'CL';
            case 'shipping_state':
            case 'billing_state':
                return $location['region_id'];
            case 'shipping_city':
            case 'billing_city':
                return $location['comuna_name'];
        }

        return $value;
    }

    public function outputHiddenFields(): void {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return;
        }
        $state = esc_attr($location['region_id']);
        $city  = esc_attr($location['comuna_name']);
        ?>
        <input type="hidden" name="shipping_country" value="CL">
        <input type="hidden" name="shipping_state" value="<?php echo $state; ?>">
        <input type="hidden" name="shipping_city" value="<?php echo $city; ?>">
        <input type="hidden" name="billing_country" value="CL">
        <input type="hidden" name="billing_state" value="<?php echo $state; ?>">
        <input type="hidden" name="billing_city" value="<?php echo $city; ?>">
        <?php
    }

    public function updateSessionFromCookie(): void {
        $location = $this->getSelectedLocation();
        if (empty($location) || !WC()->customer) {
            return;
        }

        WC()->customer->set_shipping_country('CL');
        WC()->customer->set_shipping_state($location['region_id']);
        WC()->customer->set_shipping_city($location['comuna_name']);
        WC()->customer->set_billing_country('CL');
        WC()->customer->set_billing_state($location['region_id']);
        WC()->customer->set_billing_city($location['comuna_name']);
        WC()->customer->save();
    }

    // -------------------------------------------------------------------------
    // Script de bloqueo de campos Select2
    // -------------------------------------------------------------------------

    public function outputLockScript(): void {
        if (!is_checkout()) {
            return;
        }
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return;
        }
        ?>
        <script>
        (function($) {
            var FIELD_IDS = [
                '#shipping_city', '#shipping_state', '#shipping_country',
                '#billing_city',  '#billing_state',  '#billing_country'
            ];

            function lockLocationFields() {
                FIELD_IDS.forEach(function(id) {
                    var $field = $(id);
                    if (!$field.length) return;

                    $field.prop('disabled', true).attr('readonly', true);

                    // Cubre tanto el wrapper nativo de WC (.form-row) como el de Elementor (.elementor-field-group).
                    $field.closest('.form-row, .elementor-field-group')
                          .find('.select2-container')
                          .css({'pointer-events': 'none', 'opacity': '0.65'});
                });
            }

            // Disparo inicial y tras cada refresco de WooCommerce.
            $(document).ready(lockLocationFields);
            $(document.body).on('updated_checkout', lockLocationFields);

            // Elementor Pro reinicializa Select2 al montar el widget.
            $(window).on('elementor/frontend/init', function() {
                $(document).on('elementor-pro/woocommerce/checkout/init', lockLocationFields);
                lockLocationFields();
            });

            // MutationObserver: re-bloquea si Elementor reconstruye el DOM del checkout.
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    var needsLock = mutations.some(function(m) {
                        return m.addedNodes.length > 0;
                    });
                    if (needsLock) {
                        lockLocationFields();
                    }
                });

                $(document).ready(function() {
                    var target = document.querySelector('.woocommerce-checkout, .e-checkout__container');
                    if (target) {
                        observer.observe(target, { childList: true, subtree: true });
                    }
                });
            }
        })(jQuery);
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Cookie
    // -------------------------------------------------------------------------

    private function getSelectedLocation(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cookie = $_COOKIE['sm_selected_location'] ?? '';
        if (empty($cookie)) {
            $cache = [];
            return $cache;
        }

        $data = json_decode(stripslashes(urldecode($cookie)), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $cache = [];
            return $cache;
        }

        if (empty($data['region_id']) || empty($data['comuna_name'])) {
            $cache = [];
            return $cache;
        }

        $cache = $data;
        return $cache;
    }
}
