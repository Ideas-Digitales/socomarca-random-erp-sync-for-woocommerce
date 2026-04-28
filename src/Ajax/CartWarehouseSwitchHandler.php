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
        add_action('wp_ajax_sm_cart_stock_preview',           [$this, 'handlePreview']);
        add_action('wp_ajax_nopriv_sm_cart_stock_preview',    [$this, 'handlePreview']);
        // Prioridad 1 para ejecutar antes del add_to_cart_action de WC (tambien en init).
        add_action('init', [$this, 'suppressAddToCartAfterSwitch'], 1);
    }

    /**
     * Si el request anterior fue un cambio de bodega, elimina los params add-to-cart
     * del request actual antes de que WC los procese. Esto evita que la recarga de
     * pagina post-switch re-agregue los items al carrito usando los GET params de la URL.
     */
    public function suppressAddToCartAfterSwitch(): void {
        if (empty($_COOKIE['sm_cart_switched'])) {
            return;
        }
        setcookie('sm_cart_switched', '', time() - 3600, '/');
        unset($_REQUEST['add-to-cart'], $_GET['add-to-cart']);
        unset($_REQUEST['quantity'],    $_GET['quantity']);
        unset($_REQUEST['variation_id'], $_GET['variation_id']);
        error_log('[SM-SWITCH] suppressAddToCartAfterSwitch: removed add-to-cart params');
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

        error_log('[SM-SWITCH] START: cart count=' . count($cart->get_cart()) . ' warehouse=' . $new_warehouse_id);

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

        // Usar el nombre de comuna enviado por JS (mismo que usara select_location despues).
        // Si usaramos el nombre del termino de bodega, el cart item key seria distinto al
        // de los items agregados post-recarga, generando duplicados en el carrito.
        $location_name = sanitize_text_field($_POST['location_name'] ?? '');
        if (empty($location_name)) {
            $warehouse_term = get_term($new_warehouse_id, 'locations');
            $location_name  = $warehouse_term ? $warehouse_term->name : '';
        }
        error_log('[SM-SWITCH] items_to_readd: ' . json_encode(array_map(fn($i) => ['pid' => $i['product_id'], 'vid' => $i['variation_id'], 'qty' => $i['quantity']], $items_to_readd)));

        $_SESSION['multiloca_selected_location_id']   = $new_warehouse_id;
        $_SESSION['multiloca_selected_location_name'] = $location_name;

        // Vaciar carrito via empty_cart (maneja cart_contents, removed_cart_contents,
        // totales y user meta de carrito persistente para usuarios logueados).
        // Se omite woocommerce_cart_emptied en multiloca para no limpiar la sesion
        // de ubicacion que acabamos de actualizar arriba.
        $cart->empty_cart(true);

        error_log('[SM-SWITCH] after empty_cart: count=' . count($cart->get_cart()));

        // Persistir el carrito vacio en DB ANTES de re-agregar items.
        // Si no, add_to_cart puede recargar desde DB y combinar con el carrito anterior.
        $cart->set_session();
        WC()->session->save_data();

        error_log('[SM-SWITCH] after save empty to DB: count=' . count($cart->get_cart()));

        foreach ($items_to_readd as $item) {
            error_log('[SM-SWITCH] calling add_to_cart pid=' . $item['product_id'] . ' vid=' . $item['variation_id'] . ' qty=' . $item['quantity']);
            $result_key = $cart->add_to_cart(
                $item['product_id'],
                $item['quantity'],
                $item['variation_id'],
                $item['variation']
            );
            error_log('[SM-SWITCH] add_to_cart result_key=' . var_export($result_key, true) . ' cart_count=' . count($cart->get_cart()));
        }

        error_log('[SM-SWITCH] before final save: count=' . count($cart->get_cart()) . ' contents=' . json_encode(array_map(fn($ci) => ['pid' => $ci['product_id'], 'vid' => $ci['variation_id'], 'qty' => $ci['quantity'], 'loc' => $ci['multiloca_location_id'] ?? 'none'], $cart->get_cart())));

        // Guardar estado final en sesion WC y DB.
        $cart->set_session();
        WC()->session->save_data();

        error_log('[SM-SWITCH] DONE: final count=' . count($cart->get_cart()));

        // Cookie de un minuto para que el siguiente page load sepa que ignore
        // los params add-to-cart de la URL (producto de la navegacion pre-switch).
        setcookie('sm_cart_switched', '1', time() + 60, '/');

        wp_send_json_success(['items' => $results, 'cleared' => true]);
    }

    /**
     * Devuelve una comparativa de stock por item del carrito vs la nueva bodega,
     * sin modificar el carrito ni la sesion.
     */
    public function handlePreview(): void {
        check_ajax_referer('sm_location_popup_nonce', 'nonce');

        $new_warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        if (!$new_warehouse_id) {
            wp_send_json_error(['message' => 'Bodega no valida']);
            return;
        }

        $cart = WC()->cart;
        $cart->get_cart();

        if ($cart->is_empty()) {
            wp_send_json_success(['items' => [], 'empty' => true]);
            return;
        }

        $items = [];
        foreach ($cart->get_cart() as $cart_item) {
            $product_id   = intval($cart_item['product_id']);
            $variation_id = intval($cart_item['variation_id']);
            $quantity     = intval($cart_item['quantity']);
            $product_name = $cart_item['data']->get_name();

            $stock_post_id = $variation_id ?: $product_id;

            $new_stock = intval(get_post_meta($stock_post_id, 'wcmlim_stock_at_' . $new_warehouse_id, true));
            $new_avail = get_post_meta($stock_post_id, 'wcmlim_product_availability_at_' . $new_warehouse_id, true);

            if ($new_avail !== 'yes' || $new_stock <= 0) {
                $status    = 'out_of_stock';
                $new_stock = 0;
            } elseif ($new_stock >= $quantity) {
                $status = 'ok';
            } else {
                $status = 'partial';
            }

            $items[] = [
                'product_name' => $product_name,
                'quantity'     => $quantity,
                'new_stock'    => $new_stock,
                'status'       => $status,
            ];
        }

        wp_send_json_success(['items' => $items, 'empty' => false]);
    }
}
