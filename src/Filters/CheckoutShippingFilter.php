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

        // Sobrescribir la sesion del cliente al cargar el checkout para que WC
        // serialice la direccion correcta en wc_checkout_params antes de que
        // el JS del checkout llene los campos.
        add_action('wp', [$this, 'overrideCustomerSessionOnCheckout']);

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

        // Inyecta los selects de region correctos en los fragmentos del AJAX de WC
        // para que no sean reemplazados con la direccion guardada del cliente.
        add_filter('woocommerce_update_order_review_fragments', [$this, 'injectStateFragments']);

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

    public function overrideCustomerSessionOnCheckout(): void {
        if (!is_checkout() || !WC()->customer) {
            return;
        }
        $location = $this->getSelectedLocation();
        if (empty($location)) {
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

    public function injectStateFragments(array $fragments): array {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return $fragments;
        }

        $states       = WC()->countries->get_states('CL') ?: [];
        $current      = $location['region_id'];
        $placeholder  = esc_html__('State / County', 'woocommerce');

        $options = '<option value="">' . $placeholder . '</option>';
        foreach ($states as $code => $name) {
            $sel      = $code === $current ? ' selected="selected"' : '';
            $options .= '<option value="' . esc_attr($code) . '"' . $sel . '>' . esc_html($name) . '</option>';
        }

        $tpl = '<select name="%s" id="%s" class="state_select" autocomplete="address-level1" disabled>' . $options . '</select>';

        $fragments['#billing_state']  = sprintf($tpl, 'billing_state',  'billing_state');
        $fragments['#shipping_state'] = sprintf($tpl, 'shipping_state', 'shipping_state');

        return $fragments;
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

        $state      = esc_js($location['region_id']);
        $city       = esc_js($location['comuna_name']);
        $cl_states  = WC()->countries->get_states('CL') ?: [];
        $state_name = esc_js($cl_states[$location['region_id']] ?? $location['region_id']);
        ?>
        <style>
        #billing_state, #shipping_state,
        #billing_city,  #shipping_city,
        #billing_country, #shipping_country {
            pointer-events: none !important;
            opacity: 0.65 !important;
            cursor: not-allowed !important;
        }
        #billing_state_field .select2-container,
        #shipping_state_field .select2-container,
        #billing_city_field .select2-container,
        #shipping_city_field .select2-container,
        #billing_country_field .select2-container,
        #shipping_country_field .select2-container {
            pointer-events: none !important;
            opacity: 0.65 !important;
            cursor: not-allowed !important;
        }
        </style>
        <script>
        (function($) {
            var SM_STATE      = '<?php echo $state; ?>';
            var SM_STATE_NAME = '<?php echo $state_name; ?>';
            var SM_CITY       = '<?php echo $city; ?>';

            function lockField($el) {
                if (!$el.length) return;
                $el.prop('disabled', true).attr('readonly', true);
                $el.closest('.form-row, .elementor-field-group')
                   .find('.select2-container')
                   .css({'pointer-events': 'none', 'opacity': '0.65'});
            }

            function ensureOption($el, value, label) {
                if (!$el.find('option[value="' + value + '"]').length) {
                    $el.find('option[value=""]').after(
                        $('<option>').val(value).text(label)
                    );
                }
            }

            function applyState($el) {
                if (!$el.length) return;
                ensureOption($el, SM_STATE, SM_STATE_NAME);
                $el.val(SM_STATE);
                // Actualizar Select2 visualmente sin disparar update_checkout
                if ($el.data('select2')) {
                    $el.trigger('change.select2');
                }
                lockField($el);
            }

            function applyCity(type) {
                var $city = $('#' + type + '_city');
                if (!$city.length) return;
                if ($city.is('select')) {
                    var attempts = 0;
                    var poll = setInterval(function() {
                        attempts++;
                        if ($city.find('option[value="' + SM_CITY + '"]').length) {
                            clearInterval(poll);
                            if ($city.val() !== SM_CITY) $city.val(SM_CITY).trigger('change');
                            lockField($city);
                        } else if (attempts >= 20) {
                            clearInterval(poll);
                        }
                    }, 150);
                } else {
                    if ($city.val() !== SM_CITY) $city.val(SM_CITY);
                    lockField($city);
                }
            }

            function applyAndLock() {
                applyState($('#billing_state'));
                applyState($('#shipping_state'));
                applyCity('billing');
                applyCity('shipping');
                lockField($('#billing_country'));
                lockField($('#shipping_country'));
            }

            $(document.body).on('init_checkout updated_checkout', function() {
                setTimeout(applyAndLock, 0);
            });
            $(document).ready(applyAndLock);

            // Capturar reemplazos del elemento via fragmentos WC/Elementor
            if (window.MutationObserver) {
                var mo = new MutationObserver(function(mutations) {
                    var affected = mutations.some(function(m) {
                        return Array.from(m.addedNodes).some(function(n) {
                            return n.id === 'shipping_state' || n.id === 'billing_state' ||
                                   (n.querySelector && (n.querySelector('#shipping_state') || n.querySelector('#billing_state')));
                        });
                    });
                    if (affected) setTimeout(applyAndLock, 0);
                });
                mo.observe(document.body, { childList: true, subtree: true });
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
