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
        // Inyecta la ubicacion en WC_Customer SIEMPRE que haya cookie.
        // Esto es necesario para bypasear woocommerce_shipping_cost_requires_address.
        add_filter('woocommerce_customer_get_shipping_country', [$this, 'filterCustomerCountry']);
        add_filter('woocommerce_customer_get_shipping_state',   [$this, 'filterCustomerState']);
        add_filter('woocommerce_customer_get_shipping_city',    [$this, 'filterCustomerCity']);

        // Override del paquete de calculo.
        add_filter('woocommerce_cart_shipping_packages', [$this, 'overridePackageDestination']);

        // Bloquea y pre-rellena los campos del formulario de checkout.
        add_filter('woocommerce_checkout_fields', [$this, 'lockCheckoutFields']);
        add_filter('woocommerce_checkout_get_value', [$this, 'prefillCheckoutFields'], 10, 2);

        // Agrega inputs ocultos para que los selects deshabilitados se envien igual.
        add_action('woocommerce_checkout_after_customer_details', [$this, 'outputHiddenFields']);

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

    /**
     * Bloquea los campos de region y ciudad para que el usuario no pueda
     * cambiar la ubicacion en el checkout (debe hacerlo desde el modal).
     */
    public function lockCheckoutFields(array $fields): array {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return $fields;
        }

        if (isset($fields['shipping']['shipping_state'])) {
            $fields['shipping']['shipping_state']['custom_attributes']['disabled'] = 'disabled';
            $fields['shipping']['shipping_state']['description'] = 'Segun la bodega seleccionada.';
        }

        if (isset($fields['shipping']['shipping_city'])) {
            $fields['shipping']['shipping_city']['custom_attributes']['disabled'] = 'disabled';
            $fields['shipping']['shipping_city']['description'] = 'Segun la bodega seleccionada.';
        }

        if (isset($fields['shipping']['shipping_country'])) {
            $fields['shipping']['shipping_country']['custom_attributes']['disabled'] = 'disabled';
        }

        return $fields;
    }

    /**
     * Pre-rellena los campos de envio con los valores del cookie.
     */
    public function prefillCheckoutFields($value, string $input) {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return $value;
        }

        switch ($input) {
            case 'shipping_country':
                return 'CL';
            case 'shipping_state':
                return $location['region_id'];
            case 'shipping_city':
                return $location['comuna_name'];
        }

        return $value;
    }

    /**
     * Los campos con disabled no se envian en el POST. Agrega inputs ocultos
     * para asegurar que region y pais lleguen al servidor.
     */
    public function outputHiddenFields(): void {
        $location = $this->getSelectedLocation();
        if (empty($location)) {
            return;
        }
        ?>
        <input type="hidden" name="shipping_country" value="CL">
        <input type="hidden" name="shipping_state" value="<?php echo esc_attr($location['region_id']); ?>">
        <input type="hidden" name="shipping_city" value="<?php echo esc_attr($location['comuna_name']); ?>">
        <?php
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
            function lockLocationFields() {
                ['#shipping_city', '#shipping_state', '#shipping_country'].forEach(function(id) {
                    var $field = $(id);
                    if (!$field.length) return;

                    $field.prop('disabled', true);

                    $field.closest('.form-row')
                          .find('.select2-container')
                          .css({'pointer-events': 'none', 'opacity': '0.65'});
                });
            }

            $(document).ready(lockLocationFields);
            $(document.body).on('updated_checkout', lockLocationFields);
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
