/**
 * Variations Helper - Socomarca ERP
 * - Inyecta botones +/- en el input de cantidad
 * - Auto-selecciona la ubicacion en Multiloca segun la bodega del cookie
 * - Selecciona automaticamente la primera variacion si es la unica disponible
 */
(function ($) {
    'use strict';

    var SmVariationsHelper = {
        init: function () {
            this.initQuantityButtons();
            this.initMultilocaAutoSelect();
            this.autoSelectSingleVariation();
            this.initRelatedSlider();
            this.initAddToCartGating();
            this.initVariationStockDisplay();

            // Reiniciar botones si WooCommerce recarga el formulario (variaciones)
            $(document).on('woocommerce_variation_has_changed updated_checkout', function () {
                SmVariationsHelper.initQuantityButtons();
            });
        },

        initQuantityButtons: function () {
            $('.quantity').each(function () {
                var $wrapper = $(this);

                // No agregar dos veces
                if ($wrapper.find('.sm-quantity-btn').length) return;

                var $input = $wrapper.find('input.qty');
                if (!$input.length) return;

                var $minus = $('<button type="button" class="sm-quantity-btn minus">-</button>');
                var $plus  = $('<button type="button" class="sm-quantity-btn plus">+</button>');

                $input.before($minus);
                $input.after($plus);
            });

            // Delegar clicks para que funcione con elementos futuros
            $(document).off('click.sm-qty').on('click.sm-qty', '.sm-quantity-btn', function (e) {
                e.preventDefault();
                var $btn  = $(this);
                var $qty  = $btn.closest('.quantity').find('input.qty');
                var val   = parseFloat($qty.val()) || 0;
                var step  = parseFloat($qty.attr('step'))  || 1;
                var min   = parseFloat($qty.attr('min'))   || 1;
                var max   = parseFloat($qty.attr('max'));
                if (isNaN(max)) max = Infinity;

                if ($btn.hasClass('plus')) {
                    if (val + step <= max) $qty.val(val + step).trigger('change');
                } else {
                    if (val - step >= min) $qty.val(val - step).trigger('change');
                }
            });
        },

        initMultilocaAutoSelect: function () {
            if (typeof sm_location_popup === 'undefined') return;

            var warehouseId = parseInt(sm_location_popup.selected_warehouse_id, 10);
            if (!warehouseId) return;

            // Esperar a que Multiloca renderice su tabla
            var attempts = 0;
            var interval = setInterval(function () {
                var $row = $('[data-location-id="' + warehouseId + '"]');
                if ($row.length) {
                    clearInterval(interval);
                    $('.multiloca-location-selected').removeClass('multiloca-location-selected');
                    $row.addClass('multiloca-location-selected');
                } else if (++attempts > 20) {
                    clearInterval(interval);
                }
            }, 150);
        },

        initRelatedSlider: function () {
            var $slider = $('#sm-related-slider');
            if (!$slider.length) return;

            var $items    = $slider.find('.sm-related-item');
            var total     = $items.length;
            var current   = 0;
            var visible   = 5;

            function getVisible() {
                var w = $(window).width();
                if (w <= 600)  return 1;
                if (w <= 1024) return 3;
                return 5;
            }

            function slideTo(idx) {
                visible = getVisible();
                var max = Math.max(0, total - visible);
                current = Math.max(0, Math.min(idx, max));
                var pct = current * (100 / visible);
                $items.css('transform', 'translateX(-' + pct + '%)');
            }

            $('.sm-related-prev').on('click', function () {
                slideTo(current - 1);
            });

            $('.sm-related-next').on('click', function () {
                slideTo(current + 1);
            });

            $(window).on('resize.smrelated', function () {
                slideTo(current);
            });
        },

        initAddToCartGating: function () {
            if (!$('.variations_form').length) return;

            var $btn = $('button.single_add_to_cart_button');
            if (!$btn.length) return;

            var $hint = $('<p class="sm-cart-gating-hint"></p>');
            $btn.after($hint);
            $hint.hide();

            function hasVariation() {
                return !!$('input[name="variation_id"]').val();
            }

            function hasLocation() {
                return !!$('.multiloca-location-selected').length;
            }

            function lockButton(message) {
                $btn.prop('disabled', true).addClass('sm-btn-gated');
                $hint.text(message).show();
            }

            function unlockButton() {
                $btn.prop('disabled', false).removeClass('sm-btn-gated');
                $hint.hide();
            }

            function setTriggerLocked(locked) {
                $('.sm-location-popup-trigger').toggleClass('sm-location-locked', locked);
            }

            function evaluate() {
                var v = hasVariation();
                var l = hasLocation();

                if (v && l) {
                    setTriggerLocked(false);
                    unlockButton();
                } else if (v) {
                    setTriggerLocked(false);
                    //lockButton('Selecciona tu ubicacion para agregar al carrito');
                } else {
                    setTriggerLocked(true);
                    //lockButton('Selecciona la variacion y tu ubicacion para agregar al carrito');
                }
            }

            // Estado inicial
            setTriggerLocked(true);
            //lockButton('Selecciona la variacion y tu ubicacion para agregar al carrito');

            setTimeout(function() {
                jQuery('.variations #unidad option[value!=""]:first').prop('selected', true).trigger('change');
            }, 1000);

            setTimeout(function () {
                var warehouseId = (typeof sm_location_popup !== 'undefined' && sm_location_popup.selected_warehouse_id)
                    ? sm_location_popup.selected_warehouse_id
                    : 0;
                var $row = warehouseId
                    ? jQuery('.multiloca-lite-table [data-location-id="' + warehouseId + '"]')
                    : jQuery();
                if (!$row.length) {
                    $row = jQuery('.multiloca-lite-table tbody tr:first');
                }
                $row.find('td:first').click();
            }, 2000);

            setTimeout(function() {
            unlockButton();
            }, 3000);


        },

        initVariationStockDisplay: function () {
            var $stockEl = $('.sm-meta-item.sm-stock');
            if (!$stockEl.length) return;

            var stocks = (typeof sm_location_popup !== 'undefined' && sm_location_popup.variation_stocks)
                ? sm_location_popup.variation_stocks
                : null;

            // Ocultar hasta tener el stock de la bodega
            $stockEl.hide();

            $(document).on('found_variation.smstock', function (e, variation) {
                var vid = String(variation.variation_id);
                var qty = (stocks && stocks[vid] !== undefined && stocks[vid] !== null)
                    ? stocks[vid]
                    : null;

                if (qty !== null) {
                    $stockEl.html('<strong>Stock</strong> ' + qty).show();
                } else if (variation.is_in_stock) {
                    $stockEl.html('<strong>Stock</strong> Disponible').show();
                } else {
                    $stockEl.html('<strong>Stock</strong> Sin stock').show();
                }

                // Actualizar max del input de cantidad con el stock de la bodega.
                // Corre despues de WooCommerce (setTimeout 0) para sobreescribir su valor.
                if (qty !== null) {
                    setTimeout(function () {
                        var $qty = $('input.qty');
                        $qty.attr('max', qty);
                        var current = parseInt($qty.val(), 10) || 1;
                        if (current > qty) {
                            $qty.val(qty > 0 ? qty : 0).trigger('change');
                        }
                    }, 0);
                }
            });

            $(document).on('reset_data.smstock', function () {
                $stockEl.hide();
                $('input.qty').removeAttr('max');
            });
        },

        autoSelectSingleVariation: function () {
            if (typeof sm_location_popup === 'undefined' || sm_location_popup.hide_variation_selector !== '1') return;

            var $form = $('.variations_form');
            if ($form.length === 0) return;

            setTimeout(function () {
                var $variations = $form.find('.variations select');
                var allSingle = true;

                $variations.each(function () {
                    var $select  = $(this);
                    var $options = $select.find('option').not('[value=""]');

                    if ($options.length === 1) {
                        var val = $options.val();
                        if ($select.val() !== val) {
                            $select.val(val).trigger('change');
                        }
                        $select.closest('tr').hide();
                        $select.closest('.variation-row').hide();
                    } else if ($options.length > 1) {
                        allSingle = false;
                    }
                });

                if (allSingle && $variations.length > 0) {
                    $form.find('.variations').hide();
                }
            }, 150);
        },
    };

    $(document).ready(function () {
        SmVariationsHelper.init();
    });

})(jQuery);
