<?php

namespace Socomarca\RandomERP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class OrderDocumentMetaBox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'render_metabox_inline']);
    }

    public function add_metabox() {
        if (get_post_type() === 'shop_order') {
            add_meta_box(
                'sm_document_metabox',
                'Información del Documento ERP',
                [$this, 'render_metabox'],
                'shop_order',
                'side',
                'high'
            );
        }
    }

    public function render_metabox_inline($order) {
        $this->render_metabox($order);
    }

    public function render_metabox($order) {
        $created_document = get_post_meta($order->get_id(), 'created_document', true);
        $idmaeedo = get_post_meta($order->get_id(), 'idmaeedo', true);

        if (!$created_document && !$idmaeedo) {
            echo '<p style="color: #999;">Sin información de documento</p>';
            return;
        }

        ?>
        <table class="widefat">
            <tbody>
                <tr>
                    <td style="width: 30%; font-weight: bold;">Documento creado:</td>
                    <td>
                        <?php if ($created_document == 1) : ?>
                            <span style="color: #4caf50;">Sí</span>
                        <?php else : ?>
                            <span style="color: #d32f2f;">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($idmaeedo) : ?>
                    <tr>
                        <td style="font-weight: bold;">ID Documento (idmaeedo):</td>
                        <td><strong><?php echo esc_html($idmaeedo); ?></strong></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}
