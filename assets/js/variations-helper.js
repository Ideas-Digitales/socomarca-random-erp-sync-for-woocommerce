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

        autoSelectSingleVariation: function () {
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
