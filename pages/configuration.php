<?php
add_action('admin_menu', 'add_socomarca_menu');

function render_configuration_page() {
    if(isset($_POST['submit'])) {
        update_option('sm_company_code', $_POST['sm_company_code']);
        update_option('sm_company_rut', $_POST['sm_company_rut']);
        update_option('sm_api_url', $_POST['sm_api_url']);
        update_option('sm_api_user', $_POST['sm_api_user']);
        update_option('sm_api_password', $_POST['sm_api_password']);
    }
    $company_code = get_option('sm_company_code', '');      
    $company_rut = get_option('sm_company_rut', '');
    $api_url = get_option('sm_api_url', '');
    $api_user = get_option('sm_api_user', '');
    $api_password = get_option('sm_api_password', '');
    require_once plugin_dir_path(__FILE__) . 'templates/configuration.php';
}

add_action('wp_ajax_validate_connection', 'validateERPConnection');
add_action('wp_ajax_sm_get_entities', 'getEntities');
add_action('wp_ajax_sm_process_batch_users', 'processBatchUsers');
add_action('wp_ajax_sm_delete_all_users', 'deleteAllUsers');

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

//Obtiene las entidades del ERP e inicia el proceso de creación de usuarios
function getEntities() {
    error_log('getEntities: Iniciando proceso');
    
    $erp_authentication = new ErpAuthentication();
    $result = $erp_authentication->createUsersFromEntities();
    
    error_log('getEntities: Resultado - ' . print_r($result, true));
    
    if($result['success']) {
        wp_send_json([
            'success' => true,
            'data' => [
                'message' => $result['message'],
                'total' => $result['total']
            ]
        ]);
    } else {
        wp_send_json([
            'success' => false,
            'data' => [
                'message' => $result['message']
            ]
        ]);
    }
    wp_die();
}

//Procesa un lote de usuarios
function processBatchUsers() {
    $offset = intval(isset($_POST['offset']) ? $_POST['offset'] : 0);
    $batch_size = intval(isset($_POST['batch_size']) ? $_POST['batch_size'] : 10);
    
    error_log("processBatchUsers: offset=$offset, batch_size=$batch_size");
    
    $erp_authentication = new ErpAuthentication();
    $result = $erp_authentication->processBatchUsers($offset, $batch_size);
    
    error_log('processBatchUsers: Resultado - ' . print_r($result, true));
    
    wp_send_json([
        'success' => $result['success'],
        'data' => $result
    ]);
    wp_die();
}

//Elimina todos los usuarios excepto administradores
function deleteAllUsers() {
    // Verificar que el usuario actual sea administrador
    if (!current_user_can('manage_options')) {
        wp_send_json([
            'success' => false,
            'data' => [
                'message' => 'No tienes permisos para realizar esta acción'
            ]
        ]);
        wp_die();
    }
    
    // Verificar confirmación
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    if ($confirm !== 'DELETE_ALL_USERS') {
        wp_send_json([
            'success' => false,
            'data' => [
                'message' => 'Confirmación requerida'
            ]
        ]);
        wp_die();
    }
    
    error_log('deleteAllUsers: Iniciando eliminación masiva de usuarios');
    
    $erp_authentication = new ErpAuthentication();
    $result = $erp_authentication->deleteAllUsersExceptAdmin();
    
    error_log('deleteAllUsers: Resultado - ' . print_r($result, true));
    
    wp_send_json([
        'success' => $result['success'],
        'data' => $result
    ]);
    wp_die();
}