<?php

namespace Socomarca\RandomERP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class OrderActionsAdmin {

    public function __construct() {
        add_action('add_meta_boxes_shop_order', [$this, 'add_document_metabox']);
        add_action('wp_ajax_sm_create_document', [$this, 'handle_create_document_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'sync_error_meta']);
    }

    public function sync_error_meta($order) {
        if ($this->has_document_error($order)) {
            // El meta se guardó en has_document_error() si detectó error por nota
        }
    }

    public function add_document_metabox() {
        global $post;

        if (!$post || $post->post_type !== 'shop_order') {
            return;
        }

        $order = wc_get_order($post->ID);
        if (!$order || !$this->has_document_error($order)) {
            return;
        }

        add_meta_box(
            'sm_create_document_metabox',
            'Crear Documento en Random ERP',
            [$this, 'render_metabox'],
            'shop_order',
            'side',
            'high'
        );
    }

    public function render_metabox($order) {
        $nonce = wp_create_nonce('sm_create_document_nonce');
        $order_id = $order->get_id();
        ?>
        <div id="sm_document_status">
            <p style="color: #d32f2f; margin-bottom: 15px;">
                <strong>Error detectado:</strong> No se pudo crear el documento en el ERP.
            </p>
            <button
                type="button"
                id="sm_create_document_btn"
                class="button button-primary"
                data-order-id="<?php echo $order_id; ?>"
                data-nonce="<?php echo $nonce; ?>"
                style="width: 100%;"
            >
                Reintentar crear documento
            </button>
            <div id="sm_document_loading" style="display: none; margin-top: 10px;">
                <p>Procesando...</p>
            </div>
            <div id="sm_document_message" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    public function enqueue_scripts($hook) {
        global $post;

        if ($hook !== 'post.php' || !$post || $post->post_type !== 'shop_order') {
            return;
        }

        $order = wc_get_order($post->ID);
        if (!$order || !$this->has_document_error($order)) {
            return;
        }

        wp_enqueue_script(
            'sm-order-document',
            plugin_dir_url(dirname(__FILE__)) . '../assets/js/order-document.js',
            ['jquery'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . '../assets/js/order-document.js'),
            true
        );

        wp_localize_script('sm-order-document', 'smOrderDocument', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }

    private function has_document_error($order) {
        // Verificar si existe el meta created_document = 0
        $created_document = $order->get_meta('created_document');
        if ($created_document === '0' || $created_document === 0) {
            return true;
        }

        // Para órdenes antiguas: buscar nota de error y guardar el meta
        $order_notes = wc_get_order_notes([
            'order_id' => $order->get_id()
        ]);

        foreach ($order_notes as $note) {
            // Buscar ambas formas: "Error al crear documento" y "Error al crear factura"
            if (strpos($note->content, 'Error al crear documento') !== false ||
                strpos($note->content, 'Error al crear factura') !== false) {
                // Guardar el meta para próximas búsquedas
                update_post_meta($order->get_id(), 'created_document', 0);
                return true;
            }
        }

        return false;
    }

    public function handle_create_document_ajax() {
        check_ajax_referer('sm_create_document_nonce', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Sin permisos');
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Orden no encontrada');
        }

        $documentService = new \Socomarca\RandomERP\Services\DocumentService();
        $result = $documentService->create_invoice_on_order_completion($order_id);

        if ($result) {
            wp_send_json_success('Documento creado exitosamente');
        } else {
            wp_send_json_error('Error al crear documento. Revisa las notas de la orden.');
        }
    }
}
