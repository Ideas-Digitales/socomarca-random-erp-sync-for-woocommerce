<?php

namespace Socomarca\RandomERP\Services;

use Exception;

class DocumentService extends BaseApiService {
    
    private $log_file;
    
    public function __construct() {
        parent::__construct();
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        $this->log_file = $logs_dir . '/documents.log';
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'check_invoice_on_completion_setting']);
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] DocumentService: $message" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public function check_invoice_on_completion_setting() {
        $invoice_on_completion = get_option('sm_invoice_on_completion', false);
        
        if ($invoice_on_completion) {
            add_action('woocommerce_order_status_completed', [$this, 'create_invoice_on_order_completion']);
        }
    }
    
    public function create_invoice_on_order_completion($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                $this->log("Order not found for ID: $order_id");
                return false;
            }
            
            $company_code = get_option('sm_company_code', '01');
            $entity_code = $this->get_entity_code_from_order($order);
            
            if (!$entity_code) {
                $this->log("Could not determine entity code for order: $order_id");
                return false;
            }
            
            $lines = $this->build_order_lines($order);
            if (empty($lines)) {
                $this->log("No valid lines found for order: $order_id");
                return false;
            }
            
            $document_data = [
                'datos' => [
                    'empresa' => $company_code,
                    'codigoEntidad' => $entity_code,
                    'tido' => 'BLV',
                    'modalidad' => 'WEB',
                    'lineas' => $lines
                ]
            ];
            
            $result = $this->create_document($document_data);
            
            if ($result) {
                $order->add_order_note('Factura creada en Random ERP exitosamente');
                $this->log("Invoice created successfully for order: $order_id");
            } else {
                $order->add_order_note('Error al crear factura en Random ERP');
                $this->log("Failed to create invoice for order: $order_id");
                $this->log("Error: " . $result);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage());
            return false;
        }
    }
    
    private function get_entity_code_from_order($order) {
        $user = $order->get_user();
        if ($user) {
            $entity_code = get_user_meta($user->ID, 'random_erp_entity_code', true);
            if ($entity_code) {
                return $entity_code;
            }
        }
        
        return get_option('sm_default_entity_code', '5');
    }
    
    private function build_order_lines($order) {
        $lines = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            
            $sku = $product->get_sku();
            if (empty($sku)) {
                continue;
            }
            
            $lines[] = [
                'cantidad' => $item->get_quantity(),
                'codigoProducto' => $sku
            ];
        }
        
        return $lines;
    }
    
    public function create_document($document_data) {
        try {
            $result = $this->makeApiRequest('/web32/documento', 'POST', $document_data);
            
            if ($result !== false) {
                return $result;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log("API Error: " . $e->getMessage());
            return false;
        }
    }
}