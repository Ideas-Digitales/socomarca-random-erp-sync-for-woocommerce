<?php

namespace Socomarca\RandomERP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class LocationMappingAdmin {

    public function __construct() {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_post_sm_save_location_mapping', [$this, 'saveMapping']);
        add_action('admin_post_sm_run_location_seeder', [$this, 'runSeeder']);
    }

    public function addMenuPage(): void {
        add_submenu_page(
            'socomarca',
            'Mapeo de Ubicaciones',
            'Ubicaciones',
            'manage_options',
            'socomarca-location-mapping',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void {
        $mapping    = self::getMapping();
        $warehouses = $this->getWarehouses();
        include SOCOMARCA_ERP_PLUGIN_DIR . 'templates/location-mapping.php';
    }

    public function saveMapping(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('sm_location_mapping_nonce');

        $raw     = wp_unslash($_POST['sm_mapping_json'] ?? '[]');
        $mapping = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($mapping)) {
            update_option('sm_location_mapping', $mapping);
        }

        wp_redirect(add_query_arg('saved', '1', wp_get_referer()));
        exit;
    }

    public function runSeeder(): void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('sm_location_seeder_nonce');

        $seeder = new LocationMappingSeeder();
        $seeder->run();

        wp_redirect(add_query_arg('seeded', '1', wp_get_referer()));
        exit;
    }

    public static function getMapping(): array {
        return get_option('sm_location_mapping', []);
    }

    public function getWarehouses(): array {
        $terms = get_terms([
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        return (!is_wp_error($terms) && is_array($terms)) ? $terms : [];
    }
}
