<?php

namespace Socomarca\RandomERP\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

class UserRegisterAjaxHandler extends BaseAjaxHandler {

    public function __construct() {
        parent::__construct();
    }

    protected function registerHooks() {
        // Form submission handlers
        add_action('wp_ajax_sm_register_user', [$this, 'registerUser']);
        add_action('wp_ajax_nopriv_sm_register_user', [$this, 'registerUser']);

        // Real-time checks
        add_action('wp_ajax_sm_check_email', [$this, 'checkEmail']);
        add_action('wp_ajax_nopriv_sm_check_email', [$this, 'checkEmail']);

        add_action('wp_ajax_sm_check_rut', [$this, 'checkRut']);
        add_action('wp_ajax_nopriv_sm_check_rut', [$this, 'checkRut']);
    }

    public function checkEmail(): void {
        check_ajax_referer('sm_user_register_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (empty($email)) {
            $this->sendErrorResponse('El correo electrónico es requerido.');
            wp_die();
        }

        if (!is_email($email)) {
            $this->sendErrorResponse('El correo electrónico ingresado es inválido.');
            wp_die();
        }

        if (email_exists($email)) {
            $this->sendErrorResponse('El correo electrónico ya está registrado.');
            wp_die();
        }

        $this->sendSuccessResponse(['message' => 'Correo disponible.']);
        wp_die();
    }

    public function checkRut(): void {
        check_ajax_referer('sm_user_register_nonce', 'nonce');

        $rut = isset($_POST['rut']) ? sanitize_text_field(wp_unslash($_POST['rut'])) : '';

        if (empty($rut)) {
            $this->sendErrorResponse('El RUT es requerido.');
            wp_die();
        }

        if (strlen($rut) > 12) {
            $this->sendErrorResponse('El RUT no puede superar los 12 caracteres.');
            wp_die();
        }

        if (!$this->validateRut($rut)) {
            $this->sendErrorResponse('El RUT ingresado es inválido.');
            wp_die();
        }

        $normalized_rut = preg_replace('/[^0-9kK]/', '', $rut);

        // Check if username already exists or meta key 'rut' is registered
        $existing_user_by_login = username_exists($normalized_rut);
        $existing_user_by_meta = get_users([
            'meta_key'   => 'rut',
            'meta_value' => $normalized_rut,
            'number'     => 1
        ]);

        if ($existing_user_by_login || !empty($existing_user_by_meta)) {
            $this->sendErrorResponse('El RUT ya está registrado.');
            wp_die();
        }

        $this->sendSuccessResponse(['message' => 'RUT disponible.']);
        wp_die();
    }

