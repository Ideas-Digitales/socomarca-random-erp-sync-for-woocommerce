<?php

namespace Socomarca\RandomERP\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class UserRegisterShortcode {

    public function __construct() {
        add_shortcode('socomarca_user_registration', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'socomarca_user_registration')) {
            return;
        }

        $plugin_dir = plugin_dir_path(dirname(__DIR__));
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;

        // Enqueue jQuery RUT library (already exists in the plugin)
        wp_enqueue_script(
            'jquery-rut',
            $plugin_url . 'assets/js/jquery.rut.min.js',
            ['jquery'],
            filemtime($plugin_dir . 'assets/js/jquery.rut.min.js'),
            true
        );

        // Enqueue custom registration JS
        wp_enqueue_script(
            'socomarca-user-register',
            $plugin_url . 'assets/js/user-register.js',
            ['jquery', 'jquery-rut'],
            filemtime($plugin_dir . 'assets/js/user-register.js'),
            true
        );

        // Enqueue custom registration CSS
        wp_enqueue_style(
            'socomarca-user-register',
            $plugin_url . 'assets/css/user-register.css',
            [],
            filemtime($plugin_dir . 'assets/css/user-register.css')
        );

        // Localize script with AJAX URL, Nonces, and Regions/Comunas data
        $regions_data = $this->getChileRegionsAndComunas();
        
