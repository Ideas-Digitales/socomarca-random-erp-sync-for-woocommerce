/**
 * Variations Helper - Socomarca ERP
 * - Inyecta botones +/- en el input de cantidad
 * - Auto-selecciona la ubicacion en Multiloca segun la bodega del cookie
 * - Valida stock en tiempo real segun la bodega seleccionada
 */
(function ($) {
    'use strict';

    var SmVariationsHelper = {
        init: function () {
            if (typeof window.smProductHasStock !== 'undefined' && !window.smProductHasStock) {
                console.log('[SM-VARIATIONS] Producto sin stock, deshabilitando variations helper');
                this.hideQuantityCompletely();
                return;
            }

            this.initQuantityButtons();
            this.initMultilocaAutoSelect();
            this.initRelatedSlider();
            this.initAddToCartGating();
            this.initVariationStockDisplay();

            $(document).on('woocommerce_variation_has_changed updated_checkout', function () {
                SmVariationsHelper.initQuantityButtons();
            });
        },

        hideQuantityCompletely: function () {
            $('.quantity').hide();
            $('input.qty').hide();
            $('.sm-quantity-btn').remove();
        },

        initQuantityButtons: function () {
            $('.quantity').each(function () {
                var $wrapper = $(this);
                if ($wrapper.find('.sm-quantity-btn').length) return;
                var $input = $wrapper.find('input.qty');
                if (!$input.length) return;
                var $minus = $('<button type="button" class="sm-quantity-btn minus">-</button>');
                var $plus  = $('<button type="button" class="sm-quantity-btn plus">+</button>');
                $input.before($minus);
                $input.after($plus);
            });

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

            var $form = $('form.cart');
            var $btn = $('button.single_add_to_cart_button');
            if (!$btn.length) return;

            var $stockEl = $('.sm-meta-item.sm-stock');
            var originalStockHtml = $stockEl.length ? $stockEl.html() : '';

            var productId = typeof window.smProductId !== 'undefined' ? window.smProductId : $('input[name="product_id"]').val();
            var validatingStock = false;

            function getSelectedWarehouse() {
                // Obtener el warehouse_id de la fila seleccionada en Multiloca
                var $selectedRow = $('.multiloca-location-selected');
                if ($selectedRow.length) {
                    var warehouseId = $selectedRow.attr('data-location-id');
                    if (warehouseId) {
                        return parseInt(warehouseId, 10);
                    }
                }
                // Fallback a sm_location_popup
                if (typeof sm_location_popup !== 'undefined' && sm_location_popup.selected_warehouse_id) {
                    return parseInt(sm_location_popup.selected_warehouse_id, 10);
                }
                return null;
            }

            function lockButton(message) {
                console.log('[SM-GATING] LOCK button - message:', message);
                $btn.prop('disabled', true).addClass('sm-btn-gated');
                if ($stockEl.length) {
                    $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">' + message + '</span>');
                }
            }

            function unlockButton() {
                console.log('[SM-GATING] UNLOCK button');
                $btn.prop('disabled', false).removeClass('sm-btn-gated');
                if ($stockEl.length) {
                    $stockEl.html(originalStockHtml);
                }
            }

            function hideQuantity() {
                console.log('[SM-GATING] HIDE quantity');
                $form.find('.quantity')[0].setAttribute('style', 'display: none !important');
                $form.find('input.qty')[0].setAttribute('style', 'display: none !important');
                $form.find('.sm-quantity-btn').each(function() {
                    $(this)[0].setAttribute('style', 'display: none !important');
                });
            }

            function showQuantity() {
                console.log('[SM-GATING] SHOW quantity');
                $form.find('.quantity')[0].removeAttribute('style');
                $form.find('input.qty')[0].removeAttribute('style');
                $form.find('.sm-quantity-btn').each(function() {
                    $(this)[0].removeAttribute('style');
                });
            }

            function validateVariationStock(variationId) {
                var warehouseId = getSelectedWarehouse();

                console.log('[SM-GATING] validateVariationStock', {
                    variationId: variationId,
                    productId: productId,
                    warehouseId: warehouseId
                });

                if (!variationId || !warehouseId || validatingStock) {
                    console.log('[SM-GATING] Skipping validation');
                    return;
                }

                validatingStock = true;
                $btn.prop('disabled', true);

                $.ajax({
                    url: (typeof sm_location_popup !== 'undefined' ? sm_location_popup.ajax_url : ajaxurl),
                    type: 'POST',
                    data: {
                        action: 'sm_validate_variation_stock',
                        variation_id: variationId,
                        product_id: productId,
                        warehouse_id: warehouseId
                    },
                    success: function(response) {
                        validatingStock = false;

                        console.log('[SM-GATING] Response', {
                            warehouse: warehouseId,
                            has_stock: response.data.has_stock,
                            stock: response.data.stock
                        });

                        if (response.success && response.data.has_stock) {
                            console.log('[SM-GATING] TIENE stock');
                            unlockButton();
                            showQuantity();
                        } else {
                            console.log('[SM-GATING] SIN stock');
                            hideQuantity();
                            lockButton('Sin stock en esta ubicacion');
                        }
                    },
                    error: function() {
                        validatingStock = false;
                        console.error('[SM-GATING] Error AJAX');
                        unlockButton();
                        showQuantity();
                    }
                });
            }

            function evaluate() {
                var variationId = $('input[name="variation_id"]').val();
                var warehouseId = getSelectedWarehouse();

                console.log('[SM-GATING] evaluate', {
                    variationId: variationId,
                    warehouseId: warehouseId
                });

                if (variationId && warehouseId) {
                    validateVariationStock(variationId);
                } else {
                    console.log('[SM-GATING] Waiting for selection');
                    hideQuantity();
                    lockButton('Selecciona ubicacion y variacion');
                }
            }

            // Eventos de variacion
            $(document).on('found_variation', function() {
                console.log('[SM-GATING] found_variation');
                evaluate();
            });

            $(document).on('reset_data', function() {
                console.log('[SM-GATING] reset_data');
                hideQuantity();
                lockButton('Selecciona la variacion');
            });

            // Escuchar CLICKS en tabla de Multiloca
            $(document).on('click', '.multiloca-lite-table tbody tr', function() {
                console.log('[SM-GATING] Multiloca location clicked');
                var newWarehouseId = $(this).attr('data-location-id');
                console.log('[SM-GATING] New warehouse:', newWarehouseId);

                // Marcar como seleccionada
                $('.multiloca-location-selected').removeClass('multiloca-location-selected');
                $(this).addClass('multiloca-location-selected');

                // Re-evaluar stock
                setTimeout(function() {
                    evaluate();
                }, 100);
            });

            // Esperar a que Multiloca renderice su tabla
            var multilocaReady = setInterval(function() {
                var $table = jQuery('.multiloca-lite-table tbody tr');
                if ($table.length) {
                    clearInterval(multilocaReady);
                    console.log('[SM-GATING] Multiloca table ready');

                    // Manejar selector de variaciones - auto-seleccionar y ocultar si hay una sola
                    var $variations = jQuery('.variations_form .variations');
                    if ($variations.length) {
                        var allSingleOption = true;

                        $variations.find('select').each(function() {
                            var $select = jQuery(this);
                            var $options = $select.find('option[value!=""]');

                            if ($options.length === 1) {
                                console.log('[SM-GATING] Single option found in select, auto-selecting');
                                var val = $options.val();
                                $select.val(val).trigger('change');
                                // Ocultar la row/variation-row
                                $select.closest('tr')[0].setAttribute('style', 'display: none !important');
                                $select.closest('.variation-row')[0].setAttribute('style', 'display: none !important');
                            } else if ($options.length > 1) {
                                allSingleOption = false;
                            }
                        });

                        // Si todas las variaciones tienen una sola opcion, ocultar el contenedor
                        if (allSingleOption && $variations.find('select').length > 0) {
                            console.log('[SM-GATING] All variations have single option, hiding variations wrapper');
                            $variations[0].setAttribute('style', 'display: none !important');
                        }
                    }

                    // Marcar la primera fila de Multiloca como seleccionada
                    var $firstRow = jQuery('.multiloca-lite-table tbody tr:first');
                    if ($firstRow.length && !jQuery('.multiloca-location-selected').length) {
                        console.log('[SM-GATING] Marking first Multiloca row as selected');
                        $firstRow.addClass('multiloca-location-selected');
                    }

                    // Evaluar después de un pequeño delay
                    setTimeout(function() {
                        evaluate();
                    }, 200);
                }
            }, 100);
        },

        initVariationStockDisplay: function () {
            var $stockEl = $('.sm-meta-item.sm-stock');
            if (!$stockEl.length) return;
            var stocks = (typeof sm_location_popup !== 'undefined' && sm_location_popup.variation_stocks)
                ? sm_location_popup.variation_stocks
                : null;
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
    };

    $(document).ready(function () {
        SmVariationsHelper.init();
    });

})(jQuery);
