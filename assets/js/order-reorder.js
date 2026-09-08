/**
 * Pedir de nuevo - Mi cuenta > Pedidos - Socomarca ERP
 * Reutiliza el ajax_url/nonce ya localizados en sm_location_popup.
 */
(function ($) {
    'use strict';

    var SmReorder = {

        init: function () {
            $(document).on('click', '.woocommerce-button.button.reorder', function (e) {
                e.preventDefault();

                var match = ($(this).attr('href') || '').match(/reorder-(\d+)/);
                if (!match) {
                    return;
                }

                SmReorder.start(parseInt(match[1], 10));
            });
        },

        start: function (orderId) {
            $.ajax({
                url: sm_location_popup.ajax_url,
                type: 'POST',
                data: {
                    action: 'sm_reorder_preview',
                    nonce: sm_location_popup.popup_nonce,
                    order_id: orderId,
                },
                success: function (response) {
                    if (!response.success) {
                        SmReorder.alertError(response);
                        return;
                    }

                    if (response.data.cart_has_items) {
                        SmReorder.showConfirm(orderId);
                    } else {
                        SmReorder.execute(orderId, false);
                    }
                },
                error: function () {
                    SmReorder.alertError();
                },
            });
        },

        showConfirm: function (orderId) {
            var $modal = SmReorder.buildModal(
                '<p class="sm-reorder-confirm-text">Se quitaran los productos actuales de su carrito.</p>' +
                '<div class="sm-reorder-confirm-actions">' +
                    '<button type="button" class="woocommerce-button button sm-reorder-confirm-yes">Confirmar</button>' +
                    '<button type="button" class="button sm-reorder-confirm-no">Cancelar</button>' +
                '</div>'
            );

            $modal.find('.sm-reorder-confirm-no, .sm-reorder-modal-backdrop').on('click', function () {
                SmReorder.closeModal($modal);
            });

            $modal.find('.sm-reorder-confirm-yes').on('click', function () {
                SmReorder.closeModal($modal);
                SmReorder.execute(orderId, true);
            });

            SmReorder.openModal($modal);
        },

        execute: function (orderId, hadItems) {
            var $progress = SmReorder.buildModal(
                '<div class="sm-reorder-progress">' +
                    '<div class="sm-reorder-spinner"></div>' +
                    '<p class="sm-reorder-progress-text"></p>' +
                '</div>'
            );

            var setStep = function (text) {
                $progress.find('.sm-reorder-progress-text').text(text);
            };

            setStep(hadItems ? 'Quitando productos de su carrito...' : 'Agregando productos a su carrito...');
            SmReorder.openModal($progress);

            var addStepTimer = hadItems
                ? setTimeout(function () { setStep('Agregando productos a su carrito...'); }, 700)
                : null;

            $.ajax({
                url: sm_location_popup.ajax_url,
                type: 'POST',
                data: {
                    action: 'sm_reorder_execute',
                    nonce: sm_location_popup.popup_nonce,
                    order_id: orderId,
                },
                success: function (response) {
                    clearTimeout(addStepTimer);

                    if (!response.success) {
                        SmReorder.closeModal($progress);
                        SmReorder.alertError(response);
                        return;
                    }

                    SmReorder.persistLocation(response.data);
                    setStep('Redirigiendo al carrito...');

                    setTimeout(function () {
                        window.location.href = response.data.cart_url;
                    }, 900);
                },
                error: function () {
                    clearTimeout(addStepTimer);
                    SmReorder.closeModal($progress);
                    SmReorder.alertError();
                },
            });
        },

        persistLocation: function (data) {
            if (!data || !data.comuna_name) {
                return;
            }

            var cookieData = JSON.stringify({
                region_id: data.region_id,
                region_name: data.region_name,
                comuna_id: data.comuna_id,
                comuna_name: data.comuna_name,
                warehouse_id: data.warehouse_id,
            });

            try {
                localStorage.setItem('sm_selected_location', cookieData);
                sessionStorage.setItem('sm_selected_location', cookieData);
            } catch (e) {}

            document.cookie = 'sm_selected_location=' + encodeURIComponent(cookieData) + '; path=/; max-age=2592000; SameSite=Lax';

            $(document).trigger('sm_location_selected', {
                comunaName: data.comuna_name,
                warehouseId: data.warehouse_id,
            });
        },

        buildModal: function (bodyHtml) {
            var $modal = $(
                '<div class="sm-reorder-modal" aria-modal="true" role="dialog">' +
                    '<div class="sm-reorder-modal-backdrop"></div>' +
                    '<div class="sm-reorder-modal-container">' +
                        '<div class="sm-reorder-modal-body">' + bodyHtml + '</div>' +
                    '</div>' +
                '</div>'
            );

            $('body').append($modal);

            return $modal;
        },

        openModal: function ($modal) {
            $('body').addClass('sm-modal-open');
            $modal.css('display', 'flex');
        },

        closeModal: function ($modal) {
            $('body').removeClass('sm-modal-open');
            $modal.remove();
        },

        alertError: function (response) {
            var message = (response && response.data && response.data.message)
                ? response.data.message
                : 'Ocurrio un error al procesar el pedido. Intente nuevamente.';
            window.alert(message);
        },
    };

    $(document).ready(function () {
        SmReorder.init();
    });

})(jQuery);
