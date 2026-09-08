<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Guarda en el pedido la bodega con la que se genero (id, nombre y codigo
 * ERP), para poder recuperarla despues de forma confiable (por ejemplo, al
 * usar "Pedir de nuevo"). Antes esta informacion solo quedaba en la meta de
 * cada linea del carrito (_multiloca_location_id), que no siempre se llena
 * si el item se agrego al carrito antes de fijar la sesion de ubicacion.
 */
class OrderWarehouseMeta {

    public function __construct() {
        add_action('woocommerce_checkout_create_order', [$this, 'saveWarehouseToOrder'], 20, 2);
    }

    public function saveWarehouseToOrder(\WC_Order $order, array $data): void {
        $warehouse_id = $this->resolveWarehouseId();
        if (!$warehouse_id) {
            return;
        }

        $term           = get_term($warehouse_id, 'locations');
        $warehouse_name = ($term && !is_wp_error($term)) ? $term->name : '';
        $warehouse_code = $warehouse_name !== '' ? get_term_meta($warehouse_id, 'random_erp_warehouse_code', true) : '';

        $order->update_meta_data('_sm_order_warehouse_id', $warehouse_id);
        $order->update_meta_data('_sm_order_warehouse_code', $warehouse_code);
        $order->update_meta_data('_sm_order_warehouse_name', $warehouse_name);
    }

    /**
     * Determina la bodega usada en este checkout: primero los items del
     * carrito (es lo que realmente se valido contra el stock), luego la
     * sesion, luego la bodega de retiro en tienda enviada en el POST y por
     * ultimo la cookie sm_selected_location del frontend (unica fuente que
     * siempre se escribe al elegir ubicacion, incluso si el producto se
     * agrego al carrito antes de fijar la sesion).
     */
    private function resolveWarehouseId(): int {
        $cart = WC()->cart;
        if ($cart && !$cart->is_empty()) {
            foreach ($cart->get_cart() as $cart_item) {
                if (!empty($cart_item['multiloca_location_id'])) {
                    return (int) $cart_item['multiloca_location_id'];
                }
            }
        }

        if (!empty($_SESSION['multiloca_selected_location_id'])) {
            return (int) $_SESSION['multiloca_selected_location_id'];
        }

        if (!empty($_POST['sm_pickup_store_id'])) {
            return (int) sanitize_text_field(wp_unslash($_POST['sm_pickup_store_id']));
        }

        return $this->warehouseIdFromCookie();
    }

    /**
     * Lee el warehouse_id de la cookie sm_selected_location, que guarda el
     * popup de ubicacion del frontend (misma forma que en ProductStockValidator
     * y CartWarehouseSwitchHandler).
     */
    private function warehouseIdFromCookie(): int {
        if (empty($_COOKIE['sm_selected_location'])) {
            return 0;
        }

        $raw  = wp_unslash($_COOKIE['sm_selected_location']);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = json_decode(rawurldecode($raw), true);
        }

        return (is_array($data) && !empty($data['warehouse_id'])) ? (int) $data['warehouse_id'] : 0;
    }
}
