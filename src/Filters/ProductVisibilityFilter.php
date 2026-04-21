<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

class ProductVisibilityFilter {

    public function __construct() {
        add_action('woocommerce_product_query', [$this, 'applyExclusion']);
        add_filter('posts_where', [$this, 'addSqlWhereClause'], 10, 2);
        add_filter('woocommerce_related_products', [$this, 'filterRelatedProducts'], 10, 3);
    }

    private function getHiddenProductIds(): array {
        $cached = get_transient('sm_hidden_product_ids');
        if ($cached !== false) {
            return (array) $cached;
        }

        global $wpdb;
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'product'
               AND pm.meta_key = '_sm_hidden_from_store'
               AND pm.meta_value = %s",
            '1'
        ));

        $ids = array_map('intval', $rows ?: []);
        set_transient('sm_hidden_product_ids', $ids, HOUR_IN_SECONDS);
        return $ids;
    }

    public function applyExclusion(\WP_Query $query): void {
        $hidden_ids = $this->getHiddenProductIds();
        if (empty($hidden_ids)) {
            return;
        }
        $existing = (array) ($query->get('post__not_in') ?: []);
        $query->set('post__not_in', array_unique(array_merge($existing, $hidden_ids)));
    }

    public function addSqlWhereClause(string $where, \WP_Query $query): string {
        if (is_admin()) {
            return $where;
        }

        $is_wc_product_query = $query->get('wc_query') === 'product_query';
        $post_type           = $query->get('post_type');
        $is_product_type     = $post_type === 'product' || in_array('product', (array) $post_type, true);

        if (!$is_wc_product_query && !$is_product_type) {
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
