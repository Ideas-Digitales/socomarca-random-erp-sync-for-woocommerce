<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sobreescribe get_stock_quantity() de WooCommerce para retornar el stock
 * de la bodega seleccionada en sesion (multiloca_selected_location_id).
 *
 * Sin este filtro, WooCommerce usa _stock (que es el total de todas las bodegas
 * o el de la ultima bodega sincronizada), lo que provoca que su propia validacion
 * de carrito bloquee agregar productos aunque la bodega seleccionada tenga stock.
 *
 * Se omite en admin, REST y cron para no interferir con operaciones internas.
 */
class LocationStockFilter {

    public function __construct() {
        add_filter('woocommerce_product_get_stock_quantity',   [$this, 'byLocation'], 10, 2);
        add_filter('woocommerce_variation_get_stock_quantity', [$this, 'byLocation'], 10, 2);
    }

    public function byLocation($quantity, \WC_Product $product) {
        if ($this->shouldSkip()) {
            return $quantity;
        }

        $location_id = intval($_SESSION['multiloca_selected_location_id'] ?? 0);
        if (!$location_id) {
            return $quantity;
        }

        $meta = get_post_meta($product->get_id(), 'wcmlim_stock_at_' . $location_id, true);
        if ($meta === '') {
            return $quantity;
        }

        return (int) $meta;
    }

    private function shouldSkip(): bool {
        // is_admin() devuelve true para admin-ajax.php, pero ahi es exactamente donde
        // WooCommerce procesa el add-to-cart. Solo omitir en paginas de admin reales.
        if (is_admin() && !wp_doing_ajax()) {
            return true;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }
        return false;
    }
}
