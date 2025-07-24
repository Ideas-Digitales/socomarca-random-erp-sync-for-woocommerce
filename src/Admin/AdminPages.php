<?php

namespace Socomarca\RandomERP\Admin;

class AdminPages {
    
    public function __construct() {
        $this->registerHooks();
    }
    
    private function registerHooks() {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        error_log('Socomarca ERP: AdminPages hooks registrados');
    }
    
    public function addMenuPages() {
        error_log('Socomarca ERP: addMenuPages() ejecutándose');
        
        $page = add_menu_page(
            'Socomarca',
            'Socomarca',
            'manage_options',
            'socomarca',
            [$this, 'renderConfigurationPage'],
            'dashicons-admin-settings',
            30
        );
        
        error_log('Socomarca ERP: Menú añadido con resultado: ' . ($page ? $page : 'false'));
    }
    
    public function enqueueAssets($hook) {
        
        if ($hook !== 'toplevel_page_socomarca') {
            return;
        }
        
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;
        
        wp_enqueue_style(
            'socomarca-admin-css',
            $plugin_url . 'assets/css/admin.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'socomarca-admin-js',
            $plugin_url . 'assets/js/main.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        
        wp_localize_script('socomarca-admin-js', 'socomarca_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('socomarca_nonce')
        ]);
    }
    
    public function renderConfigurationPage() {
        
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'socomarca_config')) {
            $this->saveConfiguration();
        }
        
        
        $config = $this->getConfiguration();
        
        
        $template_path = SOCOMARCA_ERP_PLUGIN_DIR . 'templates/configuration.php';
        if (file_exists($template_path)) {
            
            extract($config);
            include $template_path;
        } else {
            echo '<div class="error"><p>Error: No se pudo cargar el template de configuración.</p></div>';
        }
    }
    
    private function saveConfiguration() {
        
        $api_url = sanitize_url($_POST['sm_api_url'] ?? '');
        $api_user = sanitize_email($_POST['sm_api_user'] ?? '');
        $api_password = sanitize_text_field($_POST['sm_api_password'] ?? '');
        $company_code = sanitize_text_field($_POST['sm_company_code'] ?? '');
        $company_rut = sanitize_text_field($_POST['sm_company_rut'] ?? '');
        $company_warehouse = sanitize_text_field($_POST['sm_company_warehouse'] ?? '');
        $product_type = sanitize_text_field($_POST['sm_product_type'] ?? 'auto');
        
        
        update_option('sm_api_url', $api_url);
        update_option('sm_api_user', $api_user);
        update_option('sm_api_password', $api_password);
        update_option('sm_company_code', $company_code);
        update_option('sm_company_rut', $company_rut);
        update_option('sm_company_warehouse', $company_warehouse);
        update_option('sm_product_type', $product_type);
        
        
        delete_option('random_erp_token');
        
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
        });
    }
    
    private function getConfiguration() {
        return [
            'api_url' => get_option('sm_api_url', ''),
            'api_user' => get_option('sm_api_user', ''),
            'api_password' => get_option('sm_api_password', ''),
            'company_code' => get_option('sm_company_code', ''),
            'company_rut' => get_option('sm_company_rut', ''),
            'company_warehouse' => get_option('sm_company_warehouse', ''),
            'product_type' => get_option('sm_product_type', 'auto')
        ];
    }
}