<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reemplaza el HTML de disponibilidad de stock que genera WooCommerce
 * (<p class="stock out-of-stock">Sin existencias</p>) sin tocar los
 * templates del plugin de WooCommerce, via el filtro publico
 * 'woocommerce_get_stock_html' (usado por wc_get_stock_html(), tanto en
 * la pagina de producto como en el loop de la tienda).
 *
 * El _stock nativo de WooCommerce es la suma de todas las bodegas (ver
 * StockService::setVariationStock), por lo que no sirve para decidir que
 * mostrar aqui. En su lugar se usa el stock de la bodega actualmente
 * seleccionada (wcmlim_stock_at_{bodega}):
 *   - Sin bodega seleccionada, o sin stock en la bodega seleccionada: diseño
 *     de "sin stock".
 *   - Con stock en la bodega seleccionada: se muestra ese stock real, no el
 *     total agregado.
 */
class StockBadgeCustomizer {

    public function __construct() {
        add_filter('woocommerce_get_stock_html', [$this, 'customizeOutOfStockHtml'], 10, 2);
    }

    public function customizeOutOfStockHtml($html, $product) {
        $warehouse_id = $this->getSelectedWarehouseId();

        if (!$warehouse_id) {
            return $this->renderOutOfStockHtml($product);
        }

        $stock_post_id = $product->get_id();
        $stock = intval(get_post_meta($stock_post_id, 'wcmlim_stock_at_' . $warehouse_id, true));
        $avail = get_post_meta($stock_post_id, 'wcmlim_product_availability_at_' . $warehouse_id, true);

        if ($avail !== 'yes' || $stock <= 0) {
            return $this->renderOutOfStockHtml($product);
        }

        return '<p class="stock in-stock">' . esc_html($stock) . ' disponibles</p>';
    }

    /**
     * Sin contenido: el SKU y las categorias ya los muestra
     * ProductPageCustomizer::displayProductExtraMeta() (hook
     * woocommerce_before_add_to_cart_form) y el boton real de "Sin Stock"
     * ya lo deja ProductStockValidator sobre el form.cart -- repetirlos aqui
     * duplicaba ambos bloques en la pagina. El diseño original no mostraba
     * ningun texto adicional cuando no hay stock en la bodega seleccionada.
     */
    private function renderOutOfStockHtml($product): string {
        return '';
    }

    /**
     * Obtiene la bodega actualmente seleccionada desde sesion o cookie.
     */
    private function getSelectedWarehouseId(): int {
        if (!empty($_SESSION['multiloca_selected_location_id'])) {
            return (int) $_SESSION['multiloca_selected_location_id'];
        }

        if (isset($_COOKIE['sm_selected_location'])) {
            $data = json_decode(stripslashes($_COOKIE['sm_selected_location']), true);
            if (isset($data['warehouse_id']) && $data['warehouse_id']) {
                return (int) $data['warehouse_id'];
            }
        }

        return 0;
    }
}
