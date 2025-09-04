<?php

namespace Socomarca\RandomERP\Ajax;

use Socomarca\RandomERP\Services\DocumentService;

class InvoiceDownloadAjaxHandler extends BaseAjaxHandler {
    
    private $documentService;
    
    public function __construct() {
        $this->documentService = new DocumentService();
        parent::__construct();
    }
    
    protected function registerHooks() {
        add_action('wp_ajax_sm_download_invoice_pdf', [$this, 'downloadInvoicePdf']);
    }
    
    public function downloadInvoicePdf() {
        check_ajax_referer('sm_download_invoice', 'nonce');
        $this->requireAdminPermissions();
        
        $idmaeedo = sanitize_text_field($_POST['idmaeedo'] ?? '');
        
        if (!$idmaeedo) {
            $this->sendErrorResponse('ID de documento requerido');
            return;
        }
        
        try {
            $pdf_data = $this->documentService->downloadInvoicePdf($idmaeedo);
            
            if ($pdf_data) {
                // Enviar el PDF como descarga
                $filename = 'factura_' . $idmaeedo . '.pdf';
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($pdf_data));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                echo $pdf_data;
                wp_die(); // Importante: terminar la ejecución
            } else {
                $this->sendErrorResponse('Error al descargar el PDF desde Random ERP');
            }
            
        } catch (\Exception $e) {
            $this->logAction("Error downloading PDF for idmaeedo $idmaeedo: " . $e->getMessage());
            $this->sendErrorResponse('Error interno: ' . $e->getMessage());
        }
    }
}