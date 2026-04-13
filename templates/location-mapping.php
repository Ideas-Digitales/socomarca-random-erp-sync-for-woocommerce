<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var array $mapping */
/** @var array $warehouses */
/** @var array $cl_states  — ['CL-RM' => 'Metropolitana de Santiago', ...] */
/** @var array $cl_places  — ['CL-RM' => ['Santiago', 'Las Condes', ...], ...] */

// Regiones que ya estan en el mapeo (para excluirlas del selector)
$mapped_region_ids = array_column($mapping, 'id');
$available_states  = array_diff_key($cl_states, array_flip($mapped_region_ids));
?>
<div class="wrap sm-location-mapping-wrap">
    <h1>Mapeo de Ubicaciones</h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p>Configuracion guardada correctamente.</p></div>
    <?php endif; ?>

    <p>
        Configure las regiones de Chile disponibles para envio y asigne la bodega que atiende cada comuna.
        Las regiones y comunas provienen del plugin <strong>States, Cities and Places for WooCommerce</strong> y
        coinciden con los datos que WooCommerce registra en cada pedido.
        Las bodegas disponibles se obtienen desde la taxonomia
        <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=locations&post_type=product')); ?>">Locations</a>.
    </p>

    <?php if (empty($warehouses)): ?>
        <div class="notice notice-warning">
            <p>No hay bodegas configuradas en la taxonomia Locations.
            <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=locations&post_type=product')); ?>">Agregar bodegas</a></p>
        </div>
    <?php endif; ?>

    <?php if (empty($cl_states)): ?>
        <div class="notice notice-warning">
            <p>No se pudieron obtener las regiones de Chile. Verifique que el plugin
            <strong>States, Cities and Places for WooCommerce</strong> este activo y que Chile
            este habilitado como pais de envio en WooCommerce.</p>
        </div>
    <?php endif; ?>

    <!-- Selector para agregar region -->
    <?php if (!empty($available_states)): ?>
    <div class="sm-add-region-panel" style="margin-bottom:20px; padding:14px; background:#fff; border:1px solid #ccd0d4; border-radius:4px;">
        <strong>Agregar region</strong>
        <div style="margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <select id="sm-region-selector" style="min-width:260px;">
                <option value="">-- Seleccione una region --</option>
                <?php foreach ($available_states as $code => $name): ?>
                <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button button-secondary" id="sm-add-region-btn">Agregar region</button>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sm-location-mapping-form">
        <?php wp_nonce_field('sm_location_mapping_nonce'); ?>
        <input type="hidden" name="action" value="sm_save_location_mapping">
        <input type="hidden" name="sm_mapping_json" id="sm-mapping-json" value="<?php echo esc_attr(wp_json_encode($mapping)); ?>">

        <div id="sm-regions-container">
            <?php foreach ($mapping as $ri => $region): ?>
            <div class="sm-region-block" data-region-id="<?php echo esc_attr($region['id']); ?>">
                <div class="sm-region-header">
                    <span class="sm-region-toggle dashicons dashicons-arrow-down-alt2"></span>
                    <strong class="sm-region-title"><?php echo esc_html($region['name']); ?></strong>
                    <code class="sm-region-code" style="font-size:0.85em; color:#888; font-weight:normal;"><?php echo esc_html($region['id']); ?></code>
                    <span class="sm-region-meta">(<?php echo count($region['comunas'] ?? []); ?> comunas)</span>
                    <button type="button" class="button button-small sm-remove-region" style="margin-left:auto;">Eliminar region</button>
                </div>
                <div class="sm-region-body">
                    <div class="sm-region-bulk-assign" style="margin-bottom:10px;">
                        <label>
                            Asignar bodega a toda la region:
                            <select class="sm-region-warehouse">
                                <option value="">-- Seleccione una bodega --</option>
                                <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo esc_attr((string) $wh->term_id); ?>">
                                    <?php echo esc_html($wh->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="button" class="button button-small sm-assign-region-warehouse">Asignar a todas las comunas</button>
                    </div>
                    <table class="widefat sm-comunas-table">
                        <thead>
                            <tr>
                                <th>Comuna</th>
                                <th>Bodega asignada</th>
                            </tr>
                        </thead>
                        <tbody class="sm-comunas-body">
                            <?php foreach (($region['comunas'] ?? []) as $comuna): ?>
                            <tr class="sm-comuna-row">
                                <td>
                                    <span class="sm-comuna-name"><?php echo esc_html($comuna['name']); ?></span>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($mapping)): ?>
        <p class="submit">
            <button type="submit" class="button button-primary">Guardar configuracion</button>
        </p>
        <?php endif; ?>
    </form>
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
.sm-comunas-table td {
    vertical-align: middle;
}
</style>

