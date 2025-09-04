<div class="wrap">
    <h1>Configuración Socomarca</h1>
    <p>Panel de administración para la sincronización con Random ERP.</p>
    
    <h2 class="nav-tab-wrapper">
        <a href="#tab-sync" class="nav-tab nav-tab-active" id="tab-sync-link">Sincronización</a>
        <a href="#tab-config" class="nav-tab" id="tab-config-link">Configuración</a>
        <a href="#tab-admin" class="nav-tab" id="tab-admin-link">Administración</a>
    </h2>
    
    <div id="tab-sync" class="tab-content active">
        <h2>Sincronizar usuarios con Random ERP</h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>
                        Validar conexión RandomERP
                    </th>
                    <td>
                        <a class="button" href="#" id="sm_validate_connection">Validar conexión</a>
                        <span id="sm_validate_connection_result"></span>
                    </td>
                </tr>
                <tr>
                    <th>
                        Categorías
                    </th>
                    <td class="sm_sync" data-action="sm_get_categories">
                        <a class="button" href="#">Sincronizar categorías</a>
                        <span class="sm_sync_result"></span>
                    </td>
                </tr>
                <tr>
                    <th>
                        Productos
                    </th>
                    <td class="sm_sync_products">
                        <a class="button" href="#">Sincronizar productos</a>
                        <span class="sm_sync_result"></span>
                        <span class="sm_sync_progress">
                            <div class="sm_sync_progress_bar">
                                <span class="sm_sync_progress_bar_text">0/0</span>
                                <div class="sm_sync_progress_bar_fill"></div>
                            </div>
                            <span class="sm_sync_status_report" style="margin-left: 10px; font-weight: bold; color: #0073aa;">
                                [0 creados / 0 actualizados]
                            </span>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>
                        Lista de Precios
                    </th>
                    <td class="sm_sync" data-action="sm_get_price_lists">
                        <a class="button" href="#">Sincronizar lista de precios</a>
                        <span class="sm_sync_result"></span>
                    </td>
                </tr>
                <tr>
                    <th>
                        Marcas
                    </th>
                    <td class="sm_sync" data-action="sm_get_brands">
                        <a class="button" href="#">Sincronizar marcas</a>
                        <span class="sm_sync_result"></span>
                    </td>
                </tr>
                <tr>
                    <th>
                        Entidades
                    </th>
                    <td class="sm_sync" data-action="sm_get_entities">
                        <a class="button" href="#">Obtener entidades</a>
                        <span class="sm_sync_result"></span>
                        <span class="sm_sync_progress">
                            <div class="sm_sync_progress_bar">
                                <span class="sm_sync_progress_bar_text">100/700</span>
                                <div class="sm_sync_progress_bar_fill"></div>
                            </div>
                            <span class="sm_sync_status_report" style="margin-left: 10px; font-weight: bold; color: #0073aa;">
                                [0 creados / 0 actualizados]
                            </span>
                        </span>
                    </td>
                </tr>
                </tbody>
            </table>
    </div>
    
    <div id="tab-config" class="tab-content" style="display: none;">
        <h3>Configuración Random ERP</h3>
        <form method="post" action="#">
            <?php wp_nonce_field('socomarca_config'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>
                            Modo de Operación
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="sm_operation_mode" value="development" <?php checked($operation_mode, 'development'); ?> />
                                    Desarrollo - Generar token automáticamente con credenciales
                                </label><br>
                                <label>
                                    <input type="radio" name="sm_operation_mode" value="production" <?php checked($operation_mode, 'production'); ?> />
                                    Producción - Usar token manual
                                </label>
                            </fieldset>
                            <p class="description">En modo desarrollo se generará el token automáticamente. En modo producción debes proporcionar un token válido.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div id="development_fields" style="<?php echo ($operation_mode === 'production') ? 'display: none;' : ''; ?>">
                <h4>Configuración para Desarrollo</h4>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th>
                                URL API Desarrollo
                            </th>
                            <td>
                                <input name="sm_dev_api_url" type="text" id="sm_dev_api_url" value="<?php echo esc_attr($dev_api_url); ?>" class="regular-text code" placeholder="http://dev-hostname:port">
                                <p class="description">URL del API de Random ERP para desarrollo</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Usuario API
                            </th>
                            <td>
                                <input name="sm_api_user" type="text" id="sm_api_user" value="<?php echo esc_attr($api_user); ?>" class="regular-text">
                                <p class="description">Usuario para autenticación con Random ERP</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Contraseña API
                            </th>
                            <td>
                                <input name="sm_api_password" type="password" id="sm_api_password" value="<?php echo esc_attr($api_password); ?>" class="regular-text">
                                <p class="description">Contraseña para autenticación con Random ERP</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div id="production_fields" style="<?php echo ($operation_mode === 'development') ? 'display: none;' : ''; ?>">
                <h4>Configuración para Producción</h4>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th>
                                URL API Producción
                            </th>
                            <td>
                                <input name="sm_prod_api_url" type="text" id="sm_prod_api_url" value="<?php echo esc_attr($prod_api_url); ?>" class="regular-text code" placeholder="http://prod-hostname:port">
                                <p class="description">URL del API de Random ERP para producción</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                Token de Acceso
                            </th>
                            <td>
                                <?php if (!empty($production_token)): ?>
                                    <div id="token_display_section">
                                        <div style="background-color: #f0f0f1; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                            <code style="font-family: monospace; color: #666;">
                                                <?php echo esc_html(substr($production_token, 0, 20) . '...' . substr($production_token, -10)); ?>
                                            </code>
                                            <span style="color: #46b450; margin-left: 10px;">✓ Token configurado</span>
                                        </div>
                                        <button type="button" id="clear_production_token" class="button button-secondary" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                                            Limpiar Token
                                        </button>
                                    </div>
                                    <div id="token_input_section" style="display: none;">
                                        <textarea name="sm_production_token" id="sm_production_token" rows="4" cols="50" class="large-text code" placeholder="Pegar aquí el token de producción..."></textarea>
                                    </div>
                                <?php else: ?>
                                    <div id="token_input_section">
                                        <textarea name="sm_production_token" id="sm_production_token" rows="4" cols="50" class="large-text code" placeholder="Pegar aquí el token de producción..."></textarea>
                                    </div>
                                <?php endif; ?>
                                <p class="description">Token de acceso proporcionado para el entorno de producción. Este token debe ser válido y tener los permisos necesarios.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('input[name="sm_operation_mode"]').change(function() {
                        if ($(this).val() === 'development') {
                            $('#development_fields').show();
                            $('#production_fields').hide();
                        } else {
                            $('#development_fields').hide();
                            $('#production_fields').show();
                        }
                    });

                    $('#clear_production_token').click(function(e) {
                        e.preventDefault();
                        if (confirm('¿Estás seguro de que deseas limpiar el token de producción? Esta acción no se puede deshacer.')) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'sm_clear_production_token',
                                    nonce: '<?php echo wp_create_nonce('sm_clear_token'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $('#token_display_section').hide();
                                        $('#token_input_section').show().find('textarea').val('').prop('name', 'sm_production_token');
                                        alert('Token limpiado correctamente. Guarda los cambios para confirmar.');
                                    } else {
                                        alert('Error al limpiar el token: ' + (response.data || 'Error desconocido'));
                                    }
                                },
                                error: function() {
                                    alert('Error de comunicación con el servidor.');
                                }
                            });
                        }
                    });
                });
            </script>
            
            <h2>Configuración empresa</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>
                            Código empresa
                        </th>
                        <td>
                            <input name="sm_company_code" type="text" id="sm_company_code" value="<?php echo esc_attr($company_code); ?>" class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Modalidad
                        </th>
                        <td>
                            <input name="sm_modalidad" type="text" id="sm_modalidad" value="<?php echo esc_attr($modalidad); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>
                            RUT empresa
                        </th>
                        <td>
                            <input name="sm_company_rut" type="text" id="sm_company_rut" value="<?php echo esc_attr($company_rut); ?>" class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Bodega
                        </th>
                        <td>
                            <input name="sm_company_warehouse" type="text" id="sm_company_warehouse" value="<?php echo esc_attr($company_warehouse); ?>" class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Facturación automática
                        </th>
                        <td>
                            <label>
                                <input name="sm_invoice_on_completion" type="checkbox" id="sm_invoice_on_completion" value="1" <?php checked($invoice_on_completion, true); ?> />
                                Facturar al completar una orden
                            </label>
                            <p class="description">Cuando esta opción esté activada, se creará automáticamente una factura en Random ERP cuando una orden pase al estado "Completada"</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Tipo de productos
                        </th>
                        <td>
                            <fieldset disabled style="opacity: 0.5;">
                                <label>
                                    <input type="radio" name="sm_product_type" value="auto" <?php checked($product_type, 'auto'); ?> disabled />
                                    Automático - Detectar variaciones según nombre del producto
                                </label><br>
                                <label>
                                    <input type="radio" name="sm_product_type" value="variable" <?php checked($product_type, 'variable'); ?> disabled />
                                    Productos variables - Crear siempre con variación "Unidad"
                                </label><br>
                                <label>
                                    <input type="radio" name="sm_product_type" value="simple" <?php checked($product_type, 'simple'); ?> disabled />
                                    Productos simples - Crear siempre sin variaciones
                                </label>
                            </fieldset>
                            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin: 10px 0;">
                                <strong>CONFIGURACIÓN FORZADA:</strong> Todos los productos se crean como <strong>productos variables</strong> con variación "Unidad" = "UN"
                            </div>
                            <p class="description" style="opacity: 0.6;">
                                <em>Configuración anterior (deshabilitada):</em><br>
                                • <strong>Automático:</strong> Analiza el nombre del producto para detectar tallas (S, M, L, XL) y crear variaciones automáticamente<br>
                                • <strong>Variables:</strong> Crea todos los productos con una variación por defecto llamada "Unidad" con valor "UN"<br>
                                • <strong>Simples:</strong> Crea todos los productos como productos simples sin variaciones
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2>Sincronización Automática</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>
                            Sincronización automática
                        </th>
                        <td>
                            <label>
                                <input name="sm_cron_enabled" type="checkbox" id="sm_cron_enabled" value="1" <?php checked($cron_enabled, true); ?> />
                                Habilitar sincronización automática diaria
                            </label>
                            <p class="description">Ejecuta automáticamente la sincronización completa (categorías → productos → precios → entidades) una vez al día</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Hora de sincronización
                        </th>
                        <td>
                            <input name="sm_cron_time" type="time" id="sm_cron_time" value="<?php echo esc_attr($cron_time); ?>" class="regular-text">
                            <p class="description">Hora a la que se ejecutará la sincronización automática (formato 24 horas)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Estado del cron
                        </th>
                        <td>
                            <?php 
                            $next_scheduled = wp_next_scheduled('sm_erp_auto_sync');
                            if ($next_scheduled): 
                            ?>
                                <span style="color: #46b450;">Activo</span>
                                <p class="description">Próxima ejecución: <?php echo date('Y-m-d H:i:s', $next_scheduled); ?></p>
                            <?php else: ?>
                                <span style="color: #dc3232;">Inactivo</span>
                                <p class="description">La sincronización automática no está programada</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($last_sync): ?>
                    <tr>
                        <th>
                            Última sincronización
                        </th>
                        <td>
                            <strong>Fecha:</strong> <?php echo date('Y-m-d H:i:s', $last_sync['timestamp']); ?><br>
                            <strong>Estado:</strong> 
                            <?php if ($last_sync['status'] === 'success'): ?>
                                <span style="color: #46b450;">Exitosa</span>
                            <?php else: ?>
                                <span style="color: #dc3232;">Error</span>
                            <?php endif; ?>
                            <br>
                            <strong>Tiempo de ejecución:</strong> <?php echo $last_sync['execution_time']; ?> segundos<br>
                            <?php if (isset($last_sync['error'])): ?>
                                <strong>Error:</strong> <span style="color: #dc3232;"><?php echo esc_html($last_sync['error']); ?></span><br>
                            <?php endif; ?>
                            <?php if (isset($last_sync['results'])): ?>
                                <details style="margin-top: 10px;">
                                    <summary style="cursor: pointer; color: #0073aa;">Ver detalles de resultados</summary>
                                    <pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto;"><?php echo esc_html(json_encode($last_sync['results'], JSON_PRETTY_PRINT)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>
                            Ejecutar ahora
                        </th>
                        <td>
                            <a class="button" href="#" id="sm_manual_sync">Ejecutar sincronización completa</a>
                            <span id="sm_manual_sync_result"></span>
                            <p class="description">Ejecuta manualmente la sincronización completa en el orden correcto</p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            Modo debug
                        </th>
                        <td>
                            <label>
                                <input name="sm_debug_enabled" type="checkbox" id="sm_debug_enabled" value="1" <?php checked($debug_enabled, true); ?> />
                                Activar modo debug
                            </label>
                            <p class="description">
                                Activa el logging detallado para debugging (error_reporting = E_ALL). 
                                <strong>Advertencia:</strong> Solo usar en desarrollo, puede impactar el rendimiento.
                            </p>
                            <?php if ($debug_enabled): ?>
                                <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin: 10px 0;">
                                    <strong>Modo debug activo</strong><br>
                                    Los logs detallados están habilitados. Desactiva esta opción en producción.
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar cambios"></p>
        </form>
    </div>
    
    <div id="tab-admin" class="tab-content" style="display: none;">
        <h3>Herramientas de Administración</h3>
        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404;"><strong>ZONA DE PELIGRO:</strong> Las siguientes operaciones eliminarán datos de forma permanente. Úsalas con extrema precaución.</p>
        </div>
        
        <h4>Eliminación Masiva Total</h4>
        <table class="form-table">
            <tbody>
                <tr>
                    <th>
                        Eliminar Todo
                    </th>
                    <td>
                        <a class="button button-secondary" href="#" id="sm_delete_all_data" style="background-color: #dc3545; border-color: #dc3545; color: white; font-weight: bold;">
                            ELIMINAR TODO (Productos + Categorías + Usuarios)
                        </a>
                        <span id="sm_delete_all_data_result"></span>
                        <span class="sm_delete_all_data_progress" style="display: none;">
                            <div class="sm_sync_progress_bar">
                                <span class="sm_sync_progress_bar_text">0/0</span>
                                <div class="sm_sync_progress_bar_fill"></div>
                            </div>
                            <span class="sm_delete_status_report" style="margin-left: 10px; font-weight: bold; color: #0073aa;">
                                [Productos: 0 | Categorías: 0 | Usuarios: 0]
                            </span>
                        </span>
                        <p class="description" style="color: #d63384;">
                            <strong>MÁXIMO PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todos los productos, categorías y usuarios (excepto administradores) de una sola vez. No se puede deshacer.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    
        <table class="form-table">
            <tbody>
                <tr>
                    <th>
                        Gestión de Usuarios
                    </th>
                    <td>
                        <a class="button button-secondary" href="#" id="sm_delete_all_users" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                            Eliminar todos los usuarios (excepto admin)
                        </a>
                        <span id="sm_delete_users_result"></span>
                        <p class="description" style="color: #d63384;">
                            <strong>PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todos los usuarios excepto administradores. No se puede deshacer.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        Gestión de Categorías
                    </th>
                    <td>
                        <a class="button button-secondary" href="#" id="sm_delete_all_categories" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                            Eliminar todas las categorías de WooCommerce
                        </a>
                        <span id="sm_delete_categories_result"></span>
                        <p class="description" style="color: #d63384;">
                            <strong>PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todas las categorías de productos. No se puede deshacer.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        Gestión de Productos
                    </th>
                    <td>
                        <a class="button button-secondary" href="#" id="sm_delete_all_products" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                            Eliminar todos los productos de WooCommerce
                        </a>
                        <span id="sm_delete_products_result"></span>
                        <p class="description" style="color: #d63384;">
                            <strong>PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todos los productos. No se puede deshacer.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        Gestión de Marcas
                    </th>
                    <td>
                        <a class="button button-secondary" href="#" id="sm_delete_all_brands" style="background-color: #dc3545; border-color: #dc3545; color: white;">
                            Eliminar todas las marcas
                        </a>
                        <span id="sm_delete_brands_result"></span>
                        <p class="description" style="color: #d63384;">
                            <strong>PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todas las marcas. No se puede deshacer.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    
    </div>
</div>