<?php

namespace Socomarca\RandomERP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class CheckoutStorePickup {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_review_order_before_payment', [$this, 'renderStoreSelector']);
        add_action('woocommerce_checkout_process', [$this, 'validateStoreSelection']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveStoreSelection']);
        add_action('woocommerce_email_after_order_table', [$this, 'displayStoreInEmail']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayStoreInAdmin']);
        add_action('wp_ajax_sm_get_store_by_commune', [$this, 'ajaxGetStoreByCommune']);
        add_action('wp_ajax_nopriv_sm_get_store_by_commune', [$this, 'ajaxGetStoreByCommune']);
    }

    public function enqueueAssets(): void {
        if (!is_checkout()) {
            return;
        }

        $plugin_dir = SOCOMARCA_ERP_PLUGIN_DIR;
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;

        wp_enqueue_script(
            'socomarca-checkout-store-pickup',
            $plugin_url . 'assets/js/checkout-store-pickup.js',
            ['jquery'],
            filemtime($plugin_dir . 'assets/js/checkout-store-pickup.js'),
            true
        );

        wp_localize_script(
            'socomarca-checkout-store-pickup',
            'socomarcaStorePickup',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sm_store_pickup_nonce'),
            ]
        );
    }

    public function renderStoreSelector(): void {
        $stores = $this->getAvailableStores();

        if (empty($stores)) {
            return;
        }

        ?>
        <div id="sm-store-selector-wrapper" style="display:none;">
            <h3>Tienda de Retiro</h3>
            <div id="sm-store-info" class="sm-store-info-display">
                <p><strong>Tienda:</strong> <span id="sm-store-name">-</span></p>
                <p><strong>Dirección:</strong> <span id="sm-store-address">-</span></p>
            </div>
            <input type="hidden" name="sm_pickup_store_id" id="sm_pickup_store_id" value="">
        </div>
        <?php
    }

    public function ajaxGetStoreByCommune(): void {
        if (isset($_POST['nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sm_store_pickup_nonce')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        $commune = isset($_POST['commune']) ? sanitize_text_field(wp_unslash($_POST['commune'])) : '';

        if (empty($commune)) {
            wp_send_json_error(['message' => 'Comuna no proporcionada']);
        }

        $store = $this->getStoreByCommune($commune);

        if ($store) {
            wp_send_json_success($store);
        } else {
            wp_send_json_error(['message' => 'Tienda no encontrada para la comuna']);
        }
    }

    private function getStoreByCommune(string $commune): ?array {
        $mapping = get_option('sm_location_mapping', []);

        if (empty($mapping) || !is_array($mapping)) {
            return null;
        }

        $commune = trim($commune);
        $communeLower = strtolower($commune);

        foreach ($mapping as $region) {
            if (!isset($region['comunas']) || !is_array($region['comunas'])) {
                continue;
            }

            foreach ($region['comunas'] as $comunaData) {
                if (!isset($comunaData['name']) || !isset($comunaData['warehouse_id'])) {
                    continue;
                }

                $mapCommuneLower = strtolower(trim($comunaData['name']));

                if ($mapCommuneLower === $communeLower) {
                    $store = $this->getStoreDetails((int) $comunaData['warehouse_id']);
                    if ($store) {
                        return $store;
                    }
                }
            }
        }

        return null;
    }

    public function validateStoreSelection(): void {
        $shipping_method = isset($_POST['shipping_method']) ? $_POST['shipping_method'] : '';

        if (!is_array($shipping_method)) {
            $shipping_method = [$shipping_method];
        }

        $is_local_pickup = false;
        foreach ($shipping_method as $method) {
            $method_str = is_string($method) ? $method : '';
            if (strpos($method_str, 'local_pickup') !== false) {
                $is_local_pickup = true;
                break;
            }
        }

        if ($is_local_pickup) {
            $store_id = isset($_POST['sm_pickup_store_id']) ? sanitize_text_field(wp_unslash($_POST['sm_pickup_store_id'])) : '';

            /*
            if (empty($store_id)) {
                wc_add_notice('Debes seleccionar una tienda para retiro.', 'error');
            }
            */
        }
    }

    public function saveStoreSelection(int $order_id): void {
        $store_id = isset($_POST['sm_pickup_store_id']) ? sanitize_text_field(wp_unslash($_POST['sm_pickup_store_id'])) : '';

        if (empty($store_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order->update_meta_data('sm_pickup_store_id', $store_id);
        $order->save();
    }

    public function displayStoreInEmail($order): void {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $store_id = $order->get_meta('sm_pickup_store_id', true);

        if (empty($store_id)) {
            return;
        }

        $store = $this->getStoreDetails((int) $store_id);

        if ($store) {
            echo '<h3>Información de Retiro</h3>';
            echo '<p><strong>Tienda:</strong> ' . esc_html($store['name']) . '</p>';
            echo '<p><strong>Dirección:</strong> ' . esc_html($store['address']) . '</p>';
        }
    }

    public function displayStoreInAdmin($order): void {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $store_id = $order->get_meta('sm_pickup_store_id', true);

        if (empty($store_id)) {
            return;
        }

        $store = $this->getStoreDetails((int) $store_id);

        if ($store) {
            echo '<p><strong>Tienda Retiro:</strong> ' . esc_html($store['name']) . '</p>';
            echo '<p><strong>Dirección:</strong> ' . esc_html($store['address']) . '</p>';
        }
    }

    private function getAvailableStores(): array {
        $stores = [];
        $mapping = get_option('sm_location_mapping', []);

        if (empty($mapping) || !is_array($mapping)) {
            return $stores;
        }

        $warehouse_ids = [];

        foreach ($mapping as $region) {
            if (!isset($region['comunas']) || !is_array($region['comunas'])) {
                continue;
            }

            foreach ($region['comunas'] as $comuna) {
                if (isset($comuna['warehouse_id'])) {
                    $warehouse_ids[(int) $comuna['warehouse_id']] = true;
                }
            }
        }

        foreach ($warehouse_ids as $term_id => $unused) {
            $store = $this->getStoreDetails($term_id);
            if ($store) {
                $stores[] = $store;
            }
        }

        usort($stores, static fn($a, $b) => strcmp($a['name'], $b['name']));

        return $stores;
    }

    private function getStoreDetails(int $term_id): ?array {
        $term = get_term($term_id, 'locations');

        if (!$term || is_wp_error($term)) {
            return null;
        }

        $address = get_term_meta($term_id, 'wcmlim_street_number', true);

        if (empty($address)) {
            $address = 'Dirección no disponible';
        }

        return [
            'term_id' => $term_id,
            'name' => $term->name,
            'address' => $address,
        ];
    }
}
