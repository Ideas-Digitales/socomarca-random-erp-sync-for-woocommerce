<?php

namespace Socomarca\RandomERP\Admin;

class LocationTaxonomyAdmin {

    public function __construct() {
        // Add form fields to the locations taxonomy edit screens
        add_action('locations_add_form_fields', [$this, 'renderAddField'], 10, 1);
        add_action('locations_edit_form_fields', [$this, 'renderEditField'], 10, 1);
        
        // Save metadata fields
        add_action('created_locations', [$this, 'saveField'], 10, 1);
        add_action('edited_locations', [$this, 'saveField'], 10, 1);
        
        // Add column to term list screen
        add_filter('manage_edit-locations_columns', [$this, 'addColumns']);
        add_filter('manage_locations_custom_column', [$this, 'renderColumns'], 10, 3);
    }

    public function renderAddField($taxonomy) {
        ?>
        <div class="form-field term-warehouse-code-wrap">
            <label for="random_erp_warehouse_code">Código Bodega (KOBO)</label>
            <input type="text" name="random_erp_warehouse_code" id="random_erp_warehouse_code" value="" placeholder="Ej: PR">
            <p class="description">Código de bodega del ERP (atributo KOBO). Se rellena automáticamente al sincronizar bodegas.</p>
        </div>
        <div class="form-field term-child-commerce-code-wrap">
            <label for="sm_child_commerce_code">Código de Comercio Hijo (Webpay Mall)</label>
            <input type="text" name="sm_child_commerce_code" id="sm_child_commerce_code" value="" placeholder="Ej: 597012345678">
            <p class="description">Código de comercio de Transbank asociado a esta bodega/sucursal para pagos con Webpay Mall.</p>
        </div>
        <?php
    }

    public function renderEditField($term) {
        $warehouse_code      = get_term_meta($term->term_id, 'random_erp_warehouse_code', true);
        $child_commerce_code = get_term_meta($term->term_id, 'sm_child_commerce_code', true);
        ?>
        <tr class="form-field term-warehouse-code-wrap">
            <th scope="row"><label for="random_erp_warehouse_code">Código Bodega (KOBO)</label></th>
            <td>
                <input type="text" name="random_erp_warehouse_code" id="random_erp_warehouse_code" value="<?php echo esc_attr($warehouse_code); ?>" placeholder="Ej: PR">
                <p class="description">Código de bodega del ERP (atributo KOBO). Se rellena automáticamente al sincronizar bodegas.</p>
            </td>
        </tr>
        <tr class="form-field term-child-commerce-code-wrap">
            <th scope="row"><label for="sm_child_commerce_code">Código de Comercio Hijo (Webpay Mall)</label></th>
            <td>
                <input type="text" name="sm_child_commerce_code" id="sm_child_commerce_code" value="<?php echo esc_attr($child_commerce_code); ?>" placeholder="Ej: 597012345678">
                <p class="description">Código de comercio de Transbank asociado a esta bodega/sucursal para pagos con Webpay Mall.</p>
            </td>
        </tr>
        <?php
    }

    public function saveField($term_id) {
        if (isset($_POST['random_erp_warehouse_code'])) {
            $kobo = sanitize_text_field(wp_unslash($_POST['random_erp_warehouse_code']));
            update_term_meta($term_id, 'random_erp_warehouse_code', $kobo);
        }
        if (isset($_POST['sm_child_commerce_code'])) {
            $code = sanitize_text_field(wp_unslash($_POST['sm_child_commerce_code']));
            update_term_meta($term_id, 'sm_child_commerce_code', $code);
        }
    }

    public function addColumns($columns) {
        $columns['warehouse_code']      = 'KOBO';
        $columns['child_commerce_code'] = 'Código Comercio Hijo';
        return $columns;
    }

    public function renderColumns($content, $column_name, $term_id) {
        if ($column_name === 'warehouse_code') {
            $value = get_term_meta($term_id, 'random_erp_warehouse_code', true);
            return $value ? esc_html($value) : '—';
        }
        if ($column_name === 'child_commerce_code') {
            $value = get_term_meta($term_id, 'sm_child_commerce_code', true);
            return $value ? esc_html($value) : '—';
        }
        return $content;
    }
}
