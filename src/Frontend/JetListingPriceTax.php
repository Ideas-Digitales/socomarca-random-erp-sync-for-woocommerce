<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * El listing de JetEngine usado en el archivo de productos (categoria/shop)
 * muestra el precio con dos widgets "Dynamic Field" que llaman directo a
 * get_regular_price()/get_sale_price() del producto (ver post 4866, "Soco
 * Mayorista Listing Movil", widgets 5b8afe1/5964088). Esos metodos devuelven
 * el precio NETO (sin impuesto), mientras que el single product page usa
 * $product->get_price_html(), que SI aplica el ajuste de impuesto segun
 * woocommerce_tax_display_shop (=incl en este sitio). Resultado: el mismo
 * producto se ve con precios distintos en category vs single (diferencia de
 * ~19% = IVA).
 *
 * Se intercepta el valor ANTES de que JetEngine llame al metodo del producto
 * (via el filtro que expone para esto), y se devuelve el precio ya calculado
 * con impuesto (wc_get_price_to_display), igual que hace get_price_html().
 * Asi ambos widgets (regular y sale) quedan consistentes con el single, sin
 * tocar el template de Elementor/JetEngine.
 */
class JetListingPriceTax {

    private const TAX_ADJUSTED_FIELDS = ['get_regular_price', 'get_sale_price'];

    private bool $wrappingStrikethrough = false;

    public function __construct() {
        add_filter('jet-engine/listings/dynamic-field/custom-value', [$this, 'applyTaxToPriceField'], 10, 3);

        // El widget "precio regular" del listing (5964088) se muestra siempre,
        // este o no en oferta el producto (es el unico precio que hay cuando
        // no hay descuento). El tachado solo debe verse cuando SI hay una
        // oferta activa, asi que se envuelve el contenido del campo en un
        // <span> con line-through condicionalmente en vez de fijarlo en la
        // configuracion estatica del widget (eso lo tachaba siempre, sin
        // importar si el producto tenia descuento o no).
        add_action('jet-engine/listing/dynamic-field/before-field', [$this, 'maybeOpenStrikethrough']);
        add_action('jet-engine/listing/dynamic-field/after-field', [$this, 'maybeCloseStrikethrough']);

        // Badge "¡Oferta!" en la tarjeta del listing de categoria. El listing
        // esta armado con widgets sueltos de JetEngine (no con el template
        // estandar de WooCommerce), asi que el sale flash normal
        // (woocommerce_show_product_loop_sale_flash) nunca se dispara ahi.
        // Se inyecta el mismo markup que usa WooCommerce (via el filtro
        // woocommerce_sale_flash, para heredar texto/traduccion/otros
        // plugins que lo filtren) al inicio del HTML de cada item.
        add_filter('jet-engine/listings/frontend/listing-item-content', [$this, 'addOnSaleBadge'], 10, 3);
    }

    public function maybeOpenStrikethrough($render): void {
        $this->wrappingStrikethrough = false;

        if (!is_a($render, 'Jet_Engine_Render_Base')) {
            return;
        }

        if (($render->get_settings('dynamic_field_source') ?? '') !== 'object') {
            return;
        }

        if (($render->get_settings('dynamic_field_post_object') ?? '') !== 'get_regular_price') {
            return;
        }

        $product = $this->resolveCurrentProduct();
        if (!$product instanceof \WC_Product || !$product->is_on_sale()) {
            return;
        }

        $this->wrappingStrikethrough = true;
        echo '<span style="text-decoration:line-through;opacity:.6;">';
    }

    public function maybeCloseStrikethrough($render): void {
        if ($this->wrappingStrikethrough) {
            echo '</span>';
            $this->wrappingStrikethrough = false;
        }
    }

    public function addOnSaleBadge($content, $listing_id, $post) {
        if (!($post instanceof \WP_Post) || $post->post_type !== 'product') {
            return $content;
        }

        $product = wc_get_product($post->ID);
        if (!$product instanceof \WC_Product || !$product->is_on_sale()) {
            return $content;
        }

        $badge = apply_filters(
            'woocommerce_sale_flash',
            '<span class="onsale">' . esc_html__('Sale!', 'woocommerce') . '</span>',
            $post,
            $product
        );

        return $badge . $content;
    }

    public function applyTaxToPriceField($result, $settings) {
        if ($result !== null) {
            return $result;
        }

        if (($settings['dynamic_field_source'] ?? '') !== 'object') {
            return $result;
        }

        $field = $settings['dynamic_field_post_object'] ?? '';
        if (!in_array($field, self::TAX_ADJUSTED_FIELDS, true)) {
            return $result;
        }

        if (!function_exists('jet_engine') || !function_exists('wc_get_price_to_display')) {
            return $result;
        }

        $product = $this->resolveCurrentProduct();
        if (!$product instanceof \WC_Product) {
            return $result;
        }

        $price = $field === 'get_regular_price' ? $product->get_regular_price() : $product->get_sale_price();
        if ($price === '') {
            return $result;
        }

        return wc_get_price_to_display($product, ['price' => $price]);
    }

    private function resolveCurrentProduct(): ?\WC_Product {
        $object = jet_engine()->listings->data->get_current_object();

        if ($object instanceof \WC_Product) {
            return $object;
        }

        if ($object instanceof \WP_Post) {
            $product = wc_get_product($object->ID);
            return $product instanceof \WC_Product ? $product : null;
        }

        return null;
    }
}
