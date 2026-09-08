<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agrega el boton "Pedir de nuevo" a la tabla de pedidos de Mi Cuenta.
 * La logica de vaciar/rellenar el carrito vive en OrderReorderAjaxHandler;
 * aqui solo se agrega la accion a la tabla y se encolan los assets.
 */
class OrderReorder {

    public function __construct() {
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'addReorderAction'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addReorderAction(array $actions, $order): array {
        if (!$order instanceof \WC_Order) {
            return $actions;
        }

        unset($actions['order-again']);

        $actions['reorder'] = [
            'url'        => '#sm-reorder-' . $order->get_id(),
            'name'       => 'Pedir de nuevo',
            'aria-label' => sprintf('Pedir de nuevo el pedido %s', $order->get_order_number()),
        ];

        return $actions;
    }

    public function enqueueAssets(): void {
        if (is_admin() || !is_account_page() || !is_wc_endpoint_url('orders')) {
            return;
        }

        $plugin_dir = SOCOMARCA_ERP_PLUGIN_DIR;
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;

        wp_enqueue_style(
            'sm-order-reorder',
            $plugin_url . 'assets/css/order-reorder.css',
            [],
            filemtime($plugin_dir . 'assets/css/order-reorder.css')
        );

        wp_enqueue_script(
            'sm-order-reorder',
            $plugin_url . 'assets/js/order-reorder.js',
            ['jquery', 'sm-location-popup'],
            filemtime($plugin_dir . 'assets/js/order-reorder.js'),
            true
        );
    }
}
