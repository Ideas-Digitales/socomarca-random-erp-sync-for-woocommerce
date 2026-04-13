<?php

namespace Socomarca\RandomERP\Admin;

class CategoryTaxonomyAdmin {

    public function __construct() {
        add_filter('manage_product_cat_columns', [$this, 'addErpKeyColumn']);
        add_filter('manage_product_cat_custom_column', [$this, 'renderErpKeyColumn'], 10, 3);
        add_action('product_cat_edit_form_fields', [$this, 'renderEditField'], 10, 1);
    }

    public function addErpKeyColumn($columns) {
        $columns['erp_key'] = 'Clave ERP';
        return $columns;
    }

    public function renderErpKeyColumn($content, $column_name, $term_id) {
        if ($column_name !== 'erp_key') {
            return $content;
        }
        $value = get_term_meta($term_id, 'erp_key', true);
        return $value ? esc_html($value) : '—';
    }

    public function renderEditField($term) {
        $erp_key   = get_term_meta($term->term_id, 'erp_key', true);
        $erp_code  = get_term_meta($term->term_id, 'erp_code', true);
        $erp_level = get_term_meta($term->term_id, 'erp_level', true);

        if (!$erp_key) {
            return;
        }
        ?>
        <tr class="form-field">
            <th scope="row"><label>Datos ERP</label></th>
            <td>
                <table style="border-collapse:collapse;width:100%;max-width:400px;">
                    <tr>
                        <td style="padding:4px 8px 4px 0;color:#666;white-space:nowrap;">Clave (LLAVE)</td>
                        <td style="padding:4px 0;font-family:monospace;"><?php echo esc_html($erp_key); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:4px 8px 4px 0;color:#666;white-space:nowrap;">Codigo (CODIGO)</td>
                        <td style="padding:4px 0;font-family:monospace;"><?php echo esc_html($erp_code); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:4px 8px 4px 0;color:#666;white-space:nowrap;">Nivel</td>
                        <td style="padding:4px 0;"><?php echo esc_html($erp_level); ?></td>
                    </tr>
                </table>
                <p class="description">Estos valores provienen del ERP y no deben editarse manualmente.</p>
            </td>
        </tr>
        <?php
    }
}
