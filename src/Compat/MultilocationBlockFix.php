<?php

namespace Socomarca\RandomERP\Compat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inyecta el inventario de Multiloca Lite en temas FSE (Full Site Editing).
 *
 * En temas de bloques el hook `woocommerce_after_add_to_cart_form` no recibe
 * `global $product` correctamente, por lo que la tabla de ubicaciones del plugin
 * multiloca-lite no se renderiza. Esta clase intercepta el bloque
 * `woocommerce/add-to-cart-form` y agrega el inventario al final del HTML.
 *
 * Para productos variables con una sola variacion, muestra el stock de esa
 * variacion directamente. Para productos con multiples variaciones, muestra
 * la tabla inicial con el mensaje de seleccion, que se actualiza via AJAX
 * cuando el usuario elige una variacion.
 */
class MultilocationBlockFix {

    public function __construct() {
        add_filter('render_block_woocommerce/add-to-cart-form', [$this, 'appendInventory'], 10, 2);
        add_action('wp_footer', [$this, 'printLocationClickHandler']);
    }

    /**
     * Inyecta el JS que maneja el click en las filas de nuestra tabla sm-location-table.
     * Replica el comportamiento del selectLocation() de multiloca-lite-public.js.
     */
    public function printLocationClickHandler(): void {
        if (!is_product()) {
            return;
        }
        ?>
        <script>
        (function ($) {
            $(document).on('click', '.sm-location-table tbody tr', function (e) {
                e.preventDefault();

                if ($(this).hasClass('out-of-stock')) {
                    return;
                }

                var locationId   = $(this).data('location-id');
                var locationName = $(this).data('location-name');

                if (!locationId || !locationName) {
                    return;
                }

                var nonce = (typeof multiloca_lite !== 'undefined') ? multiloca_lite.nonce : '';
                var ajaxUrl = (typeof multiloca_lite !== 'undefined') ? multiloca_lite.ajax_url : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

                $.ajax({
                    url:  ajaxUrl,
                    type: 'POST',
                    data: {
                        action:        'select_location',
                        location_id:   locationId,
                        location_name: locationName,
                        nonce:         nonce,
                    },
                    success: function (response) {
                        if (response.success) {
                            $('.sm-location-table tbody tr').removeClass('multiloca-location-selected');
                            $('.sm-location-table tbody tr[data-location-id="' + locationId + '"]').addClass('multiloca-location-selected');
                            $('.sm-location-selected-info').remove();
                            $('.sm-location-inventory h3').after(
                                '<div class="sm-location-selected-info" style="font-size:.9em;margin:.4em 0 .8em;">' +
                                '<strong>Ubicacion seleccionada:</strong> ' + $('<span>').text(locationName).html() +
                                '</div>'
                            );
                        }
                    },
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public function appendInventory(string $blockContent, array $block): string {
        if (!class_exists('Multiloca_Lite_Public')) {
            return $blockContent;
        }

        $productId = $this->resolveProductId($block);
        if (!$productId) {
            return $blockContent;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return $blockContent;
        }

        // Para productos variables con una sola variacion, renderizar stock directamente
        // sin depender del JS de multiloca (que sobreescribe la tabla al seleccionar variacion)
        if ($product->is_type('variable')) {
            $children = $product->get_children();
            if (count($children) === 1) {
                $variationId = (int) $children[0];
                $variation   = wc_get_product($variationId);
                if ($variation) {
                    $inventory = $this->renderSingleVariationInventory($variation, $product->get_id());
                    if (!empty($inventory)) {
                        return $blockContent . $inventory;
                    }
                }
            }
        }

        // Setear el global que usa multiloca
        $GLOBALS['product'] = $product;

        $inventory = $this->renderInventory($product);
        if (empty($inventory)) {
            return $blockContent;
        }

        return $blockContent . $inventory;
    }

    private function resolveProductId(array $block): int {
        if (!empty($block['attrs']['postId'])) {
            return (int) $block['attrs']['postId'];
        }

        $id = get_the_ID();
        return $id ? (int) $id : 0;
    }

    /**
     * Renderiza la tabla de stock para una sola variacion usando clases propias
     * (no las de multiloca-lite) para evitar que el JS de multiloca sobreescriba
     * la tabla cuando el usuario selecciona la variacion en el formulario.
     * Las filas tienen data-location-id/name para que el click-to-select de
     * multiloca siga funcionando.
     */
    private function renderSingleVariationInventory(\WC_Product_Variation $variation, int $parentId): string {
        $locationsLinked = wp_get_object_terms($parentId, 'locations', ['fields' => 'ids']);
        if (empty($locationsLinked)) {
            return '';
        }

        $locations = get_terms([
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'include'    => $locationsLinked,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (empty($locations) || is_wp_error($locations)) {
            return '';
        }

        $headingText   = get_option('wcmlim_txt_in_fdiv', __('Location Availability', 'multiloca-lite-multi-location-inventory'));
        $instockText   = get_option('wcmlim_txt_in_btn_instock', __('In Stock', 'multiloca-lite-multi-location-inventory'));
        $outofstockText = get_option('wcmlim_txt_in_btn_outofstock', __('Out of Stock', 'multiloca-lite-multi-location-inventory'));
        $variationId   = $variation->get_id();

        ob_start();
        // Usar clase sm-location-inventory (NO multiloca-lite-inventory) para que el JS
        // de multiloca no detecte este div como su tabla y no la sobreescriba.
        // Las filas tienen data-location-id/name para que el click handler de multiloca funcione.
        echo '<div class="sm-location-inventory">';
        echo '<h3>' . esc_html($headingText) . '</h3>';
        echo '<table class="sm-location-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Location', 'multiloca-lite-multi-location-inventory') . '</th>';
        echo '<th>' . esc_html__('Stock', 'multiloca-lite-multi-location-inventory') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($locations as $location) {
            $stock      = (int) get_post_meta($variationId, 'wcmlim_stock_at_' . $location->term_id, true);
            $stockClass = $stock > 0 ? 'in-stock' : 'out-of-stock';
            $stockText  = $stock > 0
                ? '<span class="stock in-stock">' . $stock . ' ' . esc_html($instockText) . '</span>'
                : '<span class="stock out-of-stock">' . esc_html($outofstockText) . '</span>';

            echo '<tr class="' . esc_attr($stockClass) . '" data-location-id="' . esc_attr($location->term_id) . '" data-location-name="' . esc_attr($location->name) . '">';
            echo '<td>' . esc_html($location->name) . '</td>';
            echo '<td>' . wp_kses_post($stockText) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        return ob_get_clean();
    }

    private function renderInventory(\WC_Product $product): string {
        $locations = get_terms([
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (empty($locations) || is_wp_error($locations)) {
            return '';
        }

        $viewFile = WP_PLUGIN_DIR . '/multiloca-lite-multi-location-inventory/public/controller/shop/views/multiloca-table-view.php';
        if (!file_exists($viewFile)) {
            return '';
        }

        ob_start();
        include $viewFile;
        return ob_get_clean();
    }
}
