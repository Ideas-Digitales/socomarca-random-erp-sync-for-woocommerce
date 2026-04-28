<?php
/*
  Plugin Name: Socomarca Random ERP Sync for WooCommerce
  Plugin URI: https://socomarca.com/
  Description: Sincroniza los productos de WooCommerce con Random ERP para Socomarca
  Author: Javier Aguero
  Version: 1.0.1
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

file_put_contents('/tmp/socomarca-debug.log', "Plugin cargándose: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

define('SOCOMARCA_ERP_PLUGIN_FILE', __FILE__);
define('SOCOMARCA_ERP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SOCOMARCA_ERP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOCOMARCA_ERP_VERSION', '1.0.1');

if (!class_exists('WooCommerce')) {

    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__('Socomarca Random ERP Sync for WooCommerce requiere WooCommerce para funcionar.', 'socomarca-random-erp-sync-for-woocommerce') . '</p></div>';
    });
} else {

}

if (file_exists(SOCOMARCA_ERP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SOCOMARCA_ERP_PLUGIN_DIR . 'vendor/autoload.php';

} else {

}

require_once SOCOMARCA_ERP_PLUGIN_DIR . 'src/Autoloader.php';
Socomarca\RandomERP\Autoloader::getInstance()->register();

use Socomarca\RandomERP\Plugin;

add_action('plugins_loaded', function() {

    
    if (version_compare(PHP_VERSION, '8.0', '<')) {
    
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__('Socomarca Random ERP Sync requiere PHP 8.0 o superior.', 'socomarca-random-erp-sync-for-woocommerce') . '</p></div>';
        });
    } else {
        try {
                    
            Plugin::getInstance();
        
        } catch (Exception $e) {
        
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p>Error en Socomarca ERP Plugin: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
});



function dd($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    die();
}

add_filter( 'woocommerce_is_checkout_block_default', '__return_false' );


//Activa el modo debug
error_reporting(1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


add_action( 'get_custom_logo', 'add_custom_text_before_shop', 15 );
function add_custom_text_before_shop() {
    echo do_shortcode('[socomarca_location_stock]');
}