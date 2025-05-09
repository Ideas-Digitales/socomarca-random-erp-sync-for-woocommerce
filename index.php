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

// Evita el acceso directo a este archivo
if (!defined('ABSPATH')) {
    exit; 
}

// Verifica si WooCommerce está activo
if (!class_exists('WooCommerce')) {
    add_action('admin_notices', 'socomarca_random_erp_sync_for_woocommerce_admin_notice');
    function socomarca_random_erp_sync_for_woocommerce_admin_notice() {
        echo '<div class="error"><p>' . __('Socomarca Random ERP Sync for WooCommerce requiere WooCommerce para funcionar.', 'socomarca-random-erp-sync-for-woocommerce') . '</p></div>';
    }
}

// Registrar y cargar scripts
function socomarca_random_erp_sync_scripts() {
    wp_enqueue_script('socomarca-random-erp-sync-js',plugin_dir_url(__FILE__) . 'assets/js/main.js',array('jquery'),'1.0.0',true);
    wp_enqueue_style('socomarca-random-erp-sync-css',plugin_dir_url(__FILE__) . 'assets/css/main.css',array(),'1.0.0');
}
add_action('admin_enqueue_scripts', 'socomarca_random_erp_sync_scripts');


//Vendor
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

//Classes
require_once plugin_dir_path(__FILE__) . 'class/erp-authentication.php';

//Pages
require_once plugin_dir_path(__FILE__) . 'pages/pages.php';
require_once plugin_dir_path(__FILE__) . 'pages/configuration.php';



//Debug functions
if(true) {
    @ini_set('display_errors', 1);
    @ini_set('display_startup_errors', 1);
    @error_reporting(E_ALL);
}

function dd($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    die();
}

