# Tests del Plugin Socomarca Random ERP Sync

Este directorio contiene las pruebas automatizadas para el plugin de sincronización con Random ERP.

## Estructura de Tests

```
tests/
├── Unit/                       # Tests unitarios (sin llamadas reales al API)
│   ├── DocumentServiceTest.php # Tests del servicio de documentos
│   └── BaseApiServiceTest.php  # Tests del servicio API base
├── Integration/                # Tests de integración (con llamadas reales al API)
│   ├── DocumentServiceTest.php # Tests de integración de documentos
│   ├── EntityServiceTest.php   # Tests de sincronización de entidades
│   ├── CategoryServiceTest.php # Tests de sincronización de categorías
│   └── PriceListServiceTest.php # Tests de listas de precios
├── Stubs/                      # Mocks y funciones auxiliares
│   ├── DocumentServiceMockFunctions.php # Funciones mock compartidas
│   ├── MockDocumentServiceFunctions.php # Mocks específicos HTTP
│   ├── MockWordPressFunctions.php       # Mocks de WordPress
│   └── MockWordPressServiceFunctions.php
└── Pest.php                    # Configuración de Pest
```

## Comandos de Testing

### Tests Unitarios (Recomendado para desarrollo)
```bash
# Ejecutar solo tests unitarios (rápido, sin llamadas API reales)
composer test:unit

# O directamente con Pest
./vendor/bin/pest tests/Unit/
```

### Tests de Integración (Para validación completa)
```bash
# Ejecutar tests de integración (lento, requiere conexión API)
composer test:integration

# O directamente con Pest
./vendor/bin/pest tests/Integration/
```

### Todos los Tests
```bash
# Ejecutar todos los tests
composer test

# O directamente con Pest
./vendor/bin/pest
```

### Tests en Paralelo (Más rápido)
```bash
# Ejecutar tests unitarios en paralelo
composer test:fast
```

## DocumentService Tests

### Tests Unitarios (`tests/Unit/DocumentServiceTest.php`)

Los tests unitarios del DocumentService incluyen:

- **Instanciación básica** del servicio
- **Creación de archivos de log** automática
- **Procesamiento de órdenes** con datos válidos
- **Manejo de órdenes faltantes** de forma elegante
- **Códigos de entidad por defecto** cuando el usuario no tiene uno
- **Validación de items** (ignora productos sin SKU)
- **Procesamiento de variaciones** de productos (maneja `PRODUCTO|001` → `PRODUCTO`)
- **Manejo detallado de errores** de API con logging completo
- **Notas privadas de orden** que replican los logs

### Características Probadas

#### Creación de Facturas
- Datos de empresa y entidad correctos
- Líneas de productos con cantidades y SKUs
- Respuestas exitosas del API (status 201)

#### Manejo de Errores
- Respuestas de error estructuradas del API Random ERP
- Logging detallado con:
  - Mensaje de error
  - ID del error
  - URL de logs del servidor
- Notas de orden automáticas para trazabilidad

#### Logging Avanzado
- Logs con timestamp en archivo `logs/documents.log`
- Notas privadas de orden en WordPress que replican los logs
- Contexto de orden limpiado automáticamente después del procesamiento

## Configuración de API para Tests de Integración

Los tests de integración requieren credenciales del API Random ERP. Se configuran automáticamente con valores por defecto o variables de entorno:

```bash
# Variables de entorno opcionales
export RANDOM_ERP_API_URL="http://seguimiento.random.cl:3003"
export RANDOM_ERP_API_USER="demo@random.cl"  
export RANDOM_ERP_API_PASSWORD="d3m0r4nd0m3RP"
export RANDOM_ERP_COMPANY_CODE="01"
export RANDOM_ERP_COMPANY_RUT="134549696"
```

## Mocks y Stubs

### DocumentServiceMockFunctions.php
Contiene funciones mock compartidas para simular:
- WordPress orders (`wc_get_order`)
- WordPress users (`get_user_meta`)
- WordPress files (`wp_mkdir_p`)
- Clases mock para WooCommerce orders, products, etc.

### MockDocumentServiceFunctions.php
Proporciona mocks específicos para HTTP requests:
- `wp_remote_request`, `wp_remote_post`
- Control de respuestas de API mockeadas
- Simulación de errores de red
- Secuencias de respuestas para testing de reintentos

## Solución de Problemas

### Error "Cannot redeclare function"
**Solucionado**: Las funciones mock ahora están centralizadas en archivos separados para evitar redeclaraciones.

### Tests de Integración Timeout
Los tests de integración pueden tomar tiempo debido a llamadas reales al API. Use `composer test:unit` para desarrollo rápido.

### API Credentials
Los tests de integración se saltan automáticamente si no hay credenciales configuradas.

## Contribuir

Al agregar nuevos tests:

1. **Tests unitarios**: Use mocks, no haga llamadas reales al API
2. **Tests de integración**: Incluya validación de credenciales
3. **Funciones compartidas**: Agregue a los archivos de Stubs existentes
4. **Documentación**: Actualice este README según sea necesario