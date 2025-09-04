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
                    let action = $this.data('action');
                    
                    if (action === 'sm_get_categories') {
                        let created = response.data.created || 0;
                        let updated = response.data.updated || 0;
                        let message = response.data.message || 'Proceso completado';
                        
                        $this.find('.sm_sync_result').html('<span style="color: green;">' + message + '</span>');
                        console.log('Categorías procesadas:', response.data);
                    } 
                    else if (action === 'sm_get_price_lists') {
                        let message = response.data.message || 'Listas de precios obtenidas';
                        let quantity = response.data.quantity || 0;
                        
                        $this.find('.sm_sync_result').html('<span style="color: green;">' + message + ' (' + quantity + ' registros)</span>');
                        console.log('Listas de precios obtenidas:', response.data);
                    }
                    else if (action === 'sm_get_brands') {
                        let message = response.data.message || 'Marcas sincronizadas';
                        let stats = response.data.stats || {};
                        let statsText = '';
                        
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
                    let processed = response.data.processed;
                    let totalCount = response.data.total;
                    let totalCreated = response.data.total_created || 0;
                    let totalUpdated = response.data.total_updated || 0;
                    
                    // Actualizar barra de progreso
                    $container.find('.sm_sync_progress_bar_text').html(processed + '/' + totalCount);
                    
                    // Actualizar reporte de estado acumulativo
                    $container.find('.sm_sync_status_report').html('[' + totalCreated + ' creados / ' + totalUpdated + ' actualizados]');
                    
                    // Actualizar barra de progreso visual
                    let percentage = (processed / totalCount) * 100;
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
                    let processed = response.data.processed;
                    let totalCount = response.data.total;
                    let totalCreated = response.data.total_created || 0;
                    let totalUpdated = response.data.total_updated || 0;
                    
                    // Actualizar barra de progreso
                    $container.find('.sm_sync_progress_bar_text').html(processed + '/' + totalCount);
                    
                    // Actualizar reporte de estado acumulativo
                    $container.find('.sm_sync_status_report').html('[' + totalCreated + ' creados / ' + totalUpdated + ' actualizados]');
                    
                    // Actualizar barra de progreso visual
                    let percentage = (processed / totalCount) * 100;
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
                $container.find('.sm_sync_result').html('<span style="color: red;">Error en la petición AJAX: ' + error + '</span>');
                $container.find('.sm_sync_progress').css('display', 'none');
            }
        });
    }
    
    // Manejar botón de eliminar usuarios
    $('#sm_delete_all_users').click(function(e) {
        e.preventDefault();
        
        // Confirmación doble para seguridad
        let confirmation1 = confirm('PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODOS LOS USUARIOS excepto administradores?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        let confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_USERS');
        
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
        let confirmation1 = confirm('PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODAS LAS CATEGORÍAS de WooCommerce?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        let confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_CATEGORIES');
        
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
        let confirmation1 = confirm('PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODOS LOS PRODUCTOS de WooCommerce?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        let confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_PRODUCTS');
        
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
        let confirmation1 = confirm('PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODAS LAS MARCAS?\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        let confirmation2 = prompt('Para confirmar, escribe exactamente: DELETE_ALL_BRANDS');
        
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
    
    // Manejar botón de eliminación masiva total
    $('#sm_delete_all_data').click(function(e) {
        e.preventDefault();
        
        // Confirmación triple para máxima seguridad
        let confirmation1 = confirm('MÁXIMO PELIGRO: ¿Estás seguro de que quieres ELIMINAR TODO?\n\n• TODOS los productos de WooCommerce\n• TODAS las categorías\n• TODOS los usuarios (excepto administradores)\n\nEsta acción NO SE PUEDE DESHACER.');
        
        if (!confirmation1) {
            return;
        }
        
        let confirmation2 = confirm('ÚLTIMA ADVERTENCIA: Esta acción eliminará permanentemente:\n\nX Productos\nX Categorías\nX Usuarios\n\n¿Estás COMPLETAMENTE SEGURO?');
        
        if (!confirmation2) {
            return;
        }
        
        let confirmText = prompt('Para confirmar, escribe exactamente: DELETE_ALL_DATA');
        if (confirmText !== 'DELETE_ALL_DATA') {
            alert('Texto de confirmación incorrecto. Operación cancelada.');
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
            data: {
                action: 'sm_delete_all_data',
                confirm: 'DELETE_ALL_DATA'
            },
            beforeSend: function() {
                $button.addClass('disabled').text('Inicializando...');
                $result.html('<div class="loader"></div><span style="margin-left: 10px; color: orange;">Preparando eliminación masiva...</span>');
                $progress.hide();
            },
            success: function(response) {
                console.log('Respuesta inicialización eliminación masiva:', response);
                
                if (response.success) {
                    // Initialize progress bar
                    let totalItems = response.data.total_items;
                    let productsTotal = response.data.products_total;
                    let categoriesTotal = response.data.categories_total;
                    let usersTotal = response.data.users_total;
                    
                    $result.html('<span style="color: blue;">' + response.data.message + '</span>');
                    $progress.show();
                    $progressText.text('0/' + totalItems);
                    $progressBar.css('width', '0%');
                    $statusReport.text(`[Productos: 0/${productsTotal} | Categorías: 0/${categoriesTotal} | Usuarios: 0/${usersTotal}]`);
                    
                    // Start batch processing
                    deleteBatchData(totalItems, productsTotal, categoriesTotal, usersTotal, 0, 0, 0);
                } else {
                    $button.removeClass('disabled').text('ELIMINAR TODO (Productos + Categorías + Usuarios)');
                    $result.html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX inicialización eliminación masiva:', xhr, status, error);
                $button.removeClass('disabled').text('ELIMINAR TODO (Productos + Categorías + Usuarios)');
                $result.html('<span style="color: red;">Error en la petición: ' + error + '</span>');
            }
        });
    });
    
    function deleteBatchData(totalItems, productsTotal, categoriesTotal, usersTotal, productsDeleted, categoriesDeleted, usersDeleted) {
        var $button = $('#sm_delete_all_data');
        var $result = $('#sm_delete_all_data_result');
        var $progress = $('.sm_delete_all_data_progress');
        var $progressBar = $progress.find('.sm_sync_progress_bar_fill');
        var $progressText = $progress.find('.sm_sync_progress_bar_text');
        var $statusReport = $progress.find('.sm_delete_status_report');
        
        let totalDeleted = productsDeleted + categoriesDeleted + usersDeleted;
        
        $.ajax({
            url: socomarca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sm_delete_batch_data'
            },
            success: function(response) {
                console.log('Respuesta lote eliminación:', response);
                
                if (response.success) {
                    let data = response.data;
                    let newTotalDeleted = data.total_deleted;
                    let phaseComplete = data.phase_complete;
                    let allComplete = data.all_complete;
                    
                    // Update progress bar
                    let progressPercent = (newTotalDeleted / totalItems) * 100;
                    $progressBar.css('width', progressPercent + '%');
                    $progressText.text(newTotalDeleted + '/' + totalItems);
                    
                    // Update status based on phase
                    let currentProductsDeleted = productsDeleted;
                    let currentCategoriesDeleted = categoriesDeleted;
                    let currentUsersDeleted = usersDeleted;
                    
                    if (data.phase === 'products') {
                        currentProductsDeleted = Math.min(productsTotal, productsDeleted + data.deleted_this_batch);
                    } else if (data.phase === 'categories') {
                        currentProductsDeleted = productsTotal; // Products phase already complete
                        currentCategoriesDeleted = Math.min(categoriesTotal, categoriesDeleted + data.deleted_this_batch);
                    } else if (data.phase === 'users') {
                        currentProductsDeleted = productsTotal; // Products phase complete
                        currentCategoriesDeleted = categoriesTotal; // Categories phase complete
                        currentUsersDeleted = Math.min(usersTotal, usersDeleted + data.deleted_this_batch);
                    }
                    
                    $statusReport.text(`[Productos: ${currentProductsDeleted}/${productsTotal} | Categorías: ${currentCategoriesDeleted}/${categoriesTotal} | Usuarios: ${currentUsersDeleted}/${usersTotal}]`);
                    
                    // Update status message
                    let phaseText = data.phase === 'products' ? 'productos' : 
                                   data.phase === 'categories' ? 'categorías' : 'usuarios';
                    $result.html('<span style="color: orange;">Eliminando ' + phaseText + '... ' + data.message + '</span>');
                    
                    if (allComplete) {
                        // Process complete
                        $button.removeClass('disabled').text('ELIMINAR TODO (Productos + Categorías + Usuarios)');
                        
                        if (data.final_summary) {
                            let summary = data.final_summary;
                            let finalMsg = '' + data.message + 
                                         '<br><small style="color: #666;">Detalles: ' + 
                                         summary.products_deleted + ' productos, ' + 
                                         summary.categories_deleted + ' categorías, ' + 
                                         summary.users_deleted + ' usuarios eliminados</small>';
                            $result.html('<span style="color: green;">' + finalMsg + '</span>');
                        }
                        
                        if (data.errors && data.errors.length > 0) {
                            $result.append('<br><span style="color: orange;">Errores: ' + data.errors.join(' | ') + '</span>');
                        }
                        
                        // Hide progress bar after completion
                        setTimeout(function() {
                            $progress.fadeOut();
                        }, 3000);
                    } else {
                        // Continue with next batch after short delay
                        setTimeout(function() {
                            deleteBatchData(totalItems, productsTotal, categoriesTotal, usersTotal, 
                                          currentProductsDeleted, currentCategoriesDeleted, currentUsersDeleted);
                        }, 500);
                    }
                } else {
                    $button.removeClass('disabled').text('ELIMINAR TODO (Productos + Categorías + Usuarios)');
                    $result.html('<span style="color: red;">❌ Error en lote: ' + response.data.message + '</span>');
                    $progress.hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX lote eliminación:', xhr, status, error);
                $button.removeClass('disabled').text('ELIMINAR TODO (Productos + Categorías + Usuarios)');
                $result.html('<span style="color: red;">Error en la petición: ' + error + '</span>');
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
        let target = $(this).attr('href');
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
                    let message = '<span style="color: #46b450;">' + response.data.message + '</span>';
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
                    $result.html('<span style="color: #dc3232;">' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $button.removeClass('disabled').text('Ejecutar sincronización completa');
                $result.html('<span style="color: #dc3232;">Error de conexión: ' + error + '</span>');
            }
        });
    });
});