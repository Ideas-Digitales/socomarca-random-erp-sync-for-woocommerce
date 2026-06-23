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
                'stores' => $this->getAvailableStores(),
            ]
        );
    }

    public function renderStoreSelector(): void {
        $stores = $this->getAvailableStores();

        if (empty($stores)) {
            return;
        }

        $selected = isset($_POST['post_data']) ? wp_parse_args(wp_unslash($_POST['post_data'])) : [];
        $selected_store = isset($selected['sm_pickup_store_id']) ? sanitize_text_field($selected['sm_pickup_store_id']) : '';
        ?>
        <div id="sm-store-selector-wrapper" style="display:none;">
            <h3>Selecciona la tienda donde retirar</h3>
            <p class="form-row form-row-wide">
                <label for="sm_pickup_store_id">Tienda de Retiro <abbr title="requerido">*</abbr></label>
                <select
                    name="sm_pickup_store_id"
                    id="sm_pickup_store_id"
                    class="select"
                    style="width: 100%;">
                    <option value="">-- Elige una tienda --</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo esc_attr($store['term_id']); ?>"
                            <?php selected($selected_store, $store['term_id']); ?>>
                            <?php echo esc_html($store['name'] . ' - ' . $store['address']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
        <?php
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

            if (empty($store_id)) {
                wc_add_notice('Debes seleccionar una tienda para retiro.', 'error');
            }
        }
    }

    public function saveStoreSelection(int $order_id): void {
        $store_id = isset($_POST['sm_pickup_store_id']) ? sanitize_text_field(wp_unslash($_POST['sm_pickup_store_id'])) : '';

        if (!empty($store_id)) {
            update_post_meta($order_id, 'sm_pickup_store_id', $store_id);
        }
    }

    public function displayStoreInEmail($order): void {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $store_id = get_post_meta($order->get_id(), 'sm_pickup_store_id', true);

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

        $store_id = get_post_meta($order->get_id(), 'sm_pickup_store_id', true);

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
