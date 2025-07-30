# Socomarca Random ERP Sync for WooCommerce

## Descripción
Plugin avanzado que permite sincronizar datos completos de WooCommerce con Random ERP para Socomarca. Incluye sincronización de usuarios, categorías, productos y listas de precios con procesamiento por lotes, interfaz de tabs moderna, integración con B2B King y herramientas de administración avanzadas.

## Requisitos
- WordPress 6.0 o superior
- WooCommerce activo
- PHP 8.0 o superior
- B2B King Plugin (para funcionalidades de precios B2B)
- Acceso a Random ERP API
- Composer (para dependencias)
- PHPUnit (para testing)


## Dockerización

### Stack Completo con Docker
El proyecto incluye un `docker-compose.yml` con todos los servicios necesarios:
- **PHP 8.2** con extensiones de base de datos
- **Nginx** como servidor web
- **MySQL 8.0** como base de datos

### Servicios Docker

#### PHP Service (app)
- Imagen: `javieraguerocl/docker-php8.2-with-db-extensions`
- Documentación: https://hub.docker.com/r/javieraguerocl/docker-php8.2-with-db-extensions
- Puerto: Interno (comunicación con Nginx)
- Volúmenes: Código fuente y configuraciones PHP

#### Nginx Service (webserver)
- Imagen: `nginx:alpine`
- Puerto: `80:80`
- Sirve la aplicación PHP

#### MySQL Service (mysql)
- Imagen: `mysql:8.0`
- Puerto: `3306:3306`
- Base de datos: `socomarca_wp`
- Usuario: `socomarca` / Contraseña: `socomarca123`
- Root: `root123`
- Volumen persistente: `mysql_data`

### Comandos Docker

#### Iniciar servicios:
```bash
docker-compose up -d
```

#### Ver logs:
```bash
docker-compose logs -f
```

#### Acceder al contenedor PHP:
```bash
docker-compose exec app bash
```

#### Acceder a MySQL:
```bash
docker-compose exec mysql mysql -u socomarca -p socomarca_wp
```

#### Detener servicios:
```bash
docker-compose down
```

### Configuración de Base de Datos para WordPress

Una vez iniciados los servicios, configura WordPress con:
- **Host**: `mysql` (nombre del servicio)
- **Base de datos**: `socomarca_wp`
- **Usuario**: `socomarca`
- **Contraseña**: `socomarca123`


## Instalación
1. Clona el repositorio en `/wp-content/plugins/`
2. Ejecuta `composer install` para instalar dependencias
3. Activa el plugin desde el menú 'Plugins' en WordPress
4. Ve a **Socomarca → Configuración** en el panel de administración

## Configuración Rápida

### 1. Acceso al Panel
Navega a **wp-admin/admin.php?page=socomarca-configuration**

### 2. Tab "Configuración"
Configura las credenciales del Random ERP:
- **URL API**: `http://seguimiento.random.cl:3003`
- **Usuario API**: Tu usuario de Random ERP
- **Contraseña API**: Tu contraseña de Random ERP
- **Código empresa**: Código de la empresa (ej: `01`)
- **RUT empresa**: RUT de la empresa

### 3. Tab "Sincronización"
- **Validar conexión**: Verifica que las credenciales sean correctas
- **Sincronizar categorías**: Importa familias del ERP como categorías de WooCommerce
- **Sincronizar productos**: Importa productos del ERP con categorías asociadas
- **Obtener entidades**: Inicia la sincronización masiva de usuarios
- **Sincronizar listas de precios**: Importa precios B2B y actualiza stock
- **Herramientas de limpieza**: Eliminación masiva por lotes (uso avanzado)

## Uso del Plugin

### Flujo de Sincronización Recomendado
1. **Configuración inicial**: Configura credenciales del ERP
2. **Validar conexión**: Verifica conectividad con Random ERP
3. **Sincronizar categorías**: Importa familias como categorías de WooCommerce
4. **Sincronizar productos**: Importa productos con categorías asociadas
5. **Obtener entidades**: Sincroniza usuarios/clientes del ERP
6. **Sincronizar listas de precios**: Actualiza precios B2B y stock de variaciones

### Sincronización de Categorías
1. Ve al tab "Sincronización"
2. Haz clic en "Sincronizar categorías"
3. Se crearán automáticamente las categorías jerárquicas de WooCommerce

### Sincronización de Productos
1. Asegúrate de tener categorías sincronizadas
2. Haz clic en "Sincronizar productos"
3. Los productos se importarán con procesamiento por lotes y barra de progreso

### Sincronización de Listas de Precios (B2B)
1. Requiere B2B King plugin activo
2. Haz clic en "Sincronizar listas de precios"
3. Se crearán grupos B2B y precios escalonados para variaciones de productos
4. Se actualizará el stock de las variaciones automáticamente

### Herramientas de Limpieza (Solo para pruebas)
Para limpiar datos antes de re-sincronizar:
1. Ve al tab "Sincronización"
2. Usa "Eliminar todos los datos" para limpieza completa
3. Confirma escribiendo `DELETE_ALL_DATA`
4. El proceso usa eliminación por lotes con progreso visual

## Configuración Avanzada

### Mapeo de Datos ERP → WordPress

