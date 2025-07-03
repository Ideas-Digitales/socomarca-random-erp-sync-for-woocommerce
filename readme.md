# Socomarca Random ERP Sync for WooCommerce

## Descripción
Plugin avanzado que permite sincronizar usuarios de WordPress/WooCommerce con Random ERP para Socomarca. Incluye un sistema completo de gestión de usuarios con procesamiento por lotes, interfaz de tabs moderna y herramientas de administración seguras.

## Requisitos
- WordPress 6.0 o superior
- WooCommerce activo
- PHP 8.0 o superior
- Acceso a Random ERP API
- Composer (para dependencias)

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
- **Obtener entidades**: Inicia la sincronización masiva de usuarios
- **Gestión de usuarios**: Herramientas de limpieza (uso avanzado)

## 🚀 Uso del Plugin

### Sincronización de Usuarios
1. Configura las credenciales en el tab "Configuración"
2. Haz clic en "Guardar cambios"
3. Ve al tab "Sincronización"
4. Haz clic en "Validar conexión" para verificar
5. Haz clic en "Obtener entidades" para iniciar la sincronización


### Limpieza de Usuarios (Solo para pruebas)
Para limpiar usuarios antes de sincronizar:
1. Ve al tab "Sincronización"
2. Haz clic en "Eliminar todos los usuarios (excepto admin)"
3. Confirma escribiendo `DELETE_ALL_USERS`

## 🔧 Configuración Avanzada

### Mapeo de Datos ERP → WordPress
- `KOEN` (RUT) → `user_login` + meta `rut`
- `NOKOEN` (Nombre) → `display_name` + `first_name`
- `EMAIL` → `user_email`
- `SIEN` (Razón social) → meta `business_name`
- `FOEN` (Teléfono) → meta `phone`

### Parámetros del Sistema
- **Tamaño de lote**: 10 usuarios por procesamiento
- **Pausa entre lotes**: 500ms
- **Timeout de token**: 36000000ms (configurado en ERP)
- **Role de usuarios**: `customer`

##  Solución de Problemas

### Error de Conexión
- Verifica URL, usuario y contraseña en tab "Configuración"
- Usa "Validar conexión" para diagnosticar
- Revisa logs en `wp-content/debug.log`

### Sincronización Lenta
- Normal: procesa 10 usuarios cada 500ms
- Para 758 usuarios: ~6 minutos aproximadamente

### Usuarios Duplicados
- El plugin actualiza usuarios existentes por RUT
- No crea duplicados si el RUT ya existe

## 🔄 Changelog

### v1.0.0 (Actual)
- ✅ Sincronización por lotes con Random ERP
- ✅ Interfaz de tabs moderna
- ✅ Progreso en tiempo real
- ✅ Gestión segura de usuarios
- ✅ Configuración desde panel
- ✅ Validación de conexión
- ✅ Auto-reintento de autenticación
- ✅ Reportes de estado detallados

## Soporte
Para soporte técnico, por favor contacta a:
- **Autor**: Javier Aguero
- **Sitio web**: [https://ideasdigitales.cl/](https://ideasdigitales.cl/)
- **Versión**: 1.0.0
- **Probado hasta**: WordPress 6.8

