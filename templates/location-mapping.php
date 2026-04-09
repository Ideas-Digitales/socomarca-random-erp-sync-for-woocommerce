<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var array $mapping */
/** @var array $warehouses */
?>
<div class="wrap sm-location-mapping-wrap">
    <h1>Mapeo de Ubicaciones</h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p>Configuracion guardada correctamente.</p></div>
    <?php endif; ?>

    <?php if (isset($_GET['seeded'])): ?>
        <div class="notice notice-success is-dismissible"><p>Datos de regiones y comunas cargados correctamente.</p></div>
    <?php endif; ?>

    <p>
        Configure las regiones y comunas disponibles para envio, y asigne la bodega que atiende cada comuna.
        Las bodegas disponibles se obtienen desde la taxonomia <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=locations&post_type=product')); ?>">Locations</a>.
    </p>

    <?php if (empty($warehouses)): ?>
        <div class="notice notice-warning">
            <p>No hay bodegas configuradas en la taxonomia Locations. <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=locations&post_type=product')); ?>">Agregar bodegas</a></p>
        </div>
    <?php endif; ?>

    <?php if (empty($mapping)): ?>
        <div class="notice notice-info">
            <p>No hay regiones configuradas. Puede cargar las regiones y comunas de Chile con el siguiente boton:</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <?php wp_nonce_field('sm_location_seeder_nonce'); ?>
                <input type="hidden" name="action" value="sm_run_location_seeder">
                <button type="submit" class="button button-secondary">Cargar datos de Chile (RM y Valparaiso)</button>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sm-location-mapping-form">
        <?php wp_nonce_field('sm_location_mapping_nonce'); ?>
        <input type="hidden" name="action" value="sm_save_location_mapping">
        <input type="hidden" name="sm_mapping_json" id="sm-mapping-json" value="<?php echo esc_attr(wp_json_encode($mapping)); ?>">

        <div id="sm-regions-container">
            <?php foreach ($mapping as $ri => $region): ?>
            <div class="sm-region-block" data-region-index="<?php echo $ri; ?>">
                <div class="sm-region-header">
                    <span class="sm-region-toggle dashicons dashicons-arrow-down-alt2"></span>
                    <strong class="sm-region-title"><?php echo esc_html($region['name']); ?></strong>
                    <span class="sm-region-meta">(<?php echo count($region['comunas'] ?? []); ?> comunas)</span>
                    <button type="button" class="button button-small sm-remove-region" style="margin-left:auto;">Eliminar region</button>
                </div>
                <div class="sm-region-body">
                    <div class="sm-region-fields" style="margin-bottom:8px;">
                        <label>ID: <input type="text" class="sm-region-id regular-text" value="<?php echo esc_attr($region['id']); ?>"></label>
                        <label style="margin-left:16px;">Nombre: <input type="text" class="sm-region-name regular-text" value="<?php echo esc_attr($region['name']); ?>"></label>
                    </div>
                    <table class="widefat sm-comunas-table">
                        <thead>
                            <tr>
                                <th>Comuna</th>
                                <th>Bodega asignada</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="sm-comunas-body">
                            <?php foreach (($region['comunas'] ?? []) as $ci => $comuna): ?>
                            <tr class="sm-comuna-row">
                                <td>
                                    <input type="text" class="sm-comuna-name regular-text" value="<?php echo esc_attr($comuna['name']); ?>">
                                    <input type="hidden" class="sm-comuna-id" value="<?php echo esc_attr($comuna['id']); ?>">
                                </td>
                                <td>
                                    <select class="sm-comuna-warehouse">
                                        <option value="">-- Sin asignar --</option>
                                        <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?php echo esc_attr((string) $wh->term_id); ?>" <?php selected((string) ($comuna['warehouse_id'] ?? ''), (string) $wh->term_id); ?>>
                                            <?php echo esc_html($wh->name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="button button-small sm-remove-comuna">Eliminar</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button button-small sm-add-comuna" style="margin-top:8px;">+ Agregar comuna</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:16px;">
            <button type="button" class="button button-secondary" id="sm-add-region">+ Agregar region</button>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">Guardar configuracion</button>
        </p>
    </form>

    <?php if (!empty($mapping)): ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:0;">
        <?php wp_nonce_field('sm_location_seeder_nonce'); ?>
        <input type="hidden" name="action" value="sm_run_location_seeder">
        <button type="submit" class="button button-secondary" onclick="return confirm('Esto reemplazara la configuracion actual con los datos de Chile. Continuar?');">Recargar datos de Chile</button>
    </form>
    <?php endif; ?>
</div>

<style>
.sm-location-mapping-wrap .sm-region-block {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 12px;
}
.sm-region-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #e5e7ea;
    background: #f6f7f7;
    border-radius: 4px 4px 0 0;
}
.sm-region-header:hover {
    background: #eef0f1;
}
.sm-region-meta {
    color: #888;
    font-size: 0.9em;
}
.sm-region-body {
    padding: 14px;
}
.sm-region-toggle {
    font-size: 14px;
    color: #666;
    transition: transform 0.2s;
}
.sm-region-block.collapsed .sm-region-toggle {
    transform: rotate(-90deg);
}
.sm-region-block.collapsed .sm-region-body {
    display: none;
}
.sm-comunas-table {
    margin-top: 8px;
}
.sm-comunas-table th {
    background: #f0f0f1;
}
</style>

