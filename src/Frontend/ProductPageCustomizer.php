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
        $stock_qty  = $this->resolveStockQuantity($product);
        $categories = $this->resolveCategoryList($product);

        ?>
        <div class="sm-product-extra-meta">
            <?php if ($stock_qty !== null): ?>
                <div class="sm-meta-item sm-stock">
                    <strong>Stock</strong> <?php echo esc_html($stock_qty); ?>
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
