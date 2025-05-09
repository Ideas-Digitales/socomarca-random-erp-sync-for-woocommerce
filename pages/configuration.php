<?php
add_action('admin_menu', 'add_socomarca_menu');

function render_configuration_page() {
    require_once plugin_dir_path(__FILE__) . 'templates/configuration.html';
}

add_action('wp_ajax_validate_connection', 'validateERPConnection');
add_action('wp_ajax_sm_get_entities', 'getEntities');

//Valida la conexión con el ERP
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

//Obtiene las entidades del ERP
function getEntities() {
    $erp_authentication = new ErpAuthentication();
    $entities = $erp_authentication->getEntities();
    
    if($entities) {
        wp_send_json([
            'success' => true,
            'data' => [
                'message' => $entities['quantity'] . ' entidades obtenidas. Sincronizando...',
                'quantity' => $entities['quantity'],
                'items' => $entities['items']
            ]
        ]);
    } else {
        wp_send_json([
            'success' => false,
            'data' => [
                'message' => 'Error al obtener las entidades'
            ]
        ]);
    }
    wp_die();
}