    public function registerUser(): void {
        try {
            check_ajax_referer('sm_user_register_nonce', 'nonce');

            $errors = [];

            // 1. Sanitize & Retrieve Fields
            $customer_type = isset($_POST['customer_type']) ? sanitize_text_field(wp_unslash($_POST['customer_type'])) : 'persona';
            $rut           = isset($_POST['rut']) ? sanitize_text_field(wp_unslash($_POST['rut'])) : '';
            
            $business_name = isset($_POST['business_name']) ? sanitize_text_field(wp_unslash($_POST['business_name'])) : '';
            $giro          = isset($_POST['giro']) ? sanitize_text_field(wp_unslash($_POST['giro'])) : '';

            $first_name       = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
            $last_name        = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
            $email            = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $phone            = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
            $password         = isset($_POST['password']) ? $_POST['password'] : '';
            $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

            // Billing
            $billing_country   = isset($_POST['billing_country']) ? sanitize_text_field(wp_unslash($_POST['billing_country'])) : 'CL';
            $billing_state     = isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '';
            $billing_city      = isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '';
            $billing_address_1 = isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '';

            // Shipping Flag
            $ship_to_different_address = isset($_POST['ship_to_different_address']) && $_POST['ship_to_different_address'] === '1';

            // Shipping fields
            $shipping_first_name = isset($_POST['shipping_first_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_first_name'])) : '';
            $shipping_last_name  = isset($_POST['shipping_last_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_last_name'])) : '';
            $shipping_country    = isset($_POST['shipping_country']) ? sanitize_text_field(wp_unslash($_POST['shipping_country'])) : 'CL';
            $shipping_state      = isset($_POST['shipping_state']) ? sanitize_text_field(wp_unslash($_POST['shipping_state'])) : '';
            $shipping_city       = isset($_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '';
            $shipping_address_1  = isset($_POST['shipping_address_1']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_1'])) : '';

            // 2. Validate Step 1 - Personal & Business Details
            if (empty($rut)) {
                $errors['rut'] = 'El RUT es requerido.';
            } elseif (strlen($rut) > 12) {
                $errors['rut'] = 'El RUT no puede superar los 12 caracteres.';
            } elseif (!$this->validateRut($rut)) {
                $errors['rut'] = 'El RUT ingresado es inválido.';
            } else {
                $normalized_rut = preg_replace('/[^0-9kK]/', '', $rut);
                if (username_exists($normalized_rut) || !empty(get_users(['meta_key' => 'rut', 'meta_value' => $normalized_rut, 'number' => 1]))) {
                    $errors['rut'] = 'El RUT ya está registrado.';
                }
            }

            if ($customer_type === 'empresa') {
                if (empty($business_name)) {
                    $errors['business_name'] = 'La Razón Social es requerida para empresas.';
                }
                if (empty($giro)) {
                    $errors['giro'] = 'El Giro es requerido para empresas.';
                }
            }

            if (empty($first_name)) {
                $errors['first_name'] = 'El Nombre es requerido.';
            }
            if (empty($last_name)) {
                $errors['last_name'] = 'El Apellido es requerido.';
            }

            if (empty($email)) {
                $errors['email'] = 'El Correo electrónico es requerido.';
            } elseif (!is_email($email)) {
                $errors['email'] = 'El Correo electrónico ingresado es inválido.';
            } elseif (email_exists($email)) {
                $errors['email'] = 'El Correo electrónico ya está registrado.';
            }

            if (empty($phone)) {
                $errors['phone'] = 'El Teléfono es requerido.';
            }

            if (empty($password)) {
                $errors['password'] = 'La Contraseña es requerida.';
            } elseif (strlen($password) < 6) {
                $errors['password'] = 'La Contraseña debe tener al menos 6 caracteres.';
            }

            if ($password !== $password_confirm) {
                $errors['password_confirm'] = 'Las contraseñas no coinciden.';
            }

            // 3. Validate Step 2 - Billing Address
            if (empty($billing_state)) {
                $errors['billing_state'] = 'La Región de facturación es requerida.';
            }
            if (empty($billing_city)) {
                $errors['billing_city'] = 'La Comuna de facturación es requerida.';
            }
            if (empty($billing_address_1)) {
                $errors['billing_address_1'] = 'La Dirección de facturación es requerida.';
            }

            // 4. Validate Step 3 - Shipping Address (if different)
            if ($ship_to_different_address) {
                if (empty($shipping_first_name)) {
                    $errors['shipping_first_name'] = 'El Nombre de envío es requerido.';
                }
                if (empty($shipping_last_name)) {
                    $errors['shipping_last_name'] = 'El Apellido de envío es requerido.';
                }
                if (empty($shipping_state)) {
                    $errors['shipping_state'] = 'La Región de envío es requerida.';
                }
                if (empty($shipping_city)) {
                    $errors['shipping_city'] = 'La Comuna de envío es requerida.';
                }
                if (empty($shipping_address_1)) {
                    $errors['shipping_address_1'] = 'La Dirección de envío es requerida.';
                }
            }

            // Return errors if any
            if (!empty($errors)) {
                $this->sendJsonResponse(false, [
                    'message' => 'Por favor corrige los errores en el formulario.',
                    'errors'  => $errors
                ]);
                wp_die();
            }

            // 5. Create User
            $normalized_rut = preg_replace('/[^0-9kK]/', '', $rut);
            $user_data = [
                'user_login'   => $normalized_rut,
                'user_email'   => $email,
                'user_pass'    => $password,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => ($customer_type === 'empresa') ? $business_name : "$first_name $last_name",
                'role'         => 'customer',
            ];

            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                $this->sendErrorResponse('Error al crear el usuario: ' . $user_id->get_error_message());
                wp_die();
            }

            // 6. Save User Meta Fields
            // ERP and B2B identification
            update_user_meta($user_id, 'rut', $normalized_rut);
            update_user_meta($user_id, 'billing_rut', $rut);
            update_user_meta($user_id, 'sm_rut', $rut);
            update_user_meta($user_id, 'tipo_cliente', $customer_type);
            update_user_meta($user_id, 'sm_documento_tipo', ($customer_type === 'empresa' ? 'factura' : 'boleta'));
            update_user_meta($user_id, 'is_active', true);

            if ($customer_type === 'empresa') {
                update_user_meta($user_id, 'business_name', $business_name);
                update_user_meta($user_id, 'billing_company', $business_name);
                update_user_meta($user_id, 'giro', $giro);
                update_user_meta($user_id, 'billing_giro', $giro);
                update_user_meta($user_id, 'sm_giro', $giro);
                update_user_meta($user_id, 'sm_razon_social', $business_name);

                // B2B King integration: flag as B2B user
                update_user_meta($user_id, 'b2bking_b2buser', 'yes');
            } else {
                // Retail user
                update_user_meta($user_id, 'b2bking_b2buser', 'no');
            }

            // Phone & general profile meta
            update_user_meta($user_id, 'phone', $phone);

            // Billing addresses
            update_user_meta($user_id, 'billing_first_name', $first_name);
            update_user_meta($user_id, 'billing_last_name', $last_name);
            update_user_meta($user_id, 'billing_email', $email);
            update_user_meta($user_id, 'billing_phone', $phone);
            update_user_meta($user_id, 'billing_country', $billing_country);
            update_user_meta($user_id, 'billing_state', $billing_state);
            update_user_meta($user_id, 'billing_city', $billing_city);
            update_user_meta($user_id, 'billing_address_1', $billing_address_1);

            // Shipping addresses
            if (!$ship_to_different_address) {
                update_user_meta($user_id, 'shipping_first_name', $first_name);
                update_user_meta($user_id, 'shipping_last_name', $last_name);
                update_user_meta($user_id, 'shipping_country', $billing_country);
                update_user_meta($user_id, 'shipping_state', $billing_state);
                update_user_meta($user_id, 'shipping_city', $billing_city);
                update_user_meta($user_id, 'shipping_address_1', $billing_address_1);
            } else {
                update_user_meta($user_id, 'shipping_first_name', $shipping_first_name);
                update_user_meta($user_id, 'shipping_last_name', $shipping_last_name);
                update_user_meta($user_id, 'shipping_country', $shipping_country);
                update_user_meta($user_id, 'shipping_state', $shipping_state);
                update_user_meta($user_id, 'shipping_city', $shipping_city);
                update_user_meta($user_id, 'shipping_address_1', $shipping_address_1);
            }

            // 7. Sync with Random ERP
            $entityService = new \Socomarca\RandomERP\Services\EntityService();
            
            $koen_code = $normalized_rut;
            $suen = get_option('sm_suen', 'BLB');
            $koen_template = get_option('sm_koen_template', 'BOLETA');
            $region_num = $entityService->getRegionNumberByState($billing_state);
            
            $payload = [
                'koen'         => $koen_code,
                'rten'         => $rut, // original RUT formatted
                'suen'         => $suen,
                'ter1'         => 'CHL',
                'ter2'         => $region_num,
                'foen'         => preg_replace('/[^0-9]/', '', $phone),
                'dien'         => $billing_address_1,
                'email'        => $email,
                'koenTemplate' => $koen_template
            ];
            
            $erp_response = $entityService->createEntity($payload);
            
            if ($erp_response === false || (isset($erp_response['error']) && $erp_response['error'] !== false)) {
                // Log failure
                error_log('UserRegisterAjaxHandler - ERP Entity Sync Failed for RUT ' . $rut . ': ' . print_r($erp_response, true));
                
                // Rollback: delete the WordPress user
                if (!function_exists('wp_delete_user')) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }
                wp_delete_user($user_id);
                
                $error_msg = 'No se pudo sincronizar la entidad con el ERP.';
                if (is_array($erp_response) && !empty($erp_response['message'])) {
                    $error_msg = $erp_response['message'];
                }
                
                $this->sendErrorResponse('Error al registrar en el ERP: ' . $error_msg);
                wp_die();
            }
            
