<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class ProductPageCustomizer {

    public function __construct() {
        // Categoria hoja encima del titulo (prioridad 4, el titulo es 5)
        add_action('woocommerce_single_product_summary', [$this, 'displayLeafCategory'], 4);

        // Meta (Stock, SKU, Categorias) antes del formulario de carrito
        add_action('woocommerce_before_add_to_cart_form', [$this, 'displayProductExtraMeta']);

        // Quitar el meta default de WooCommerce (SKU/categorias) para evitar duplicados
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);

        // Ocultar tab "Informacion adicional"
        add_filter('woocommerce_product_tabs', [$this, 'removeAdditionalInformationTab'], 20);

        // Reemplazar productos relacionados por slider personalizado
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
        add_action('woocommerce_after_single_product', [$this, 'displayRelatedProductsSlider'], 10);
    }

    public function removeAdditionalInformationTab(array $tabs): array {
        unset($tabs['additional_information']);
        return $tabs;
    }

    public function displayRelatedProductsSlider(): void {
        global $product;

        if (!$product) {
            return;
        }

        $related_ids = wc_get_related_products($product->get_id(), 12);

        if (empty($related_ids)) {
            return;
        }

        $related_products = array_filter(array_map('wc_get_product', $related_ids));

        if (empty($related_products)) {
            return;
        }

        ?>
        <section class="sm-related-products">
            <div class="sm-related-header">
                <span class="sm-related-bar"></span>
                <h2 class="sm-related-title">Productos relacionados</h2>
            </div>
            <div class="sm-related-slider" id="sm-related-slider">
                <?php foreach ($related_products as $related): ?>
                    <?php
                    $image = get_the_post_thumbnail_url($related->get_id(), 'medium');
                    $link  = get_permalink($related->get_id());
                    $name  = $related->get_name();
                    $price = $related->get_price_html();
                    $id    = $related->get_id();
                    $sku   = $related->get_sku();
                    $type  = $related->get_type();
                    ?>
                    <div class="sm-related-item">
                        <a href="<?php echo esc_url($link); ?>" class="sm-related-image">
                            <?php if ($image): ?>
                                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy">
                            <?php else: ?>
                                <img src="<?php echo esc_url(wc_placeholder_img_src()); ?>" alt="<?php echo esc_attr($name); ?>">
                            <?php endif; ?>
                        </a>
                        <div class="sm-related-info">
                            <h2 class="sm-related-name"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($name); ?></a></h2>
                            <div class="sm-related-price"><?php echo $price; ?></div>
                        </div>
                        <div class="sm-related-action">
                            <?php if ($type === 'simple' && $related->is_purchasable() && $related->is_in_stock()): ?>
                                <a href="<?php echo esc_url($related->add_to_cart_url()); ?>"
                                   data-quantity="1"
                                   data-product_id="<?php echo esc_attr($id); ?>"
                                   data-product_sku="<?php echo esc_attr($sku); ?>"
                                   class="sm-related-buy button add_to_cart_button ajax_add_to_cart">
                                    COMPRAR
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url($link); ?>" class="sm-related-buy button">
                                    VER PRODUCTO
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="sm-related-arrow sm-related-prev" aria-label="Anterior">&#8249;</button>
            <button class="sm-related-arrow sm-related-next" aria-label="Siguiente">&#8250;</button>
        </section>
        <?php
    }

    public function displayProductExtraMeta(): void {
        global $product;

        if (!$product) {
            return;
        }

        $sku        = $product->get_sku();
        $categories = $this->resolveCategoryList($product);
        $location_stock = $this->getLocationStock($product);

        // Para productos variables, no mostrar stock del padre (el stock se mostrará cuando se seleccione variación)
        if ($product->get_type() === 'variable') {
            error_log('[SM-PRODUCT-META] Product ' . $product->get_id() . ' is variable, stock display will be updated by JS');
        }

        ?>
        <div class="sm-product-extra-meta">
            <?php if ($product->get_type() == 'variable'): ?>
                <div class="sm-meta-item sm-stock">
                    <?php if ($location_stock === 0 || $location_stock === null): ?>
                        <span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicación</span>
                    <?php else: ?>
                        <strong>Stock</strong> <?php echo esc_html($location_stock); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($sku): ?>
                <div class="sm-meta-item sm-sku">
                    SKU: <?php echo esc_html($sku); ?>
                </div>
            <?php endif; ?>

            <?php if ($categories): ?>
                <div class="sm-meta-item sm-categories">
                    Categoría: <?php echo $categories; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Obtiene el stock del producto en la ubicación seleccionada
     */
    private function getLocationStock(\WC_Product $product): ?int {
        // Obtener la ubicación seleccionada - IMPORTANTE: verificar cookie primero (más actual después de cambio)
        $location_id = null;
        $cookie_value = isset($_COOKIE['sm_selected_location']) ? $_COOKIE['sm_selected_location'] : 'no cookie';
        $session_value = isset($_SESSION['multiloca_selected_location_id']) ? $_SESSION['multiloca_selected_location_id'] : 'no session';

        // Primero revisar la cookie (se actualiza cuando el usuario cambia de ubicación)
        if (isset($_COOKIE['sm_selected_location'])) {
            $data = json_decode(stripslashes($_COOKIE['sm_selected_location']), true);
            if (isset($data['warehouse_id']) && $data['warehouse_id']) {
                $location_id = (int) $data['warehouse_id'];
            }
        }

        // Fallback a sesión si no hay cookie
        if (!$location_id && isset($_SESSION['multiloca_selected_location_id'])) {
            $location_id = (int) $_SESSION['multiloca_selected_location_id'];
        }

        error_log('[SM-LOCATION-STOCK-DEBUG] Product ' . $product->get_id() . ', cookie=' . $cookie_value . ', session=' . $session_value . ', resolved_location_id=' . ($location_id ?? 'null'));

        if (!$location_id) {
            return null;
        }

        // Obtener el stock en esa ubicación
        $meta_key = 'wcmlim_stock_at_' . $location_id;
        $stock = (int) get_post_meta($product->get_id(), $meta_key, true);

        error_log('[SM-LOCATION-STOCK] Product ' . $product->get_id() . ', location_id=' . $location_id . ', meta_key=' . $meta_key . ', stock=' . $stock);

        return $stock;
    }

    public function displayLeafCategory(): void {
        global $product;

        if (!$product) {
            return;
        }

        $term = $this->resolveLeafCategory($product);

        if (!$term) {
            return;
        }

        $url = get_term_link($term);
        ?>
        <div class="sm-product-leaf-category">
            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($term->name); ?></a>
        </div>
        <?php
    }

    private function resolveLeafCategory(\WC_Product $product): ?\WP_Term {
        $terms = get_the_terms($product->get_id(), 'product_cat');

        if (!$terms || is_wp_error($terms)) {
            return null;
        }

        $terms = array_filter($terms, fn($t) => strtolower($t->name) !== 'uncategorized');

        if (empty($terms)) {
            return null;
        }

        // La categoria hoja es la que no es padre de ninguna otra en la lista
        foreach ($terms as $term) {
            $is_parent = false;
            foreach ($terms as $other) {
                if ((int) $other->parent === $term->term_id) {
                    $is_parent = true;
                    break;
                }
            }
            if (!$is_parent) {
                return $term;
            }
        }

        // Fallback: devolver la ultima
        return end($terms) ?: null;
    }

    private function resolveStockQuantity(\WC_Product $product): ?int {
        if ($product->managing_stock()) {
            return (int) $product->get_stock_quantity();
        }

        if ($product->get_type() === 'variable') {
            $total = 0;
            foreach ($product->get_available_variations() as $variation_data) {
                $variation = wc_get_product($variation_data['variation_id']);
                if ($variation && $variation->managing_stock()) {
                    $total += (int) $variation->get_stock_quantity();
                }
            }
            return $total > 0 ? $total : null;
        }

        return null;
    }

    private function resolveCategoryList(\WC_Product $product): string {
        $terms = get_the_terms($product->get_id(), 'product_cat');

        if (!$terms || is_wp_error($terms)) {
            return '';
        }

        $links = [];
        foreach ($terms as $term) {
            if (strtolower($term->name) === 'uncategorized') {
                continue;
            }
            $url     = get_term_link($term);
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
        }

        return implode(', ', $links);
    }
}
