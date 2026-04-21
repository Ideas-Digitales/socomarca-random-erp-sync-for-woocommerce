<?php

namespace Socomarca\RandomERP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class ProductFilterAdmin {

    public function __construct() {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('wp_ajax_sm_pf_search', [$this, 'ajaxSearch']);
        add_action('wp_ajax_sm_pf_toggle', [$this, 'ajaxToggle']);
        add_action('wp_ajax_sm_pf_list_hidden', [$this, 'ajaxListHidden']);
    }

    public function addMenuPage(): void {
        add_submenu_page(
            'socomarca',
            'Filtro de Productos',
            'Product Filter',
            'manage_options',
            'socomarca-product-filter',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void {
        include SOCOMARCA_ERP_PLUGIN_DIR . 'templates/product-filter.php';
    }

    public function ajaxSearch(): void {
        check_ajax_referer('sm_product_filter_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado']);
            return;
        }

        $query = sanitize_text_field($_POST['query'] ?? '');

        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 30,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if (!empty($query)) {
            $args['s'] = $query;
        }

        $posts   = get_posts($args);
        $results = [];

        foreach ($posts as $post) {
            $product   = wc_get_product($post->ID);
            $results[] = [
                'id'     => $post->ID,
                'name'   => $post->post_title,
                'sku'    => $product ? $product->get_sku() : '',
                'status' => $post->post_status,
                'hidden' => get_post_meta($post->ID, '_sm_hidden_from_store', true) === '1',
            ];
        }

        wp_send_json_success(['products' => $results]);
    }

    public function ajaxToggle(): void {
        check_ajax_referer('sm_product_filter_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado']);
            return;
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => 'ID de producto requerido']);
            return;
        }

        $post = get_post($product_id);
        if (!$post || $post->post_type !== 'product') {
            wp_send_json_error(['message' => 'Producto no encontrado']);
            return;
        }

        $currently_hidden = get_post_meta($product_id, '_sm_hidden_from_store', true) === '1';
        $new_hidden       = !$currently_hidden;

        if ($new_hidden) {
            update_post_meta($product_id, '_sm_hidden_from_store', '1');
        } else {
            delete_post_meta($product_id, '_sm_hidden_from_store');
        }

        delete_transient('sm_hidden_product_ids');

        wp_send_json_success([
            'product_id' => $product_id,
            'hidden'     => $new_hidden,
        ]);
    }

    public function ajaxListHidden(): void {
        check_ajax_referer('sm_product_filter_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado']);
            return;
        }

        $posts = get_posts([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_key'       => '_sm_hidden_from_store',
            'meta_value'     => '1',
        ]);

        $results = [];
        foreach ($posts as $post) {
            $product   = wc_get_product($post->ID);
            $results[] = [
                'id'     => $post->ID,
                'name'   => $post->post_title,
                'sku'    => $product ? $product->get_sku() : '',
                'status' => $post->post_status,
                'hidden' => true,
            ];
        }

        wp_send_json_success(['products' => $results]);
    }
}
