<?php

namespace Socomarca\RandomERP\Filters;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajusta los resultados del buscador AJAX de Jet Search (plugin de terceros)
 * sin modificar su codigo, mediante el filtro publico 'jet-search/ajax-search/query-args'.
 *
 * 1. Si el termino buscado coincide con el nombre de una categoria de producto,
 *    se agregan a los resultados todos los productos de esa categoria (union,
 *    no reemplazo, de los resultados que la busqueda normal ya encontraria).
 * 2. Los resultados se restringen a la bodega/ubicacion seleccionada por el
 *    usuario (via popup Socomarca / Multiloca Lite), mostrando solo productos
 *    con stock > 0 y precio > 0 en esa bodega especifica.
 */
class JetSearchFilter {

    public function __construct() {
        add_filter('jet-search/ajax-search/query-args', [$this, 'adjustQueryArgs'], 20);
        add_filter('posts_search', [$this, 'neutralizeSearchForCategoryUnion'], 20, 2);
    }

    /**
     * Anula el WHERE de busqueda de WordPress unicamente para la consulta
     * marcada por includeCategoryMatches(): el 's' se deja intacto en los
     * query args (Jet Search lo usa para 'search_value' / el resaltado en
     * su JS), pero no debe restringir el post__in ya calculado, que es el
     * que realmente define los resultados de esa consulta.
     */
    public function neutralizeSearchForCategoryUnion($search, $wp_query) {
        if ($wp_query instanceof \WP_Query && $wp_query->get('sm_jet_search_category_union')) {
            return '';
        }

        return $search;
    }

    public function adjustQueryArgs($args) {
        if (!is_array($args) || !$this->isProductSearch($args)) {
            return $args;
        }

        $args = $this->includeCategoryMatches($args);
        $args = $this->restrictToLocation($args);

        return $args;
    }

    private function isProductSearch(array $args): bool {
        $post_type  = $args['post_type'] ?? '';
        $post_types = is_array($post_type) ? $post_type : [$post_type];

        return in_array('product', $post_types, true);
    }

    /**
     * Si el texto buscado coincide con el nombre de una categoria de producto,
     * agrega todos los productos de esa categoria a los resultados.
     */
    private function includeCategoryMatches(array $args): array {
        $search = trim((string) ($args['s'] ?? ''));
        if ($search === '') {
            return $args;
        }

        global $wpdb;

        $term_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'product_cat'
             AND t.name LIKE %s",
            '%' . $wpdb->esc_like($search) . '%'
        ));

        if (empty($term_ids)) {
            return $args;
        }

        $category_product_ids = get_objects_in_term(array_map('intval', $term_ids), 'product_cat');
        if (is_wp_error($category_product_ids) || empty($category_product_ids)) {
            return $args;
        }

        // Productos que la busqueda normal (titulo/contenido/sku) ya encontraria,
        // calculados antes de fijar post__in para no perder esos resultados.
        $default_search_args = array_merge($args, [
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'nopaging'       => true,
            'no_found_rows'  => true,
        ]);
        unset($default_search_args['post__in']);

        $default_results = (new \WP_Query($default_search_args))->posts;

        $merged_ids = array_values(array_unique(array_merge(
            array_map('intval', $default_results),
            array_map('intval', $category_product_ids)
        )));

        if (empty($merged_ids)) {
            return $args;
        }

        $args['post__in'] = $merged_ids;

        // Se mantiene 's' (Jet Search lo usa para 'search_value' / el resaltado
        // en su JS); el filtro 'posts_search' de arriba anula su efecto
        // restrictivo sobre esta consulta puntual, ya que post__in ya define
        // el set final de resultados.
        $args['sm_jet_search_category_union'] = true;

        return $args;
    }

    /**
     * Restringe los resultados a productos con stock > 0 y precio > 0
     * en la bodega/ubicacion seleccionada por el usuario. Al ser un filtro
     * por meta_key especifico de la bodega, funciona igual para productos
     * simples y variaciones (cada uno guarda su propio stock por bodega).
     */
    private function restrictToLocation(array $args): array {
        $warehouse_id = $this->getSelectedWarehouseId();
        if (!$warehouse_id) {
            return $args;
        }

        $meta_query   = (array) ($args['meta_query'] ?? []);
        $meta_query[] = [
            'key'     => 'wcmlim_stock_at_' . $warehouse_id,
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ];
        $meta_query[] = [
            'key'     => '_price',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'DECIMAL(10,2)',
        ];

        $args['meta_query'] = $meta_query;

        return $args;
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
