<?php

namespace Socomarca\RandomERP\Admin;

class AdminPages {
    
    public function __construct() {
        $this->registerHooks();
    }
    
    private function registerHooks() {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }
    
    public function addMenuPages() {
        
        $page = add_menu_page(
            'Socomarca',
            'Socomarca',
            'manage_options',
            'socomarca',
            [$this, 'renderConfigurationPage'],
            'dashicons-admin-settings',
            30
        );
        
    }
    
    public function enqueueAssets($hook) {
        
        if ($hook !== 'toplevel_page_socomarca') {
            return;
        }
        
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;
        
        $admin_css_version = file_exists(SOCOMARCA_ERP_PLUGIN_DIR . 'assets/css/admin.css')
            ? (string) filemtime(SOCOMARCA_ERP_PLUGIN_DIR . 'assets/css/admin.css')
            : SOCOMARCA_ERP_VERSION;
        $admin_js_version = file_exists(SOCOMARCA_ERP_PLUGIN_DIR . 'assets/js/main.js')
            ? (string) filemtime(SOCOMARCA_ERP_PLUGIN_DIR . 'assets/js/main.js')
            : SOCOMARCA_ERP_VERSION;

        wp_enqueue_style(
            'socomarca-admin-css',
            $plugin_url . 'assets/css/admin.css',
            [],
            $admin_css_version
        );
        
        wp_enqueue_script(
            'socomarca-admin-js',
            $plugin_url . 'assets/js/main.js',
            ['jquery'],
            $admin_js_version,
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
        
        $operation_mode = sanitize_text_field($_POST['sm_operation_mode'] ?? 'development');
        $dev_api_url = sanitize_url($_POST['sm_dev_api_url'] ?? '');
        $prod_api_url = sanitize_url($_POST['sm_prod_api_url'] ?? '');
        $api_user = sanitize_email($_POST['sm_api_user'] ?? '');
        $api_password = sanitize_text_field($_POST['sm_api_password'] ?? '');
        $production_token = sanitize_textarea_field($_POST['sm_production_token'] ?? '');
        $company_code = sanitize_text_field($_POST['sm_company_code'] ?? '');
        $company_rut = sanitize_text_field($_POST['sm_company_rut'] ?? '');
        $company_warehouse = sanitize_text_field($_POST['sm_company_warehouse'] ?? '');
        $modalidad = sanitize_text_field($_POST['sm_modalidad'] ?? '');
        $product_type = sanitize_text_field($_POST['sm_product_type'] ?? 'auto');
        $invoice_on_completion = isset($_POST['sm_invoice_on_completion']) ? 1 : 0;
        $cron_enabled = isset($_POST['sm_cron_enabled']) ? 1 : 0;
        $cron_time = sanitize_text_field($_POST['sm_cron_time'] ?? '02:00');
        $product_limit = intval($_POST['sm_product_limit'] ?? -1);
        if ($product_limit < -1) {
            $product_limit = -1;
        }
        $hide_variation_selector = isset($_POST['sm_hide_variation_selector']) ? 1 : 0;
        $hide_warehouse_selector = isset($_POST['sm_hide_warehouse_selector']) ? 1 : 0;
        $asset_version = sanitize_text_field($_POST['sm_asset_version'] ?? SOCOMARCA_ERP_VERSION);
        $default_region = sanitize_text_field($_POST['sm_default_region'] ?? '');
        $default_comuna = sanitize_text_field($_POST['sm_default_comuna'] ?? '');
        
        
        update_option('sm_operation_mode', $operation_mode);
        update_option('sm_dev_api_url', $dev_api_url);
        update_option('sm_prod_api_url', $prod_api_url);
        update_option('sm_api_user', $api_user);
        update_option('sm_api_password', $api_password);
        update_option('sm_production_token', $production_token);
        update_option('sm_company_code', $company_code);
        update_option('sm_company_rut', $company_rut);
        update_option('sm_company_warehouse', $company_warehouse);
        update_option('sm_modalidad', $modalidad);
        update_option('sm_product_type', $product_type);
        update_option('sm_invoice_on_completion', $invoice_on_completion);
        update_option('sm_cron_enabled', $cron_enabled);
        update_option('sm_cron_time', $cron_time);
        update_option('sm_product_limit', $product_limit);
        update_option('sm_hide_variation_selector', $hide_variation_selector);
        update_option('sm_hide_warehouse_selector', $hide_warehouse_selector);
        update_option('sm_asset_version', $asset_version);
        update_option('sm_default_region', $default_region);
        update_option('sm_default_comuna', $default_comuna);
        
        // Reconfigurar el cron job
        $cronService = new \Socomarca\RandomERP\Services\CronSyncService();
        $cronService->scheduleCronJob();
        
        // Limpiar token automático al cambiar configuración
        delete_option('random_erp_token');
        
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
        });
    }
    
    private function getConfiguration() {
        $cronService = new \Socomarca\RandomERP\Services\CronSyncService();
        
        return [
            'operation_mode' => get_option('sm_operation_mode', 'development'),
            'dev_api_url' => get_option('sm_dev_api_url', 'http://seguimiento.random.cl:3003'),
            'prod_api_url' => get_option('sm_prod_api_url', ''),
            'api_user' => get_option('sm_api_user', 'demo@random.cl'),
            'api_password' => get_option('sm_api_password', 'd3m0r4nd0m3RP'),
            'production_token' => get_option('sm_production_token', ''),
            'company_code' => get_option('sm_company_code', ''),
            'company_rut' => get_option('sm_company_rut', ''),
            'company_warehouse' => get_option('sm_company_warehouse', ''),
            'modalidad' => get_option('sm_modalidad', ''),
            'product_type' => get_option('sm_product_type', 'auto'),
            'invoice_on_completion' => get_option('sm_invoice_on_completion', false),
            'cron_enabled' => get_option('sm_cron_enabled', false),
            'cron_time' => get_option('sm_cron_time', '02:00'),
            'product_limit' => intval(get_option('sm_product_limit', -1)),
            'hide_variation_selector' => get_option('sm_hide_variation_selector', false),
            'hide_warehouse_selector' => get_option('sm_hide_warehouse_selector', false),
            'asset_version' => get_option('sm_asset_version', SOCOMARCA_ERP_VERSION),
            'default_region' => get_option('sm_default_region', 'CL-RM'),
            'default_comuna' => get_option('sm_default_comuna', 'Santiago'),
            'last_sync' => $cronService->getLastSyncInfo()
        ];
    }
}
