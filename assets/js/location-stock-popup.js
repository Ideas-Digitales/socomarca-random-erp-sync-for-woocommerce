/**
 * Location Stock Popup - Socomarca ERP
 * Selector de ubicacion por region/comuna con asignacion de bodega via multiloca-lite
 */
(function ($) {
    'use strict';

    var SmLocationPopup = {

        selectedRegionId:   null,
        selectedRegionName: null,
        selectedComunaId:   null,
        selectedComunaName: null,
        selectedWarehouseId: null,

        init: function () {
            this.bindEvents();
            this.restoreFromConfig();
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

            // Leer bodega actual del cookie antes de sobreescribirlo
            var prevCookie       = SmLocationPopup.parseCookie();
            var prevWarehouseId  = prevCookie ? parseInt(prevCookie.warehouse_id, 10) : null;
            var newWarehouseId   = warehouseId ? parseInt(warehouseId, 10) : null;
            var warehouseChanged = newWarehouseId && newWarehouseId !== prevWarehouseId;

            console.log('[SM] confirmSelection', {
                warehouseId:     warehouseId,
                newWarehouseId:  newWarehouseId,
                prevWarehouseId: prevWarehouseId,
                warehouseChanged: warehouseChanged,
                prevCookie:      prevCookie,
            });

            // Guardar nueva seleccion en cookie
            var cookieData = JSON.stringify({
                region_id:    regionId,
                region_name:  regionName,
                comuna_id:    comunaId,
                comuna_name:  comunaName,
                warehouse_id: newWarehouseId,
            });
            document.cookie = 'sm_selected_location=' + encodeURIComponent(cookieData) + '; path=/; max-age=2592000';

            SmLocationPopup.closeModal();

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
                            window.location.reload();
                        },
                    });
                } else {
                    window.location.reload();
                }
            };

            if (warehouseChanged) {
                // Verificar stock del carrito en la nueva bodega antes de recargar
                $.ajax({
                    url:  sm_location_popup.ajax_url,
                    type: 'POST',
                    data: {
                        action:       'sm_switch_warehouse_cart',
                        nonce:        sm_location_popup.popup_nonce,
                        warehouse_id: newWarehouseId,
                    },
                    success: function (response) {
                        if (response.success && response.data.cleared) {
                            SmLocationPopup.renderCartSwitchToasts(response.data.items);
                            // Dar tiempo al usuario de leer los toasts antes de recargar
                            setTimeout(doReload, 2200);
                        } else {
                            doReload();
                        }
                    },
                    error: function () {
                        doReload();
                    },
                });
            } else {
                doReload();
            }
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
