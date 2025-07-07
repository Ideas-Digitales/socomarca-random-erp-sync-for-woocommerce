<?php

namespace Socomarca\RandomERP;

class Autoloader {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function register() {
        spl_autoload_register([$this, 'autoload']);
    }
    
    public function autoload($class) {
        // Only autoload classes from our namespace
        if (strpos($class, 'Socomarca\\RandomERP\\') !== 0) {
            return;
        }
        
        // Remove namespace prefix
        $class = str_replace('Socomarca\\RandomERP\\', '', $class);
        
        // Convert namespace separators to directory separators
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        
        // Build file path (go up one directory from src/)
        $file = dirname(plugin_dir_path(__FILE__)) . '/src/' . $class . '.php';
        
        // Include file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
}