#### Usuarios/Entidades
- `KOEN` (RUT) → `user_login` + meta `rut`
- `NOKOEN` (Nombre) → `display_name` + `first_name`
- `EMAIL` → `user_email`
- `SIEN` (Razón social) → meta `business_name`
- `FOEN` (Teléfono) → meta `phone`
- Asignación automática a grupos B2B King

#### Categorías
- `familias` endpoint → taxonomía `product_cat`
- Estructura jerárquica preservada
- Slug generado automáticamente

#### Productos
- `productos` endpoint → post type `product`
- SKU mapping y asociación con categorías
- Soporte para productos variables
- Gestión de stock y precios

#### Listas de Precios B2B
- `precios/pidelistaprecio` → grupos y precios B2B King
- Precios escalonados por cantidad
- Actualización de stock por variación
- Meta fields personalizados

### Parámetros del Sistema
- **Tamaño de lote**: 10 elementos por procesamiento
- **Pausa entre lotes**: 500ms
- **Timeout de token**: 36000000ms (configurado en ERP)
- **Role de usuarios**: `customer`
- **Procesamiento**: Por lotes con progreso visual

##  Solución de Problemas

### Error de Conexión
- Verifica URL, usuario y contraseña en tab "Configuración"
- Usa "Validar conexión" para diagnosticar
- Revisa logs en `wp-content/debug.log`

### Sincronización Lenta
- Normal: procesa 10 elementos cada 500ms
- Tiempo estimado depende del volumen de datos
- El progreso se muestra en tiempo real

### Datos Duplicados
- El plugin actualiza elementos existentes (usuarios por RUT, productos por SKU)
- No crea duplicados si ya existen
- Las categorías se actualizan por nombre/slug

### Problemas con B2B King
- Verifica que B2B King esté activo para precios
- Los grupos se crean automáticamente
- Revisa meta fields de productos para precios escalonados

### Problemas de Stock
- El stock se actualiza solo en productos variables
- Revisa que las variaciones tengan atributos correctos
- El stock se mapea desde el campo `stockventa` del ERP

## Testing

El plugin incluye pruebas automatizadas usando **Pest PHP** para garantizar la calidad del código y funcionalidad.

### Configuración de Testing

#### Instalar dependencias:
```bash
composer install
```

### Ejecutar Pruebas

#### Comandos principales:
```bash
./vendor/bin/pest                              # Unitarias + Feature (por defecto)
./vendor/bin/pest tests/Integration/           # Integración (requiere API real)
./vendor/bin/pest tests/Unit/BaseApiServiceTest.php  # Test específico
```

#### Script automatizado para integración:
```bash
./run-integration-tests.sh                    # Todas las pruebas de integración
./run-integration-tests.sh Category           # Solo CategoryService
```

### Tipos de Pruebas

#### Unitarias (11 pruebas)
- **BaseApiService**: Constructor, autenticación, configuración, manejo de errores
- Ejecutan sin conexión externa, usan mocks

#### Integración (70+ pruebas) 
- **CategoryService**: Obtención, procesamiento, validación de categorías
- **EntityService**: Entidades, usuarios, validación de datos 
- **ProductService**: Productos, cache, rendimiento, análisis de datos
- **PriceListService**: Precios B2B, estructura, compatibilidad
- Ejecutan con conexiones reales al API de Random ERP

### Configuración para Pruebas de Integración

Para pruebas con API real, configura credenciales:

```bash
# 1. Copia configuración
cp .env.testing .env.testing.local

# 2. Edita credenciales
RANDOM_ERP_API_USER=tu_usuario@ejemplo.com
RANDOM_ERP_API_PASSWORD=tu_password
RANDOM_ERP_COMPANY_CODE=01
```

### Notas de Testing

- Las pruebas unitarias usan mocks para WordPress y HTTP
- Las pruebas de integración requieren credenciales válidas del ERP
- Por defecto `./vendor/bin/pest` ejecuta solo unitarias y feature
- Para integración usar `./vendor/bin/pest tests/Integration/`


## Changelog

### v2.0.0 (Actual)
- **Sincronización completa**: Usuarios, categorías, productos y precios
- **Integración B2B King**: Precios escalonados y grupos B2B automáticos
- **Gestión de stock**: Actualización automática de stock por variaciones
- **Procesamiento por lotes**: Para todos los tipos de datos con progreso visual
- **Eliminación masiva**: Sistema de limpieza por lotes con confirmación
- **Arquitectura moderna**: Servicios separados y AJAX handlers especializados
- **Suite de testing completa**: 
  - Pest PHP como framework de testing moderno
  - 11 pruebas unitarias para BaseApiService
  - 70+ pruebas de integración con API real para todos los servicios
  - Script automatizado para pruebas de integración
  - Configuración por variables de entorno
  - Análisis de rendimiento y calidad de datos
- **Docker Stack**: MySQL 8.0, Nginx, PHP 8.2 con extensiones
- **Logging avanzado**: Trazabilidad completa de operaciones

### v1.0.0 (Anterior)
- Sincronización básica de usuarios con Random ERP
- Interfaz de tabs moderna
- Validación de conexión
- Configuración desde panel

## Soporte
Para soporte técnico, por favor contacta a:
- **Autor**: Javier Aguero
- **Sitio web**: [https://ideasdigitales.cl/](https://ideasdigitales.cl/)
- **Versión**: 1.0.0
- **Probado hasta**: WordPress 6.8

