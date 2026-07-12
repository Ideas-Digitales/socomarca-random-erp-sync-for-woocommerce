jQuery(function($) {
    'use strict';

    // Form elements
    var $form = $('#sm-register-form');
    var $alert = $('#sm-register-alert');
    var $btnSubmit = $('#sm-btn-submit');
    var $spinner = $btnSubmit.find('.sm-spinner');

    // Initialize jquery-rut on RUT field
    var $rutInput = $('#reg_rut');
    if (typeof $rutInput.rut === 'function') {
        $rutInput.rut({
            formatOn: 'keyup',
            validateOn: 'change'
        });
    }

    // Real-time AJAX Check Flags
    var isEmailChecking = false;
    var isEmailValid = false;
    var isRutChecking = false;
    var isRutValid = false;

    // Helper: HTML escaping (casts value to String first to prevent type errors)
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        var stringVal = String(str);
        return stringVal.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Helper: Replace dynamic Comuna elements
    function replaceWithSelect(id, name, optionsHtml, disabled) {
        var $el = $('#' + id);
        if ($el.is('select')) {
            $el.html(optionsHtml).prop('disabled', disabled);
        } else {
            var $newEl = $('<select>', {
                id: id,
                name: name,
                class: 'sm-select',
                required: true
            }).html(optionsHtml).prop('disabled', disabled);
            $el.replaceWith($newEl);
        }
        // Re-bind validation on change
        $('#' + id).on('change', function() {
            validateField($(this));
        });
    }

    function replaceWithTextInput(id, name) {
        var $el = $('#' + id);
        if ($el.is('input[type="text"]')) {
            return;
        }
        var $newEl = $('<input>', {
            type: 'text',
            id: id,
            name: name,
            class: 'sm-input',
            placeholder: 'Ingresa tu comuna o ciudad',
            required: true
        });
        $el.replaceWith($newEl);
        // Re-bind validation on change/keyup
        $('#' + id).on('change keyup', function() {
            validateField($(this));
        });
    }

    // Populate Comunas dynamically based on selected Region
    function updateComunasDropdown($stateSelect, isBilling) {
        var stateCode = $stateSelect.val();
        var idAttr = isBilling ? 'reg_billing_city' : 'reg_shipping_city';
        var nameAttr = isBilling ? 'billing_city' : 'shipping_city';

        if (!stateCode) {
            replaceWithSelect(idAttr, nameAttr, '<option value="">-- Selecciona una región primero --</option>', true);
            return;
        }

        var comunas = (sm_register_params.comunas && sm_register_params.comunas[stateCode]) ? sm_register_params.comunas[stateCode] : null;

        if (comunas && (Array.isArray(comunas) || typeof comunas === 'object')) {
            var optionsHtml = '<option value="">-- Selecciona una comuna --</option>';
            $.each(comunas, function(key, val) {
                // If comunas is an array, key is a numeric index, and val is the comuna name.
                // If it is an object, key is the comuna identifier and val is the comuna name.
                var optionValue = Array.isArray(comunas) ? val : key;
                optionsHtml += '<option value="' + escHtml(optionValue) + '">' + escHtml(val) + '</option>';
            });
            replaceWithSelect(idAttr, nameAttr, optionsHtml, false);
        } else {
            // Fallback: convert to text input
            replaceWithTextInput(idAttr, nameAttr);
        }
    }

    // Toggle Client Type Tiles (Persona / Empresa)
    $('.sm-type-tile').on('click', function() {
        var $tile = $(this);
        $tile.addClass('active').siblings().removeClass('active');
        $tile.find('input[type="radio"]').prop('checked', true).trigger('change');
    });

    $('input[name="customer_type"]').on('change', function() {
        var type = $(this).val();
        var $companyFields = $('#sm-company-fields-wrapper');
        
        if (type === 'empresa') {
            $companyFields.slideDown(250);
            $('#reg_business_name, #reg_giro').prop('required', true);
        } else {
            $companyFields.slideUp(200);
            $('#reg_business_name, #reg_giro').prop('required', false).val('').removeClass('sm-invalid sm-valid');
            $('#error_reg_business_name, #error_reg_giro').removeClass('show').text('');
        }
    });

    // Checkbox "Dirección de envío es la misma" change handler
    function handleShippingSectionToggle() {
        var isSame = $('#reg_ship_to_different_address').is(':checked');
        var $shippingWrapper = $('#sm-shipping-section-wrapper');
        var $shippingFields = $('#reg_shipping_first_name, #reg_shipping_last_name, #reg_shipping_state, #reg_shipping_city, #reg_shipping_address_1');

        if (isSame) {
            $shippingWrapper.slideUp(250);
            $shippingFields.prop('required', false).removeClass('sm-invalid sm-valid');
            // Clear errors
            $shippingFields.each(function() {
                var fieldId = $(this).attr('id');
                $('#error_' + fieldId).removeClass('show').text('');
            });
        } else {
            $shippingWrapper.slideDown(250);
            $shippingFields.prop('required', true);
        }
    }

    $('#reg_ship_to_different_address').on('change', function() {
        var isSame = $(this).is(':checked');
        $(this).val(isSame ? '0' : '1');
        handleShippingSectionToggle();
    });

    // Region dropdowns change handler
    $('#reg_billing_state').on('change', function() {
        updateComunasDropdown($(this), true);
    });

    $('#reg_shipping_state').on('change', function() {
        updateComunasDropdown($(this), false);
    });

    // Chilean RUT validation algorithm
    function validateRutAlgorithm(rut) {
        var rutClean = rut.replace(/[^0-9kK]/g, '').toUpperCase();
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

    // Display error on field
    function showFieldError($field, message) {
        $field.addClass('sm-invalid').removeClass('sm-valid');
        var fieldId = $field.attr('id');
        var $errorSpan = $('#error_' + fieldId);
        var $successSpan = $('#success_' + fieldId);
        
        if ($errorSpan.length) {
            $errorSpan.text(message).addClass('show');
        }
        if ($successSpan.length) {
            $successSpan.removeClass('show').text('');
        }
    }

    // Display success on field
    function showFieldSuccess($field, message) {
        $field.addClass('sm-valid').removeClass('sm-invalid');
        var fieldId = $field.attr('id');
        var $errorSpan = $('#error_' + fieldId);
        var $successSpan = $('#success_' + fieldId);
        
        if ($errorSpan.length) {
            $errorSpan.removeClass('show').text('');
        }
        if ($successSpan.length) {
            $successSpan.text(message).addClass('show');
        }
    }

    // Clear field status
    function clearFieldStatus($field) {
        $field.removeClass('sm-invalid sm-valid');
        var fieldId = $field.attr('id');
        var $errorSpan = $('#error_' + fieldId);
        var $successSpan = $('#success_' + fieldId);
        
        if ($errorSpan.length) {
            $errorSpan.removeClass('show').text('');
        }
        if ($successSpan.length) {
            $successSpan.removeClass('show').text('');
        }
    }

    // Real-time check: RUT
    function checkRutRealtime() {
        var rutVal = $rutInput.val().trim();
        if (rutVal === '') {
            showFieldError($rutInput, 'El RUT es requerido.');
            isRutValid = false;
            return;
        }

        if (rutVal.length > 12) {
            showFieldError($rutInput, 'El RUT no puede superar los 12 caracteres.');
            isRutValid = false;
            return;
        }

        if (!validateRutAlgorithm(rutVal)) {
            showFieldError($rutInput, 'RUT inválido.');
            isRutValid = false;
            return;
        }

        isRutChecking = true;
        showFieldSuccess($rutInput, 'Validando RUT...');

        $.ajax({
            url: sm_register_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sm_check_rut',
                nonce: sm_register_params.nonce,
                rut: rutVal
            },
            success: function(response) {
                isRutChecking = false;
                if (response.success) {
                    showFieldSuccess($rutInput, 'RUT disponible.');
                    isRutValid = true;
                } else {
                    showFieldError($rutInput, response.data.message || 'El RUT ya está registrado.');
                    isRutValid = false;
                }
            },
            error: function() {
                isRutChecking = false;
                clearFieldStatus($rutInput);
                isRutValid = true; // Fallback
            }
        });
    }

    // Real-time check: Email
    function checkEmailRealtime() {
        var $emailInput = $('#reg_email');
        var emailVal = $emailInput.val().trim();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (emailVal === '') {
            showFieldError($emailInput, 'El correo electrónico es requerido.');
            isEmailValid = false;
            return;
        }

        if (!emailRegex.test(emailVal)) {
            showFieldError($emailInput, 'Correo electrónico inválido.');
            isEmailValid = false;
            return;
        }

        isEmailChecking = true;
        showFieldSuccess($emailInput, 'Verificando disponibilidad...');

        $.ajax({
            url: sm_register_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sm_check_email',
                nonce: sm_register_params.nonce,
                email: emailVal
            },
            success: function(response) {
                isEmailChecking = false;
                if (response.success) {
                    showFieldSuccess($emailInput, 'Correo disponible.');
                    isEmailValid = true;
                } else {
                    showFieldError($emailInput, response.data.message || 'El correo ya está registrado.');
                    isEmailValid = false;
                }
            },
            error: function() {
                isEmailChecking = false;
                clearFieldStatus($emailInput);
                isEmailValid = true; // Fallback
            }
        });
    }

    // Attach real-time validation events
    $rutInput.on('blur', function() {
        checkRutRealtime();
    });

    $('#reg_email').on('blur', function() {
        checkEmailRealtime();
    });

    // Validate generic field on change/blur
    function validateField($field) {
        var val = $field.val();
        var isRequired = $field.prop('required') || false;
        
        if (isRequired && (!val || val.toString().trim() === '')) {
            showFieldError($field, 'Este campo es requerido.');
            return false;
        }

        // Custom validation types
        var id = $field.attr('id');
        if (id === 'reg_password' && val.length < 6) {
            showFieldError($field, 'La contraseña debe tener al menos 6 caracteres.');
            return false;
        }

        if (id === 'reg_password_confirm') {
            var pass = $('#reg_password').val();
            if (val !== pass) {
                showFieldError($field, 'Las contraseñas no coinciden.');
                return false;
            }
        }

        showFieldSuccess($field, '');
        return true;
    }

    // Attach change/keyup handlers for generic fields
    $form.find('input, select').not('#reg_rut, #reg_email').on('blur change keyup', function() {
        // Skip validation on keyup for empty/untouched fields to avoid aggressive styling
        if ($(this).val() === '' && !$(this).hasClass('sm-invalid')) {
            return;
        }
        validateField($(this));
    });

    // Validate All Fields in the Form
    function validateForm() {
        var isValid = true;

        // 1. Validate RUT
        var rutVal = $rutInput.val().trim();
        if (rutVal === '') {
            showFieldError($rutInput, 'El RUT es requerido.');
            isValid = false;
        } else if (rutVal.length > 12) {
            showFieldError($rutInput, 'El RUT no puede superar los 12 caracteres.');
            isValid = false;
        } else if (!validateRutAlgorithm(rutVal)) {
            showFieldError($rutInput, 'RUT inválido.');
            isValid = false;
        } else if (!isRutValid && !isRutChecking) {
            showFieldError($rutInput, 'El RUT ya está registrado.');
            isValid = false;
        }

        // 2. Validate client type specific fields
        var clientType = $('input[name="customer_type"]:checked').val();
        if (clientType === 'empresa') {
            if (!validateField($('#reg_business_name'))) isValid = false;
            if (!validateField($('#reg_giro'))) isValid = false;
        }

        // 3. Validate general account fields
        if (!validateField($('#reg_first_name'))) isValid = false;
        if (!validateField($('#reg_last_name'))) isValid = false;

        // 4. Validate Email
        var emailVal = $('#reg_email').val().trim();
        if (emailVal === '') {
            showFieldError($('#reg_email'), 'El correo electrónico es requerido.');
            isValid = false;
        } else if (!isEmailValid && !isEmailChecking) {
            showFieldError($('#reg_email'), 'El correo electrónico ya está registrado o es inválido.');
            isValid = false;
        }

        if (!validateField($('#reg_phone'))) isValid = false;
        if (!validateField($('#reg_password'))) isValid = false;
        if (!validateField($('#reg_password_confirm'))) isValid = false;

        // 5. Validate Billing Address
        if (!validateField($('#reg_billing_state'))) isValid = false;
        if (!validateField($('#reg_billing_city'))) isValid = false;
        if (!validateField($('#reg_billing_address_1'))) isValid = false;

        // 6. Validate Shipping Address (if different)
        var isSameAddress = $('#reg_ship_to_different_address').is(':checked');
        if (!isSameAddress) {
            if (!validateField($('#reg_shipping_first_name'))) isValid = false;
            if (!validateField($('#reg_shipping_last_name'))) isValid = false;
            if (!validateField($('#reg_shipping_state'))) isValid = false;
            if (!validateField($('#reg_shipping_city'))) isValid = false;
            if (!validateField($('#reg_shipping_address_1'))) isValid = false;
        }

        return isValid;
    }

    // Display notification alert banner
    function showAlert(type, message) {
        $alert.removeClass('sm-alert-error sm-alert-success').addClass('sm-alert-' + type).text(message).slideDown(200);
        
        // Auto scroll to alert
        $('html, body').animate({
            scrollTop: $('.sm-register-container').offset().top - 80
        }, 300);
    }

    function hideAlert() {
        $alert.slideUp(150);
    }

    // Event: Form Submission via Ajax
    $form.on('submit', function(e) {
        e.preventDefault();
        hideAlert();

        // Form validation
        if (!validateForm()) {
            showAlert('error', 'Por favor corrige los errores antes de continuar.');
            return;
        }

        // Disable buttons and show spinner
        $btnSubmit.prop('disabled', true);
        $spinner.fadeIn(150);

        // Prepare form data
        var formData = $form.serializeArray();
        formData.push({ name: 'action', value: 'sm_register_user' });
        formData.push({ name: 'nonce', value: sm_register_params.nonce });

        // Correct the ship_to_different_address value based on checkbox
        var isSameAddress = $('#reg_ship_to_different_address').is(':checked');
        formData = formData.filter(function(item) {
            return item.name !== 'ship_to_different_address';
        });
        formData.push({ name: 'ship_to_different_address', value: isSameAddress ? '0' : '1' });

        $.ajax({
            url: sm_register_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.data.message);
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                } else {
                    $btnSubmit.prop('disabled', false);
                    $spinner.fadeOut(150);

                    if (response.data.errors) {
                        // Display backend validations inline
                        $.each(response.data.errors, function(fieldId, errorMsg) {
                            var $field = $('#reg_' + fieldId);
                            if ($field.length) {
                                showFieldError($field, errorMsg);
                            }
                        });
                        showAlert('error', response.data.message || 'Corrige los errores indicados abajo.');
                    } else {
                        showAlert('error', response.data.message || 'Ocurrió un error en el registro.');
                    }
                }
            },
            error: function(xhr, status, error) {
                $btnSubmit.prop('disabled', false);
                $spinner.fadeOut(150);
                console.error("AJAX Error details:", { status: status, error: error, responseText: xhr.responseText });
                showAlert('error', 'Error de conexión con el servidor. Inténtalo de nuevo.');
            }
        });
    });

    // Initialize toggle state on page load
    handleShippingSectionToggle();
});
