/**
 * Location Stock Popup - Socomarca ERP
 * Selector de ubicacion por region/comuna con asignacion de bodega via multiloca-lite
 */
(function ($) {
    'use strict';

    var SmLocationPopup = {

        selectedRegionId:    null,
        selectedRegionName:  null,
        selectedComunaId:    null,
        selectedComunaName:  null,
        selectedWarehouseId: null,
        _savedModalBody:     null,

        init: function () {
            this.bindEvents();
            this.watchForLocationRequiredNotice();

            var saved = this.parseCookie();
            if (saved && saved.comuna_name) {
                this.selectedRegionId    = saved.region_id;
                this.selectedRegionName  = saved.region_name;
                this.selectedComunaId    = saved.comuna_id;
                this.selectedComunaName  = saved.comuna_name;
                this.selectedWarehouseId = saved.warehouse_id;
                this.updateTriggerText(saved.region_name, saved.comuna_name);
            }

            this.restoreFromConfig();

            if (!saved) {
                SmLocationPopup.openModal();
            }
        },

        updateTriggerText: function (regionName, comunaName) {
            if (!comunaName) return;
            var reg = (regionName || '').trim();
            var com = (comunaName || '').trim();
            var text = reg ? (reg + ' - ' + com) : com;
            var $triggers = $('.sm-location-popup-trigger');
            if ($triggers.length) {
                $triggers.html(
                    text + ' <span class="sm-trigger-change">(cambiar)</span>'
                );
            }
        },

        bindEvents: function () {
            // Abrir modal
            $(document).on('click', '.sm-location-popup-trigger', function (e) {
                e.preventDefault();
                SmLocationPopup.openModal();
            });

            // Cerrar modal con X o backdrop
            $(document).on('click', '.sm-location-modal-close, .sm-location-modal-backdrop', function () {
                SmLocationPopup.closeModal();
            });

            // Cerrar con ESC
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    SmLocationPopup.closeModal();
                }
            });

            // Cambio de region
            $(document).on('change', '#sm-region-select', function () {
                var regionId   = $(this).val();
                var regionName = $(this).find('option:selected').text();
                SmLocationPopup.onRegionChange(regionId, regionName);
            });

            // Cambio de comuna
            $(document).on('change', '#sm-comuna-select', function () {
                var $opt         = $(this).find('option:selected');
                var comunaId     = $(this).val();
                var comunaName   = $opt.text();
                var warehouseId  = $opt.data('warehouse-id');

                SmLocationPopup.selectedComunaId    = comunaId;
                SmLocationPopup.selectedComunaName  = comunaName;
                SmLocationPopup.selectedWarehouseId = warehouseId || null;

                console.log('[SM] comuna change', { comunaId: comunaId, warehouseId: warehouseId, selectedWarehouseId: SmLocationPopup.selectedWarehouseId });

                if (comunaId) {
                    $('.sm-location-confirm').prop('disabled', false);
                } else {
                    $('.sm-location-confirm').prop('disabled', true);
                }
            });

            // Confirmar seleccion
            $(document).on('click', '.sm-location-confirm', function () {
                SmLocationPopup.confirmSelection();
            });
        },

        openModal: function () {
            var $modal = $('#sm-location-modal');
            if (!$modal.length) {
                // El modal no esta en el DOM en esta pagina: no bloquear el
                // scroll del body si no hay nada visible que mostrar.
                return;
            }
            $modal.fadeIn(200);
            $('body').addClass('sm-modal-open');
        },

        closeModal: function () {
            $('#sm-location-modal').fadeOut(200);
            $('body').removeClass('sm-modal-open');
            SmLocationPopup._restoreModalBody();
        },

        restoreFromConfig: function () {
            var saved     = SmLocationPopup.parseCookie();
            var hasSaved  = !!saved;

            // Si el producto no tiene stock en la ubicación por defecto, no pre-seleccionar
            // El usuario debe elegir una bodega manualmente que tenga stock
            if (!hasSaved && typeof window.smProductHasStock !== 'undefined' && !window.smProductHasStock) {
                console.log('[SM-LOCATION] Producto sin stock, no pre-seleccionando ubicación');
                return;
            }

            var regionId   = (saved && saved.region_id) ? saved.region_id : (sm_location_popup.selected_region || sm_location_popup.default_region);
            var comunaId   = (saved && saved.comuna_id) ? saved.comuna_id : (sm_location_popup.selected_comuna || sm_location_popup.default_comuna);
            var regionName = (saved && saved.region_name) ? saved.region_name : null;

            if (!regionId) return;

            var $regionSelect = $('#sm-region-select');
            $regionSelect.val(regionId);
            if (!regionName) {
                regionName = $regionSelect.find('option:selected').text();
            }

            SmLocationPopup.loadComunas(regionId, regionName, comunaId, function () {
                var $comunaSelect = $('#sm-comuna-select');
                var comunaName    = $comunaSelect.find('option:selected').text();
                if (comunaName && SmLocationPopup.selectedComunaId) {
                    SmLocationPopup.updateTriggerText(regionName, comunaName);
                }
            });
        },

        onRegionChange: function (regionId, regionName) {
            SmLocationPopup.selectedRegionId   = regionId;
            SmLocationPopup.selectedRegionName = regionName;
            SmLocationPopup.selectedComunaId   = null;
            SmLocationPopup.selectedComunaName = null;
            SmLocationPopup.selectedWarehouseId = null;
            $('.sm-location-confirm').prop('disabled', true);

            if (!regionId) {
                var $select = $('#sm-comuna-select');
                $select.empty().append('<option value="">-- Seleccione una region primero --</option>').prop('disabled', true);
                return;
            }

            SmLocationPopup.loadComunas(regionId, regionName, null);
        },

        loadComunas: function (regionId, regionName, preselectComunaId, onComplete) {
            var $select  = $('#sm-comuna-select');
            var $loading = $('.sm-location-loading');

            $select.prop('disabled', true).empty().append('<option value="">Cargando...</option>');
            $loading.show();

            $.ajax({
                url:  sm_location_popup.ajax_url,
                type: 'POST',
                data: {
                    action:    'sm_get_comunas',
                    nonce:     sm_location_popup.popup_nonce,
                    region_id: regionId,
                },
                success: function (response) {
                    $loading.hide();
                    $select.empty().append('<option value="">-- Seleccione una comuna --</option>');

                    if (response.success && response.data.comunas.length > 0) {
                        $.each(response.data.comunas, function (i, comuna) {
                            var $opt = $('<option>')
                                .val(comuna.id)
                                .text(comuna.name)
                                .data('warehouse-id', comuna.warehouse_id);
                            $select.append($opt);
                        });
                        $select.prop('disabled', false);

                        SmLocationPopup.selectedRegionId   = regionId;
                        SmLocationPopup.selectedRegionName = regionName;

                        if (preselectComunaId) {
                            $select.val(preselectComunaId).trigger('change');
                        }
                    } else {
                        $select.append('<option value="" disabled>No hay comunas disponibles</option>');
                    }

                    if (typeof onComplete === 'function') {
                        onComplete();
                    }
                },
                error: function () {
                    $loading.hide();
                    $select.empty().append('<option value="">Error al cargar comunas</option>');
                },
            });
        },

        confirmSelection: function () {
            var warehouseId  = SmLocationPopup.selectedWarehouseId;
            var comunaId     = SmLocationPopup.selectedComunaId;
            var comunaName   = SmLocationPopup.selectedComunaName;
            var regionId     = SmLocationPopup.selectedRegionId;
            var regionName   = SmLocationPopup.selectedRegionName;

            console.log('[SM-LOCATION] confirmSelection called with warehouseId=' + warehouseId + ', regionName=' + regionName + ', comunaName=' + comunaName);

            if (!comunaId) return;

            var prevCookie       = SmLocationPopup.parseCookie();
            var prevWarehouseId  = prevCookie ? parseInt(prevCookie.warehouse_id, 10) : null;
            var newWarehouseId   = warehouseId ? parseInt(warehouseId, 10) : null;
            var warehouseChanged = newWarehouseId && newWarehouseId !== prevWarehouseId;

            console.log('[SM] confirmSelection', {
                warehouseId:      warehouseId,
                newWarehouseId:   newWarehouseId,
                prevWarehouseId:  prevWarehouseId,
                warehouseChanged: warehouseChanged,
                prevCookie:       prevCookie,
            });

            var saveCookie = function () {
                var cookieData = JSON.stringify({
                    region_id:    regionId,
                    region_name:  regionName,
                    comuna_id:    comunaId,
                    comuna_name:  comunaName,
                    warehouse_id: newWarehouseId,
                });
                try {
                    localStorage.setItem('sm_selected_location', cookieData);
                    sessionStorage.setItem('sm_selected_location', cookieData);
                } catch (e) {}
                document.cookie = 'sm_selected_location=' + encodeURIComponent(cookieData) + '; path=/; max-age=2592000; SameSite=Lax';
                console.log('[SM-LOCATION] saveCookie (storage + cookie):', cookieData);
                SmLocationPopup.updateTriggerText(regionName, comunaName);
                $(document).trigger('sm_location_selected', { comunaName: comunaName, warehouseId: warehouseId });
            };

            var showReloadOverlay = function () {
                var $overlay = $(
                    '<div id="sm-reload-overlay" style="' +
                        'position:fixed;top:0;left:0;width:100%;height:100%;' +
                        'background:rgba(0,0,0,0.55);z-index:999999;' +
                        'display:flex;align-items:center;justify-content:center;' +
                    '">' +
                        '<div style="' +
                            'background:#fff;border-radius:8px;padding:32px 48px;' +
                            'text-align:center;box-shadow:0 4px 24px rgba(0,0,0,0.2);' +
                        '">' +
                            '<div style="' +
                                'width:36px;height:36px;border:4px solid #e0e0e0;' +
                                'border-top-color:#333;border-radius:50%;' +
                                'animation:sm-spin 0.7s linear infinite;margin:0 auto 16px;' +
                            '"></div>' +
                            '<p style="margin:0;font-size:15px;color:#333;font-weight:500;">Cargando...</p>' +
                        '</div>' +
                    '</div>'
                );
                if (!$('#sm-reload-overlay').length) {
                    $('body').append($overlay);
                }
                if (!$('#sm-spin-style').length) {
                    $('head').append(
                        '<style id="sm-spin-style">' +
                        '@keyframes sm-spin{to{transform:rotate(360deg)}}' +
                        '</style>'
                    );
                }
            };

            var doReload = function () {
                console.log('[SM-LOCATION] doReload called, will navigate to new location');
                showReloadOverlay();
                // WooCommerce agrega un beforeunload en el checkout que muestra "Leave site?".
                // Lo removemos para que la recarga sea inmediata y sin dialogo.
                $(window).off('beforeunload');

                // Limpiar params add-to-cart de la URL antes de navegar.
                // window.location.reload() re-envia los GET params actuales, lo que provoca
                // que WC procese el add-to-cart nuevamente y duplique items en el carrito.
                var url = new URL(window.location.href);
                url.searchParams.delete('add-to-cart');
                url.searchParams.delete('quantity');
                url.searchParams.delete('variation_id');
                var targetUrl = url.toString();
                console.log('[SM-LOCATION] Navigating to: ' + targetUrl);

                // Disparar evento personalizado antes de recargar
                console.log('[SM-LOCATION] Firing sm_location_changed event with warehouseId=' + warehouseId);
                $(document).trigger('sm_location_changed', {
                    warehouse_id: warehouseId,
                    location_name: comunaName
                });

                var performNavigation = function() {
                    if (warehouseId) {
                        $.ajax({
                            url:  sm_location_popup.ajax_url,
                            type: 'POST',
                            data: {
                                action:        'select_location',
                                location_id:   warehouseId,
                                location_name: comunaName,
                                nonce:         sm_location_popup.multiloca_nonce,
                            },
                            complete: function () {
                                window.location.href = targetUrl;
                            },
                        });
                    } else {
                        window.location.href = targetUrl;
                    }
                };

                // Esperar 500ms para asegurar que la cookie se haya guardado en el navegador
                // antes de recargar la página
                setTimeout(performNavigation, 500);
            };

            var executeSwitchAndReload = function () {
                SmLocationPopup.closeModal();
                // Marcar que se acaba de cambiar bodega. El servidor lee esta cookie en init
                // (prioridad 1) y elimina los params add-to-cart antes de que WC los procese,
                // evitando que la recarga re-agregue items al carrito desde la URL anterior.
                document.cookie = 'sm_cart_switched=1; path=/; max-age=60';
                $.ajax({
                    url:  sm_location_popup.ajax_url,
                    type: 'POST',
                    data: {
                        action:        'sm_switch_warehouse_cart',
                        nonce:         sm_location_popup.popup_nonce,
                        warehouse_id:  newWarehouseId,
                        location_name: comunaName,
                    },
                    success: function (response) {
                        if (response.success && response.data.cleared) {
                            SmLocationPopup.renderCartSwitchToasts(response.data.items);
                            setTimeout(doReload, 2200);
                        } else {
                            doReload();
                        }
                    },
                    error: function () {
                        doReload();
                    },
                });
            };

            var proceed = function () {
                saveCookie();
                if (warehouseChanged) {
                    executeSwitchAndReload();
                } else {
                    SmLocationPopup.closeModal();
                    doReload();
                }
            };

            if (warehouseChanged) {
                // Bloquear boton mientras se consulta el stock
                $('.sm-location-confirm').prop('disabled', true);

                $.ajax({
                    url:  sm_location_popup.ajax_url,
                    type: 'POST',
                    data: {
                        action:       'sm_cart_stock_preview',
                        nonce:        sm_location_popup.popup_nonce,
                        warehouse_id: newWarehouseId,
                    },
                    success: function (response) {
                        if (response.success && !response.data.empty && response.data.items.length > 0) {
                            SmLocationPopup.renderStockComparison(response.data.items, proceed);
                        } else {
                            proceed();
                        }
                    },
                    error: function () {
                        proceed();
                    },
                });
            } else {
                proceed();
            }
        },

        renderStockComparison: function (items, onConfirm) {
            var problemItems = $.grep(items, function (item) {
                return item.status !== 'ok';
            });

            if (problemItems.length === 0) {
                onConfirm();
                return;
            }

            var $container = $('.sm-location-modal-container');
            var $body      = $('.sm-location-modal-body');

            SmLocationPopup._savedModalBody = $body.children().detach();
            $container.addClass('sm-location-modal--comparison');

            var html = '<div class="sm-stock-comparison">';
            html += '<div class="sm-stock-notice">';
            html += '<strong>Importante:</strong> Al cambiar de localidad, algunos productos tendran otro stock disponible.';
            html += '</div>';

            html += '<table class="sm-stock-table">';
            html += '<thead><tr>';
            html += '<th>Producto</th>';
            html += '<th>Cant. en carrito</th>';
            html += '<th>Stock nueva ubicacion</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(problemItems, function (i, item) {
                var rowClass, stockLabel;

                if (item.status === 'out_of_stock') {
                    rowClass   = 'sm-stock-row-error';
                    stockLabel = '0 (Sin Stock)';
                } else {
                    rowClass   = 'sm-stock-row-warning';
                    stockLabel = item.new_stock + ' disponibles';
                }

                html += '<tr class="' + rowClass + '">';
                html += '<td>' + $('<span>').text(item.product_name).html() + '</td>';
                html += '<td class="sm-stock-center">' + item.quantity + '</td>';
                html += '<td class="sm-stock-center">' + stockLabel + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<p class="sm-stock-question">¿Desea proceder con el cambio de ubicacion?</p>';
            html += '<div class="sm-stock-actions">';
            html += '<button type="button" class="sm-stock-cancel button">No, cancelar</button>';
            html += '<button type="button" class="sm-stock-proceed button button-primary">Si, cambiar ubicación</button>';
            html += '</div>';
            html += '</div>';

            $body.html(html);

            $body.find('.sm-stock-cancel').one('click', function () {
                SmLocationPopup._restoreModalBody();
            });

            $body.find('.sm-stock-proceed').one('click', function () {
                onConfirm();
            });
        },

        _restoreModalBody: function () {
            if (!SmLocationPopup._savedModalBody) return;
            var $body = $('.sm-location-modal-body');
            $body.empty().append(SmLocationPopup._savedModalBody);
            SmLocationPopup._savedModalBody = null;
            $('.sm-location-modal-container').removeClass('sm-location-modal--comparison');
        },

        renderCartSwitchToasts: function (items) {
            if (!items || items.length === 0) return;

            var $container = $('<div class="sm-toast-container"></div>');
            $('body').append($container);

            $.each(items, function (i, item) {
                var icon, text;
                if (item.type === 'success') {
                    icon = '&#10003;';
                    text = item.product_name + ': agregado al carrito (' + item.quantity + ' unid.)';
                } else if (item.type === 'warning') {
                    icon = '&#9888;';
                    text = item.product_name + ': solo ' + item.quantity + ' unid. disponibles en esta bodega (pedias ' + item.requested + ')';
                } else {
                    icon = '&#10007;';
                    text = item.product_name + ': sin stock en esta bodega, eliminado del carrito';
                }

                var $toast = $(
                    '<div class="sm-toast sm-toast-' + item.type + '">' +
                    '<span class="sm-toast-icon">' + icon + '</span>' +
                    '<span>' + text + '</span>' +
                    '</div>'
                );
                $container.append($toast);
            });

            setTimeout(function () {
                $container.fadeOut(400, function () { $(this).remove(); });
            }, 2000);
        },

        /**
         * El plugin multiloca-lite bloquea el agregar al carrito sin ubicacion
         * seleccionada mostrando el aviso "Please select a location before
         * adding to cart.". En este sitio hay varios mecanismos de agregar al
         * carrito (ajax estandar de WooCommerce, ajax propio de
         * woocommerce-cart-all-in-one, y un widget de Elementor a medida), cada
         * uno renderiza ese aviso de forma distinta. En vez de parchear cada
         * uno, se detecta el texto del aviso donde aparezca en el DOM (al
         * cargar la pagina o inyectado por ajax) y se reemplaza por el modal
         * de seleccion de ubicacion.
         */
        watchForLocationRequiredNotice: function () {
            var TARGET_TEXT  = 'select a location before adding to cart';
            var HANDLED_FLAG = 'smLocationNoticeHandled';

            var isMatch = function ($el) {
                return $el.children().length === 0 && $el.text().toLowerCase().indexOf(TARGET_TEXT) !== -1;
            };

            var handleMatch = function ($leaf) {
                // Subir al contenedor del aviso (li, div, etc.) en vez de dejar
                // un wrapper vacio con padding/borde tras quitar solo el texto.
                var $container = $leaf.closest('.woocommerce-error, .woocommerce-notices-wrapper, .vicatna-message-wrap, li, div');
                var $target    = $container.length ? $container : $leaf;

                if ($target.data(HANDLED_FLAG)) {
                    return;
                }
                $target.data(HANDLED_FLAG, true);
                $target.remove();

                // Si el usuario ya tiene una ubicacion seleccionada no se abre
                // el modal automaticamente: el aviso suele venir de una
                // desincronizacion de sesion o de falta de stock en la bodega,
                // no de que falte elegir ubicacion.
                var saved = SmLocationPopup.parseCookie();
                if (!saved || !saved.comuna_name) {
                    SmLocationPopup.openModal();
                }
            };

            // Escaneo inicial: solo se revisan los descendientes de body, nunca
            // el body mismo (evita eliminar la pagina completa si el texto
            // aparece en cualquier parte, por ejemplo dentro de un script).
            var scanDescendants = function (root) {
                $(root).find('*').each(function () {
                    var $el = $(this);
                    if (isMatch($el)) {
                        handleMatch($el);
                    }
                });
            };

            // Chequeo de un nodo agregado por ajax: puede ser el propio aviso
            // (sin hijos) o un contenedor que lo incluya entre sus descendientes.
            var checkAddedNode = function (node) {
                var $node = $(node);
                if (isMatch($node)) {
                    handleMatch($node);
                } else {
                    scanDescendants(node);
                }
            };

            scanDescendants(document.body);

            if (!window.MutationObserver || !document.body) {
                return;
            }

            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    $(mutation.addedNodes).each(function () {
                        if (this.nodeType === 1) {
                            checkAddedNode(this);
                        }
                    });
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        },

        parseCookie: function () {
            var data = null;

            // 1. Intentar leer de localStorage primero
            try {
                var localData = localStorage.getItem('sm_selected_location');
                if (localData) {
                    data = JSON.parse(localData);
                    if (data && data.comuna_name) {
                        return data;
                    }
                }
            } catch (e) {}

            // 2. Intentar leer de sessionStorage
            try {
                var sessionData = sessionStorage.getItem('sm_selected_location');
                if (sessionData) {
                    data = JSON.parse(sessionData);
                    if (data && data.comuna_name) {
                        return data;
                    }
                }
            } catch (e) {}

            // 3. Fallback a cookies
            var raw = document.cookie.split('; ').reduce(function (acc, part) {
                var idx = part.indexOf('=');
                var key = part.substring(0, idx);
                if (key === 'sm_selected_location') {
                    acc = part.substring(idx + 1);
                }
                return acc;
            }, null);

            if (!raw) {
                return null;
            }

            try {
                data = JSON.parse(decodeURIComponent(raw));
                return data;
            } catch (e) {
                try {
                    data = JSON.parse(raw);
                    return data;
                } catch (e2) {
                    return null;
                }
            }
        },
    };

    $(document).ready(function () {
        SmLocationPopup.init();

        // Actualizar el texto del shortcode cuando cambia la ubicación
        $(document).on('sm_location_changed sm_location_selected', function (e, data) {
            var saved = SmLocationPopup.parseCookie();
            if (saved && saved.comuna_name) {
                SmLocationPopup.updateTriggerText(saved.region_name, saved.comuna_name);
            }
        });

        // Otros widgets de "agregar al carrito" (por ejemplo id-add-to-cart.js)
        // avisan aqui cuando WooCommerce responde con error sin agregar nada.
        // Si no hay ubicacion seleccionada, es casi seguro que esa es la causa
        // (es la primera validacion que corre multiloca-lite), asi que se abre
        // el modal de seleccion en vez de dejar el error sin explicacion.
        $(document).on('sm_add_to_cart_error', function () {
            var saved = SmLocationPopup.parseCookie();
            if (!saved || !saved.comuna_name) {
                SmLocationPopup.openModal();
            }
        });
    });

})(jQuery);
