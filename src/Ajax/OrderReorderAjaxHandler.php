<?php

namespace Socomarca\RandomERP\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maneja el boton "Pedir de nuevo" de Mi Cuenta > Pedidos.
 * Vacia el carrito actual (si tiene productos), cambia a la bodega con la
 * que se hizo el pedido original y vuelve a agregar los mismos productos,
 * respetando el stock disponible en esa bodega.
 */
class OrderReorderAjaxHandler extends BaseAjaxHandler {

    protected function registerHooks(): void {
        add_action('wp_ajax_sm_reorder_preview', [$this, 'preview']);
        add_action('wp_ajax_sm_reorder_execute', [$this, 'execute']);
    }

    /**
     * Indica si el carrito actual tiene productos, para decidir si se debe
     * mostrar el modal de confirmacion antes de reordenar.
     */
    public function preview(): void {
        check_ajax_referer('sm_location_popup_nonce', 'nonce');

        $order = $this->getOwnedOrder();
        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no valido']);
            return;
        }

        $cart = WC()->cart;
        $cart->get_cart();

        wp_send_json_success(['cart_has_items' => !$cart->is_empty()]);
    }

    public function execute(): void {
        check_ajax_referer('sm_location_popup_nonce', 'nonce');

        $order = $this->getOwnedOrder();
        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no valido']);
            return;
        }

        [$warehouse_id, $warehouse_name] = $this->getOrderWarehouse($order);

        if (!$warehouse_id) {
            wp_send_json_error(['message' => 'No se pudo determinar la bodega de este pedido. Seleccione una ubicacion e intente nuevamente.']);
            return;
        }

        $results      = [];
        $items_to_add = [];

        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product_name = $item->get_name();
            $product      = $item->get_product();

            if (!$product) {
                $results[] = ['type' => 'error', 'product_name' => $product_name];
                continue;
            }

            $product_id   = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity     = $item->get_quantity();

            $stock_post_id = $variation_id ?: $product_id;
            $stock = intval(get_post_meta($stock_post_id, 'wcmlim_stock_at_' . $warehouse_id, true));
            $avail = get_post_meta($stock_post_id, 'wcmlim_product_availability_at_' . $warehouse_id, true);

            if ($avail !== 'yes' || $stock <= 0) {
                $results[] = ['type' => 'error', 'product_name' => $product_name];
                continue;
            }

            $add_qty = min($quantity, $stock);

            $items_to_add[] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $add_qty,
                'variation'    => $variation_id ? wc_get_product_variation_attributes($variation_id) : [],
            ];

            $results[] = [
                'type'         => $add_qty < $quantity ? 'warning' : 'success',
                'product_name' => $product_name,
                'quantity'     => $add_qty,
                'requested'    => $quantity,
            ];
        }

        $cart = WC()->cart;
        $cart->get_cart();

        if (!$cart->is_empty()) {
            $cart->empty_cart(true);
            $cart->set_session();
            WC()->session->save_data();
        }

        $_SESSION['multiloca_selected_location_id']   = $warehouse_id;
        $_SESSION['multiloca_selected_location_name'] = $warehouse_name;
        wc_clear_notices();

        foreach ($items_to_add as $item) {
            $cart->add_to_cart(
                $item['product_id'],
                $item['quantity'],
                $item['variation_id'],
                $item['variation']
            );
        }

        $cart->set_session();
        WC()->session->save_data();

        $display = $this->findLocationDisplay($warehouse_id);

        wp_send_json_success(array_merge([
            'items'        => $results,
            'warehouse_id' => $warehouse_id,
            'cart_url'     => wc_get_cart_url(),
        ], $display));
    }

    /**
     * Obtiene el pedido desde el POST y verifica que pertenezca al usuario
     * actualmente logueado, para evitar reordenar pedidos ajenos.
     */
    private function getOwnedOrder(): ?\WC_Order {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            return null;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return null;
        }

        if ((int) $order->get_customer_id() !== $user_id) {
            return null;
        }

        return $order;
    }

    /**
     * Determina la bodega con la que se hizo el pedido original: primero la
     * meta a nivel de pedido guardada por OrderWarehouseMeta (confiable, se
     * guarda desde esta funcionalidad en adelante), luego la meta de linea
     * guardada por multiloca-lite, luego la bodega de retiro en tienda, y
     * por ultimo la bodega actualmente seleccionada (sesion o cookie
     * sm_selected_location) como ultimo recurso, para pedidos antiguos que
     * no tienen ninguna de las anteriores: el usuario elige una ubicacion
     * y reintenta.
     */
    private function getOrderWarehouse(\WC_Order $order): array {
        $order_warehouse_id = (int) $order->get_meta('_sm_order_warehouse_id');
        if ($order_warehouse_id) {
            return [$order_warehouse_id, (string) $order->get_meta('_sm_order_warehouse_name')];
        }

        foreach ($order->get_items() as $item) {
            $location_id = (int) $item->get_meta('_multiloca_location_id');
            if ($location_id) {
                $location_name = (string) $item->get_meta('_multiloca_location_name');
                return [$location_id, $location_name];
            }
        }

        $pickup_id = (int) $order->get_meta('sm_pickup_store_id');
        if ($pickup_id) {
            $term = get_term($pickup_id, 'locations');
            return [$pickup_id, ($term && !is_wp_error($term)) ? $term->name : ''];
        }

        if (!empty($_SESSION['multiloca_selected_location_id'])) {
            $current = (int) $_SESSION['multiloca_selected_location_id'];
            $term    = get_term($current, 'locations');
            return [$current, ($term && !is_wp_error($term)) ? $term->name : ''];
        }

        $cookie_warehouse_id = $this->warehouseIdFromCookie();
        if ($cookie_warehouse_id) {
            $term = get_term($cookie_warehouse_id, 'locations');
            return [$cookie_warehouse_id, ($term && !is_wp_error($term)) ? $term->name : ''];
        }

        return [0, ''];
    }

    /**
     * Lee el warehouse_id de la cookie sm_selected_location que escribe el
     * popup de ubicacion del frontend.
     */
    private function warehouseIdFromCookie(): int {
        if (empty($_COOKIE['sm_selected_location'])) {
            return 0;
        }

        $raw  = wp_unslash($_COOKIE['sm_selected_location']);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = json_decode(rawurldecode($raw), true);
        }

        return (is_array($data) && !empty($data['warehouse_id'])) ? (int) $data['warehouse_id'] : 0;
    }

    /**
     * Busca region/comuna asociadas a la bodega para poder actualizar la
     * cookie de ubicacion del frontend (misma forma que sm_selected_location).
     */
    private function findLocationDisplay(int $warehouse_id): array {
        $mapping = get_option('sm_location_mapping', []);
        if (!is_array($mapping)) {
            return [];
        }

        foreach ($mapping as $region) {
            foreach (($region['comunas'] ?? []) as $comuna) {
                if ((int) ($comuna['warehouse_id'] ?? 0) === $warehouse_id) {
                    return [
                        'region_id'   => $region['id'] ?? '',
                        'region_name' => $region['name'] ?? '',
                        'comuna_id'   => $comuna['id'] ?? '',
                        'comuna_name' => $comuna['name'] ?? '',
                    ];
                }
            }
        }

        return [];
    }
}
