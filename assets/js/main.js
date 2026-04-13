jQuery(document).ready(function($) {
    $('#sm_validate_connection').click(function() {
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'validate_connection',
                nonce: socomarca_ajax.nonce
            },
            beforeSend: function() {
                $('#sm_validate_connection').addClass('disabled');
                $('#sm_validate_connection_result').html('<div class="loader"></div>');
            },
            success: function(response) {
                $('#sm_validate_connection').removeClass('disabled');
                if (response.success) {
                    $('#sm_validate_connection_result').html('<span style="color: green;">' + response.data.message + '</span>');
                } else {
                    $('#sm_validate_connection_result').html('<span style="color: red;">' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $('#sm_validate_connection').removeClass('disabled');
                $('#sm_validate_connection_result').html('<span style="color: red;">' + 'Error al conectar con el ERP' + '</span>');
            }
        });
    });

    $('.sm_sync a').click(function() {
        var $this = $(this).parent();
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: $this.data('action'),
                nonce: socomarca_ajax.nonce
            },
            beforeSend: function() {
                $this.find('a').addClass('disabled');
                $this.find('.sm_sync_result').html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta inicial:', response);
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('');
                
                if (response.success) {
                    var action = $this.data('action');
                    
                    if (action === 'sm_get_categories') {
                        var created = response.data.created || 0;
                        var updated = response.data.updated || 0;
                        var message = response.data.message || 'Proceso completado';
                        
                        $this.find('.sm_sync_result').html('<span style="color: green;">' + message + '</span>');
                        console.log('Categorías procesadas:', response.data);
                    } 
                    else if (action === 'sm_get_price_lists') {
                        var message = response.data.message || 'Listas de precios obtenidas';
                        var total = response.data.total || 0;

                        console.log('Listas de precios obtenidas:', response.data);

                        if (total > 0) {
                            $this.find('.sm_sync_progress').css('display', 'inline-block');
                            $this.find('.sm_sync_progress_bar_text').html('0/' + total);
                            $this.find('.sm_sync_status_report').html('[0 procesados / 0 actualizados]');
                            processBatchPriceLists($this, 0, total, 10);
                        } else {
                            $this.find('.sm_sync_result').html('<span style="color: orange;">Sin datos para procesar</span>');
                        }
                    }
                    else if (action === 'sm_get_brands') {
                        var message = response.data.message || 'Marcas sincronizadas';
                        var stats = response.data.stats || {};
                        var statsText = '';
                        
                        if (stats.created || stats.updated) {
                            statsText = ' (' + (stats.created || 0) + ' creadas, ' + (stats.updated || 0) + ' actualizadas)';
                        }
                        
                        $this.find('.sm_sync_result').html('<span style="color: green;">' + message + statsText + '</span>');
                    }
                    else if (action === 'sm_get_warehouses') {
                        var message = response.data.message || 'Bodegas sincronizadas';
                        var stats = response.data.stats || {};
                        var statsText = '';
                        
                        if (stats.created || stats.updated) {
                            statsText = ' (' + (stats.created || 0) + ' creadas, ' + (stats.updated || 0) + ' actualizadas)';
                        }
                        
                        $this.find('.sm_sync_result').html('<span style="color: green;">' + message + statsText + '</span>');
                    } 
                    else {
                        $this.find('.sm_sync_progress').css('display', 'inline-block');
                        $this.find('.sm_sync_progress_bar_text').html('0/' + (response.data.total || 0));
                        
                        if (response.data.total && response.data.total > 0) {
                            $this.find('.sm_sync_status_report').html('[0 creados / 0 actualizados]');
                            processBatchUsers($this, 0, response.data.total, 10);
                        } else {
                            $this.find('.sm_sync_result').html('<span style="color: orange;">Sin datos para procesar</span>');
                        }
                    }
                } else {
                    console.error('Error:', response);
                    $this.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Sin datos') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('<span style="color: red;">' + 'Error al obtener las entidades' + '</span>');
            }
        });
    });
    
    // Manejar sincronización de categorías
    $('.sm_sync_categories a').click(function() {
        var $this = $(this).parent();
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_process_categories'
            },
            beforeSend: function() {
                $this.find('a').addClass('disabled');
                $this.find('.sm_sync_result').html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta categorías:', response);
                $this.find('a').removeClass('disabled');
                
                if (response.success) {
                    $this.find('.sm_sync_result').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    if (response.data.errors && response.data.errors.length > 0) {
                        $this.find('.sm_sync_result').append('<br><span style="color: orange;">Errores: ' + response.data.errors.join(', ') + '</span>');
                    }
                } else {
                    $this.find('.sm_sync_result').html('<span style="color: red;">✗ Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('<span style="color: red;">✗ Error al sincronizar categorías</span>');
            }
        });
    });
    
    // Manejar sincronización de productos
    $('.sm_sync_products a').click(function() {
        var $this = $(this).parent();
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_process_products'
            },
            beforeSend: function() {
                $this.find('a').addClass('disabled');
                $this.find('.sm_sync_result').html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta inicial productos:', response);
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('');
                $this.find('.sm_sync_progress').css('display', 'inline-block');
                $this.find('.sm_sync_progress_bar_text').html('0/' + response.data.total);
                
                if (response.success && response.data.total > 0) {
                    // Resetear el reporte de estado
                    $this.find('.sm_sync_status_report').html('[0 creados / 0 actualizados]');
                    processBatchProducts($this, 0, response.data.total, 10);
                } else {
                    console.error('Error o no hay datos:', response);
                    $this.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Sin datos') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('<span style="color: red;">✗ Error al obtener los productos</span>');
            }
        });
    });
    
    function processBatchUsers($container, offset, total, batchSize) {
        console.log('Procesando lote - offset:', offset, 'total:', total, 'batchSize:', batchSize);
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_process_batch_users',
                offset: offset,
                batch_size: batchSize
            },
            success: function(response) {
                console.log('Respuesta del lote:', response);
                
                if (response.success) {
                    var processed = response.data.processed;
                    var totalCount = response.data.total;
                    var totalCreated = response.data.total_created || 0;
                    var totalUpdated = response.data.total_updated || 0;
                    
                    // Actualizar barra de progreso
                    $container.find('.sm_sync_progress_bar_text').html(processed + '/' + totalCount);
                    
                    // Actualizar reporte de estado acumulativo
                    $container.find('.sm_sync_status_report').html('[' + totalCreated + ' creados / ' + totalUpdated + ' actualizados]');
                    
                    // Actualizar barra de progreso visual
                    var percentage = (processed / totalCount) * 100;
                    $container.find('.sm_sync_progress_bar_fill').css('width', percentage + '%');
                    
                    // Continuar con el siguiente lote si no hemos terminado
                    if (!response.data.is_complete) {
                        setTimeout(function() {
                            processBatchUsers($container, processed, totalCount, batchSize);
                        }, 500); // Pausa de 500ms entre lotes
                    } else {
                        // Proceso completado
                        $container.find('.sm_sync_result').html('<span style="color: green;">Proceso completado: ' + processed + ' usuarios procesados [' + totalCreated + ' creados / ' + totalUpdated + ' actualizados]</span>');
                        $container.find('.sm_sync_progress').css('display', 'none');
                    }
                } else {
                    console.error('Error en el lote:', response);
                    $container.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Error desconocido') + '</span>');
                    $container.find('.sm_sync_progress').css('display', 'none');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', xhr, status, error);
                $container.find('.sm_sync_result').html('<span style="color: red;">Error en la petición AJAX: ' + error + '</span>');
                $container.find('.sm_sync_progress').css('display', 'none');
            }
        });
    }
    
    function processBatchProducts($container, offset, total, batchSize) {
        console.log('Procesando lote productos - offset:', offset, 'total:', total, 'batchSize:', batchSize);
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_process_batch_products',
                offset: offset,
                batch_size: batchSize
            },
            success: function(response) {
                console.log('Respuesta del lote productos:', response);
                
                if (response.success) {
                    var processed = response.data.processed;
                    var totalCount = response.data.total;
                    var totalCreated = response.data.total_created || 0;
                    var totalUpdated = response.data.total_updated || 0;
                    
                    // Actualizar barra de progreso
                    $container.find('.sm_sync_progress_bar_text').html(processed + '/' + totalCount);
                    
                    // Actualizar reporte de estado acumulativo
                    $container.find('.sm_sync_status_report').html('[' + totalCreated + ' creados / ' + totalUpdated + ' actualizados]');
                    
                    // Actualizar barra de progreso visual
                    var percentage = (processed / totalCount) * 100;
                    $container.find('.sm_sync_progress_bar_fill').css('width', percentage + '%');
                    
                    // Continuar con el siguiente lote si no hemos terminado
                    if (!response.data.is_complete) {
                        setTimeout(function() {
                            processBatchProducts($container, processed, totalCount, batchSize);
                        }, 500); // Pausa de 500ms entre lotes
                    } else {
                        // Proceso completado
                        $container.find('.sm_sync_result').html('<span style="color: green;">Proceso completado: ' + processed + ' productos procesados [' + totalCreated + ' creados / ' + totalUpdated + ' actualizados]</span>');
                        $container.find('.sm_sync_progress').css('display', 'none');
                    }
                } else {
                    console.error('Error en el lote productos:', response);
                    $container.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Error desconocido') + '</span>');
                    $container.find('.sm_sync_progress').css('display', 'none');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX productos:', xhr, status, error);
                $container.find('.sm_sync_result').html('<span style="color: red;">Error en la peticion AJAX: ' + error + '</span>');
                $container.find('.sm_sync_progress').css('display', 'none');
            }
        });
    }

    function processBatchPriceLists($container, offset, total, batchSize) {
        console.log('Procesando lote precios - offset:', offset, 'total:', total, 'batchSize:', batchSize);

        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_process_batch_price_lists',
                offset: offset,
                batch_size: batchSize
            },
            success: function(response) {
                console.log('Respuesta del lote precios:', response);

                if (response.success) {
                    var processed = response.data.processed;
                    var totalCount = response.data.total;
                    var totalProcessed = response.data.total_processed || 0;
                    var totalUpdated = response.data.total_updated || 0;

                    // Actualizar barra de progreso
                    $container.find('.sm_sync_progress_bar_text').html(processed + '/' + totalCount);

                    // Actualizar reporte de estado acumulativo
                    $container.find('.sm_sync_status_report').html('[' + totalProcessed + ' procesados / ' + totalUpdated + ' actualizados]');

                    // Actualizar barra de progreso visual
                    var percentage = (processed / totalCount) * 100;
                    $container.find('.sm_sync_progress_bar_fill').css('width', percentage + '%');

                    // Continuar con el siguiente lote si no hemos terminado
                    if (!response.data.is_complete) {
                        setTimeout(function() {
                            processBatchPriceLists($container, processed, totalCount, batchSize);
                        }, 500); // Pausa de 500ms entre lotes
                    } else {
                        // Proceso completado
                        $container.find('.sm_sync_result').html('<span style="color: green;">Proceso completado: ' + processed + ' productos procesados [' + totalProcessed + ' procesados / ' + totalUpdated + ' actualizados]</span>');
                        $container.find('.sm_sync_progress').css('display', 'none');
                    }
                } else {
                    console.error('Error en el lote precios:', response);
                    $container.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Error desconocido') + '</span>');
                    $container.find('.sm_sync_progress').css('display', 'none');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX precios:', xhr, status, error);
                $container.find('.sm_sync_result').html('<span style="color: red;">Error en la peticion AJAX: ' + error + '</span>');
                $container.find('.sm_sync_progress').css('display', 'none');
            }
        });
    }

    // Sincronizacion de stock por bodega
    $('.sm_sync_stock a').click(function() {
        var $this = $(this).parent();

        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_fetch_stock',
                nonce: socomarca_ajax.nonce
            },
            beforeSend: function() {
                $this.find('a').addClass('disabled');
                $this.find('.sm_sync_result').html('<div class="loader"></div>');
                $this.find('.sm_sync_progress').css('display', 'none');
            },
            success: function(response) {
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('');

                if (response.success && response.data.total > 0) {
                    $this.find('.sm_sync_progress').css('display', 'inline-block');
                    $this.find('.sm_sync_progress_bar_text').html('0/' + response.data.total);
                    $this.find('.sm_sync_status_report').html('[0 procesados / 0 actualizados]');
                    processBatchStock($this, 0, response.data.total, 20);
                } else if (response.success) {
                    $this.find('.sm_sync_result').html('<span style="color: orange;">Sin datos de stock para procesar</span>');
                } else {
                    $this.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Error desconocido') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('<span style="color: red;">Error al obtener stock: ' + error + '</span>');
            }
        });
    });

    function processBatchStock($container, offset, total, batchSize) {
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_process_batch_stock',
                offset: offset,
                batch_size: batchSize,
                nonce: socomarca_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var processed       = response.data.processed;
                    var totalCount      = response.data.total;
                    var totalProcessed  = response.data.total_processed || 0;
                    var totalUpdated    = response.data.total_updated   || 0;

                    $container.find('.sm_sync_progress_bar_text').html(processed + '/' + totalCount);
                    $container.find('.sm_sync_status_report').html('[' + totalProcessed + ' procesados / ' + totalUpdated + ' actualizados]');

                    var percentage = (processed / totalCount) * 100;
                    $container.find('.sm_sync_progress_bar_fill').css('width', percentage + '%');

                    if (!response.data.is_complete) {
                        setTimeout(function() {
                            processBatchStock($container, processed, totalCount, batchSize);
                        }, 300);
                    } else {
                        $container.find('.sm_sync_result').html('<span style="color: green;">Stock sincronizado: ' + totalProcessed + ' productos procesados, ' + totalUpdated + ' actualizados</span>');
                        $container.find('.sm_sync_progress').css('display', 'none');
                    }
                } else {
                    $container.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Error desconocido') + '</span>');
                    $container.find('.sm_sync_progress').css('display', 'none');
                }
            },
            error: function(xhr, status, error) {
                $container.find('.sm_sync_result').html('<span style="color: red;">Error en la peticion AJAX: ' + error + '</span>');
                $container.find('.sm_sync_progress').css('display', 'none');
            }
        });
    }

    // Manejar boton de eliminar usuarios
    $('#sm_delete_all_users').click(function(e) {
        e.preventDefault();
        
        // Confirmación doble para seguridad
        var confirmation1 = confirm('⚠️ PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODOS LOS USUARIOS excepto administradores?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        var confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_USERS');
        
        if (confirmation2 !== 'DELETE_ALL_USERS') {
            alert('Confirmación incorrecta. Operación cancelada.');
            return;
        }
        
        var $button = $(this);
        var $result = $('#sm_delete_users_result');
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_delete_all_users',
                confirm: 'DELETE_ALL_USERS'
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Eliminando usuarios...');
                $result.html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta eliminación:', response);
                $button.removeClass('disabled').text('Eliminar todos los usuarios (excepto admin)');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    if (response.data.errors && response.data.errors.length > 0) {
                        $result.append('<br><span style="color: orange;">Errores: ' + response.data.errors.join(', ') + '</span>');
                    }
                } else {
                    $result.html('<span style="color: red;">✗ Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX eliminación:', xhr, status, error);
                $button.removeClass('disabled').text('Eliminar todos los usuarios (excepto admin)');
                $result.html('<span style="color: red;">✗ Error en la petición: ' + error + '</span>');
            }
        });
    });
    
    // Manejar botón de eliminar categorías
    $('#sm_delete_all_categories').click(function(e) {
        e.preventDefault();
        
        // Confirmación doble para seguridad
        var confirmation1 = confirm('⚠️ PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODAS LAS CATEGORÍAS de WooCommerce?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        var confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_CATEGORIES');
        
        if (confirmation2 !== 'DELETE_ALL_CATEGORIES') {
            alert('Confirmación incorrecta. Operación cancelada.');
            return;
        }
        
        var $button = $(this);
        var $result = $('#sm_delete_categories_result');
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_delete_all_categories',
                confirm: 'DELETE_ALL_CATEGORIES'
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Eliminando categorías...');
                $result.html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta eliminación categorías:', response);
                $button.removeClass('disabled').text('Eliminar todas las categorías de WooCommerce');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    if (response.data.errors && response.data.errors.length > 0) {
                        $result.append('<br><span style="color: orange;">Errores: ' + response.data.errors.join(', ') + '</span>');
                    }
                } else {
                    $result.html('<span style="color: red;">✗ Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX eliminación categorías:', xhr, status, error);
                $button.removeClass('disabled').text('Eliminar todas las categorías de WooCommerce');
                $result.html('<span style="color: red;">✗ Error en la petición: ' + error + '</span>');
            }
        });
    });
    
    // Manejar botón de eliminar productos
    $('#sm_delete_all_products').click(function(e) {
        e.preventDefault();
        
        // Confirmación doble para seguridad
        var confirmation1 = confirm('⚠️ PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODOS LOS PRODUCTOS de WooCommerce?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        var confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_PRODUCTS');
        
        if (confirmation2 !== 'DELETE_ALL_PRODUCTS') {
            alert('Confirmación incorrecta. Operación cancelada.');
            return;
        }
        
        var $button = $(this);
        var $result = $('#sm_delete_products_result');
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_delete_all_products',
                confirm: 'DELETE_ALL_PRODUCTS'
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Eliminando productos...');
                $result.html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta eliminación productos:', response);
                $button.removeClass('disabled').text('Eliminar todos los productos de WooCommerce');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    if (response.data.errors && response.data.errors.length > 0) {
                        $result.append('<br><span style="color: orange;">Errores: ' + response.data.errors.join(', ') + '</span>');
                    }
                } else {
                    $result.html('<span style="color: red;">✗ Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX eliminación productos:', xhr, status, error);
                $button.removeClass('disabled').text('Eliminar todos los productos de WooCommerce');
                $result.html('<span style="color: red;">✗ Error en la petición: ' + error + '</span>');
            }
        });
    });
    
    // Manejar botón de eliminar marcas
    $('#sm_delete_all_brands').click(function(e) {
        e.preventDefault();
        
        // Confirmación doble para seguridad
        var confirmation1 = confirm('⚠️ PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODAS LAS MARCAS?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        var confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_BRANDS');
        
        if (confirmation2 !== 'DELETE_ALL_BRANDS') {
            alert('Confirmación incorrecta. Operación cancelada.');
            return;
        }
        
        var $button = $(this);
        var $result = $('#sm_delete_brands_result');
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_delete_all_brands',
                confirm: 'DELETE_ALL_BRANDS'
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Eliminando marcas...');
                $result.html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta eliminación marcas:', response);
                $button.removeClass('disabled').text('Eliminar todas las marcas');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    if (response.data.errors && response.data.errors.length > 0) {
                        $result.append('<br><span style="color: orange;">Errores: ' + response.data.errors.join(', ') + '</span>');
                    }
                } else {
                    $result.html('<span style="color: red;">✗ Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX eliminación marcas:', xhr, status, error);
                $button.removeClass('disabled').text('Eliminar todas las marcas');
                $result.html('<span style="color: red;">✗ Error en la petición: ' + error + '</span>');
            }
        });
    });

    // Manejar botón de eliminar bodegas
    $('#sm_delete_all_warehouses').click(function(e) {
        e.preventDefault();
        
        var confirmation1 = confirm('⚠️ PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODAS LAS BODEGAS de la taxonomía locations?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        var confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_WAREHOUSES');
        
        if (confirmation2 !== 'DELETE_ALL_WAREHOUSES') {
            alert('Confirmación incorrecta. Operación cancelada.');
            return;
        }
        
        var $button = $(this);
        var $result = $('#sm_delete_warehouses_result');
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_delete_all_warehouses',
                confirm: 'DELETE_ALL_WAREHOUSES'
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Eliminando bodegas...');
                $result.html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta eliminación bodegas:', response);
                $button.removeClass('disabled').text('Eliminar todas las bodegas');
                
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    if (response.data.errors && response.data.errors.length > 0) {
                        $result.append('<br><span style="color: orange;">Errores: ' + response.data.errors.join(', ') + '</span>');
                    }
                } else {
                    $result.html('<span style="color: red;">✗ Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX eliminación bodegas:', xhr, status, error);
                $button.removeClass('disabled').text('Eliminar todas las bodegas');
                $result.html('<span style="color: red;">✗ Error en la petición: ' + error + '</span>');
            }
        });
    });
    
    // Manejar boton de eliminacion masiva total
    var BTN_DELETE_ALL_LABEL = 'ELIMINAR TODO';

    $('#sm_delete_all_data').click(function(e) {
        e.preventDefault();

        var confirmation1 = confirm(
            'PELIGRO: Esta accion eliminara permanentemente:\n\n' +
            '  - Todos los productos (y sus variaciones)\n' +
            '  - Todas las categorias\n' +
            '  - Todas las marcas\n' +
            '  - Todas las bodegas (taxonomy locations)\n' +
            '  - Todos los grupos de lista de precios B2B King\n' +
            '  - Todos los usuarios (excepto administradores)\n\n' +
            'Esta accion NO SE PUEDE DESHACER.'
        );

        if (!confirmation1) return;

        var confirmation2 = confirm(
            'ULTIMA ADVERTENCIA\n\n' +
            'Se borrara TODO el contenido sincronizado desde el ERP:\n' +
            'productos, categorias, marcas, bodegas, precios B2B y usuarios.\n\n' +
            'Estas completamente seguro?'
        );

        if (!confirmation2) return;

        var confirmText = prompt('Para confirmar escribe exactamente: DELETE_ALL_DATA');
        if (confirmText !== 'DELETE_ALL_DATA') {
            alert('Texto de confirmacion incorrecto. Operacion cancelada.');
            return;
        }

        var $button = $(this);
        var $result = $('#sm_delete_all_data_result');
        var $progress = $('.sm_delete_all_data_progress');
        var $progressBar = $progress.find('.sm_sync_progress_bar_fill');
        var $progressText = $progress.find('.sm_sync_progress_bar_text');
        var $statusReport = $progress.find('.sm_delete_status_report');

        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: { action: 'sm_delete_all_data', confirm: 'DELETE_ALL_DATA' },
            beforeSend: function() {
                $button.addClass('disabled').text('Inicializando...');
                $result.html('<div class="loader"></div><span style="margin-left:10px;color:orange;">Preparando eliminacion masiva...</span>');
                $progress.hide();
            },
            success: function(response) {
                if (response.success) {
                    var d = response.data;
                    $result.html('<span style="color:blue;">' + d.message + '</span>');
                    $progress.show();
                    $progressText.text('0/' + d.total_items);
                    $progressBar.css('width', '0%');
                    $statusReport.text(buildStatusText(d.total_items, 0, 0, 0, 0, 0, 0,
                        d.products_total, d.categories_total, d.brands_total,
                        d.warehouses_total, d.b2bking_total, d.users_total));
                    deleteBatchData(d.total_items, d.products_total, d.categories_total,
                        d.brands_total, d.warehouses_total, d.b2bking_total, d.users_total,
                        0, 0, 0, 0, 0, 0);
                } else {
                    $button.removeClass('disabled').text(BTN_DELETE_ALL_LABEL);
                    $result.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $button.removeClass('disabled').text(BTN_DELETE_ALL_LABEL);
                $result.html('<span style="color:red;">Error en la peticion: ' + error + '</span>');
            }
        });
    });

    function buildStatusText(totalItems, pd, cd, bd, wd, b2bd, ud, pt, ct, bt, wt, b2bt, ut) {
        return '[Productos: ' + pd + '/' + pt +
               ' | Categorias: ' + cd + '/' + ct +
               ' | Marcas: ' + bd + '/' + bt +
               ' | Bodegas: ' + wd + '/' + wt +
               ' | B2B King: ' + b2bd + '/' + b2bt +
               ' | Usuarios: ' + ud + '/' + ut + ']';
    }

    function deleteBatchData(totalItems, pt, ct, bt, wt, b2bt, ut, pd, cd, bd, wd, b2bd, ud) {
        var $button = $('#sm_delete_all_data');
        var $result = $('#sm_delete_all_data_result');
        var $progress = $('.sm_delete_all_data_progress');
        var $progressBar = $progress.find('.sm_sync_progress_bar_fill');
        var $progressText = $progress.find('.sm_sync_progress_bar_text');
        var $statusReport = $progress.find('.sm_delete_status_report');

        var phaseLabels = {
            products:   'productos',
            categories: 'categorias',
            brands:     'marcas',
            warehouses: 'bodegas',
            b2bking:    'grupos B2B King',
            users:      'usuarios'
        };

        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: { action: 'sm_delete_batch_data' },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var newPd = pd, newCd = cd, newBd = bd, newWd = wd, newB2bd = b2bd, newUd = ud;

                    if (data.phase === 'products')    newPd   = Math.min(pt,   pd   + data.deleted_this_batch);
                    if (data.phase === 'categories')  newCd   = Math.min(ct,   cd   + data.deleted_this_batch);
                    if (data.phase === 'brands')      newBd   = Math.min(bt,   bd   + data.deleted_this_batch);
                    if (data.phase === 'warehouses')  newWd   = Math.min(wt,   wd   + data.deleted_this_batch);
                    if (data.phase === 'b2bking')     newB2bd = Math.min(b2bt, b2bd + data.deleted_this_batch);
                    if (data.phase === 'users')       newUd   = Math.min(ut,   ud   + data.deleted_this_batch);

                    var progressPercent = (data.total_deleted / totalItems) * 100;
                    $progressBar.css('width', progressPercent + '%');
                    $progressText.text(data.total_deleted + '/' + totalItems);
                    $statusReport.text(buildStatusText(totalItems, newPd, newCd, newBd, newWd, newB2bd, newUd, pt, ct, bt, wt, b2bt, ut));
                    $result.html('<span style="color:orange;">Eliminando ' + (phaseLabels[data.phase] || data.phase) + '... ' + data.message + '</span>');

                    if (data.all_complete) {
                        $button.removeClass('disabled').text(BTN_DELETE_ALL_LABEL);
                        if (data.final_summary) {
                            var s = data.final_summary;
                            $result.html('<span style="color:green;">' + data.message +
                                '<br><small style="color:#666;">Productos: ' + s.products_deleted +
                                ' | Categorias: ' + s.categories_deleted +
                                ' | Marcas: ' + s.brands_deleted +
                                ' | Bodegas: ' + s.warehouses_deleted +
                                ' | B2B King: ' + (s.b2bking_deleted || 0) +
                                ' | Usuarios: ' + s.users_deleted + '</small></span>');
                        }
                        if (data.errors && data.errors.length > 0) {
                            $result.append('<br><span style="color:orange;">Errores: ' + data.errors.join(' | ') + '</span>');
                        }
                        setTimeout(function() { $progress.fadeOut(); }, 3000);
                    } else {
                        setTimeout(function() {
                            deleteBatchData(totalItems, pt, ct, bt, wt, b2bt, ut, newPd, newCd, newBd, newWd, newB2bd, newUd);
                        }, 500);
                    }
                } else {
                    $button.removeClass('disabled').text(BTN_DELETE_ALL_LABEL);
                    $result.html('<span style="color:red;">Error en lote: ' + response.data.message + '</span>');
                    $progress.hide();
                }
            },
            error: function(xhr, status, error) {
                $button.removeClass('disabled').text(BTN_DELETE_ALL_LABEL);
                $result.html('<span style="color:red;">Error en la peticion: ' + error + '</span>');
                $progress.hide();
            }
        });
    }
    
    // Manejar tabs de WordPress
    $('.nav-tab-wrapper .nav-tab').click(function(e) {
        e.preventDefault();
        
        // Remover clases activas
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active').hide();
        
        // Agregar clase activa al tab clickeado
        $(this).addClass('nav-tab-active');
        
        // Mostrar el contenido correspondiente
        var target = $(this).attr('href');
        $(target).addClass('active').show();
    });
    
    // Sincronización manual completa
    $('#sm_manual_sync').click(function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('#sm_manual_sync_result');
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_manual_sync',
                nonce: socomarca_ajax.nonce
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Ejecutando...');
                $result.html('<div class="loader"></div> <span style="color: #0073aa;">Ejecutando sincronización completa, esto puede tomar varios minutos...</span>');
            },
            success: function(response) {
                $button.removeClass('disabled').text('Ejecutar sincronización completa');
                
                if (response.success) {
                    var message = '<span style="color: #46b450;">✅ ' + response.data.message + '</span>';
                    message += '<br><strong>Tiempo de ejecución:</strong> ' + response.data.execution_time + ' segundos';
                    
                    if (response.data.results) {
                        message += '<br><details style="margin-top: 10px;">';
                        message += '<summary style="cursor: pointer; color: #0073aa;">Ver detalles de resultados</summary>';
                        message += '<pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto;">';
                        message += JSON.stringify(response.data.results, null, 2);
                        message += '</pre></details>';
                    }
                    
                    $result.html(message);
                    
                    // Recargar la página después de 3 segundos para mostrar la información actualizada
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $result.html('<span style="color: #dc3232;">❌ ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $button.removeClass('disabled').text('Ejecutar sincronización completa');
                $result.html('<span style="color: #dc3232;">❌ Error de conexión: ' + error + '</span>');
            }
        });
    });
});
