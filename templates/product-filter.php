<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap sm-product-filter-wrap">
    <h1>Product Filter</h1>
    <p>Marca productos para ocultarlos en toda la tienda (catalogo, busquedas, productos relacionados). Solo los administradores pueden verlos.</p>

    <div class="sm-pf-layout">
        <!-- Panel izquierdo: buscar -->
        <div class="sm-pf-panel sm-pf-search-panel">
            <h2>Buscar producto</h2>
            <div class="sm-pf-search-row">
                <input type="text" id="sm-pf-search-input" placeholder="Nombre o SKU..." autocomplete="off">
                <button type="button" class="button" id="sm-pf-search-btn">Buscar</button>
            </div>
            <div id="sm-pf-search-results" class="sm-pf-list"></div>
        </div>

        <!-- Panel derecho: ocultos -->
        <div class="sm-pf-panel sm-pf-hidden-panel">
            <h2>Productos ocultos <span id="sm-pf-hidden-count" class="sm-pf-badge">0</span></h2>
            <div id="sm-pf-hidden-list" class="sm-pf-list">
                <p class="sm-pf-loading">Cargando...</p>
            </div>
        </div>
    </div>
</div>

<style>
.sm-product-filter-wrap {
    max-width: 1200px;
}
.sm-pf-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}
.sm-pf-panel {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 16px;
}
.sm-pf-panel h2 {
    margin-top: 0;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #50575e;
    border-bottom: 1px solid #e5e7ea;
    padding-bottom: 10px;
    margin-bottom: 12px;
}
.sm-pf-search-row {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.sm-pf-search-row input {
    flex: 1;
}
.sm-pf-list {
    min-height: 60px;
    max-height: 540px;
    overflow-y: auto;
}
.sm-pf-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}
.sm-pf-item:last-child {
    border-bottom: none;
}
.sm-pf-item-info {
    flex: 1;
    min-width: 0;
}
.sm-pf-item-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
.sm-pf-item-meta {
    font-size: 12px;
    color: #888;
}
.sm-pf-btn-hide {
    background: #d63638;
    color: #fff;
    border-color: #b32d2e;
    flex-shrink: 0;
}
.sm-pf-btn-hide:hover {
    background: #b32d2e;
    border-color: #922d2d;
    color: #fff;
}
.sm-pf-btn-show {
    background: #2271b1;
    color: #fff;
    border-color: #1d6197;
    flex-shrink: 0;
}
.sm-pf-btn-show:hover {
    background: #1d6197;
    border-color: #145178;
    color: #fff;
}
.sm-pf-badge {
    display: inline-block;
    background: #d63638;
    color: #fff;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 600;
    vertical-align: middle;
    margin-left: 6px;
}
.sm-pf-badge.empty {
    background: #8c8f94;
}
.sm-pf-empty {
    color: #888;
    font-style: italic;
    padding: 8px 0;
    font-size: 13px;
}
.sm-pf-loading {
    color: #888;
    font-size: 13px;
}
.sm-pf-item.is-hidden .sm-pf-item-name {
    color: #d63638;
}
</style>

<script>
(function ($) {
    var nonce = <?php echo wp_json_encode(wp_create_nonce('sm_product_filter_nonce')); ?>;
    var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

    function renderItem(p, context) {
        var statusLabel = p.status === 'publish' ? '' : ' <em style="color:#888;">(' + p.status + ')</em>';
        var sku         = p.sku ? ' &middot; SKU: ' + $('<span>').text(p.sku).html() : '';
        var actionBtn;

        if (p.hidden) {
            actionBtn = '<button type="button" class="button button-small sm-pf-btn-show" data-id="' + p.id + '">Mostrar</button>';
        } else {
            actionBtn = '<button type="button" class="button button-small sm-pf-btn-hide" data-id="' + p.id + '">Ocultar</button>';
        }

        return '<div class="sm-pf-item' + (p.hidden ? ' is-hidden' : '') + '" data-id="' + p.id + '" data-context="' + context + '">' +
            '<div class="sm-pf-item-info">' +
                '<span class="sm-pf-item-name">' + $('<span>').text(p.name).html() + statusLabel + '</span>' +
                '<span class="sm-pf-item-meta">#' + p.id + sku + '</span>' +
            '</div>' +
            actionBtn +
            '</div>';
    }

    function loadHidden() {
        var $list = $('#sm-pf-hidden-list');
        $list.html('<p class="sm-pf-loading">Cargando...</p>');
        $.post(ajaxUrl, { action: 'sm_pf_list_hidden', nonce: nonce }, function (res) {
            if (!res.success) {
                $list.html('<p class="sm-pf-empty">Error al cargar.</p>');
                return;
            }
            var products = res.data.products;
            updateBadge(products.length);
            if (!products.length) {
                $list.html('<p class="sm-pf-empty">Ningun producto oculto.</p>');
                return;
            }
            var html = '';
            products.forEach(function (p) { html += renderItem(p, 'hidden'); });
            $list.html(html);
        });
    }

    function updateBadge(count) {
        var $badge = $('#sm-pf-hidden-count');
        $badge.text(count).toggleClass('empty', count === 0);
    }

    function doSearch() {
        var query   = $('#sm-pf-search-input').val().trim();
        var $results = $('#sm-pf-search-results');
        $results.html('<p class="sm-pf-loading">Buscando...</p>');
        $.post(ajaxUrl, { action: 'sm_pf_search', nonce: nonce, query: query }, function (res) {
            if (!res.success) {
                $results.html('<p class="sm-pf-empty">Error al buscar.</p>');
                return;
            }
            var products = res.data.products;
            if (!products.length) {
                $results.html('<p class="sm-pf-empty">Sin resultados.</p>');
                return;
            }
            var html = '';
            products.forEach(function (p) { html += renderItem(p, 'search'); });
            $results.html(html);
        });
    }

    function toggleProduct(productId) {
        $.post(ajaxUrl, { action: 'sm_pf_toggle', nonce: nonce, product_id: productId }, function (res) {
            if (!res.success) return;
            var hidden = res.data.hidden;
            var id     = res.data.product_id;

            // Actualizar ambos paneles
            $('[data-id="' + id + '"]').each(function () {
                var $item = $(this);
                var p = {
                    id:     id,
                    name:   $item.find('.sm-pf-item-name').text().trim(),
                    sku:    ($item.find('.sm-pf-item-meta').text().match(/SKU: (.+)/) || [])[1] || '',
                    status: 'publish',
                    hidden: hidden,
                };
                var context = $item.data('context');
                $item.replaceWith(renderItem(p, context));
            });

            // Recargar la lista de ocultos para mantener consistencia
            loadHidden();
        });
    }

    // Eventos
    $('#sm-pf-search-btn').on('click', doSearch);

    $('#sm-pf-search-input').on('keydown', function (e) {
        if (e.key === 'Enter') doSearch();
    });

    $(document).on('click', '.sm-pf-btn-hide, .sm-pf-btn-show', function () {
        toggleProduct(parseInt($(this).data('id'), 10));
    });

    // Carga inicial
    loadHidden();
    doSearch(); // Muestra los primeros 30 productos
})(jQuery);
</script>
