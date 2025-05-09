# Socomarca Random ERP Sync for WooCommerce

## Descripción
Este plugin permite sincronizar los productos de WooCommerce con Random ERP para Socomarca. Facilita la gestión y actualización de productos entre ambas plataformas de manera automatizada.

## Requisitos
- WordPress 6.0 o superior
- WooCommerce activo
- PHP 8.0 o superior
- Acceso a Random ERP API

## Instalación
1. Clona el repositorio en tu servidor local al directorio `/wp-content/plugins/` de tu instalación de WordPress
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Ve a la sección 'Socomarca' en el menú de administración para configurar el plugin

## Configuración
1. Accede al menú 'Socomarca' en el panel de administración de WordPress
2. Ve a la sección 'Configuración'
3. Configura las credenciales de acceso a Random ERP en el archivo `wp-config.php`

```php
define('API_URL', 'http://hostname:port');
define('API_USER', 'username');
define('API_PASSWORD', 'password');
```

4. Utiliza el botón 'Validar conexión' en /wp-admin/admin.php?page=socomarca-configuration para verificar que la configuración es correcta

## Soporte
Para soporte técnico, por favor contacta a:
- Autor: Javier Aguero
- Sitio web: [https://ideasdigitales.cl/](https://ideasdigitales.cl/)

## Versión
- Versión actual: 1.0.0
- Probado hasta WordPress 6.8

