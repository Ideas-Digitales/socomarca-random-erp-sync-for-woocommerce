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
            var regionId = sm_location_popup.selected_region;
            var comunaId = sm_location_popup.selected_comuna;

            if (!regionId) return;

            var $regionSelect = $('#sm-region-select');
            $regionSelect.val(regionId);

            if (regionId) {
                var regionName = $regionSelect.find('option:selected').text();
                SmLocationPopup.loadComunas(regionId, regionName, comunaId);
            }
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

        loadComunas: function (regionId, regionName, preselectComunaId) {
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

            // Guardar seleccion en cookie para mostrar en el boton y para filtrar el loop
            var cookieData = JSON.stringify({
                region_id:    regionId,
                region_name:  regionName,
                comuna_id:    comunaId,
                comuna_name:  comunaName,
                warehouse_id: warehouseId ? parseInt(warehouseId, 10) : null,
            });
            document.cookie = 'sm_selected_location=' + encodeURIComponent(cookieData) + '; path=/; max-age=2592000';

            if (warehouseId) {
                // Pasar bodega a multiloca y recargar
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
                // Sin bodega asignada, solo actualizar el display
                window.location.reload();
            }
        },
    };

    $(document).ready(function () {
        SmLocationPopup.init();
    });

})(jQuery);
