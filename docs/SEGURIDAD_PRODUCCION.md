# 🔒 Guía de Seguridad para Producción

## ✅ Archivos Eliminados (Seguridad)

Se han eliminado los siguientes archivos que representaban riesgos de seguridad:

### Archivos de Prueba y Debug
- ✅ `public/test_hostinger_connection.php` - Expone credenciales
- ✅ `public/verificar_despliegue.php` - Muestra información del sistema
- ✅ `public/diag.php` - Información de diagnóstico
- ✅ `public/fix_test_credentials.php` - Modifica credenciales

### Scripts de Migración y Reset
- ✅ `public/create_database_rcelbosque.php` - Crea/modifica BD
- ✅ `public/rename_database.php` - Renombra BD
- ✅ `public/fix_foreign_keys.php` - Modifica estructura BD
- ✅ `public/reset_admin.php` - Resetea contraseñas admin

### Archivos de Configuración Duplicados
- ✅ `app/config.hostinger.php` - Configuración duplicada
- ✅ `app/config.production.php` - Configuración duplicada
- ✅ `app/config.php.backup.*` - Backups con credenciales

### Scripts de Desarrollo
- ✅ `switch_to_hostinger.php` - Script de desarrollo
- ✅ `switch_to_local.php` - Script de desarrollo

### Archivos SQL Antiguos
- ✅ `agrogan.sql` - Archivo SQL antiguo

## 📋 Archivos que NO Debes Subir a Producción

### Carpetas Completas
- ❌ `testsprite_tests/` - Carpeta completa de pruebas
- ❌ `sprints/` - Solo para documentación local
- ❌ `.git/` - Si usas control de versiones (opcional)

### Archivos de Documentación (Opcional)
- `*.md` - Archivos Markdown (puedes mantenerlos o eliminarlos)
- `README.md`, `INSTALLATION.md`, etc.

## 🔐 Protecciones Implementadas

### 1. Archivos .htaccess

**`public/.htaccess`:**
- ✅ Protege archivos de prueba y debug
- ✅ Protege archivos SQL
- ✅ Oculta errores PHP
- ✅ Previene listado de directorios
- ✅ Protege carpeta de uploads

**`app/.htaccess`:**
- ✅ Bloquea acceso directo a archivos PHP en `app/`
- ✅ Solo permite includes desde otros archivos PHP

**`.htaccess` (raíz):**
- ✅ Protege archivos sensibles
- ✅ Protege archivos SQL y backups
- ✅ Protege scripts de desarrollo

### 2. Estructura Segura

- ✅ `app/config.php` está protegido por `.htaccess`
- ✅ Archivos sensibles no son accesibles públicamente
- ✅ Carpetas de uploads tienen permisos restringidos

## ⚠️ Verificaciones Antes de Subir

### Checklist de Seguridad

- [ ] Verificar que no existan archivos de prueba en `public/`
- [ ] Verificar que no existan scripts de reset/migración
- [ ] Verificar que `app/config.php` no sea accesible directamente
- [ ] Verificar que los `.htaccess` estén en su lugar
- [ ] Verificar que no existan backups de configuración
- [ ] Verificar que no existan archivos SQL en `public/`
- [ ] Verificar permisos de carpetas (755 para uploads)

### Comandos de Verificación

```bash
# Verificar archivos peligrosos en public/
find public/ -name "*test*.php" -o -name "*debug*.php" -o -name "*reset*.php" -o -name "*fix*.php"

# Verificar archivos SQL
find . -name "*.sql" -not -path "./rcelbosque.sql"

# Verificar backups
find . -name "*.backup*"
```

## 🛡️ Recomendaciones Adicionales

### 1. Configuración PHP

En `app/config.php`, asegúrate de tener:

```php
// En producción, ocultar errores
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
```

### 2. Permisos de Archivos

- Archivos PHP: `644`
- Carpetas: `755`
- `public/uploads/animals/`: `755` o `777` (según hosting)

### 3. Base de Datos

- ✅ No exponer credenciales en código
- ✅ Usar variables de entorno si es posible
- ✅ Limitar permisos del usuario de BD

### 4. SSL/HTTPS

- ✅ Activar SSL en Hostinger
- ✅ Forzar HTTPS en `.htaccess`

### 5. reCAPTCHA

- ✅ Configurar reCAPTCHA v3 para producción
- ✅ Actualizar claves en `public/login.php` y `public/register.php`

## 📝 Archivos Seguros para Producción

Estos archivos son seguros y deben estar en producción:

### Públicos (public/)
- ✅ `index.php`
- ✅ `login.php`
- ✅ `register.php`
- ✅ `logout.php`
- ✅ `admin.php`
- ✅ `catalogo.php`
- ✅ `veterinary.php`
- ✅ `get-animal-details.php`
- ✅ `get-lot-details.php`
- ✅ `assets/` (CSS, imágenes, etc.)

### Privados (app/)
- ✅ `config.php` (protegido por .htaccess)
- ✅ `recaptcha.php`

### Base de Datos
- ✅ `rcelbosque.sql` (solo para importación inicial)

## 🚨 Si Encuentras Problemas

Si después de eliminar archivos algo no funciona:

1. **Verifica los logs de error** en Hostinger
2. **Revisa los permisos** de archivos y carpetas
3. **Verifica que `app/config.php`** tenga las credenciales correctas
4. **Asegúrate de que los `.htaccess`** estén funcionando

---

**Última Actualización:** 2025-11-09  
**Estado:** ✅ Archivos peligrosos eliminados

