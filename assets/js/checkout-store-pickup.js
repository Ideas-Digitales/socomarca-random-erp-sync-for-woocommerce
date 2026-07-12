jQuery(function($) {
    'use strict';

    var $wrapper = $('#sm-store-selector-wrapper');
    var $submit = $('#place_order');
    var $storeIdInput = $('#sm_pickup_store_id');
    var ajaxUrl = socomarcaStorePickup.ajaxUrl;
    var nonce = socomarcaStorePickup.nonce;
    var lastSelectedCommune = null;

    function isLocalPickup() {
        var shippingMethod = $('input[name^="shipping_method"]:checked').val() || '';
        return shippingMethod.indexOf('local_pickup') !== -1;
    }

    function getSelectedCommune() {
        try {
            var locationData = JSON.parse(sessionStorage.getItem('sm_selected_location'));
            if (locationData && locationData.comuna_name) {
                return locationData.comuna_name;
            }
        } catch (e) {
            // Silently fail
        }

        if (lastSelectedCommune) {
            return lastSelectedCommune;
        }

        return $('#billing_city').val() || '';
    }

    function displayStoreInfo(store) {
        if (store) {
            $('#sm-store-name').text(store.name);
            $('#sm-store-address').text(store.address);
            $storeIdInput.val(store.term_id);
        } else {
            $('#sm-store-name').text('-');
            $('#sm-store-address').text('-');
            $storeIdInput.val('');
        }
        updateSubmitButtonState();
    }

    function updateStoreDisplay() {
        var commune = getSelectedCommune();

        if (!commune) {
            displayStoreInfo(null);
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'sm_get_store_by_commune',
                commune: commune,
                nonce: nonce,
            },
            success: function(response) {
                if (response.success) {
                    displayStoreInfo(response.data);
                } else {
                    displayStoreInfo(null);
                }
            },
            error: function() {
                displayStoreInfo(null);
            }
        });
    }

    function updateVisibility() {
        var pickup = isLocalPickup();

        if (pickup) {
            $wrapper.slideDown(200);
            updateStoreDisplay();
        } else {
            $wrapper.slideUp(200);
            $storeIdInput.val('');
        }
        updateSubmitButtonState();
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
            var storeSelected = $storeIdInput.val() !== '';

            if (rutValid && storeSelected) {
                $submit.prop('disabled', false).css('opacity', '1').css('cursor', 'pointer');
            } else {
                $submit.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
            }
        } else {
            if (checkBillingFieldsState()) {
                $submit.prop('disabled', false).css('opacity', '1').css('cursor', 'pointer');
            } else {
                $submit.prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
            }
        }
    }

    $(document).on('sm_location_selected', function(e, data) {
        lastSelectedCommune = data.comunaName;
        updateStoreDisplay();
    });

    $(document).on('change', '#billing_city', updateStoreDisplay);
    $(document).on('change', 'input[name^="shipping_method"]', updateVisibility);
    $(document.body).on('updated_checkout', updateVisibility);
    $(document).on('keyup change', '#sm_rut, #sm_razon_social, #sm_giro, input[name="sm_documento_tipo"]', updateSubmitButtonState);

    updateVisibility();
});
