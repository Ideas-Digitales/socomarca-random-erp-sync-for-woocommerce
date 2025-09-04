<?php

namespace Socomarca\RandomERP\Admin;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class AdminPages {
    
    public function __construct() {
        $this->registerHooks();
    }
    
    private function registerHooks() {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('add_meta_boxes', [$this, 'addInvoiceViewMetaBox']);
        add_action('wp_ajax_sm_clear_production_token', [$this, 'clearProductionToken']);
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
        $plugin_url = SOCOMARCA_ERP_PLUGIN_URL;
        
        // Enqueue invoice download script for order pages
        if (get_post_type() === 'shop_order') {
            wp_enqueue_script(
                'sm-invoice-download',
                $plugin_url . 'assets/js/invoice-download.js',
                ['jquery'],
                '1.0.0',
                true
            );
            
            wp_localize_script('sm-invoice-download', 'smInvoiceDownloadData', [
                'nonce' => wp_create_nonce('sm_download_invoice'),
                'ajaxurl' => admin_url('admin-ajax.php')
            ]);
        }
        
        if ($hook !== 'toplevel_page_socomarca') {
            return;
        }
        
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
        $debug_enabled = isset($_POST['sm_debug_enabled']) ? 1 : 0;
        
        
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
        update_option('sm_debug_enabled', $debug_enabled);
        
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
            'debug_enabled' => get_option('sm_debug_enabled', false),
            'last_sync' => $cronService->getLastSyncInfo()
        ];
    }

    public function addInvoiceViewMetaBox() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'sm_invoice_view',
            __('Random ERP - Factura'),
            [$this, 'renderInvoiceViewMetaBox'],
            $screen,
            'side',
            'high'
        );
    }

    public function renderInvoiceViewMetaBox($object) {
        // Get the WC_Order object
        $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;
        
        if (!$order) {
            echo '<p>' . __('No se pudo cargar la orden.') . '</p>';
            return;
        }

        // Get the Random ERP document ID
        $idmaeedo = $order->get_meta('_random_erp_idmaeedo');
        
        if ($idmaeedo) {
            echo '<div style="padding: 10px 0;">';
            echo '<p><strong>' . __('ID Documento:') . '</strong> ' . esc_html($idmaeedo) . '</p>';
            echo '<button type="button" id="sm-download-invoice" class="button button-primary" data-idmaeedo="' . esc_attr($idmaeedo) . '">' . __('Ver Factura') . '</button>';
            echo '<p class="description" style="margin-top: 10px;">' . __('Documento creado en Random ERP.') . '</p>';
            echo '</div>';
        } else {
            echo '<div style="padding: 10px 0;">';
            echo '<p class="description">' . __('No hay factura asociada a esta orden.') . '</p>';
            echo '</div>';
        }
    }

    public function clearProductionToken() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sm_clear_token')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        delete_option('sm_production_token');
        
        wp_send_json_success('Token cleared successfully');
    }
}