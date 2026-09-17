<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

class ProductVisibilityFilter {

    public function __construct() {
        add_action('woocommerce_product_query', [$this, 'applyExclusion']);
        add_action('pre_get_posts', [$this, 'applyExclusionPreGetPosts']);
        add_filter('posts_where', [$this, 'addSqlWhereClause'], 10, 2);
        add_filter('woocommerce_related_products', [$this, 'filterRelatedProducts'], 10, 3);
        add_filter('jet-engine/query-builder/query', [$this, 'filterJetEngineQuery']);
        add_filter('jet-search/ajax-search/query-args', [$this, 'filterJetSearchProducts']);
        add_filter('rest_post_query', [$this, 'filterRestPostQuery'], 10, 2);
    }

    private function getHiddenProductIds(): array {
        $cached = get_transient('sm_hidden_product_ids');
        if ($cached !== false) {
            return (array) $cached;
        }

        global $wpdb;
        
        // 1. Productos ocultos explícitamente por meta _sm_hidden_from_store
        $hidden_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'product'
               AND pm.meta_key = '_sm_hidden_from_store'
               AND pm.meta_value = %s",
            '1'
        ));
        
        $ids = array_map('intval', $hidden_ids ?: []);
        
        // 2. Si la opción está activa, buscar productos con precio 0 o SIN precio
        if (get_option('sm_hide_zero_price', false)) {
            // Buscamos productos que NO tengan un meta '_price' mayor que 0
            $zero_price_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'product' 
                 AND post_status = 'publish'
                 AND ID NOT IN (
                     SELECT post_id FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_price' 
                     AND meta_value != '' 
                     AND CAST(meta_value AS DECIMAL(10,2)) > 0
                 )"
            );
            
            if (!empty($zero_price_ids)) {
                $ids = array_unique(array_merge($ids, array_map('intval', $zero_price_ids)));
            }
        }

        set_transient('sm_hidden_product_ids', $ids, HOUR_IN_SECONDS);
        return $ids;
    }

    public function handleZeroPrice404(): void {
        if (!is_singular('product') || !get_option('sm_hide_zero_price', false)) {
            return;
        }

        $product_id = get_queried_object_id();
        if (!$product_id) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $price = $product->get_price();
        
        // Un producto no tiene precio si el precio es una cadena vacía, null, o numéricamente 0
        if ($price === '' || $price === null || (float)$price <= 0) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            return;
        }
    }

    public function applyExclusion(\WP_Query $query): void {
        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return;
        }
        $existing = (array) ($query->get('post__not_in') ?: []);
        $query->set('post__not_in', array_unique(array_merge($existing, $hidden_ids)));
    }

    public function applyExclusionPreGetPosts(\WP_Query $query): void {
        // is_admin() es true tambien en admin-ajax.php, que es exactamente
        // donde corre el "Load More" de JetEngine en la grilla de categorias
        // (accion listing_load_more). Sin el !wp_doing_ajax() aqui, esa
        // llamada AJAX queda sin excluir productos ocultos/sin precio
        // mientras la carga inicial (SSR) si los excluye, desalineando el
        // conjunto de productos entre ambas paginas y duplicando/saltando
        // items en el scroll infinito. Ver LocationStockFilter::shouldSkip()
        // para el mismo fix ya aplicado a otro filtro de este plugin.
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        if ($query->is_singular()) {
            return;
        }

        $post_type = $query->get('post_type');
        $is_product_query = $post_type === 'product' ||
                           (is_array($post_type) && in_array('product', $post_type)) ||
                           $query->get('wc_query') === 'product_query' ||
                           $query->get('s'); // Búsquedas

        if (!$is_product_query) {
            return;
        }

        $this->applyExclusion($query);
    }

    public function filterJetEngineQuery($query) {
        if (!is_array($query) || !isset($query['post_type'])) {
            return $query;
        }

        if ($query['post_type'] !== 'product') {
            return $query;
        }

        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return $query;
        }

        $existing = $query['post__not_in'] ?? [];
        $query['post__not_in'] = array_unique(array_merge($existing, $hidden_ids));

        return $query;
    }

    public function filterJetSearchProducts($args) {
        if (!is_array($args)) {
            return $args;
        }

        $post_type  = $args['post_type'] ?? '';
        $post_types = is_array($post_type) ? $post_type : [$post_type];

        if (!in_array('product', $post_types, true)) {
            return $args;
        }

        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return $args;
        }

        $existing = $args['post__not_in'] ?? [];
        $args['post__not_in'] = array_unique(array_merge($existing, $hidden_ids));

        return $args;
    }

    public function filterRestPostQuery($prepared_args, $request) {
        if (!is_array($prepared_args)) {
            return $prepared_args;
        }

        if (($prepared_args['post_type'] ?? null) !== 'product') {
            return $prepared_args;
        }

        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return $prepared_args;
        }

        $existing = $prepared_args['post__not_in'] ?? [];
        $prepared_args['post__not_in'] = array_unique(array_merge($existing, $hidden_ids));

        return $prepared_args;
    }

    public function addSqlWhereClause(string $where, \WP_Query $query): string {
        // Mismo motivo que en applyExclusionPreGetPosts(): no saltarse este
        // filtro durante admin-ajax.php (Load More de JetEngine), solo en
        // paginas de admin reales.
        if (is_admin() && !wp_doing_ajax()) {
            return $where;
        }

        $is_wc_product_query = $query->get('wc_query') === 'product_query';
        $post_type           = $query->get('post_type');
        $is_product_type     = $post_type === 'product' || in_array('product', (array) $post_type, true);

        if (!$is_wc_product_query && !$is_product_type) {
            return $where;
        }

        if ($query->is_singular()) {
            return $where;
        }

        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return $where;
        }

        global $wpdb;
        $ids_str = implode(',', $hidden_ids);
        $where  .= " AND {$wpdb->posts}.ID NOT IN ({$ids_str})";
        return $where;
    }

    public function filterRelatedProducts(array $related_posts, int $product_id, array $args): array {
        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return $related_posts;
        }
        return array_values(array_diff($related_posts, $hidden_ids));
    }
}
