# Guía de Configuración para Hostinger

Esta guía te ayudará a configurar tu aplicación Rc El Bosque en Hostinger.

> **📌 Para una guía completa de cómo SUBIR el proyecto a Hostinger, consulta: [GUIA_SUBIDA_HOSTINGER.md](GUIA_SUBIDA_HOSTINGER.md)**

## 📋 Información de la Base de Datos

Según la configuración que estás creando en Hostinger:

- **Nombre de Base de Datos:** `u919054360_rcelbosque`
- **Usuario MySQL:** `u919054360_admin`
- **Contraseña:** `rcelbosque@Admin1`
- **Host:** `localhost` (típicamente en Hostinger)

## 🔧 Pasos de Configuración

### 1. Crear Base de Datos y Usuario en Hostinger

1. Accede al panel de control de Hostinger (hPanel)
2. Ve a **Bases de datos MySQL**
3. Crea una nueva base de datos:
   - Nombre: `rcelbosque` (se convertirá en `u919054360_rcelbosque`)
   - Usuario: `admin` (se convertirá en `u919054360_admin`)
   - Contraseña: `rcelbosque@Admin1`
4. Anota las credenciales exactas que Hostinger te proporcione

### 2. Importar el Esquema de Base de Datos

1. Accede a **phpMyAdmin** desde el panel de Hostinger
2. Selecciona la base de datos `u919054360_rcelbosque`
3. Ve a la pestaña **Importar**
4. Selecciona el archivo `rcelbosque.sql` de tu proyecto
5. Haz clic en **Ejecutar**

**Nota:** Si el archivo es muy grande, puedes usar la línea de comandos o dividirlo en partes.

### 3. Configurar la Aplicación

#### Opción A: Usar script automático (Recomendado) ⭐

1. Ejecuta el script desde la línea de comandos:
   ```bash
   php switch_to_hostinger.php
   ```
   
   Este script:
   - ✅ Hace backup automático de tu configuración actual
   - ✅ Cambia a la configuración de Hostinger
   - ✅ Te muestra las credenciales configuradas

2. Si necesitas volver a desarrollo local:
   ```bash
   php switch_to_local.php
   ```

#### Opción B: Usar archivo de configuración de producción

1. Copia el contenido de `app/config.production.php`
2. Reemplaza el contenido de `app/config.php` con el de producción
3. Ajusta las credenciales si Hostinger te proporcionó valores diferentes

#### Opción C: Modificar config.php directamente

Edita `app/config.php` y descomenta/ajusta estas líneas:

```php
// Para producción en Hostinger, descomenta y ajusta estas líneas:
$DB_HOST = 'localhost'; // O el host que Hostinger te proporcione
$DB_NAME = 'u919054360_rcelbosque';
$DB_USER = 'u919054360_admin';
$DB_PASS = 'rcelbosque@Admin1';
```

Y comenta las líneas de desarrollo local.

### 4. Verificar el Host de MySQL

En Hostinger, el host puede ser:
- `localhost` (más común)
- `mysql.hostinger.com`
- Un host específico que te proporcione Hostinger

**Para verificar el host correcto:**
1. Ve a **Bases de datos MySQL** en hPanel
2. Busca la sección "Información de conexión"
3. Anota el host que aparece allí

### 5. Subir Archivos al Servidor

1. Sube todos los archivos del proyecto a tu hosting
2. Asegúrate de que la estructura de carpetas se mantenga:
   ```
   public_html/
   ├── app/
   ├── public/
   ├── backend/
   └── ...
   ```

3. **Importante:** Ajusta las rutas si es necesario según la estructura de tu hosting

### 6. Configurar Permisos de Carpetas

Asegúrate de que la carpeta de uploads tenga permisos de escritura:

```bash
chmod 755 public/uploads/animals/
```

O desde el administrador de archivos de Hostinger, establece permisos 755 para:
- `public/uploads/animals/`

### 7. Crear Usuario Administrador Inicial

Una vez que la base de datos esté importada, crea el usuario administrador:

**Opción A: Desde phpMyAdmin**
```sql
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Administrador', 'admin@rcelbosque.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

**Opción B: Usar el script de registro**
1. Accede a `tu-dominio.com/public/register.php`
2. Regístrate normalmente
3. Luego actualiza el rol en la base de datos a `admin_general`

### 8. Verificar la Conexión

Usa el script de prueba incluido:

1. Accede a: `tu-dominio.com/public/test_hostinger_connection.php`
2. El script mostrará:
   - ✅ Estado de la conexión
   - 📊 Tablas en la base de datos
   - 👥 Número de usuarios
   - 🔧 Versión de MySQL
   - 💡 Soluciones si hay errores

**⚠️ IMPORTANTE:** Elimina este archivo (`public/test_hostinger_connection.php`) después de verificar por seguridad.

## 🔒 Seguridad en Producción

### 1. Ocultar Información de Errores

En `app/config.php`, asegúrate de que los errores no muestren información sensible:

```php
// En producción, desactiva la visualización de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
```

### 2. Configurar reCAPTCHA v3

1. Registra tu dominio en [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
2. Obtén las claves de sitio y secreto
3. Actualiza en:
   - `public/login.php`
   - `public/register.php`
   - `app/recaptcha.php`

### 3. Proteger Archivos Sensibles

Asegúrate de que estos archivos no sean accesibles públicamente:
- `app/config.php`
- `app/config.production.php`
- `.htaccess` (si existe)

## 📝 Checklist de Despliegue

- [ ] Base de datos creada en Hostinger
- [ ] Usuario de base de datos creado
- [ ] Esquema `rcelbosque.sql` importado
- [ ] `app/config.php` configurado con credenciales de Hostinger
- [ ] Archivos subidos al servidor
- [ ] Permisos de carpeta `uploads/animals/` configurados (755)
- [ ] Usuario administrador creado
- [ ] Conexión verificada
- [ ] reCAPTCHA v3 configurado (opcional pero recomendado)
- [ ] Errores ocultos en producción
- [ ] Archivos de prueba eliminados

## 🐛 Solución de Problemas

### Error: "Access denied for user"

**Causa:** Credenciales incorrectas o usuario sin permisos

**Solución:**
1. Verifica las credenciales en `app/config.php`
2. Asegúrate de que el usuario tenga todos los privilegios en la base de datos
3. En Hostinger, verifica que el usuario esté asociado a la base de datos

### Error: "Unknown database"

**Causa:** El nombre de la base de datos es incorrecto

**Solución:**
1. Verifica el nombre exacto en el panel de Hostinger
2. Asegúrate de incluir el prefijo `u919054360_`
3. Verifica que la base de datos exista

### Error: "Connection timeout"

**Causa:** Host incorrecto o problemas de red

**Solución:**
1. Verifica el host en el panel de Hostinger
2. Prueba con `localhost` primero
3. Si no funciona, usa el host específico que Hostinger proporcione

### Error al subir imágenes

**Causa:** Permisos de carpeta incorrectos

**Solución:**
1. Verifica que `public/uploads/animals/` exista
2. Establece permisos 755 o 777 (según lo que Hostinger permita)
3. Verifica que el servidor web tenga permisos de escritura

## 📞 Soporte

Si tienes problemas:
1. Revisa los logs de error de PHP en Hostinger
2. Verifica la configuración de la base de datos
3. Contacta al soporte de Hostinger si el problema persiste

---

**Última Actualización:** 2025-11-09

