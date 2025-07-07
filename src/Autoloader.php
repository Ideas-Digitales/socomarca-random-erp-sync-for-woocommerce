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
        
        if (strpos($class, 'Socomarca\\RandomERP\\') !== 0) {
            return;
        }
        
        
        $class = str_replace('Socomarca\\RandomERP\\', '', $class);
        
        
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        
        
        $file = dirname(plugin_dir_path(__FILE__)) . '/src/' . $class . '.php';
        
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
}