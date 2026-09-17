<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sobreescribe get_stock_quantity() y get_stock_status() de WooCommerce para
 * que reflejen el stock de la bodega seleccionada en sesion
 * (multiloca_selected_location_id) o cookie (sm_selected_location).
 *
 * Sin el filtro de cantidad, WooCommerce usa _stock (que es el total de todas
 * las bodegas o el de la ultima bodega sincronizada), lo que provoca que su
 * propia validacion de carrito bloquee agregar productos aunque la bodega
 * seleccionada tenga stock.
 *
 * Sin el filtro de status, _stock_status (que multiloca-lite y StockService
 * recalculan de forma independiente y pueden dejar desincronizado del total
 * real de bodegas vinculadas, ver wcmlim_calculate_and_update_total_stock() y
 * StockService::sumLocationStock()) puede quedar en "outofstock" aunque la
 * bodega seleccionada SI tenga stock. Como WooCommerce solo imprime el
 * <form class="cart"> (boton "Añadir al carrito") cuando is_in_stock() es
 * true -- que a su vez solo mira get_stock_status(), no get_stock_quantity()
 * -- ese desfase hace que el boton nunca llegue a existir en el HTML, sin
 * importar que la cantidad ya se muestre correcta en otros lados (badge de
 * stock, validador JS, etc).
 *
 * Se omite en admin, REST y cron para no interferir con operaciones internas.
 */
class LocationStockFilter {

    public function __construct() {
        add_filter('woocommerce_product_get_stock_quantity',   [$this, 'quantityByLocation'], 10, 2);
        add_filter('woocommerce_variation_get_stock_quantity', [$this, 'quantityByLocation'], 10, 2);
        add_filter('woocommerce_product_get_stock_status',     [$this, 'statusByLocation'], 10, 2);
        add_filter('woocommerce_variation_get_stock_status',   [$this, 'statusByLocation'], 10, 2);
    }

    public function quantityByLocation($quantity, \WC_Product $product) {
        if ($this->shouldSkip()) {
            return $quantity;
        }

        $meta = $this->getLocationStockMeta($product);
        if ($meta === null) {
            return $quantity;
        }

        return (int) $meta;
    }

    public function statusByLocation($status, \WC_Product $product) {
        if ($this->shouldSkip()) {
            return $status;
        }

        $meta = $this->getLocationStockMeta($product);
        if ($meta === null) {
            return $status;
        }

        // Si la bodega seleccionada SI tiene stock, forzar 'instock' aunque el
        // _stock_status agregado (recalculado por separado por multiloca-lite
        // y StockService) haya quedado desincronizado en 'outofstock'.
        if ((int) $meta > 0) {
            return 'instock';
        }

        // Si la bodega seleccionada tiene 0 pero el producto SI tiene stock en
        // otras bodegas vinculadas, no forzar 'outofstock' aqui: eso haria que
        // WooCommerce omita <form class="cart"> por completo (is_in_stock()
        // falso), dejando la pagina sin boton alguno. En cambio se deja pasar
        // el status nativo (tipicamente 'instock', por el stock de esas otras
        // bodegas) para que el form SI se imprima y sea ProductStockValidator
        // (via wcmlim_stock_at_{bodega}=0) el que lo deshabilite mostrando
        // "Sin Stock" -- el mismo diseño que ya se usa cuando el producto ni
        // siquiera esta vinculado a la bodega seleccionada.
        return $status;
    }

    /**
     * Lee wcmlim_stock_at_{bodega} para la bodega actualmente seleccionada.
     * Devuelve null cuando no hay bodega seleccionada o el producto no tiene
     * meta para esa bodega (caso en que se debe dejar el valor nativo tal cual).
     */
    private function getLocationStockMeta(\WC_Product $product): ?string {
        $location_id = 0;

        // Prioridad 1: Sesión (Multiloca)
        if (isset($_SESSION['multiloca_selected_location_id'])) {
            $location_id = (int) $_SESSION['multiloca_selected_location_id'];
        }
        // Prioridad 2: Cookie (Socomarca)
        elseif (isset($_COOKIE['sm_selected_location'])) {
            $data = json_decode(stripslashes($_COOKIE['sm_selected_location']), true);
            if (isset($data['warehouse_id'])) {
                $location_id = (int) $data['warehouse_id'];
            }
        }

        if (!$location_id) {
            return null;
        }

        $meta = get_post_meta($product->get_id(), 'wcmlim_stock_at_' . $location_id, true);
        if ($meta === '') {
            return null;
        }

        return $meta;
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
