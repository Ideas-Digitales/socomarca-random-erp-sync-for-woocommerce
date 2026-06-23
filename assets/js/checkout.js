jQuery(function($) {
    'use strict';

    var $rutInput = $('#sm_rut');
    var $rutError = $('#sm_rut_error');
    var $documentoType = $('input[name="sm_documento_tipo"]');
    var $facturaFields = $('#sm-factura-fields');
    var $razonSocialWrapper = $('#sm-razon-social-wrapper');
    var $submitBtn = $('#place_order');

    // Validar RUT según el algoritmo chileno
    function validateRutAlgorithm(rut) {
        var rutClean = rut.replace(/[^0-9k]/gi, '').toUpperCase();
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

    // Actualizar estado de validación del RUT
    function updateRutValidation() {
        var rutValue = $rutInput.val().trim();

        if (rutValue === '') {
            $rutError.removeClass('show').text('');
            $rutInput.removeClass('sm-rut-invalid');
            return;
        }

        var isValid = validateRutAlgorithm(rutValue);

        if (isValid) {
            $rutError.removeClass('show').text('');
            $rutInput.removeClass('sm-rut-invalid');
        } else {
            $rutError.addClass('show').text('RUT Inválido');
            $rutInput.addClass('sm-rut-invalid');
        }

        updateSubmitButtonState();
    }

    // Actualizar estado del botón de envío
    function updateSubmitButtonState() {
        var rutValue = $rutInput.val().trim();
        var documentoType = $documentoType.filter(':checked').val();
        var razonSocialValue = $('#sm_razon_social').val().trim();
        var giroValue = $('#sm_giro').val().trim();

        var isRutValid = rutValue !== '' && validateRutAlgorithm(rutValue);
        var isFacturaValid = documentoType === 'factura' ? (razonSocialValue !== '' && giroValue !== '') : true;

        if (isRutValid && isFacturaValid) {
            $submitBtn.prop('disabled', false).css('opacity', '1').css('cursor', 'pointer');
        } else {
            $submitBtn.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
        }
    }

    // Mostrar/ocultar campos según el tipo de documento
    function toggleFacturaFields() {
        var tipo = $documentoType.filter(':checked').val();
        if (tipo === 'factura') {
            $facturaFields.slideDown(200);
            $razonSocialWrapper.slideDown(200);
        } else {
            $facturaFields.slideUp(200);
            $razonSocialWrapper.slideUp(200);
            $('#sm_razon_social').val('');
            $('#sm_giro').val('');
        }
        updateSubmitButtonState();
    }

    // Eventos
    $rutInput.on('keyup change', function() {
        // Aplicar formato usando jquery.rut si está disponible
        if (typeof $(this).rut === 'function') {
            $(this).rut({
                formatOn: 'keyup',
                validateOn: 'change'
            });
        }
        updateRutValidation();
    });

    $rutInput.on('blur', function() {
        updateRutValidation();
    });

    $documentoType.on('change', toggleFacturaFields);

    $('#sm_razon_social, #sm_giro').on('change keyup', function() {
        updateSubmitButtonState();
    });

    // Actualizar estado al cargar y cuando WooCommerce actualiza el checkout
    toggleFacturaFields();
    updateRutValidation();
    updateSubmitButtonState();

    $(document.body).on('updated_checkout', function() {
        updateRutValidation();
        updateSubmitButtonState();
    });
});