            // Save the returned KOEN ERP code in the user metadata
            $returned_koen = isset($erp_response['KOEN']) ? $erp_response['KOEN'] : $koen_code;
            update_user_meta($user_id, 'random_erp_entity_code', $returned_koen);

            // 8. Auto Login
            clean_user_cache($user_id);
            wp_clear_auth_cookie();
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            
            if (function_exists('WC') && WC()->session) {
                wc_set_customer_auth_cookie($user_id);
            }

            // 8. Redirect URL
            $redirect_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url();

            $this->sendSuccessResponse([
                'message'      => '¡Registro exitoso! Iniciando sesión...',
                'redirect_url' => $redirect_url
            ]);
            wp_die();
        } catch (\Throwable $e) {
            error_log('UserRegisterAjaxHandler Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->sendErrorResponse('Error interno del servidor: ' . $e->getMessage() . ' (en línea ' . $e->getLine() . ' de ' . basename($e->getFile()) . ')');
            wp_die();
        }
    }

    private function validateRut(string $rut): bool {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        $rut_body = substr($rut, 0, -1);
        $rut_check = strtoupper(substr($rut, -1));

        if (empty($rut_body) || !is_numeric($rut_body)) {
            return false;
        }

        $sum = 0;
        $multiplier = 2;

        for ($i = strlen($rut_body) - 1; $i >= 0; $i--) {
            $sum += (int)$rut_body[$i] * $multiplier;
            $multiplier++;
            if ($multiplier > 7) {
                $multiplier = 2;
            }
        }

        $check_digit = 11 - ($sum % 11);

        if ($check_digit == 11) {
            $check_digit = '0';
        } elseif ($check_digit == 10) {
            $check_digit = 'K';
        } else {
            $check_digit = (string)$check_digit;
        }

        return $check_digit === $rut_check;
    }
}
