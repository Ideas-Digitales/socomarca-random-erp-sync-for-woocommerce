<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Valida el stock de un producto en una bodega específica.
 * Proporciona información al frontend para desactivar la selección automática
 * y mostrar "Sin Stock" si no hay disponibilidad.
 */
class ProductStockValidator {

    public function __construct() {
        add_action('wp_footer', [$this, 'renderStockValidator'], 99);
    }

    /**
     * Renderiza el validador de stock inline en el footer
     */
    public function renderStockValidator() {
        if (!is_product()) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        // Para productos variables sin variación seleccionada, no hacer check de stock
        // El stock se validará cuando se seleccione una variación específica
        if ($product->get_type() === 'variable') {
            error_log('[SM-VALIDATOR-PHP] Product ' . $product->get_id() . ' is variable, skipping stock check on parent');
            return;
        }

        $location_id = $this->getSelectedLocation();
        $product_id = $product->get_id();
        
        // Si no hay ubicación, no podemos validar stock, así que asumimos "desconocido" pero no bloqueamos de forma permanente
        if (!$location_id) {
            $has_stock = 'null';
            $stock_qty = 0;
            error_log('[SM-VALIDATOR-PHP] No location selected for product ' . $product_id);
        } else {
            $stock_info = $this->getProductStockInfo($product_id, $location_id);
            $has_stock = $stock_info['has_stock'] ? 'true' : 'false';
            $stock_qty = $stock_info['stock_qty'];
            error_log('[SM-VALIDATOR-PHP] Rendering: product_id=' . $product_id . ', location_id=' . $location_id . ', has_stock=' . $has_stock . ', stock_qty=' . $stock_qty);
        }

        ?>
        <script type="text/javascript">
        // Variable global para que location-stock-popup.js pueda acceder
        window.smProductHasStock = <?php echo $has_stock; ?>;
        window.smProductId = <?php echo $product_id; ?>;
        window.smSelectedLocation = <?php echo $location_id; ?>;
        console.log('[SM-VALIDATOR-RENDER] Page rendering with location_id=' + window.smSelectedLocation + ', has_stock=' + window.smProductHasStock);

        (function ($) {
            $(document).ready(function () {
                console.log('[SM-VALIDATOR] Iniciando validador de stock');

                var hasStock = <?php echo $has_stock; ?>;
                var locationId = <?php echo $location_id; ?>;

                // Sin bodega seleccionada se trata igual que sin stock: no se
                // puede confirmar disponibilidad, asi que se bloquea el
                // agregar al carrito y se pide elegir una ubicacion.
                if (!locationId || hasStock === false) {
                    console.log('[SM-VALIDATOR] Producto sin stock en ubicación seleccionada, deshabilitando carrito');

                    // Ocultar el precio y el mensaje de stock disponible
                    //$('p.stock').hide();
                    //$('.price').hide();

                    $(".stock.in-stock").hide();
                    //$(".sm-product-extra-meta").hide();
                    //$("form.cart").hide();
                    //$(".stock.in-stock").text('Sin existencias').css('color', '#a00').show();
                    

                    // Cambiar botón de compra
                    var $form = $('form.cart');
                    if ($form.length) {
                        var $addToCartBtn = $form.find('button[name="add-to-cart"], button.single_add_to_cart_button, a.single_add_to_cart_button');

                        if ($addToCartBtn.length) {
                            $addToCartBtn
                                .removeClass('button alt')
                                .addClass('disabled sm-out-of-stock')
                                .prop('disabled', true)
                                .html('Sin Stock')
                                .off('click')
                                .on('click', function (e) {
                                    e.preventDefault();
                                    return false;
                                });

                            console.log('[SM-VALIDATOR] Botón deshabilitado');
                        }
                    }
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Obtiene la información de stock del producto en una bodega
     */
    private function getProductStockInfo($product_id, $location_id) {
        if (!$location_id) {
            error_log('[SM-VALIDATOR-STOCK] No location_id provided');
            return ['has_stock' => false, 'stock_qty' => 0];
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return ['has_stock' => false, 'stock_qty' => 0];
        }

        $price = $product->get_price();
        if ($price === '' || $price === null || (float) $price <= 0) {
            error_log('[SM-VALIDATOR-STOCK] Product ' . $product_id . ' has zero/invalid price, treating as out of stock');
            return ['has_stock' => false, 'stock_qty' => 0];
        }

        $meta_key = 'wcmlim_stock_at_' . $location_id;
        $stock_qty = (int) get_post_meta($product_id, $meta_key, true);
        $raw_value = get_post_meta($product_id, $meta_key, true);

        error_log('[SM-VALIDATOR-STOCK] Product ' . $product_id . ', location_id=' . $location_id . ', meta_key=' . $meta_key . ', raw_value=' . var_export($raw_value, true) . ', converted_stock=' . $stock_qty);

        return [
            'has_stock' => $stock_qty > 0,
            'stock_qty' => $stock_qty,
        ];
    }

    /**
     * Obtiene la ubicación seleccionada desde sesión o cookie
     */
    private function getSelectedLocation() {
        if (isset($_SESSION['multiloca_selected_location_id'])) {
            return (int) $_SESSION['multiloca_selected_location_id'];
        }

        if (isset($_COOKIE['sm_selected_location'])) {
            $data = json_decode(stripslashes($_COOKIE['sm_selected_location']), true);
            return isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : 0;
        }

        return 0;
    }
}
