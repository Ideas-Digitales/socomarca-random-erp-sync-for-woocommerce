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
            if (typeof window.smProductHasStock !== 'undefined' && window.smProductHasStock === false) {
                console.log('[SM-VARIATIONS] Producto sin stock confirmado, deshabilitando variations helper');
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
            
            console.log('[SM-VARIATIONS] Attempting to auto-select warehouse:', warehouseId);
            
            var attempts = 0;
            var interval = setInterval(function () {
                var $row = $('.multiloca-lite-table tr[data-location-id="' + warehouseId + '"]');
                if ($row.length) {
                    clearInterval(interval);
                    console.log('[SM-VARIATIONS] Found row for warehouse ' + warehouseId + ', clicking...');
                    
                    // Simular click real para que Multiloca Lite procese la selección
                    $row.trigger('click');
                    
                    // Asegurar visual
                    $('.multiloca-location-selected').removeClass('multiloca-location-selected');
                    $row.addClass('multiloca-location-selected');
                } else if (++attempts > 30) {
                    clearInterval(interval);
                    console.warn('[SM-VARIATIONS] Could not find row for warehouse ' + warehouseId + ' after 30 attempts');
                }
            }, 200);
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
            var $form = $('form.cart');
            var $btn = $('button.single_add_to_cart_button');
            if (!$btn.length) return;

            var isVariable = $('.variations_form').length > 0;
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

            // DISEÑO "SIN STOCK" NO UNIFICADO (conocido, pendiente, ver tambien
            // ProductPageCustomizer::displayProductExtraMeta() en PHP):
            //
            // Para productos simples sin stock, ProductStockValidator.php le
            // CAMBIA EL TEXTO al boton real ("Sin Stock") y le agrega la
            // clase sm-out-of-stock. Aqui, en cambio, el boton se deja
            // disabled con su texto ORIGINAL sin tocar (ej: "Añadir al
            // carrito" en gris) y se agrega sm-btn-gated en vez de
            // sm-out-of-stock; el `message` recibido ("Sin stock en esta
            // ubicacion", etc.) NO se pinta en el boton, solo llega hasta
            // console.log -- el aviso real para el usuario lo escribe
            // initVariationStockDisplay()/fetchVariationStock() en un
            // <span> rojo aparte, dentro de .sm-meta-item.sm-stock (ver
            // ProductPageCustomizer.php), un elemento del DOM totalmente
            // distinto al boton.
            //
            // No se unifico con el diseño de productos simples porque, al
            // momento de escribir esto, el catalogo no tiene ningun producto
            // variable (0 variable / 2981 simple en la taxonomia
            // product_type) para probar el cambio contra el sitio real. Para
            // unificarlo: ademas de disabled/sm-btn-gated, poner
            // $btn.html(message || 'Sin Stock') y usar/agregar la clase
            // sm-out-of-stock aqui, y decidir si el <span> rojo de
            // .sm-meta-item.sm-stock se mantiene como complemento o se
            // retira por quedar redundante.
            function lockButton(message) {
                console.log('[SM-GATING] LOCK button - message:', message);
                $btn.prop('disabled', true).addClass('sm-btn-gated');
            }

            function unlockButton() {
                console.log('[SM-GATING] UNLOCK button');
                $btn.prop('disabled', false).removeClass('sm-btn-gated');
            }

            function hideQuantity() {
                console.log('[SM-GATING] HIDE quantity');
                var $qty = $form.find('.quantity');
                if ($qty.length) $qty[0].setAttribute('style', 'display: none !important');
                
                var $qtyInput = $form.find('input.qty');
                if ($qtyInput.length) $qtyInput[0].setAttribute('style', 'display: none !important');
                
                $form.find('.sm-quantity-btn').each(function() {
                    $(this)[0].setAttribute('style', 'display: none !important');
                });
            }

            function showQuantity() {
                console.log('[SM-GATING] SHOW quantity');
                var $qty = $form.find('.quantity');
                if ($qty.length) $qty[0].removeAttribute('style');
                
                var $qtyInput = $form.find('input.qty');
                if ($qtyInput.length) $qtyInput[0].removeAttribute('style');
                
                $form.find('.sm-quantity-btn').each(function() {
                    $(this)[0].removeAttribute('style');
                });
            }

            function validateStock(id, isVariation) {
                var warehouseId = getSelectedWarehouse();

                console.log('[SM-GATING] validateStock', {
                    id: id,
                    isVariation: isVariation,
                    productId: productId,
                    warehouseId: warehouseId
                });

                if (!id || !warehouseId || validatingStock) {
                    console.log('[SM-GATING] Skipping validation');
                    return;
                }

                validatingStock = true;
                $btn.prop('disabled', true);

                $.ajax({
                    url: (typeof sm_location_popup !== 'undefined' ? sm_location_popup.ajax_url : ajaxurl),
                    type: 'POST',
                    data: {
                        action: isVariation ? 'sm_validate_variation_stock' : 'sm_get_variation_stock', // Reusamos el de variacion o el general
                        variation_id: isVariation ? id : 0,
                        product_id: isVariation ? productId : id,
                        warehouse_id: warehouseId
                    },
                    success: function(response) {
                        validatingStock = false;

                        console.log('[SM-GATING] Response', response);

                        if (response.success && (response.data.has_stock || response.data.stock > 0)) {
                            console.log('[SM-GATING] TIENE stock');
                            unlockButton();
                            showQuantity();
                            
                            // Actualizar visual de stock para productos simples
                            if (!isVariation && $stockEl.length) {
                                $stockEl.html('<strong>Stock</strong> ' + response.data.stock).show();
                                SmVariationsHelper.updateQtyMax(response.data.stock);
                            }
                        } else {
                            console.log('[SM-GATING] SIN stock');
                            hideQuantity();
                            lockButton('Sin stock en esta ubicacion');
                            
                            // Actualizar visual de stock para productos simples
                            if (!isVariation && $stockEl.length) {
                                $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicacion</span>').show();
                            }
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
                var warehouseId = getSelectedWarehouse();
                
                if (isVariable) {
                    var variationId = $('input[name="variation_id"]').val();
                    console.log('[SM-GATING] evaluate (variable)', { variationId: variationId, warehouseId: warehouseId });
                    if (variationId && warehouseId) {
                        validateStock(variationId, true);
                    } else {
                        hideQuantity();
                        lockButton('Selecciona ubicacion y variacion');
                    }
                } else {
                    console.log('[SM-GATING] evaluate (simple)', { productId: productId, warehouseId: warehouseId });
                    if (productId && warehouseId) {
                        validateStock(productId, false);
                    } else {
                        hideQuantity();
                        lockButton('Selecciona ubicacion');
                    }
                }
            }

            // Eventos de variacion (solo si es variable)
            if (isVariable) {
                $(document).on('found_variation', function() {
                    console.log('[SM-GATING] found_variation');
                    evaluate();
                });

                $(document).on('reset_data', function() {
                    console.log('[SM-GATING] reset_data');
                    hideQuantity();
                    lockButton('Selecciona la variacion');
                });
            }

            // Escuchar CLICKS en tabla de Multiloca
            $(document).on('click', '.multiloca-lite-table tbody tr', function() {
                console.log('[SM-GATING] Multiloca location clicked');
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

                    // Marcar la primera fila de Multiloca como seleccionada si no hay seleccion previa
                    if (!jQuery('.multiloca-location-selected').length) {
                        var $selectedRow = null;
                        
                        // Intentar buscar la bodega que viene por defecto o por cookie
                        if (typeof sm_location_popup !== 'undefined' && sm_location_popup.selected_warehouse_id) {
                            $selectedRow = jQuery('.multiloca-lite-table tbody tr[data-location-id="' + sm_location_popup.selected_warehouse_id + '"]');
                        }
                        
                        if (!$selectedRow || !$selectedRow.length) {
                            $selectedRow = jQuery('.multiloca-lite-table tbody tr:first');
                        }
                        
                        if ($selectedRow.length) {
                            console.log('[SM-GATING] Marking location as selected');
                            $selectedRow.addClass('multiloca-location-selected');
                        }
                    }

                    // Evaluar después de un pequeño delay
                    setTimeout(function() {
                        evaluate();
                    }, 200);
                }
            }, 100);

            // Para productos simples, si ya tenemos warehouseId, evaluar de inmediato
            if (!isVariable) {
                var initialWarehouse = getSelectedWarehouse();
                if (initialWarehouse) {
                    console.log('[SM-GATING] Simple product with initial warehouse, evaluating...');
                    evaluate();
                }
            }
        },

        initVariationStockDisplay: function () {
            var $stockEl = $('.sm-meta-item.sm-stock');
            if (!$stockEl.length) return;
            var stocks = (typeof sm_location_popup !== 'undefined' && sm_location_popup.variation_stocks)
                ? sm_location_popup.variation_stocks
                : null;
            var warehouseId = (typeof sm_location_popup !== 'undefined' && sm_location_popup.selected_warehouse_id)
                ? parseInt(sm_location_popup.selected_warehouse_id, 10)
                : null;
            $stockEl.hide();

            $(document).on('found_variation.smstock', function (e, variation) {
                var vid = String(variation.variation_id);
                var qty = (stocks && stocks[vid] !== undefined && stocks[vid] !== null)
                    ? parseInt(stocks[vid], 10)
                    : null;

                console.log('[SM-STOCK-DISPLAY] Variation ' + vid + ' - qty: ' + qty + ' - warehouse: ' + warehouseId + ' - is_in_stock: ' + variation.is_in_stock);

                // Si tenemos datos de stock de la ubicacion, usarlos
                if (qty !== null) {
                    if (qty > 0) {
                        $stockEl.html('<strong>Stock</strong> ' + qty).show();
                    } else {
                        $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicacion</span>').show();
                    }
                    SmVariationsHelper.updateQtyMax(qty);
                }
                // Si no hay datos de ubicacion pero el warehouse esta seleccionado, consultar servidor
                else if (warehouseId) {
                    $stockEl.html('Cargando stock...').show();
                    SmVariationsHelper.fetchVariationStock(variation.variation_id, warehouseId, vid);
                }
                // Si no hay warehouse seleccionado, mostrar stock global
                else if (variation.is_in_stock) {
                    $stockEl.html('<strong>Stock</strong> Disponible').show();
                } else {
                    $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicacion</span>').show();
                }
            });

            $(document).on('reset_data.smstock', function () {
                $stockEl.hide();
                $('input.qty').removeAttr('max');
            });
        },

        updateQtyMax: function (qty) {
            setTimeout(function () {
                var $qty = $('input.qty');
                $qty.attr('max', qty);
                var current = parseInt($qty.val(), 10) || 1;
                if (current > qty) {
                    $qty.val(qty > 0 ? qty : 0).trigger('change');
                }
            }, 0);
        },

        fetchVariationStock: function (variationId, warehouseId, vid) {
            var $stockEl = $('.sm-meta-item.sm-stock');
            $.ajax({
                url: (typeof sm_location_popup !== 'undefined' ? sm_location_popup.ajax_url : ajaxurl),
                type: 'POST',
                data: {
                    action: 'sm_get_variation_stock',
                    variation_id: variationId,
                    warehouse_id: warehouseId
                },
                success: function(response) {
                    console.log('[SM-STOCK-DISPLAY] Server response:', response);
                    if (response.success && response.data) {
                        var qty = response.data.stock;
                        if (qty > 0) {
                            $stockEl.html('<strong>Stock</strong> ' + qty).show();
                        } else {
                            $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicacion</span>').show();
                        }
                        SmVariationsHelper.updateQtyMax(qty);
                    } else {
                        $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicacion</span>').show();
                    }
                },
                error: function() {
                    console.error('[SM-STOCK-DISPLAY] Error fetching stock');
                    $stockEl.html('<span style="color: #d32f2f; font-weight: 600;">Sin stock en esta ubicacion</span>').show();
                }
            });
        },
    };

    $(document).ready(function () {
        SmVariationsHelper.init();
    });

})(jQuery);
