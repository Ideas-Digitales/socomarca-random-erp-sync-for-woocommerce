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
        add_action('woocommerce_single_product_summary', [$this, 'renderFallbackButtonIfMissing'], 31);
    }

    /**
     * Cuando el producto no tiene stock en NINGUNA bodega (is_in_stock() falso
     * de forma legitima, no por un desfase de bodega), WooCommerce ni siquiera
     * imprime <form class="cart"> -- ver woocommerce_template_single_add_to_cart()
     * (prioridad 30 en el mismo hook) y templates/single-product/add-to-cart/simple.php,
     * que retorna temprano si !is_in_stock(). Sin boton en el DOM, el JS de
     * renderStockValidator() no tiene nada que deshabilitar, y StockBadgeCustomizer
     * tampoco imprime texto (renderOutOfStockHtml() devuelve ''), asi que la
     * pagina queda completamente en blanco donde deberia estar el boton.
     *
     * Aqui se imprime, a proposito, el mismo boton deshabilitado "Sin Stock"
     * (misma clase sm-out-of-stock) que usan los productos que SI tienen
     * <form class="cart"> pero sin stock en la bodega seleccionada, para que
     * el diseño sea uno solo sin importar la causa de la falta de stock.
     *
     * Se limita a productos simples: los productos variables tienen su propio
     * diseño de "sin stock" (ver variations-helper.js::initAddToCartGating()
     * y ProductPageCustomizer::displayProductExtraMeta()), documentado pero
     * intencionalmente no tocado aqui -- no hay ningun producto variable en el
     * catalogo actual para verificar un cambio contra el sitio real.
     */
    public function renderFallbackButtonIfMissing(): void {
        global $product;

        if (!$product instanceof \WC_Product || $product->get_type() !== 'simple') {
            return;
        }

        // Si no es purchasable por otra razon (precio invalido, regla de
        // B2BKing, etc.), no es un tema de stock: no mostrar "Sin Stock" para
        // no dar un mensaje enganoso sobre la causa real.
        if (!$product->is_purchasable()) {
            return;
        }

        // El form.cart real ya se imprimio en la prioridad 30 de este mismo hook.
        if ($product->is_in_stock()) {
            return;
        }

        ?>
        <form class="cart" action="<?php echo esc_url($product->get_permalink()); ?>" method="post" enctype="multipart/form-data">
            <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button disabled sm-out-of-stock" disabled="disabled">Sin Stock</button>
        </form>
        <?php
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

        // Estado AGREGADO de WooCommerce (independiente de la bodega seleccionada).
        // Si is_in_stock() es false, WooCommerce ni siquiera imprime <form class="cart">
        // (ver templates/single-product/add-to-cart/simple.php), asi que este
        // script nunca llega a encontrar el boton para deshabilitarlo: el boton
        // simplemente nunca existio en el DOM. Esto distingue ese caso ("bug de
        // agregado de stock") del caso en que el boton SI se imprime y es este
        // mismo script el que lo deshabilita mas abajo.
        $wc_stock_status = $product->get_stock_status();
        $wc_stock_qty    = $product->get_stock_quantity();
        $wc_is_in_stock  = $product->is_in_stock() ? 'true' : 'false';
        $wc_is_purchasable = $product->is_purchasable() ? 'true' : 'false';
        error_log(sprintf(
            '[SM-VALIDATOR-PHP-WC] product_id=%d, wc_stock_status=%s, wc_stock_qty=%s, wc_is_in_stock=%s, wc_is_purchasable=%s',
            $product_id,
            $wc_stock_status,
            var_export($wc_stock_qty, true),
            $wc_is_in_stock,
            $wc_is_purchasable
        ));

        ?>
        <script type="text/javascript">
        // Variable global para que location-stock-popup.js pueda acceder
        window.smProductHasStock = <?php echo $has_stock; ?>;
        window.smProductId = <?php echo $product_id; ?>;
        window.smSelectedLocation = <?php echo $location_id; ?>;
        // Estado agregado de WooCommerce (_stock_status), independiente de la
        // bodega seleccionada. Si wcIsInStock es false, form.cart nunca se
        // imprimio en el HTML (no es este script deshabilitando el boton).
        window.smWcStockStatus = <?php echo wp_json_encode($wc_stock_status); ?>;
        window.smWcStockQty = <?php echo wp_json_encode($wc_stock_qty); ?>;
        window.smWcIsInStock = <?php echo $wc_is_in_stock; ?>;
        window.smWcIsPurchasable = <?php echo $wc_is_purchasable; ?>;
        console.log('[SM-VALIDATOR-RENDER] Page rendering with location_id=' + window.smSelectedLocation + ', has_stock=' + window.smProductHasStock);
        console.log('[SM-VALIDATOR-RENDER-WC] wc_stock_status=' + window.smWcStockStatus + ', wc_stock_qty=' + window.smWcStockQty + ', wc_is_in_stock=' + window.smWcIsInStock + ', wc_is_purchasable=' + window.smWcIsPurchasable);
        console.log('[SM-VALIDATOR-RENDER-DOM] form.cart existe en el DOM: ' + (jQuery('form.cart').length > 0) + ' (si es false, el boton nunca se imprimio; WooCommerce lo omitio en el template por _stock_status=' + window.smWcStockStatus + ')');

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
