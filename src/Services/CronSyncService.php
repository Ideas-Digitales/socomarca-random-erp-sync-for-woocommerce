<?php

namespace Socomarca\RandomERP\Services;

class CronSyncService {
    
    private $categoryService;
    private $productService;
    private $priceListService;
    private $entityService;
    
    public function __construct() {
        $this->categoryService = new CategoryService();
        $this->productService = new ProductService();
        $this->priceListService = new PriceListService();
        $this->entityService = new EntityService();
    }
    
    public function init() {
        // Registrar el cron hook
        add_action('sm_erp_auto_sync', [$this, 'executeSynchronization']);
        
        // Programar el cron si está habilitado
        add_action('init', [$this, 'scheduleCronJob']);
        
        // Limpiar cron al desactivar plugin
        register_deactivation_hook(__FILE__, [$this, 'clearCronJob']);
    }
    
    public function scheduleCronJob() {
        $cron_enabled = get_option('sm_cron_enabled', false);
        $cron_time = get_option('sm_cron_time', '02:00');
        
        if ($cron_enabled) {
            // Limpiar cron existente
            $this->clearCronJob();
            
            // Programar nuevo cron
            $timestamp = $this->getNextScheduledTime($cron_time);
            wp_schedule_event($timestamp, 'daily', 'sm_erp_auto_sync');
            
            $this->log("Cron programado para ejecutarse diariamente a las {$cron_time}");
        } else {
            $this->clearCronJob();
        }
    }
    
    public function clearCronJob() {
        $timestamp = wp_next_scheduled('sm_erp_auto_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'sm_erp_auto_sync');
            $this->log("Cron job eliminado");
        }
    }
    
    public function executeSynchronization() {
        $this->log("Iniciando sincronización automática...");
        
        $start_time = time();
        $results = [];
        
        try {
            // 1. Sincronizar categorías
            $this->log("Paso 1/4: Sincronizando categorías...");
            $category_result = $this->categoryService->getCategories();
            $results['categories'] = $category_result;
            
            if (!$category_result || !isset($category_result['success']) || !$category_result['success']) {
                throw new \Exception("Error al sincronizar categorías");
            }
            
            // 2. Sincronizar productos
            $this->log("Paso 2/4: Sincronizando productos...");
            $product_result = $this->productService->getProducts();
            $results['products'] = $product_result;
            
            if (!$product_result || !isset($product_result['success']) || !$product_result['success']) {
                throw new \Exception("Error al sincronizar productos");
            }
            
            // 3. Sincronizar lista de precios
            $this->log("Paso 3/4: Sincronizando listas de precios...");
            $price_result = $this->priceListService->getPriceLists();
            $results['price_lists'] = $price_result;
            
            if (!$price_result || !isset($price_result['success']) || !$price_result['success']) {
                throw new \Exception("Error al sincronizar listas de precios");
            }
            
            // 4. Obtener entidades
            $this->log("Paso 4/4: Obteniendo entidades...");
            $entity_result = $this->entityService->getEntities();
            $results['entities'] = $entity_result;
            
            if (!$entity_result || !isset($entity_result['success']) || !$entity_result['success']) {
                throw new \Exception("Error al obtener entidades");
            }
            
            $execution_time = time() - $start_time;
            $this->log("Sincronización completada exitosamente en {$execution_time} segundos");
            
            // Guardar resultado de última ejecución
            update_option('sm_last_cron_sync', [
                'timestamp' => time(),
                'status' => 'success',
                'execution_time' => $execution_time,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            $execution_time = time() - $start_time;
            $error_message = "Error en sincronización automática: " . $e->getMessage();
            $this->log($error_message, 'error');
            
            // Guardar resultado de error
            update_option('sm_last_cron_sync', [
                'timestamp' => time(),
                'status' => 'error',
                'execution_time' => $execution_time,
                'error' => $e->getMessage(),
                'results' => $results
            ]);
        }
    }
    
    private function getNextScheduledTime($time) {
        $time_parts = explode(':', $time);
        $hour = intval($time_parts[0]);
        $minute = intval($time_parts[1] ?? 0);
        
        $today = strtotime("today {$hour}:{$minute}:00");
        $tomorrow = strtotime("tomorrow {$hour}:{$minute}:00");
        
        // Si ya pasó la hora de hoy, programar para mañana
        return (time() > $today) ? $tomorrow : $today;
    }
    
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Socomarca ERP Cron] [{$level}] {$message}");
        }
        
        // También guardar en log interno
        $logs = get_option('sm_cron_logs', []);
        $logs[] = [
            'timestamp' => time(),
            'level' => $level,
            'message' => $message
        ];
        
        // Mantener solo los últimos 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('sm_cron_logs', $logs);
    }
    
    public function getLastSyncInfo() {
        return get_option('sm_last_cron_sync', null);
    }
    
    public function getCronLogs($limit = 50) {
        $logs = get_option('sm_cron_logs', []);
        return array_slice(array_reverse($logs), 0, $limit);
    }
}