<script>
(function ($) {
    // Datos de regiones y comunas desde el plugin de WooCommerce
    var clPlaces = <?php echo wp_json_encode($cl_places); ?>;
    var clStates = <?php echo wp_json_encode($cl_states); ?>;

    var warehouseOptions = <?php
        $opts = '<option value="">-- Sin asignar --</option>';
        foreach ($warehouses as $wh) {
            $opts .= '<option value="' . esc_attr((string) $wh->term_id) . '">' . esc_html($wh->name) . '</option>';
        }
        echo wp_json_encode($opts);
    ?>;

    function buildMapping() {
        var mapping = [];
        $('#sm-regions-container .sm-region-block').each(function () {
            var $region    = $(this);
            var regionId   = $region.data('region-id');
            var regionName = $region.find('.sm-region-title').text().trim();

            var comunas = [];
            $region.find('.sm-comuna-row').each(function () {
                var $row = $(this);
                var cname = $row.find('.sm-comuna-name').text().trim();
                var cid   = $row.find('.sm-comuna-id').val().trim();
                var cwh   = $row.find('.sm-comuna-warehouse').val();
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

    function buildComunaRows(places) {
        var rows = '';
        if (!places || !places.length) return rows;
        places.forEach(function (cityName) {
            rows += '<tr class="sm-comuna-row">' +
                '<td><span class="sm-comuna-name">' + $('<span>').text(cityName).html() + '</span>' +
                    '<input type="hidden" class="sm-comuna-id" value="' + $('<span>').text(cityName).html() + '"></td>' +
                '<td><select class="sm-comuna-warehouse">' + warehouseOptions + '</select></td>' +
                '</tr>';
        });
        return rows;
    }

    function addRegionBlock(regionCode, regionName, places) {
        var comunaRows = buildComunaRows(places);
        var $block = $(
            '<div class="sm-region-block" data-region-id="' + $('<span>').text(regionCode).html() + '">' +
                '<div class="sm-region-header">' +
                    '<span class="sm-region-toggle dashicons dashicons-arrow-down-alt2"></span>' +
                    '<strong class="sm-region-title">' + $('<span>').text(regionName).html() + '</strong>' +
                    '<code class="sm-region-code" style="font-size:0.85em;color:#888;font-weight:normal;">' + $('<span>').text(regionCode).html() + '</code>' +
                    '<span class="sm-region-meta">(' + (places ? places.length : 0) + ' comunas)</span>' +
                    '<button type="button" class="button button-small sm-remove-region" style="margin-left:auto;">Eliminar region</button>' +
                '</div>' +
                '<div class="sm-region-body">' +
                    '<div class="sm-region-bulk-assign" style="margin-bottom:10px;">' +
                        '<label>Asignar bodega a toda la region: ' +
                            '<select class="sm-region-warehouse">' +
                                '<option value="">-- Seleccione una bodega --</option>' +
                                warehouseOptions +
                            '</select>' +
                        '</label> ' +
                        '<button type="button" class="button button-small sm-assign-region-warehouse">Asignar a todas las comunas</button>' +
                    '</div>' +
                    '<table class="widefat sm-comunas-table">' +
                        '<thead><tr><th>Comuna</th><th>Bodega asignada</th></tr></thead>' +
                        '<tbody class="sm-comunas-body">' + comunaRows + '</tbody>' +
                    '</table>' +
                '</div>' +
            '</div>'
        );
        $('#sm-regions-container').append($block);
    }

    // Guardar JSON antes de submit
    $('#sm-location-mapping-form').on('submit', function () {
        $('#sm-mapping-json').val(JSON.stringify(buildMapping()));
    });

    // Toggle collapse
    $(document).on('click', '.sm-region-header', function (e) {
        if ($(e.target).is('button, select')) return;
        $(this).closest('.sm-region-block').toggleClass('collapsed');
    });

    // Agregar region desde el selector
    $('#sm-add-region-btn').on('click', function () {
        var $sel       = $('#sm-region-selector');
        var regionCode = $sel.val();
        if (!regionCode) {
            alert('Seleccione una region para agregar.');
            return;
        }

        var regionName = clStates[regionCode] || regionCode;
        var places     = clPlaces[regionCode] || [];

        addRegionBlock(regionCode, regionName, places);

        // Quitar la region del selector para evitar duplicados
        $sel.find('option[value="' + regionCode + '"]').remove();
        $sel.val('');

        // Mostrar el boton de guardar si estaba oculto
        if ($('.submit').length === 0) {
            $('#sm-location-mapping-form').append('<p class="submit"><button type="submit" class="button button-primary">Guardar configuracion</button></p>');
        }
    });

    // Eliminar region
    $(document).on('click', '.sm-remove-region', function (e) {
        e.stopPropagation();
        if (!confirm('Eliminar esta region y todas sus comunas?')) return;

        var $block     = $(this).closest('.sm-region-block');
        var regionCode = $block.data('region-id');
        var regionName = $block.find('.sm-region-title').text().trim();

        $block.remove();

        // Devolver la region al selector si existe en clStates
        if (regionCode && clStates[regionCode]) {
            $('#sm-region-selector').append(
                $('<option>').val(regionCode).text(regionName)
            );
        }
    });

    // Asignar una bodega a todas las comunas de la region
    $(document).on('click', '.sm-assign-region-warehouse', function () {
        var $region     = $(this).closest('.sm-region-block');
        var warehouseId = $region.find('.sm-region-warehouse').val();

        if (!warehouseId) {
            alert('Seleccione una bodega para asignar.');
            return;
        }

        var $rows = $region.find('.sm-comuna-row');
        if ($rows.length === 0) {
            alert('No hay comunas en esta region.');
            return;
        }

        if (!confirm('Asignar esta bodega a todas las comunas de la region?')) return;

        $rows.find('.sm-comuna-warehouse').val(warehouseId);
    });

})(jQuery);
</script>
