jQuery(function($) {
    'use strict';

    var $wrapper = $('#sm-store-selector-wrapper');
    var $submit = $('#place_order');
    var $select = $('#sm_pickup_store_id');

    function isLocalPickup() {
        var shippingMethod = $('input[name^="shipping_method"]:checked').val() || '';
        return shippingMethod.indexOf('local_pickup') !== -1;
    }

    function updateVisibility() {
        var pickup = isLocalPickup();

        if (pickup) {
            $wrapper.slideDown(200);
            if (!$select.val()) {
                // El estado del botón será controlado por CheckoutBillingFields
                // pero aseguramos que se deshabilite si no hay tienda seleccionada
                var rutValid = checkRutValid();
                if (!rutValid) {
                    $submit.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
                } else {
                    $submit.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
                }
            } else {
                // Si hay tienda seleccionada, verificar RUT
                var rutValid = checkRutValid();
                if (rutValid) {
                    $submit.prop('disabled', false).css('opacity', '1').css('cursor', 'pointer');
                }
            }
        } else {
            $wrapper.slideUp(200);
            // Si no es local pickup, dejar que CheckoutBillingFields maneje el estado del botón
            if ($('#sm_rut').length) {
                checkBillingFieldsState();
            }
        }
    }

    function checkRutValid() {
        var rutValue = $('#sm_rut').val().trim();
        if (rutValue === '') {
            return false;
        }

        var rutClean = rutValue.replace(/[^0-9k]/gi, '').toUpperCase();
        var rutBody = rutClean.slice(0, -1);
        var rutCheckDigit = rutClean.slice(-1);

        if (!rutBody || !/^\d+$/.test(rutBody)) {
            return false;
        }

        var sum = 0;
        var multiplier = 2;

        for (var i = rutBody.length - 1; i >= 0; i--) {
            sum += parseInt(rutBody[i]) * multiplier;
            multiplier++;
            if (multiplier > 7) {
                multiplier = 2;
            }
        }

        var checkDigit = 11 - (sum % 11);
        var expectedCheckDigit;

        if (checkDigit === 11) {
            expectedCheckDigit = '0';
        } else if (checkDigit === 10) {
            expectedCheckDigit = 'K';
        } else {
            expectedCheckDigit = String(checkDigit);
        }

        return expectedCheckDigit === rutCheckDigit;
    }

    function checkBillingFieldsState() {
        var rutValid = checkRutValid();
        var documentoType = $('input[name="sm_documento_tipo"]:checked').val() || 'factura';
        var razonSocialValue = $('#sm_razon_social').val().trim();
        var giroValue = $('#sm_giro').val().trim();

        var isFacturaValid = documentoType === 'factura' ? (razonSocialValue !== '' && giroValue !== '') : true;

        if (rutValid && isFacturaValid) {
            return true;
        }
        return false;
    }

    function updateSubmitButtonState() {
        var pickup = isLocalPickup();

        if (pickup) {
            var rutValid = checkRutValid();
            var storeSelected = $select.val() !== '';

            if (rutValid && storeSelected) {
                $submit.prop('disabled', false).css('opacity', '1').css('cursor', 'pointer');
            } else {
                $submit.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
            }
        } else {
            // CheckoutBillingFields maneja el estado
            if (checkBillingFieldsState()) {
                $submit.prop('disabled', false).css('opacity', '1').css('cursor', 'pointer');
            } else {
                $submit.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
            }
        }
    }

    // Eventos
    $select.on('change', updateSubmitButtonState);
    $(document).on('change', 'input[name^="shipping_method"]', updateVisibility);
    $(document.body).on('updated_checkout', updateVisibility);

    // También monitorear cambios en RUT
    $(document).on('keyup change', '#sm_rut, #sm_razon_social, #sm_giro, input[name="sm_documento_tipo"]', updateSubmitButtonState);

    // Inicializar
    updateVisibility();
});
