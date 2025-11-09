# 🚀 Guía Completa: Subir Proyecto a Hostinger (rcelbosque.com)

Esta guía te llevará paso a paso para subir tu proyecto Rc El Bosque a Hostinger con el dominio **rcelbosque.com**.

> **📌 Para una guía visual paso a paso específica de tu panel, consulta: [PASOS_SUBIR_HOSTINGER.md](PASOS_SUBIR_HOSTINGER.md)**

## 📋 Prerequisitos

- ✅ Cuenta de Hostinger activa
- ✅ Dominio `rcelbosque.com` configurado en Hostinger
- ✅ Base de datos MySQL creada en Hostinger (u919054360_rcelbosque)
- ✅ Usuario de base de datos creado (u919054360_admin)
- ✅ Archivo `rcelbosque.sql` listo para importar

---

## 🔧 Paso 1: Preparar el Proyecto Localmente

### 1.1 Cambiar Configuración a Hostinger

Antes de subir, cambia la configuración a Hostinger:

**Opción A: Script Automático (Recomendado)**
```bash
cd C:\xampp\htdocs\Rcelbosque
php switch_to_hostinger.php
```

**Opción B: Manual**
Edita `app/config.php` y descomenta las líneas de Hostinger:
```php
$DB_HOST = 'localhost';
$DB_NAME = 'u919054360_rcelbosque';
$DB_USER = 'u919054360_admin';
$DB_PASS = 'rcelbosque@Admin1';
```

### 1.2 Verificar Archivos a Subir

Asegúrate de tener estos archivos listos:
- ✅ Todo el proyecto (carpetas: `app/`, `public/`, `backend/`, etc.)
- ✅ Archivo `rcelbosque.sql` para importar
- ✅ Archivo `.htaccess` si existe (para reescritura de URLs)

### 1.3 Archivos que NO debes subir (opcional, pero recomendado)

Puedes excluir estos para ahorrar espacio:
- `testsprite_tests/` (solo para desarrollo)
- `node_modules/` (si existe)
- `.git/` (si usas control de versiones)
- Archivos de backup (`.backup`, `.bak`)

---

## 📤 Paso 2: Acceder a Hostinger

### 2.1 Acceder al Panel de Control

