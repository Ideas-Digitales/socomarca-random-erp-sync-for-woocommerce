jQuery(document).ready(function($) {
    $('#sm_validate_connection').click(function() {
        $.ajax({
            url: 'http://localhost:8081/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'validate_connection'
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
            url: 'http://localhost:8081/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: $this.data('action')
            },
            beforeSend: function() {
                $this.find('a').addClass('disabled');
                $this.find('.sm_sync_result').html('<div class="loader"></div>');
            },
            success: function(response) {
                console.log('Respuesta inicial:', response);
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('');
                $this.find('.sm_sync_progress').css('display', 'inline-block');
                $this.find('.sm_sync_progress_bar_text').html('0/' + response.data.total);
                
                // Iniciar procesamiento por lotes
                if (response.success && response.data.total > 0) {
                    // Resetear el reporte de estado
                    $this.find('.sm_sync_status_report').html('[0 creados / 0 actualizados]');
                    processBatchUsers($this, 0, response.data.total, 10);
                } else {
                    console.error('Error o no hay datos:', response);
                    $this.find('.sm_sync_result').html('<span style="color: red;">Error: ' + (response.data ? response.data.message : 'Sin datos') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $this.find('a').removeClass('disabled');
                $this.find('.sm_sync_result').html('<span style="color: red;">' + 'Error al obtener las entidades' + '</span>');
            }
        });
    });
    
    function processBatchUsers($container, offset, total, batchSize) {
        console.log('Procesando lote - offset:', offset, 'total:', total, 'batchSize:', batchSize);
        
        $.ajax({
            url: 'http://localhost:8081/wp-admin/admin-ajax.php',
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
    
    // Manejar botón de eliminar usuarios
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
            url: 'http://localhost:8081/wp-admin/admin-ajax.php',
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
});