        wp_localize_script('socomarca-user-register', 'sm_register_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sm_user_register_nonce'),
            'regions'  => $regions_data['regions'],
            'comunas'  => $regions_data['comunas'],
        ]);
    }

    private function getChileRegionsAndComunas(): array {
        $regions = [];
        $comunas = [];

        if (function_exists('WC') && WC()->countries) {
            $regions = WC()->countries->get_states('CL');
        }

        // Fallback regions if WC is not initialized or states empty
        if (empty($regions)) {
            $regions = [
                'CL-RM' => 'Metropolitana de Santiago',
                'CL-AI' => 'Aysén de General Carlos Ibáñez del Campo',
                'CL-AN' => 'Antofagasta',
                'CL-AP' => 'Arica y Parinacota',
                'CL-AR' => 'La Araucanía',
                'CL-AT' => 'Atacama',
                'CL-BI' => 'Bío Bío',
                'CL-CO' => 'Coquimbo',
                'CL-LI' => 'Libertador General Bernardo O\'Higgins',
                'CL-LL' => 'Los Lagos',
                'CL-LR' => 'Los Ríos',
                'CL-MA' => 'Magallanes y de la Antártica Chilena',
                'CL-ML' => 'Maule',
                'CL-NB' => 'Ñuble',
                'CL-TA' => 'Tarapacá',
                'CL-VS' => 'Valparaíso',
            ];
        }

        if (isset($GLOBALS['wc_states_places']) && method_exists($GLOBALS['wc_states_places'], 'get_places')) {
            $comunas = $GLOBALS['wc_states_places']->get_places('CL');
        }

        return [
            'regions' => $regions,
            'comunas' => $comunas,
        ];
    }

    public function render($atts): string {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $my_account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/mi-cuenta');
            ob_start();
            ?>
            <div class="sm-register-logged-in">
                <div class="sm-register-card">
                    <div class="sm-register-success-icon">
                        <svg viewBox="0 0 24 24" width="48" height="48">
                            <path fill="currentColor" d="M12,2C6.48,2 2,6.48 2,12C2,17.52 6.48,22 12,22C17.52,22 22,17.52 22,12C22,6.48 17.52,2 12,2ZM10,17L5,12L6.41,10.59L10,14.17L17.59,6.58L19,8L10,17Z" />
                        </svg>
                    </div>
                    <h3>Sesión iniciada</h3>
                    <p>Ya has iniciado sesión como <strong><?php echo esc_html($current_user->display_name); ?></strong> (<?php echo esc_html($current_user->user_email); ?>).</p>
                    <div class="sm-register-logged-in-actions">
                        <a href="<?php echo esc_url($my_account_url); ?>" class="button btn-primary">Ir a mi cuenta</a>
                        <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="button btn-secondary">Cerrar sesión</a>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        $regions_data = $this->getChileRegionsAndComunas();
        $regions = $regions_data['regions'];

        ob_start();
        ?>
        <div class="sm-register-container">
            <div class="sm-register-card">
                
                <!-- Registration Form -->
                <form id="sm-register-form" method="post" novalidate>
                    
                    <!-- Alert Message Box -->
                    <div id="sm-register-alert" class="sm-alert" style="display: none;"></div>

                    <!-- SECTION 1: Personal & Account Details -->
                    <div class="sm-register-section">
                        <div class="sm-section-heading">
                            <h3>Datos personales y de cuenta</h3>
                        </div>
                        
                        <!-- Client Type Selection -->
                        <div class="sm-field-group">
                            <label class="sm-group-title">Tipo de cliente <span class="sm-required">*</span></label>
                            <div class="sm-client-type-tiles">
                                <label class="sm-type-tile active">
                                    <input type="radio" name="customer_type" value="persona" checked>
                                    <span class="sm-tile-icon">
                                        <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z" /></svg>
                                    </span>
                                    <span class="sm-tile-label">Persona natural</span>
                                    <span class="sm-tile-desc">Compra con boleta</span>
                                </label>
                                <label class="sm-type-tile">
                                    <input type="radio" name="customer_type" value="empresa">
                                    <span class="sm-tile-icon">
                                        <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12,7V3H2V21H22V7H12M6,19H4V17H6V19M6,15H4V13H6V15M6,11H4V9H6V11M6,7H4V5H6V7M10,19H8V17H10V19M10,15H8V13H10V15M10,11H8V9H10V11M10,7H8V5H10V7M20,19H12V9H20V19M18,11H14V13H18V11M18,15H14V17H18V15" /></svg>
                                    </span>
                                    <span class="sm-tile-label">Empresa</span>
                                    <span class="sm-tile-desc">Compra con factura</span>
                                </label>
                            </div>
                        </div>

                        <!-- RUT Field (Always required, validated in real-time, max 12 chars) -->
                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_rut">RUT <span class="sm-required">*</span></label>
                                <input type="text" name="rut" id="reg_rut" class="sm-input" placeholder="12.345.678-9" required autocomplete="off" maxlength="12">
                                <span class="sm-field-error" id="error_reg_rut"></span>
                                <span class="sm-field-success" id="success_reg_rut"></span>
                            </div>
                        </div>

                        <!-- Company specific fields (Hidden by default, shown when Empresa selected) -->
                        <div id="sm-company-fields-wrapper" style="display: none;">
                            <div class="sm-field-row">
                                <div class="sm-field">
                                    <label for="reg_business_name">Razón social <span class="sm-required">*</span></label>
                                    <input type="text" name="business_name" id="reg_business_name" class="sm-input" placeholder="Nombre legal de la empresa">
                                    <span class="sm-field-error" id="error_reg_business_name"></span>
                                </div>
                            </div>
                            <div class="sm-field-row">
                                <div class="sm-field">
                                    <label for="reg_giro">Giro <span class="sm-required">*</span></label>
                                    <input type="text" name="giro" id="reg_giro" class="sm-input" placeholder="Giro de la empresa">
                                    <span class="sm-field-error" id="error_reg_giro"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Name and Last Name -->
                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_first_name">Nombre <span class="sm-required">*</span></label>
                                <input type="text" name="first_name" id="reg_first_name" class="sm-input" placeholder="Tu nombre" required>
                                <span class="sm-field-error" id="error_reg_first_name"></span>
                            </div>
                            <div class="sm-field">
                                <label for="reg_last_name">Apellido <span class="sm-required">*</span></label>
                                <input type="text" name="last_name" id="reg_last_name" class="sm-input" placeholder="Tu apellido" required>
                                <span class="sm-field-error" id="error_reg_last_name"></span>
                            </div>
                        </div>

                        <!-- Email & Phone -->
                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_email">Correo electrónico <span class="sm-required">*</span></label>
                                <input type="email" name="email" id="reg_email" class="sm-input" placeholder="ejemplo@correo.com" required autocomplete="email">
                                <span class="sm-field-error" id="error_reg_email"></span>
                                <span class="sm-field-success" id="success_reg_email"></span>
                            </div>
                            <div class="sm-field">
                                <label for="reg_phone">Teléfono <span class="sm-required">*</span></label>
                                <input type="tel" name="phone" id="reg_phone" class="sm-input" placeholder="+56 9 1234 5678" required autocomplete="tel">
                                <span class="sm-field-error" id="error_reg_phone"></span>
                            </div>
                        </div>

                        <!-- Passwords -->
                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_password">Contraseña <span class="sm-required">*</span></label>
                                <input type="password" name="password" id="reg_password" class="sm-input" placeholder="Mínimo 6 caracteres" required autocomplete="new-password">
                                <span class="sm-field-error" id="error_reg_password"></span>
                            </div>
                            <div class="sm-field">
                                <label for="reg_password_confirm">Confirmar contraseña <span class="sm-required">*</span></label>
                                <input type="password" name="password_confirm" id="reg_password_confirm" class="sm-input" placeholder="Repite tu contraseña" required autocomplete="new-password">
                                <span class="sm-field-error" id="error_reg_password_confirm"></span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Billing Address -->
                    <div class="sm-register-section">
                        <div class="sm-section-heading">
                            <h3>Dirección de facturación</h3>
                        </div>

                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_billing_country">País <span class="sm-required">*</span></label>
                                <select name="billing_country" id="reg_billing_country" class="sm-select" required>
                                    <option value="CL" selected>Chile</option>
                                </select>
                            </div>
                            <div class="sm-field">
                                <label for="reg_billing_state">Región <span class="sm-required">*</span></label>
                                <select name="billing_state" id="reg_billing_state" class="sm-select" required>
                                    <option value="">-- Selecciona una región --</option>
                                    <?php foreach ($regions as $code => $name): ?>
                                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="sm-field-error" id="error_reg_billing_state"></span>
                            </div>
                        </div>

                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_billing_city">Comuna / Ciudad <span class="sm-required">*</span></label>
                                <select name="billing_city" id="reg_billing_city" class="sm-select" required disabled>
                                    <option value="">-- Selecciona una región primero --</option>
                                </select>
                                <span class="sm-field-error" id="error_reg_billing_city"></span>
                            </div>
                        </div>

                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_billing_address_1">Dirección (Calle, número, departamento) <span class="sm-required">*</span></label>
                                <input type="text" name="billing_address_1" id="reg_billing_address_1" class="sm-input" placeholder="Ej: Av. Providencia 1234, Of. 501" required>
                                <span class="sm-field-error" id="error_reg_billing_address_1"></span>
                            </div>
                        </div>

                        <!-- Same Address Checkbox -->
                        <div class="sm-field-row sm-checkbox-row">
                            <label class="sm-checkbox-container">
                                <input type="checkbox" name="ship_to_different_address" id="reg_ship_to_different_address" value="0" checked>
                                <span class="sm-checkbox-checkmark"></span>
                                <span class="sm-checkbox-label">La dirección de envío es la misma que la de facturación</span>
                            </label>
                        </div>
                    </div>

                    <!-- SECTION 3: Shipping Address (Hidden dynamically via JS when checkbox is checked) -->
                    <div class="sm-register-section" id="sm-shipping-section-wrapper" style="display: none;">
                        <div class="sm-section-heading">
                            <h3>Dirección de envío</h3>
                        </div>

                        <!-- Shipping Name fields -->
                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_shipping_first_name">Nombre para el envío <span class="sm-required">*</span></label>
                                <input type="text" name="shipping_first_name" id="reg_shipping_first_name" class="sm-input" placeholder="Nombre de quien recibe">
                                <span class="sm-field-error" id="error_reg_shipping_first_name"></span>
                            </div>
                            <div class="sm-field">
                                <label for="reg_shipping_last_name">Apellido para el envío <span class="sm-required">*</span></label>
                                <input type="text" name="shipping_last_name" id="reg_shipping_last_name" class="sm-input" placeholder="Apellido de quien recibe">
                                <span class="sm-field-error" id="error_reg_shipping_last_name"></span>
                            </div>
                        </div>

                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_shipping_country">País <span class="sm-required">*</span></label>
                                <select name="shipping_country" id="reg_shipping_country" class="sm-select">
                                    <option value="CL" selected>Chile</option>
                                </select>
                            </div>
                            <div class="sm-field">
                                <label for="reg_shipping_state">Región <span class="sm-required">*</span></label>
                                <select name="shipping_state" id="reg_shipping_state" class="sm-select">
                                    <option value="">-- Selecciona una región --</option>
                                    <?php foreach ($regions as $code => $name): ?>
                                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="sm-field-error" id="error_reg_shipping_state"></span>
                            </div>
                        </div>

                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_shipping_city">Comuna / Ciudad <span class="sm-required">*</span></label>
                                <select name="shipping_city" id="reg_shipping_city" class="sm-select">
                                    <option value="">-- Selecciona una región primero --</option>
                                </select>
                                <span class="sm-field-error" id="error_reg_shipping_city"></span>
                            </div>
                        </div>

                        <div class="sm-field-row">
                            <div class="sm-field">
                                <label for="reg_shipping_address_1">Dirección de despacho <span class="sm-required">*</span></label>
                                <input type="text" name="shipping_address_1" id="reg_shipping_address_1" class="sm-input" placeholder="Ej: Av. Vitacura 4321, Depto 102">
                                <span class="sm-field-error" id="error_reg_shipping_address_1"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Action Button -->
                    <div class="sm-register-actions">
                        <button type="submit" id="sm-btn-submit" class="sm-btn sm-btn-primary">
                            Registrarse <span class="sm-spinner" style="display: none;"></span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
