<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Admin\LocationMappingAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class LocationMappingAjaxHandler {

    public function __construct() {
        add_action('wp_ajax_sm_get_comunas', [$this, 'getComunas']);
        add_action('wp_ajax_nopriv_sm_get_comunas', [$this, 'getComunas']);
    }

    public function getComunas(): void {
        check_ajax_referer('sm_location_popup_nonce', 'nonce');

        $region_id = sanitize_text_field($_POST['region_id'] ?? '');

        if (empty($region_id)) {
            wp_send_json_error(['message' => 'Region requerida']);
            return;
        }

        $mapping = LocationMappingAdmin::getMapping();

        foreach ($mapping as $region) {
            if (($region['id'] ?? '') === $region_id) {
                $comunas = array_values(array_filter($region['comunas'] ?? [], function (array $c): bool {
                    return ($c['warehouse_id'] ?? '') !== 'disabled';
                }));
                wp_send_json_success(['comunas' => $comunas]);
                return;
            }
        }

        wp_send_json_success(['comunas' => []]);
    }
}
