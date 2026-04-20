<?php

namespace Socomarca\RandomERP\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maneja el cambio de bodega cuando el carrito tiene productos.
 * Limpia el carrito y re-agrega los items con el stock disponible
 * en la nueva bodega seleccionada.
 */
class CartWarehouseSwitchHandler extends BaseAjaxHandler {

    protected function registerHooks(): void {
        add_action('wp_ajax_sm_switch_warehouse_cart',        [$this, 'handle']);
        add_action('wp_ajax_nopriv_sm_switch_warehouse_cart', [$this, 'handle']);
    }

    public function handle(): void {
        check_ajax_referer('sm_location_popup_nonce', 'nonce');

        $new_warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        if (!$new_warehouse_id) {
            wp_send_json_error(['message' => 'Bodega no valida']);
            return;
        }

        $cart = WC()->cart;

        // Forzar carga de carrito desde sesion antes de operar.
        $cart->get_cart();

        if ($cart->is_empty()) {
            wp_send_json_success(['items' => [], 'cleared' => false]);
            return;
        }

        $results        = [];
        $items_to_readd = [];

        foreach ($cart->get_cart() as $cart_item) {
            $variation_id = intval($cart_item['variation_id']);
            $quantity     = intval($cart_item['quantity']);
            $product_name = $cart_item['data']->get_name();

            $stock = intval(get_post_meta($variation_id, 'wcmlim_stock_at_' . $new_warehouse_id, true));
            $avail = get_post_meta($variation_id, 'wcmlim_product_availability_at_' . $new_warehouse_id, true);

            if ($avail !== 'yes' || $stock <= 0) {
                $results[] = [
                    'type'         => 'error',
                    'product_name' => $product_name,
                ];
            } elseif ($stock >= $quantity) {
                $items_to_readd[] = [
                    'product_id'   => intval($cart_item['product_id']),
                    'variation_id' => $variation_id,
                    'quantity'     => $quantity,
                    'variation'    => $cart_item['variation'],
                ];
                $results[] = [
                    'type'         => 'success',
                    'product_name' => $product_name,
                    'quantity'     => $quantity,
                ];
            } else {
                $items_to_readd[] = [
                    'product_id'   => intval($cart_item['product_id']),
                    'variation_id' => $variation_id,
                    'quantity'     => $stock,
                    'variation'    => $cart_item['variation'],
                ];
                $results[] = [
                    'type'         => 'warning',
                    'product_name' => $product_name,
                    'quantity'     => $stock,
                    'requested'    => $quantity,
                ];
            }
        }

        // Actualizar session de multiloca ANTES de limpiar para que
        // woocommerce_cart_emptied no lo resetee (el hook fue deshabilitado en multiloca).
        $warehouse_term = get_term($new_warehouse_id, 'locations');
        $_SESSION['multiloca_selected_location_id']   = $new_warehouse_id;
        $_SESSION['multiloca_selected_location_name'] = $warehouse_term ? $warehouse_term->name : '';

        // Limpiar carrito item por item para garantizar persistencia en sesion AJAX.
        foreach (array_keys($cart->cart_contents) as $key) {
            $cart->remove_cart_item($key);
        }
        $cart->cart_contents = [];
        WC()->session->set('cart', []);

        foreach ($items_to_readd as $item) {
            $cart->add_to_cart(
                $item['product_id'],
                $item['quantity'],
                $item['variation_id'],
                $item['variation']
            );
        }

        // Forzar guardado de sesion WC.
        $cart->set_session();
        WC()->session->save_data();

        // Para usuarios logueados, WooCommerce tambien persiste el carrito en user meta.
        // Si no lo limpiamos, la pagina del carrito puede restaurar el carrito viejo
        // desde ese meta ignorando la sesion que acabamos de actualizar.
        $user_id = get_current_user_id();
        if ($user_id) {
            delete_user_meta($user_id, '_woocommerce_persistent_cart_' . get_current_blog_id());
        }

        wp_send_json_success(['items' => $results, 'cleared' => true]);
    }
}
