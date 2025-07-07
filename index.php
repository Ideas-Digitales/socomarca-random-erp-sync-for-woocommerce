<?php
/*
  Plugin Name: Socomarca Random ERP Sync for WooCommerce
  Plugin URI: https://socomarca.com/
  Description: Sincroniza los productos de WooCommerce con Random ERP para Socomarca
  Author: Javier Aguero
  Version: 1.0.0
  Requires at least: WP 6.0
  Tested up to: WP 6.8
  Requires PHP: 8.0
  Text Domain: socomarca-random-erp-sync-for-woocommerce
  Author URI: https://ideasdigitales.cl/
  Author: Javier Aguero
 */

if (!defined('ABSPATH')) {
    exit; 
}

error_log('=== SOCOMARCA ERP: Plugin cargándose ===');
file_put_contents('/tmp/socomarca-debug.log', "Plugin cargándose: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

define('SOCOMARCA_ERP_PLUGIN_FILE', __FILE__);
define('SOCOMARCA_ERP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SOCOMARCA_ERP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOCOMARCA_ERP_VERSION', '1.0.0');

if (!class_exists('WooCommerce')) {
    error_log('Socomarca ERP: WooCommerce no encontrado');
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__('Socomarca Random ERP Sync for WooCommerce requiere WooCommerce para funcionar.', 'socomarca-random-erp-sync-for-woocommerce') . '</p></div>';
    });
} else {
    error_log('Socomarca ERP: WooCommerce encontrado');
}

if (file_exists(SOCOMARCA_ERP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SOCOMARCA_ERP_PLUGIN_DIR . 'vendor/autoload.php';
    error_log('Socomarca ERP: Composer autoloader cargado');
} else {
    error_log('Socomarca ERP: Composer autoloader no encontrado (usando WordPress HTTP API)');
}

require_once SOCOMARCA_ERP_PLUGIN_DIR . 'src/Autoloader.php';
Socomarca\RandomERP\Autoloader::getInstance()->register();
error_log('Socomarca ERP: Autoloader del plugin cargado y registrado');

use Socomarca\RandomERP\Plugin;

add_action('plugins_loaded', function() {
    error_log('Socomarca ERP: Hook plugins_loaded ejecutándose');
    
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        error_log('Socomarca ERP: Error - PHP version < 8.0');
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__('Socomarca Random ERP Sync requiere PHP 8.0 o superior.', 'socomarca-random-erp-sync-for-woocommerce') . '</p></div>';
        });
    } else {
        try {
                        error_log('Socomarca ERP: Creando instancia del plugin');
            Plugin::getInstance();
            error_log('Socomarca ERP: Plugin instanciado exitosamente');
        } catch (Exception $e) {
            error_log('Socomarca ERP: Error al instanciar plugin: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p>Error en Socomarca ERP Plugin: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
});


if (defined('WP_DEBUG') && WP_DEBUG) {
    function dd($data) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        die();
    }
}

