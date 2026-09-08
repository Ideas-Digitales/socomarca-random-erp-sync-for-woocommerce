<?php

namespace Socomarca\RandomERP\Compat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rehidrata la ubicacion seleccionada en la sesion PHP de Multiloca Lite.
 *
 * Multiloca Lite guarda la bodega elegida SOLO en $_SESSION
 * ('multiloca_selected_location_id' / '_name'). Esa sesion PHP muere al cerrar
 * el navegador y expira en el servidor tras el gc_maxlifetime (por defecto
 * ~24 min de inactividad).
 *
 * Nuestro popup de ubicacion guarda la seleccion en la cookie
 * `sm_selected_location` (30 dias). Cuando la sesion PHP ya expiro pero la
 * cookie sigue viva, la UI muestra la bodega ("BODEGA CAMINO INTERNACIONAL")
 * pero `validate_cart_location()` de Multiloca ve la sesion vacia y bloquea el
 * agregar al carrito con "Please select a location before adding to cart".
 *
 * Esta clase toma la cookie como fuente de verdad: en cada request de frontend,
 * si la sesion PHP no tiene la bodega, la repuebla desde la cookie antes de que
 * corra la validacion de add-to-cart (hook `woocommerce_add_to_cart_validation`
 * durante `wp_loaded`).
 */
class MultilocaSessionRehydrate {

    public function __construct() {
        // init @ 20: despues del start_session() de Multiloca (init @ 10) y
        // muy antes del procesamiento de add-to-cart de WooCommerce (wp_loaded @ 20).
        add_action('init', [$this, 'rehydrate'], 20);
    }

    public function rehydrate(): void {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        // Si la sesion PHP ya tiene una bodega valida, no hay nada que hacer.
        if (!empty($_SESSION['multiloca_selected_location_id'])) {
            return;
        }

        $data = $this->parseCookie();
        if (!$data) {
            return;
        }

        $warehouse_id = (int) ($data['warehouse_id'] ?? 0);
        if ($warehouse_id <= 0) {
            return;
        }

        // Nombre: usar el de la comuna (igual que hace el popup y el switch de
        // bodega) para que la key del item de carrito sea consistente.
        $location_name = sanitize_text_field($data['comuna_name'] ?? '');
        if ($location_name === '') {
            $term = get_term($warehouse_id, 'locations');
            $location_name = ($term && !is_wp_error($term)) ? $term->name : '';
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['multiloca_selected_location_id']   = $warehouse_id;
        $_SESSION['multiloca_selected_location_name'] = $location_name;
    }

    /**
     * Lee la cookie sm_selected_location que escribe el popup de ubicacion.
     */
    private function parseCookie(): ?array {
        if (empty($_COOKIE['sm_selected_location'])) {
            return null;
        }

        $raw  = wp_unslash($_COOKIE['sm_selected_location']);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = json_decode(rawurldecode($raw), true);
        }

        return is_array($data) ? $data : null;
    }
}
