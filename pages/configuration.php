<?php
add_action('admin_menu', 'add_socomarca_menu');

function render_configuration_page() {
    require_once plugin_dir_path(__FILE__) . 'templates/configuration.html';
}

add_action('wp_ajax_validate_connection', 'validateERPConnection');

function validateERPConnection() {
    $erp_authentication = new ErpAuthentication();
    $token = $erp_authentication->authenticate();

    if($token) {
        wp_send_json([
            'success' => true,
            'data' => [
                'message' => 'Conexion correcta. Token creado exitosamente.'
            ]
        ]);
    } else {
        wp_send_json([
            'success' => false,
            'data' => [
                'message' => 'Error al autenticar con el ERP'
            ]
        ]);
    }
    
    wp_die();
}