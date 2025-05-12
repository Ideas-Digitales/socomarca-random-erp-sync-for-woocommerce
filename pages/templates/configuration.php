<div class="wrap">
    <h1>Configuración</h1>
    <p>Página de configuración de Socomarca.</p>
</div>

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
                </span>
            </td>
        </tr>
    </tbody>
</table>


<h2 class="title">Configuracion empresa</h2>
<form method="post" action="#">
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
        </tbody>
    </table>
    <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar cambios"></p>
</form>