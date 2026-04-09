<?php

namespace Socomarca\RandomERP\Shortcodes;

use Socomarca\RandomERP\Admin\LocationMappingAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class LocationStockShortcode {

    public function __construct() {
        add_shortcode('socomarca_location_stock', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'sm-location-popup',
            SOCOMARCA_ERP_PLUGIN_URL . 'assets/css/location-stock-popup.css',
            [],
            SOCOMARCA_ERP_VERSION
        );

        wp_enqueue_script(
            'sm-location-popup',
            SOCOMARCA_ERP_PLUGIN_URL . 'assets/js/location-stock-popup.js',
            ['jquery'],
            SOCOMARCA_ERP_VERSION,
            true
        );

        $display = $this->getSelectedLocationDisplay();

        wp_localize_script('sm-location-popup', 'sm_location_popup', [
            'ajax_url'        => admin_url('admin-ajax.php'),
            'multiloca_nonce' => wp_create_nonce('multiloca_lite_nonce'),
            'popup_nonce'     => wp_create_nonce('sm_location_popup_nonce'),
            'selected_region' => $display['region_id'] ?? '',
            'selected_comuna' => $display['comuna_id'] ?? '',
        ]);
    }

    public function render(array $atts): string {
        $atts = shortcode_atts([
            'button_text' => 'Seleccionar ubicacion',
        ], $atts, 'socomarca_location_stock');

        $mapping     = LocationMappingAdmin::getMapping();
        $display     = $this->getSelectedLocationDisplay();
        $has_selected = !empty($display['comuna_name']);

        ob_start();
        ?>
        <div class="sm-location-popup-wrapper">
            <button type="button" class="sm-location-popup-trigger button">
                <?php if ($has_selected): ?>
                    <?php echo esc_html($display['region_name'] . ' - ' . $display['comuna_name']); ?>
                    <span class="sm-trigger-change">(cambiar)</span>
                <?php else: ?>
                    <?php echo esc_html($atts['button_text']); ?>
                <?php endif; ?>
            </button>
        </div>

        <div class="sm-location-modal" id="sm-location-modal" style="display:none;" aria-modal="true" role="dialog">
            <div class="sm-location-modal-backdrop"></div>
            <div class="sm-location-modal-container">
                <div class="sm-location-modal-header">
                    <h3>Seleccione ubicacion para envio</h3>
                    <button type="button" class="sm-location-modal-close" aria-label="Cerrar">&times;</button>
                </div>
                <div class="sm-location-modal-body">
                    <?php if (empty($mapping)): ?>
                        <p class="sm-no-locations">No hay regiones configuradas. Configure las ubicaciones en el panel de administracion.</p>
                    <?php else: ?>
                        <div class="sm-location-form">
                            <div class="sm-location-field">
                                <label for="sm-region-select">Region:</label>
                                <select id="sm-region-select">
                                    <option value="">-- Seleccione una region --</option>
                                    <?php foreach ($mapping as $region): ?>
                                        <option value="<?php echo esc_attr($region['id']); ?>"
                                            <?php selected($display['region_id'] ?? '', $region['id']); ?>>
                                            <?php echo esc_html($region['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sm-location-field">
                                <label for="sm-comuna-select">Comuna:</label>
                                <select id="sm-comuna-select" disabled>
                                    <option value="">-- Seleccione una region primero --</option>
                                </select>
                            </div>
                            <div class="sm-location-actions">
                                <button type="button" class="sm-location-confirm button button-primary" disabled>
                                    Confirmar ubicacion
                                </button>
                                <span class="sm-location-loading" style="display:none;">Cargando...</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function getSelectedLocationDisplay(): array {
        $cookie = $_COOKIE['sm_selected_location'] ?? '';
        if (!empty($cookie)) {
            $data = json_decode(stripslashes($cookie), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
        return [];
    }
}