1. Ve a [hPanel de Hostinger](https://hpanel.hostinger.com/)
2. Inicia sesión con tus credenciales
3. Selecciona el dominio `rcelbosque.com`

### 2.2 Verificar Estructura de Carpetas

En Hostinger, normalmente tienes:
- `public_html/` - Carpeta raíz del dominio (aquí van los archivos públicos)

---

## 📁 Paso 3: Subir Archivos

Tienes 3 opciones para subir archivos:

### Opción A: File Manager (Más Fácil) ⭐

1. **Acceder al File Manager:**
   - En hPanel, busca "Administrador de Archivos" o "File Manager"
   - Haz clic para abrir

2. **Navegar a public_html:**
   - Ve a la carpeta `public_html`
   - Esta es la raíz de tu dominio `rcelbosque.com`

3. **Subir Archivos:**
   - Opción 1: Arrastrar y soltar
     - Abre el File Manager
     - Abre el explorador de Windows con tu proyecto
     - Arrastra la carpeta `Rcelbosque` completa a `public_html`
   
   - Opción 2: Botón Subir
     - Haz clic en "Subir" o "Upload"
     - Selecciona todos los archivos y carpetas
     - Espera a que termine la subida

4. **Estructura Final:**
   ```
   public_html/
   ├── app/
   ├── public/
   ├── backend/
   ├── sprints/
   ├── testsprite_tests/
   ├── rcelbosque.sql
   ├── README.md
   └── ...
   ```

### Opción B: FTP (Más Rápido para muchos archivos)

1. **Obtener Credenciales FTP:**
   - En hPanel, ve a "FTP Accounts" o "Cuentas FTP"
   - Anota:
     - Host: `ftp.rcelbosque.com` o la IP que te proporcione
     - Usuario: (tu usuario FTP)
     - Contraseña: (tu contraseña FTP)
     - Puerto: `21` (típicamente)

2. **Usar Cliente FTP:**
   - **FileZilla** (gratis): https://filezilla-project.org/
   - **WinSCP** (Windows): https://winscp.net/
   
3. **Conectar:**
   - Abre FileZilla
   - Ingresa:
     - Host: `ftp.rcelbosque.com`
     - Usuario: (tu usuario FTP)
     - Contraseña: (tu contraseña)
     - Puerto: `21`
   - Haz clic en "Conexión rápida"

4. **Subir Archivos:**
   - Panel izquierdo: Tu computadora (navega a `C:\xampp\htdocs\Rcelbosque`)
   - Panel derecho: Servidor (navega a `/public_html`)
   - Selecciona todos los archivos y carpetas
   - Arrastra del panel izquierdo al derecho
   - Espera a que termine la transferencia

### Opción C: Git (Si tienes repositorio)

Si tu proyecto está en GitHub/GitLab:

1. **Conectar por SSH:**
   ```bash
   ssh usuario@rcelbosque.com
   cd public_html
   git clone https://github.com/tu-usuario/Rcelbosque.git .
   ```

2. **O usar Git en Hostinger:**
   - En hPanel, busca "Git"
   - Configura el repositorio
   - Clona en `public_html`

---

## 🗄️ Paso 4: Configurar Base de Datos

### 4.1 Verificar Base de Datos Creada

1. En hPanel, ve a "Bases de datos MySQL"
2. Verifica que exista:
   - Base de datos: `u919054360_rcelbosque`
   - Usuario: `u919054360_admin`
   - Estado: Activo

### 4.2 Importar Esquema

1. **Acceder a phpMyAdmin:**
   - En hPanel, busca "phpMyAdmin"
   - Haz clic para abrir

2. **Seleccionar Base de Datos:**
   - En el panel izquierdo, selecciona `u919054360_rcelbosque`

3. **Importar:**
   - Haz clic en la pestaña "Importar"
   - Haz clic en "Elegir archivo"
   - Selecciona `rcelbosque.sql` (debe estar en `public_html/`)
   - Haz clic en "Continuar" o "Ejecutar"
   - Espera a que termine la importación

4. **Verificar:**
   - Deberías ver todas las tablas creadas
   - Verifica que existan: `users`, `animals`, `farms`, `lots`, etc.

---

## ⚙️ Paso 5: Configurar la Aplicación

### 5.1 Verificar Configuración

1. **Verificar app/config.php:**
   - Asegúrate de que tenga las credenciales de Hostinger
   - Puedes editarlo desde File Manager o FTP

2. **Verificar Host de MySQL:**
   - En hPanel, ve a "Bases de datos MySQL"
   - Busca "Información de conexión"
   - Anota el host (puede ser `localhost` o algo como `mysql.hostinger.com`)
   - Si es diferente a `localhost`, actualiza `app/config.php`:
     ```php
     $DB_HOST = 'mysql.hostinger.com'; // O el host que te proporcione
     ```

### 5.2 Configurar Permisos de Carpetas

1. **Desde File Manager:**
   - Navega a `public_html/public/uploads/animals/`
   - Haz clic derecho en la carpeta
   - Selecciona "Cambiar permisos" o "Change Permissions"
   - Establece: `755` o `777`
   - Aplica recursivamente a subcarpetas

2. **Desde FTP:**
   - Conecta con FileZilla
   - Navega a `public/uploads/animals/`
   - Haz clic derecho → "Permisos de archivo"
   - Marca: Lectura, Escritura, Ejecución (755)
   - Aplica a subdirectorios

### 5.3 Crear .htaccess (Opcional pero Recomendado)

Crea un archivo `.htaccess` en `public_html/` para mejorar la seguridad:

```apache
# Proteger archivos sensibles
<FilesMatch "^(config\.php|config\.production\.php|\.htaccess)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Redireccionar a public/ si acceden a la raíz
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ /public/$1 [L,R=301]

# Ocultar errores en producción
php_flag display_errors off
php_flag log_errors on
```

---

## 🔗 Paso 6: Configurar URLs y Rutas

### 6.1 Estructura de URLs

Con la estructura actual, las URLs serán:
- `https://rcelbosque.com/public/index.php` - Página principal
- `https://rcelbosque.com/public/login.php` - Login
- `https://rcelbosque.com/public/admin.php` - Panel admin

### 6.2 Opción: Mover public/ a la raíz (Recomendado)

Para tener URLs más limpias (`rcelbosque.com/login.php` en lugar de `rcelbosque.com/public/login.php`):

1. **Mover contenido de public/ a public_html/:**
   - Mueve todos los archivos de `public/` a `public_html/`
   - Mueve `app/` a `public_html/app/`
   - Mantén `backend/` si lo necesitas

2. **Actualizar rutas en los archivos:**
   - Cambia `require __DIR__ . '/../app/config.php';` a `require __DIR__ . '/app/config.php';`
   - O crea un script de actualización

**Estructura Alternativa:**
```
public_html/
├── app/
├── index.php (movido de public/)
├── login.php (movido de public/)
├── admin.php (movido de public/)
├── assets/
├── uploads/
└── ...
```

### 6.3 Crear index.php en la raíz (Alternativa Simple)

Si prefieres mantener la estructura actual, crea `public_html/index.php`:

```php
<?php
// Redireccionar a public/
header('Location: /public/');
exit;
?>
```

---

## ✅ Paso 7: Verificar y Probar

### 7.1 Verificar Conexión a Base de Datos

1. Accede a: `https://rcelbosque.com/public/test_hostinger_connection.php`
2. Deberías ver:
   - ✅ Conexión exitosa
   - 📊 Lista de tablas
   - 👥 Número de usuarios

3. **⚠️ IMPORTANTE:** Elimina este archivo después de verificar:
   - Desde File Manager: borra `public/test_hostinger_connection.php`
   - O desde FTP: elimina el archivo

### 7.2 Crear Usuario Administrador

**Opción A: Desde phpMyAdmin**
```sql
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Administrador', 'admin@rcelbosque.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

**Opción B: Desde el sitio**
1. Accede a: `https://rcelbosque.com/public/register.php`
2. Regístrate normalmente
3. Luego actualiza el rol en la base de datos a `admin_general`

### 7.3 Probar Funcionalidades

1. **Acceder al sitio:**
   - `https://rcelbosque.com/public/` - Página principal
   - `https://rcelbosque.com/public/catalogo.php` - Catálogo público

2. **Probar Login:**
   - `https://rcelbosque.com/public/login.php`
   - Usa las credenciales del administrador

3. **Verificar Panel Admin:**
   - `https://rcelbosque.com/public/admin.php`
   - Deberías ver el dashboard

---

## 🔒 Paso 8: Seguridad y Optimización

### 8.1 Ocultar Errores PHP

Edita `app/config.php` y agrega al inicio:
```php
// Ocultar errores en producción
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
```

### 8.2 Configurar reCAPTCHA v3

1. Ve a [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
2. Registra tu dominio: `rcelbosque.com`
3. Obtén las claves
4. Actualiza en:
   - `public/login.php`
   - `public/register.php`
   - `app/recaptcha.php`

### 8.3 Proteger Archivos Sensibles

Asegúrate de que estos archivos NO sean accesibles públicamente:
- `app/config.php`
- `app/config.production.php`
- `.htaccess` (si contiene información sensible)

### 8.4 Configurar SSL/HTTPS

1. En hPanel, busca "SSL" o "Certificados SSL"
2. Activa SSL para `rcelbosque.com`
3. Hostinger suele proporcionar SSL gratuito (Let's Encrypt)
4. Una vez activado, todas las URLs usarán `https://`

---

## 🐛 Solución de Problemas Comunes

### Error 500 - Internal Server Error

**Causas posibles:**
- Error en `app/config.php`
- Permisos incorrectos
- Error de sintaxis PHP

**Solución:**
1. Revisa los logs de error en hPanel
2. Verifica `app/config.php` tiene sintaxis correcta
3. Verifica permisos de carpetas (755)

### Error: "Access denied for user"

**Causa:** Credenciales incorrectas

**Solución:**
1. Verifica credenciales en `app/config.php`
2. Verifica que el usuario tenga permisos en la base de datos
3. Verifica el host (puede no ser `localhost`)

### Error: "Unknown database"

**Causa:** Nombre de base de datos incorrecto

**Solución:**
1. Verifica el nombre exacto en hPanel
2. Asegúrate de incluir el prefijo `u919054360_`
3. Verifica que la base de datos exista

### Página en blanco

**Causa:** Error de PHP o ruta incorrecta

**Solución:**
1. Activa temporalmente `display_errors` en `app/config.php`
2. Revisa los logs de error
3. Verifica que las rutas de `require` sean correctas

### No se pueden subir imágenes

**Causa:** Permisos de carpeta incorrectos

**Solución:**
1. Verifica que `public/uploads/animals/` exista
2. Establece permisos 755 o 777
3. Verifica que el servidor web tenga permisos de escritura

---

## 📋 Checklist Final

Antes de considerar el despliegue completo:

- [ ] Archivos subidos a `public_html/`
- [ ] Base de datos creada y esquema importado
- [ ] `app/config.php` configurado con credenciales de Hostinger
- [ ] Host de MySQL verificado y configurado
- [ ] Permisos de `public/uploads/animals/` configurados (755)
- [ ] Usuario administrador creado
- [ ] Conexión verificada con `test_hostinger_connection.php`
- [ ] Archivo de prueba eliminado
- [ ] Login funcionando
- [ ] Panel admin accesible
- [ ] Catálogo público visible
- [ ] SSL/HTTPS configurado
- [ ] reCAPTCHA v3 configurado (opcional)
- [ ] Errores PHP ocultos
- [ ] `.htaccess` configurado (opcional)

---

## 🔄 Mantenimiento

### Actualizar el Sitio

1. **Hacer cambios localmente:**
   - Desarrolla en XAMPP
   - Prueba todo localmente

2. **Subir cambios:**
   - Usa FTP o File Manager
   - Sube solo los archivos modificados
   - O usa Git si está configurado

3. **Verificar:**
   - Prueba las funcionalidades actualizadas
   - Revisa logs de error si hay problemas

### Backup Regular

1. **Base de Datos:**
   - Desde phpMyAdmin: Exportar base de datos
   - Guarda el archivo `.sql` regularmente

2. **Archivos:**
   - Descarga copia de `public_html/` regularmente
   - O usa herramientas de backup de Hostinger

---

## 📞 Soporte

Si tienes problemas:

1. **Revisa los logs:**
   - En hPanel: "Error Logs" o "Registros de Error"
   - Revisa errores de PHP y MySQL

2. **Contacta a Hostinger:**
   - Soporte 24/7 disponible
   - Chat en vivo desde hPanel

3. **Documentación:**
   - [HOSTINGER_SETUP.md](HOSTINGER_SETUP.md) - Configuración detallada
   - [README.md](README.md) - Documentación general

---

## 🎯 URLs Finales

Una vez configurado, tus URLs serán:

- **Página Principal:** `https://rcelbosque.com/public/`
- **Login:** `https://rcelbosque.com/public/login.php`
- **Registro:** `https://rcelbosque.com/public/register.php`
- **Panel Admin:** `https://rcelbosque.com/public/admin.php`
- **Catálogo:** `https://rcelbosque.com/public/catalogo.php`
- **Veterinario:** `https://rcelbosque.com/public/veterinary.php`

---

**¡Listo!** Tu proyecto debería estar funcionando en `https://rcelbosque.com`

**Última Actualización:** 2025-11-09

