# Reestructuración del Plugin - Mejores Prácticas PHP

## Estructura Nueva

```
socomarca-random-erp-sync-for-woocommerce/
├── src/
│   ├── Autoloader.php                    # Autoloader personalizado
│   ├── Plugin.php                        # Clase principal del plugin
│   ├── Services/                         # Servicios de negocio
│   │   ├── BaseApiService.php           # Clase base para API
│   │   ├── EntityService.php            # Servicio de entidades
│   │   ├── CategoryService.php          # Servicio de categorías
│   │   └── ProductService.php           # Servicio de productos
│   ├── Ajax/                            # Manejadores AJAX
│   │   ├── BaseAjaxHandler.php          # Clase base para AJAX
│   │   ├── AuthAjaxHandler.php          # Autenticación
│   │   ├── EntityAjaxHandler.php        # Entidades
│   │   ├── CategoryAjaxHandler.php      # Categorías
│   │   └── ProductAjaxHandler.php       # Productos
│   └── Admin/                           # Administración
│       └── AdminPages.php               # Páginas de admin
├── assets/                              # CSS y JS (sin cambios)
├── pages/                               # Templates (depreciado)
├── class/                               # Clases antiguas (depreciado)
├── vendor/                              # Composer dependencies
├── index.php                            # Archivo principal actualizado
└── composer.json                        # Dependencias
```

## Mejoras Implementadas

### 1. **Separación de Responsabilidades (SRP)**
- **Servicios**: Lógica de negocio separada por entidad
- **AJAX Handlers**: Manejo de peticiones AJAX específicas
- **Admin**: Gestión de páginas de administración

### 2. **Namespaces y Autoloading**
- Namespace: `Socomarca\RandomERP`
- Autoloader PSR-4 personalizado
- Carga automática de clases

### 3. **Herencia y Polimorfismo**
- `BaseApiService`: Funcionalidad común de API
- `BaseAjaxHandler`: Funcionalidad común de AJAX
- Métodos específicos en clases hijas

### 4. **Seguridad Mejorada**
- Validación de permisos centralizada
- Nonces para formularios
- Sanitización de datos
- Verificación de capacidades

### 5. **Arquitectura Limpia**
- Singleton para Plugin principal
- Inyección de dependencias
- Configuración centralizada
- Logging estructurado

## Servicios

### EntityService
- Gestiona entidades del ERP
- Creación/actualización de usuarios
- Procesamiento por lotes
- Eliminación masiva

### CategoryService  
- Gestiona categorías del ERP
- Creación jerárquica de categorías
- Procesamiento por niveles
- Asociación padre-hijo

### ProductService
- Gestiona productos del ERP
- Creación/actualización de productos WooCommerce
- Asociación con categorías
- Procesamiento por lotes

## AJAX Handlers

### Responsabilidades
- Validación de permisos
- Sanitización de entrada
- Llamadas a servicios
- Respuestas JSON estructuradas

### Seguridad
- Verificación de capacidades
- Confirmación para acciones destructivas
- Logging de acciones

## Configuración

### Constantes Definidas
```php
SOCOMARCA_ERP_PLUGIN_FILE   # Archivo principal
SOCOMARCA_ERP_PLUGIN_DIR    # Directorio del plugin
SOCOMARCA_ERP_PLUGIN_URL    # URL del plugin
SOCOMARCA_ERP_VERSION       # Versión actual
```

### Opciones WordPress
- `sm_api_url`: URL del API
- `sm_api_user`: Usuario del API
- `sm_api_password`: Contraseña del API
- `sm_company_code`: Código de empresa
- `sm_company_rut`: RUT de empresa

## Inicialización

1. Verificación de dependencias (WooCommerce, PHP 8.0+)
2. Carga de Composer autoloader
3. Registro de autoloader personalizado
4. Inicialización del plugin principal
5. Registro de componentes

## Beneficios

### Mantenibilidad
- Código organizado por responsabilidad
- Fácil localización de funcionalidad
- Estructura estándar de WordPress

### Escalabilidad
- Fácil agregar nuevos servicios
- Extensión mediante herencia
- Configuración centralizada

### Testabilidad
- Clases pequeñas y enfocadas
- Dependencias inyectables
- Métodos con responsabilidad única

### Seguridad
- Validaciones centralizadas
- Sanitización automática
- Logging de acciones críticas

## Migración

Los archivos antiguos se mantienen temporalmente:
- `class/erp-authentication.php` → Servicios en `src/Services/`
- `pages/configuration.php` → `src/Ajax/` y `src/Admin/`
- `pages/pages.php` → `src/Admin/AdminPages.php`

## Uso

El plugin funciona igual desde la perspectiva del usuario, pero internamente usa la nueva arquitectura limpia y mantenible.