<script>
(function ($) {
    var warehouseOptions = <?php
        $opts = '<option value="">-- Sin asignar --</option>';
        foreach ($warehouses as $wh) {
            $opts .= '<option value="' . esc_attr((string) $wh->term_id) . '">' . esc_html($wh->name) . '</option>';
        }
        echo json_encode($opts);
    ?>;

    function slugify(text) {
        return text.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();
    }

    function buildMapping() {
        var mapping = [];
        $('#sm-regions-container .sm-region-block').each(function () {
            var $region = $(this);
            var regionId   = $region.find('.sm-region-id').val().trim();
            var regionName = $region.find('.sm-region-name').val().trim();
            if (!regionName) return;
            if (!regionId) regionId = slugify(regionName);

            var comunas = [];
            $region.find('.sm-comuna-row').each(function () {
                var $row  = $(this);
                var cname = $row.find('.sm-comuna-name').val().trim();
                var cid   = $row.find('.sm-comuna-id').val().trim() || slugify(cname);
                var cwh   = $row.find('.sm-comuna-warehouse').val();
                if (!cname) return;
                comunas.push({
                    id:           cid,
                    name:         cname,
                    warehouse_id: cwh ? parseInt(cwh, 10) : null,
                });
            });

            mapping.push({ id: regionId, name: regionName, comunas: comunas });
        });
        return mapping;
    }

    // Guardar JSON antes de submit
    $('#sm-location-mapping-form').on('submit', function () {
        $('#sm-mapping-json').val(JSON.stringify(buildMapping()));
    });

    // Toggle collapse
    $(document).on('click', '.sm-region-header', function (e) {
        if ($(e.target).is('button, input')) return;
        $(this).closest('.sm-region-block').toggleClass('collapsed');
    });

    // Agregar region
    $('#sm-add-region').on('click', function () {
        var $tpl = $('<div class="sm-region-block">' +
            '<div class="sm-region-header">' +
                '<span class="sm-region-toggle dashicons dashicons-arrow-down-alt2"></span>' +
                '<strong class="sm-region-title">Nueva region</strong>' +
                '<span class="sm-region-meta">(0 comunas)</span>' +
                '<button type="button" class="button button-small sm-remove-region" style="margin-left:auto;">Eliminar region</button>' +
            '</div>' +
            '<div class="sm-region-body">' +
                '<div class="sm-region-fields" style="margin-bottom:8px;">' +
                    '<label>ID: <input type="text" class="sm-region-id regular-text" value=""></label>' +
                    '<label style="margin-left:16px;">Nombre: <input type="text" class="sm-region-name regular-text" value=""></label>' +
                '</div>' +
                '<table class="widefat sm-comunas-table"><thead><tr><th>Comuna</th><th>Bodega asignada</th><th></th></tr></thead>' +
                '<tbody class="sm-comunas-body"></tbody></table>' +
                '<button type="button" class="button button-small sm-add-comuna" style="margin-top:8px;">+ Agregar comuna</button>' +
            '</div>' +
        '</div>');
        $('#sm-regions-container').append($tpl);
    });

    // Eliminar region
    $(document).on('click', '.sm-remove-region', function (e) {
        e.stopPropagation();
        if (confirm('Eliminar esta region y todas sus comunas?')) {
            $(this).closest('.sm-region-block').remove();
        }
    });

    // Agregar comuna
    $(document).on('click', '.sm-add-comuna', function () {
        var $tbody = $(this).siblings('.sm-comunas-table').find('.sm-comunas-body');
        $tbody.append('<tr class="sm-comuna-row">' +
            '<td><input type="text" class="sm-comuna-name regular-text" value=""><input type="hidden" class="sm-comuna-id" value=""></td>' +
            '<td><select class="sm-comuna-warehouse">' + warehouseOptions + '</select></td>' +
            '<td><button type="button" class="button button-small sm-remove-comuna">Eliminar</button></td>' +
        '</tr>');
    });

    // Eliminar comuna
    $(document).on('click', '.sm-remove-comuna', function () {
        $(this).closest('tr').remove();
    });

    // Actualizar titulo de region al escribir
    $(document).on('input', '.sm-region-name', function () {
        $(this).closest('.sm-region-block').find('.sm-region-title').text($(this).val() || 'Nueva region');
    });

})(jQuery);
</script>
