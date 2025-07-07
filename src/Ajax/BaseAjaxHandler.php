<?php

namespace Socomarca\RandomERP\Ajax;

abstract class BaseAjaxHandler {
    
    public function __construct() {
        $this->registerHooks();
    }
    
    abstract protected function registerHooks();
    
    protected function sendJsonResponse($success, $data) {
        wp_send_json([
            'success' => $success,
            'data' => $data
        ]);
    }
    
    protected function sendErrorResponse($message) {
        $this->sendJsonResponse(false, ['message' => $message]);
    }
    
    protected function sendSuccessResponse($data) {
        $this->sendJsonResponse(true, $data);
    }
    
    protected function requireAdminPermissions() {
        if (!current_user_can('manage_options')) {
            $this->sendErrorResponse('No tienes permisos para realizar esta acción');
            wp_die();
        }
    }
    
    protected function requireConfirmation($required_text) {
        $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
        if ($confirm !== $required_text) {
            $this->sendErrorResponse('Confirmación requerida');
            wp_die();
        }
    }
    
    protected function logAction($message) {
        error_log(static::class . ': ' . $message);
    }
}