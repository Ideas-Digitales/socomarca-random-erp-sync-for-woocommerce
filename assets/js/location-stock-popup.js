/**
 * Location Stock Popup - Socomarca ERP
 * Selector de ubicacion por region/comuna con asignacion de bodega via multiloca-lite
 */
(function ($) {
    'use strict';

    var SmLocationPopup = {

        selectedRegionId:    null,
        selectedRegionName:  null,
        selectedComunaId:    null,
        selectedComunaName:  null,
        selectedWarehouseId: null,
        _savedModalBody:     null,

        init: function () {
            this.bindEvents();
            this.restoreFromConfig();

            if (!SmLocationPopup.parseCookie()) {
                SmLocationPopup.openModal();
            }
        },

        bindEvents: function () {
            // Abrir modal
            $(document).on('click', '.sm-location-popup-trigger', function (e) {
                e.preventDefault();
                SmLocationPopup.openModal();
            });

            // Cerrar modal con X o backdrop
            $(document).on('click', '.sm-location-modal-close, .sm-location-modal-backdrop', function () {
                SmLocationPopup.closeModal();
            });

            // Cerrar con ESC
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    SmLocationPopup.closeModal();
                }
            });

            // Cambio de region
            $(document).on('change', '#sm-region-select', function () {
                var regionId   = $(this).val();
                var regionName = $(this).find('option:selected').text();
                SmLocationPopup.onRegionChange(regionId, regionName);
            });

            // Cambio de comuna
            $(document).on('change', '#sm-comuna-select', function () {
                var $opt         = $(this).find('option:selected');
                var comunaId     = $(this).val();
                var comunaName   = $opt.text();
                var warehouseId  = $opt.data('warehouse-id');

                SmLocationPopup.selectedComunaId    = comunaId;
                SmLocationPopup.selectedComunaName  = comunaName;
                SmLocationPopup.selectedWarehouseId = warehouseId || null;

                console.log('[SM] comuna change', { comunaId: comunaId, warehouseId: warehouseId, selectedWarehouseId: SmLocationPopup.selectedWarehouseId });

                if (comunaId) {
                    $('.sm-location-confirm').prop('disabled', false);
                } else {
                    $('.sm-location-confirm').prop('disabled', true);
                }
            });

            // Confirmar seleccion
            $(document).on('click', '.sm-location-confirm', function () {
                SmLocationPopup.confirmSelection();
            });
        },

        openModal: function () {
            $('#sm-location-modal').fadeIn(200);
            $('body').addClass('sm-modal-open');
        },

        closeModal: function () {
            $('#sm-location-modal').fadeOut(200);
            $('body').removeClass('sm-modal-open');
            SmLocationPopup._restoreModalBody();
        },

        restoreFromConfig: function () {
            var hasCookie = !!SmLocationPopup.parseCookie();
            var regionId  = hasCookie ? sm_location_popup.selected_region : sm_location_popup.default_region;
            var comunaId  = hasCookie ? sm_location_popup.selected_comuna  : sm_location_popup.default_comuna;

            if (!regionId) return;

            var $regionSelect = $('#sm-region-select');
            $regionSelect.val(regionId);
            var regionName = $regionSelect.find('option:selected').text();

            SmLocationPopup.loadComunas(regionId, regionName, comunaId, function () {
                if (hasCookie) return;

                var $comunaSelect = $('#sm-comuna-select');
                var comunaName    = $comunaSelect.find('option:selected').text();
                if (!comunaName || !SmLocationPopup.selectedComunaId) return;

                var $trigger = $('.sm-location-popup-trigger');
                $trigger.html(
                    regionName + ' - ' + comunaName +
                    ' <span class="sm-trigger-change">(cambiar)</span>'
                );
            });
        },

        onRegionChange: function (regionId, regionName) {
            SmLocationPopup.selectedRegionId   = regionId;
            SmLocationPopup.selectedRegionName = regionName;
            SmLocationPopup.selectedComunaId   = null;
            SmLocationPopup.selectedComunaName = null;
            SmLocationPopup.selectedWarehouseId = null;
            $('.sm-location-confirm').prop('disabled', true);

            if (!regionId) {
                var $select = $('#sm-comuna-select');
                $select.empty().append('<option value="">-- Seleccione una region primero --</option>').prop('disabled', true);
                return;
            }

            SmLocationPopup.loadComunas(regionId, regionName, null);
        },

        loadComunas: function (regionId, regionName, preselectComunaId, onComplete) {
            var $select  = $('#sm-comuna-select');
            var $loading = $('.sm-location-loading');

            $select.prop('disabled', true).empty().append('<option value="">Cargando...</option>');
            $loading.show();

            $.ajax({
                url:  sm_location_popup.ajax_url,
                type: 'POST',
                data: {
                    action:    'sm_get_comunas',
                    nonce:     sm_location_popup.popup_nonce,
                    region_id: regionId,
                },
                success: function (response) {
                    $loading.hide();
                    $select.empty().append('<option value="">-- Seleccione una comuna --</option>');

                    if (response.success && response.data.comunas.length > 0) {
                        $.each(response.data.comunas, function (i, comuna) {
                            var $opt = $('<option>')
                                .val(comuna.id)
                                .text(comuna.name)
                                .data('warehouse-id', comuna.warehouse_id);
                            $select.append($opt);
                        });
                        $select.prop('disabled', false);

                        SmLocationPopup.selectedRegionId   = regionId;
                        SmLocationPopup.selectedRegionName = regionName;

                        if (preselectComunaId) {
                            $select.val(preselectComunaId).trigger('change');
                        }
                    } else {
                        $select.append('<option value="" disabled>No hay comunas disponibles</option>');
                    }

                    if (typeof onComplete === 'function') {
                        onComplete();
                    }
                },
                error: function () {
                    $loading.hide();
                    $select.empty().append('<option value="">Error al cargar comunas</option>');
                },
            });
        },

        confirmSelection: function () {
            var warehouseId  = SmLocationPopup.selectedWarehouseId;
            var comunaId     = SmLocationPopup.selectedComunaId;
            var comunaName   = SmLocationPopup.selectedComunaName;
            var regionId     = SmLocationPopup.selectedRegionId;
            var regionName   = SmLocationPopup.selectedRegionName;

            if (!comunaId) return;

            var prevCookie       = SmLocationPopup.parseCookie();
            var prevWarehouseId  = prevCookie ? parseInt(prevCookie.warehouse_id, 10) : null;
            var newWarehouseId   = warehouseId ? parseInt(warehouseId, 10) : null;
            var warehouseChanged = newWarehouseId && newWarehouseId !== prevWarehouseId;

            console.log('[SM] confirmSelection', {
                warehouseId:      warehouseId,
                newWarehouseId:   newWarehouseId,
                prevWarehouseId:  prevWarehouseId,
                warehouseChanged: warehouseChanged,
                prevCookie:       prevCookie,
            });

            var saveCookie = function () {
                var cookieData = JSON.stringify({
                    region_id:    regionId,
                    region_name:  regionName,
                    comuna_id:    comunaId,
                    comuna_name:  comunaName,
                    warehouse_id: newWarehouseId,
                });
                document.cookie = 'sm_selected_location=' + encodeURIComponent(cookieData) + '; path=/; max-age=2592000';
            };

            var showReloadOverlay = function () {
                var $overlay = $(
                    '<div id="sm-reload-overlay" style="' +
                        'position:fixed;top:0;left:0;width:100%;height:100%;' +
                        'background:rgba(0,0,0,0.55);z-index:999999;' +
                        'display:flex;align-items:center;justify-content:center;' +
                    '">' +
                        '<div style="' +
                            'background:#fff;border-radius:8px;padding:32px 48px;' +
                            'text-align:center;box-shadow:0 4px 24px rgba(0,0,0,0.2);' +
                        '">' +
                            '<div style="' +
                                'width:36px;height:36px;border:4px solid #e0e0e0;' +
                                'border-top-color:#333;border-radius:50%;' +
                                'animation:sm-spin 0.7s linear infinite;margin:0 auto 16px;' +
                            '"></div>' +
                            '<p style="margin:0;font-size:15px;color:#333;font-weight:500;">Cargando...</p>' +
                        '</div>' +
                    '</div>'
                );
                if (!$('#sm-reload-overlay').length) {
                    $('body').append($overlay);
                }
                if (!$('#sm-spin-style').length) {
                    $('head').append(
                        '<style id="sm-spin-style">' +
                        '@keyframes sm-spin{to{transform:rotate(360deg)}}' +
                        '</style>'
                    );
                }
            };

            var doReload = function () {
                showReloadOverlay();
                // WooCommerce agrega un beforeunload en el checkout que muestra "Leave site?".
                // Lo removemos para que la recarga sea inmediata y sin dialogo.
                $(window).off('beforeunload');

                // Limpiar params add-to-cart de la URL antes de navegar.
                // window.location.reload() re-envia los GET params actuales, lo que provoca
                // que WC procese el add-to-cart nuevamente y duplique items en el carrito.
                var url = new URL(window.location.href);
                url.searchParams.delete('add-to-cart');
                url.searchParams.delete('quantity');
                url.searchParams.delete('variation_id');
                var targetUrl = url.toString();

                if (warehouseId) {
                    $.ajax({
                        url:  sm_location_popup.ajax_url,
                        type: 'POST',
                        data: {
                            action:        'select_location',
                            location_id:   warehouseId,
                            location_name: comunaName,
                            nonce:         sm_location_popup.multiloca_nonce,
                        },
                        complete: function () {
                            window.location.href = targetUrl;
                        },
                    });
                } else {
                    window.location.href = targetUrl;
                }
            };

            var executeSwitchAndReload = function () {
                SmLocationPopup.closeModal();
                // Marcar que se acaba de cambiar bodega. El servidor lee esta cookie en init
                // (prioridad 1) y elimina los params add-to-cart antes de que WC los procese,
                // evitando que la recarga re-agregue items al carrito desde la URL anterior.
                document.cookie = 'sm_cart_switched=1; path=/; max-age=60';
                $.ajax({
                    url:  sm_location_popup.ajax_url,
                    type: 'POST',
                    data: {
                        action:        'sm_switch_warehouse_cart',
                        nonce:         sm_location_popup.popup_nonce,
                        warehouse_id:  newWarehouseId,
                        location_name: comunaName,
                    },
                    success: function (response) {
                        if (response.success && response.data.cleared) {
                            SmLocationPopup.renderCartSwitchToasts(response.data.items);
                            setTimeout(doReload, 2200);
                        } else {
                            doReload();
                        }
                    },
                    error: function () {
                        doReload();
                    },
                });
            };

            var proceed = function () {
                saveCookie();
                if (warehouseChanged) {
                    executeSwitchAndReload();
                } else {
                    SmLocationPopup.closeModal();
                    doReload();
                }
            };

            if (warehouseChanged) {
                // Bloquear boton mientras se consulta el stock
                $('.sm-location-confirm').prop('disabled', true);

                $.ajax({
                    url:  sm_location_popup.ajax_url,
                    type: 'POST',
                    data: {
                        action:       'sm_cart_stock_preview',
                        nonce:        sm_location_popup.popup_nonce,
                        warehouse_id: newWarehouseId,
                    },
                    success: function (response) {
                        if (response.success && !response.data.empty && response.data.items.length > 0) {
                            SmLocationPopup.renderStockComparison(response.data.items, proceed);
                        } else {
                            proceed();
                        }
                    },
                    error: function () {
                        proceed();
                    },
                });
            } else {
                proceed();
            }
        },

        renderStockComparison: function (items, onConfirm) {
            var problemItems = $.grep(items, function (item) {
                return item.status !== 'ok';
            });

            if (problemItems.length === 0) {
                onConfirm();
                return;
            }

            var $container = $('.sm-location-modal-container');
            var $body      = $('.sm-location-modal-body');

            SmLocationPopup._savedModalBody = $body.children().detach();
            $container.addClass('sm-location-modal--comparison');

            var html = '<div class="sm-stock-comparison">';
            html += '<div class="sm-stock-notice">';
            html += '<strong>Importante:</strong> Al cambiar de localidad, algunos productos tendran otro stock disponible.';
            html += '</div>';

            html += '<table class="sm-stock-table">';
            html += '<thead><tr>';
            html += '<th>Producto</th>';
            html += '<th>Cant. en carrito</th>';
            html += '<th>Stock nueva ubicacion</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(problemItems, function (i, item) {
                var rowClass, stockLabel;

                if (item.status === 'out_of_stock') {
                    rowClass   = 'sm-stock-row-error';
                    stockLabel = '0 (Sin Stock)';
                } else {
                    rowClass   = 'sm-stock-row-warning';
                    stockLabel = item.new_stock + ' disponibles';
                }

                html += '<tr class="' + rowClass + '">';
                html += '<td>' + $('<span>').text(item.product_name).html() + '</td>';
                html += '<td class="sm-stock-center">' + item.quantity + '</td>';
                html += '<td class="sm-stock-center">' + stockLabel + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<p class="sm-stock-question">¿Desea proceder con el cambio de ubicacion?</p>';
            html += '<div class="sm-stock-actions">';
            html += '<button type="button" class="sm-stock-cancel button">No, cancelar</button>';
            html += '<button type="button" class="sm-stock-proceed button button-primary">Si, cambiar ubicación</button>';
            html += '</div>';
            html += '</div>';

            $body.html(html);

            $body.find('.sm-stock-cancel').one('click', function () {
                SmLocationPopup._restoreModalBody();
            });

            $body.find('.sm-stock-proceed').one('click', function () {
                onConfirm();
            });
        },

        _restoreModalBody: function () {
            if (!SmLocationPopup._savedModalBody) return;
            var $body = $('.sm-location-modal-body');
            $body.empty().append(SmLocationPopup._savedModalBody);
            SmLocationPopup._savedModalBody = null;
            $('.sm-location-modal-container').removeClass('sm-location-modal--comparison');
        },

        renderCartSwitchToasts: function (items) {
            if (!items || items.length === 0) return;

            var $container = $('<div class="sm-toast-container"></div>');
            $('body').append($container);

            $.each(items, function (i, item) {
                var icon, text;
                if (item.type === 'success') {
                    icon = '&#10003;';
                    text = item.product_name + ': agregado al carrito (' + item.quantity + ' unid.)';
                } else if (item.type === 'warning') {
                    icon = '&#9888;';
                    text = item.product_name + ': solo ' + item.quantity + ' unid. disponibles en esta bodega (pedias ' + item.requested + ')';
                } else {
                    icon = '&#10007;';
                    text = item.product_name + ': sin stock en esta bodega, eliminado del carrito';
                }

                var $toast = $(
                    '<div class="sm-toast sm-toast-' + item.type + '">' +
                    '<span class="sm-toast-icon">' + icon + '</span>' +
                    '<span>' + text + '</span>' +
                    '</div>'
                );
                $container.append($toast);
            });

            setTimeout(function () {
                $container.fadeOut(400, function () { $(this).remove(); });
            }, 2000);
        },

        parseCookie: function () {
            var raw = document.cookie.split('; ').reduce(function (acc, part) {
                var idx = part.indexOf('=');
                var key = part.substring(0, idx);
                if (key === 'sm_selected_location') {
                    acc = part.substring(idx + 1);
                }
                return acc;
            }, null);
            if (!raw) return null;
            try { return JSON.parse(decodeURIComponent(raw)); } catch (e) { return null; }
        },
    };

    $(document).ready(function () {
        SmLocationPopup.init();
    });

})(jQuery);
