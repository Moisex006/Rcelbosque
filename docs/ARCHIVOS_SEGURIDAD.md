# 🔒 Análisis de Seguridad - Archivos a Eliminar

Este documento lista todos los archivos que representan riesgos de seguridad y deben ser eliminados antes de subir a producción.

## ⚠️ Archivos Críticos de Seguridad (ELIMINAR)

### En `public/` (Accesibles públicamente - ALTO RIESGO)

1. **`create_database_rcelbosque.php`**
   - **Riesgo:** Permite crear/modificar bases de datos
   - **Acción:** ELIMINAR

2. **`rename_database.php`**
   - **Riesgo:** Permite renombrar bases de datos
   - **Acción:** ELIMINAR

3. **`fix_foreign_keys.php`**
   - **Riesgo:** Modifica estructura de BD
   - **Acción:** ELIMINAR

4. **`fix_test_credentials.php`**
   - **Riesgo:** Modifica credenciales de usuarios
   - **Acción:** ELIMINAR

5. **`reset_admin.php`**
   - **Riesgo:** Permite resetear contraseñas de administrador
   - **Acción:** ELIMINAR

6. **`diag.php`**
   - **Riesgo:** Muestra información sensible del sistema
   - **Acción:** ELIMINAR

7. **`test_hostinger_connection.php`**
   - **Riesgo:** Expone credenciales y configuración de BD
   - **Acción:** ELIMINAR

8. **`verificar_despliegue.php`**
   - **Riesgo:** Muestra información del sistema y configuración
   - **Acción:** ELIMINAR (solo para desarrollo)

### En `app/` (Configuración - MEDIO RIESGO)

1. **`config.hostinger.php`**
   - **Riesgo:** Contiene credenciales de base de datos
   - **Acción:** ELIMINAR o mover fuera de `public/`

2. **`config.production.php`**
   - **Riesgo:** Contiene credenciales de producción
   - **Acción:** ELIMINAR o mover fuera de `public/`

3. **`config.php.backup.*`**
   - **Riesgo:** Backup con credenciales
   - **Acción:** ELIMINAR todos los backups

### Scripts de Desarrollo (BAJO RIESGO pero eliminar)

1. **`switch_to_hostinger.php`**
   - **Riesgo:** Script de desarrollo
   - **Acción:** ELIMINAR (no necesario en producción)

2. **`switch_to_local.php`**
   - **Riesgo:** Script de desarrollo
   - **Acción:** ELIMINAR (no necesario en producción)

### Carpetas de Pruebas

1. **`testsprite_tests/`**
   - **Riesgo:** Contiene scripts de prueba y configuración
   - **Acción:** ELIMINAR carpeta completa

### Archivos SQL (MEDIO RIESGO)

1. **`agrogan.sql`**
   - **Riesgo:** Archivo SQL antiguo con posibles datos sensibles
   - **Acción:** ELIMINAR (ya no se usa)

2. **`rcelbosque.sql`**
   - **Riesgo:** Contiene estructura y datos de BD
   - **Acción:** MANTENER pero proteger con `.htaccess`

## ✅ Archivos Seguros (MANTENER)

- `public/index.php`
- `public/login.php`
- `public/register.php`
- `public/admin.php`
- `public/catalogo.php`
- `public/veterinary.php`
- `public/logout.php`
- `public/get-animal-details.php`
- `public/get-lot-details.php`
- `app/config.php` (principal, debe estar protegido)
- `app/recaptcha.php`

## 📋 Checklist de Seguridad

- [ ] Eliminar archivos de prueba/debug
- [ ] Eliminar scripts de migración/reset
- [ ] Eliminar archivos de configuración duplicados
- [ ] Eliminar backups de configuración
- [ ] Eliminar scripts de desarrollo
- [ ] Eliminar carpeta de pruebas
- [ ] Verificar protección de `.htaccess`
- [ ] Verificar que `app/config.php` no sea accesible públicamente

