<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class ZeroPriceValidator {

    public function __construct() {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateBeforeAddToCart'], 10, 5);
        add_action('woocommerce_before_checkout_form', [$this, 'validateCheckout']);
        add_action('woocommerce_review_order_before_submit', [$this, 'validateCheckoutItems']);
    }

    public function validateBeforeAddToCart($passed, $product_id, $quantity, $variation_id = null, $variations = null) {
        $product = wc_get_product($variation_id ?: $product_id);

        if (!$product) {
            return $passed;
        }

        $price = $product->get_price();

        if ($price === '' || $price === null || (float) $price <= 0) {
            wc_add_notice(
                'Este producto no puede ser agregado al carrito porque no tiene un precio válido.',
                'error'
            );
            return false;
        }

        return $passed;
    }

    public function validateCheckout() {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if (!$product) {
                continue;
            }

            $price = $product->get_price();
            if ($price === '' || $price === null || (float) $price <= 0) {
                wc_add_notice(
                    sprintf(
                        'El producto "%s" no puede ser comprado porque no tiene un precio válido. Por favor, remuévalo del carrito.',
                        $product->get_name()
                    ),
                    'error'
                );
            }
        }
    }

    public function validateCheckoutItems() {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product) {
                continue;
            }

            $price = $product->get_price();
            if ($price === '' || $price === null || (float) $price <= 0) {
                wc_add_notice(
                    sprintf(
                        'El producto "%s" no puede ser comprado porque no tiene un precio válido.',
                        $product->get_name()
                    ),
                    'error'
                );
            }
        }
    }
}
