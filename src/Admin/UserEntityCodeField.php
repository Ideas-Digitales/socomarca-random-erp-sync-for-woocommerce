<?php

namespace Socomarca\RandomERP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class UserEntityCodeField {

    public function __construct() {
        add_action('show_user_profile', [$this, 'render_entity_code_field']);
        add_action('edit_user_profile', [$this, 'render_entity_code_field']);
        add_action('personal_options_update', [$this, 'save_entity_code_field']);
        add_action('edit_user_profile_update', [$this, 'save_entity_code_field']);
    }

    public function render_entity_code_field($user) {
        $entity_code = get_user_meta($user->ID, 'random_erp_entity_code', true);
        ?>
        <h3>Datos Random ERP</h3>
        <table class="form-table">
            <tr>
                <th><label for="random_erp_entity_code">Código de Entidad ERP</label></th>
                <td>
                    <input type="text" name="random_erp_entity_code" id="random_erp_entity_code" value="<?php echo esc_attr($entity_code); ?>" class="regular-text" />
                    <p class="description">Código de la entidad en Random ERP (sincronizado automáticamente, editable si es necesario)</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_entity_code_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['random_erp_entity_code'])) {
            $entity_code = sanitize_text_field($_POST['random_erp_entity_code']);
            update_user_meta($user_id, 'random_erp_entity_code', $entity_code);
        }
    }
}
