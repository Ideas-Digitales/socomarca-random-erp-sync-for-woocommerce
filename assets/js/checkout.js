jQuery(function($) {
    'use strict';

    // Shared RUT validation algorithm
    var validateRutAlgorithm = function(rut) {
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
    };

    // --- Lógica para Checkout ---
    var $rutInput = $('#sm_rut');
    var $rutError = $('#sm_rut_error');
    var $documentoType = $('input[name="sm_documento_tipo"]');
    var $facturaFields = $('#sm-factura-fields');
    var $razonSocialWrapper = $('#sm-razon-social-wrapper');
    var $submitBtn = $('#place_order');

    if ($rutInput.length) {
        // Actualizar estado de validación del RUT
        var updateRutValidation = function() {
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
        };

        // Actualizar estado del botón de envío
        var updateSubmitButtonState = function() {
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
        };

        // Mostrar/ocultar campos según el tipo de documento
        var toggleFacturaFields = function() {
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
        };

        // Eventos
        $rutInput.on('keyup change', function() {
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

        // Inicialización
        toggleFacturaFields();
        updateRutValidation();
        updateSubmitButtonState();

        $(document.body).on('updated_checkout', function() {
            updateRutValidation();
            updateSubmitButtonState();
        });
    }

    // --- Lógica para Mi Cuenta (Edición de Dirección de Facturación) ---
    var $editDocType = $('#billing_documento_tipo');
    var $companyField = $('#billing_company_field');
    var $giroField = $('#billing_giro_field');
    var $editRutInput = $('#billing_rut');

    if ($editDocType.length) {
        console.log('[SM-MYACCOUNT-DEBUG]', {
            editDocTypeLength: $editDocType.length,
            companyFieldLength: $companyField.length,
            giroFieldLength: $giroField.length,
            editRutInputLength: $editRutInput.length
        });

        // Insertar contenedor de error para RUT dinámicamente si no existe
        if (!$('#billing_rut_error').length) {
            $editRutInput.after('<span id="billing_rut_error" class="sm-rut-error" style="color: #a00; font-size: 12px; display: none; margin-top: 5px;"></span>');
        }
        var $editRutError = $('#billing_rut_error');

        // Función para validar RUT en Mi Cuenta
        var updateEditRutValidation = function() {
            var val = $editRutInput.val().trim();
            if (val === '') {
                $editRutError.text('').hide();
                $editRutInput.removeClass('sm-rut-invalid');
                return;
            }

            var isValid = validateRutAlgorithm(val);
            if (isValid) {
                $editRutError.text('').hide();
                $editRutInput.removeClass('sm-rut-invalid');
            } else {
                $editRutError.text('RUT Inválido').show();
                $editRutInput.addClass('sm-rut-invalid');
            }
        };

        // Formatear RUT en Mi Cuenta si jquery.rut está disponible
        if (typeof $editRutInput.rut === 'function') {
            $editRutInput.rut({
                formatOn: 'keyup',
                validateOn: 'change'
            });
        }

        $editRutInput.on('keyup change blur', updateEditRutValidation);
        updateEditRutValidation(); // Validación inicial

        // Forzar etiquetas como requeridas en la vista de forma ultra-robusta
        var forceRequiredLabel = function(fieldId) {
            // 1. Intentar por atributo 'for' de la etiqueta label
            var $label = $('label[for="' + fieldId + '"]');
            
            // 2. Si no la encuentra, buscar dentro del contenedor común del input
            if (!$label.length) {
                var $input = $('#' + fieldId);
                if ($input.length) {
                    $label = $input.closest('.form-row, .elementor-field-group, .jet-woo-builder-field, .woocommerce-form-row').find('label');
                }
            }

            if ($label.length) {
                $label.find('.optional').hide();
                
                // Reemplazar texto crudo " (opcional)" o "(optional)" si no viene en una etiqueta span
                var html = $label.html();
                if (html.indexOf('(opcional)') !== -1) {
                    $label.html(html.replace(' (opcional)', '').replace('(opcional)', ''));
                }
                if (html.indexOf('(optional)') !== -1) {
                    $label.html(html.replace(' (optional)', '').replace('(optional)', ''));
                }
                
                // Añadir asterisco si no existe
                if (!$label.find('.required').length) {
                    $label.append(' <abbr class="required" title="obligatorio">*</abbr>');
                }
            }
        };

        // Función para ocultar/mostrar en Mi Cuenta
        var toggleEditFields = function() {
            var val = $editDocType.val();
            var $companyLabel = $companyField.find('label');
            var $giroLabel = $giroField.find('label');

            if (val === 'factura') {
                $companyField.show(200);
                $giroField.show(200);

                // Ocultar el texto "(opcional)" nativo de WooCommerce
                $companyLabel.find('.optional').hide();
                $giroLabel.find('.optional').hide();

                // Añadir asterisco de requerido si no existe
                if (!$companyLabel.find('.required').length) {
                    $companyLabel.append(' <abbr class="required" title="obligatorio">*</abbr>');
                }
                if (!$giroLabel.find('.required').length) {
                    $giroLabel.append(' <abbr class="required" title="obligatorio">*</abbr>');
                }
            } else {
                $companyField.hide(200);
                $giroField.hide(200);
                
                // Mostrar el texto "(opcional)" nativo de WooCommerce
                $companyLabel.find('.optional').show();
                $giroLabel.find('.optional').show();

                // Quitar asterisco de requerido
                $companyLabel.find('.required').remove();
                $giroLabel.find('.required').remove();
                
                $('#billing_company').val('');
                $('#billing_giro').val('');
            }

            // Aplicar obligatoriedad visual en el DOM
            forceRequiredLabel('billing_first_name');
            forceRequiredLabel('billing_last_name');
            forceRequiredLabel('billing_address_1');
            forceRequiredLabel('billing_state');
            forceRequiredLabel('billing_city');
            forceRequiredLabel('billing_phone');
            forceRequiredLabel('billing_email');
            forceRequiredLabel('billing_rut');
        };

        $editDocType.on('change', toggleEditFields);
        toggleEditFields(); // Carga inicial

        // Ejecutar cuando WooCommerce actualiza el estado/país o campos de dirección
        $(document.body).on('country_to_state_changed', function() {
            toggleEditFields();
        });

        // Copia de seguridad con retardos para ganarle la carrera a scripts de plantilla/B2BKing
        setTimeout(toggleEditFields, 200);
        setTimeout(toggleEditFields, 1000);
    }
});
