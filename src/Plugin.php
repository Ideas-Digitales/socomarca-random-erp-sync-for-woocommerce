<?php

namespace Socomarca\RandomERP;

use Socomarca\RandomERP\Ajax\AuthAjaxHandler;
use Socomarca\RandomERP\Ajax\EntityAjaxHandler;
use Socomarca\RandomERP\Ajax\CategoryAjaxHandler;
use Socomarca\RandomERP\Ajax\ProductAjaxHandler;
use Socomarca\RandomERP\Admin\AdminPages;

class Plugin {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        error_log('Socomarca ERP: Plugin::init() iniciado');
        
        // Inicializar componentes
        $this->initializeComponents();
        
        // Registrar hooks
        $this->registerHooks();
        
        error_log('Socomarca ERP: Plugin::init() completado');
    }
    
    private function initializeComponents() {
        error_log('Socomarca ERP: Inicializando componentes...');
        
        // Inicializar manejadores AJAX
        new AuthAjaxHandler();
        new EntityAjaxHandler();
        new CategoryAjaxHandler();
        new ProductAjaxHandler();
        
        // Inicializar páginas de administración
        new AdminPages();
        
        error_log('Socomarca ERP: Componentes inicializados exitosamente');
    }
    
    private function registerHooks() {
        // Hook de activación
        register_activation_hook($this->getPluginFile(), [$this, 'activate']);
        
        // Hook de desactivación
        register_deactivation_hook($this->getPluginFile(), [$this, 'deactivate']);
        
        // Hook de desinstalación
        register_uninstall_hook($this->getPluginFile(), [__CLASS__, 'uninstall']);
        
        // Enlace de ajustes en la página de plugins
        add_filter('plugin_action_links_' . plugin_basename($this->getPluginFile()), [$this, 'addSettingsLink']);
    }
    
    public function activate() {
        // Crear tablas o configuraciones necesarias al activar
        $this->createDatabaseTables();
        $this->setDefaultOptions();
        
        error_log('Socomarca Random ERP Plugin: Activado');
    }
    
    public function deactivate() {
        // Limpiar trabajos programados, etc.
        $this->cleanupScheduledTasks();
        
        error_log('Socomarca Random ERP Plugin: Desactivado');
    }
    
    public static function uninstall() {
        // Eliminar opciones y datos del plugin
        self::removePluginData();
        
        error_log('Socomarca Random ERP Plugin: Desinstalado');
    }
    
    private function createDatabaseTables() {
        // Por ahora no necesitamos tablas adicionales
        // pero esta función está lista para futuras expansiones
    }
    
    private function setDefaultOptions() {
        // Establecer valores por defecto si no existen
        add_option('sm_api_url', 'http://seguimiento.random.cl:3003');
        add_option('sm_api_user', 'demo@random.cl');
        add_option('sm_api_password', 'd3m0r4nd0m3RP');
        add_option('sm_company_code', '01');
        add_option('sm_company_rut', '134549696');
    }
    
    private function cleanupScheduledTasks() {
        // Limpiar cualquier tarea programada
        wp_clear_scheduled_hook('sm_sync_entities');
        wp_clear_scheduled_hook('sm_sync_products');
        wp_clear_scheduled_hook('sm_sync_categories');
    }
    
    private static function removePluginData() {
        // Eliminar opciones del plugin
        delete_option('sm_api_url');
        delete_option('sm_api_user');
        delete_option('sm_api_password');
        delete_option('sm_company_code');
        delete_option('sm_company_rut');
        delete_option('random_erp_token');
        
        // Eliminar opciones de cache
        delete_option('sm_entities_cache');
        delete_option('sm_products_cache');
        delete_option('sm_total_created_users');
        delete_option('sm_total_updated_users');
        delete_option('sm_total_created_products');
        delete_option('sm_total_updated_products');
    }
    
    private function getPluginFile() {
        return plugin_dir_path(__DIR__) . 'index.php';
    }
    
    public function getVersion() {
        return '1.0.0';
    }
    
    public function getPluginName() {
        return 'Socomarca Random ERP Sync for WooCommerce';
    }
    
    public function addSettingsLink($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=socomarca') . '">Ajustes</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}