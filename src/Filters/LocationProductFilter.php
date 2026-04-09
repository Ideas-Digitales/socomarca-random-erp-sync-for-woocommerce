<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filtra el loop de productos de WooCommerce segun la bodega seleccionada
 * por el usuario via el popup de ubicacion.
 *
 * La bodega seleccionada se obtiene de la sesion de Multiloca Lite:
 *   $_SESSION['multiloca_selected_location_id'] => term_id de la bodega
 *
 * Solo se muestran productos que:
 *   1. Esten vinculados a la bodega en la taxonomia 'locations'
 *   2. Tengan disponibilidad 'yes' en esa bodega (wcmlim_product_availability_at_{term_id})
 */
class LocationProductFilter {

    public function __construct() {
        add_action('pre_get_posts', [$this, 'filterByLocation'], 20);
    }

    public function filterByLocation(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$this->isProductQuery($query)) {
            return;
        }

        $warehouse_id = $this->getSelectedWarehouseId();
        if (!$warehouse_id) {
            return;
        }

        // Filtrar por taxonomia: solo productos vinculados a esta bodega
        $tax_query = (array) $query->get('tax_query');
        $tax_query[] = [
            'taxonomy' => 'locations',
            'field'    => 'term_id',
            'terms'    => [$warehouse_id],
            'operator' => 'IN',
        ];
        $query->set('tax_query', $tax_query);
    }

    private function isProductQuery(\WP_Query $query): bool {
        if (is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy()) {
            return true;
        }

        $post_type = $query->get('post_type');
        return $post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true));
    }

    private function getSelectedWarehouseId(): ?int {
        // Fuente primaria: sesion de Multiloca Lite
        if (isset($_SESSION['multiloca_selected_location_id'])) {
            $id = (int) $_SESSION['multiloca_selected_location_id'];
            return $id > 0 ? $id : null;
        }

        // Fuente alternativa: cookie establecida por el popup de Socomarca
        $cookie = $_COOKIE['sm_selected_location'] ?? '';
        if (empty($cookie)) {
            return null;
        }

        $data = json_decode(stripslashes($cookie), true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['warehouse_id'])) {
            return null;
        }

        $id = (int) $data['warehouse_id'];
        return $id > 0 ? $id : null;
    }
}
