<div class="wrap">
    <h1>Configuración Socomarca</h1>
    <p>Panel de administración para la sincronización con Random ERP.</p>
    
    <h2 class="nav-tab-wrapper">
        <a href="#tab-sync" class="nav-tab nav-tab-active" id="tab-sync-link">Sincronización</a>
        <a href="#tab-config" class="nav-tab" id="tab-config-link">Configuración</a>
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
            <h2>Botones para reiniciar las pruebas</h2>
            <p>Despues se van a borrar en producción.</p>
            <table class="form-table">
                <tbody>
                <tr>
                    <th>
                        Eliminación Masiva Total
                    </th>
                    <td>
                        <a class="button button-secondary" href="#" id="sm_delete_all_data" style="background-color: #dc3545; border-color: #dc3545; color: white; font-weight: bold;">
                            🗑️ ELIMINAR TODO (Productos + Categorías + Usuarios)
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
                            <strong>⚠️ MÁXIMO PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todos los productos, categorías y usuarios (excepto administradores) de una sola vez. No se puede deshacer.
                        </p>
                    </td>
                </tr>
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
                            <strong>⚠️ PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todos los usuarios excepto administradores. No se puede deshacer.
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
                            <strong>⚠️ PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todas las categorías de productos. No se puede deshacer.
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
                            <strong>⚠️ PELIGRO:</strong> Esta acción eliminará PERMANENTEMENTE todos los productos. No se puede deshacer.
                        </p>
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
                            URL API
                        </th>
                        <td>
                            <input name="sm_api_url" type="text" id="sm_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text code" placeholder="http://hostname:port">
                            <p class="description">URL base del API de Random ERP</p>
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
                                <strong>⚠️ CONFIGURACIÓN FORZADA:</strong> Todos los productos se crean como <strong>productos variables</strong> con variación "Unidad" = "UN"
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
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar cambios"></p>
        </form>
    </div>
</div>