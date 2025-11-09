# 📤 Pasos para Subir tu Página a Hostinger (rcelbosque.com)

Guía paso a paso específica para tu cuenta de Hostinger.

## 🎯 Información de tu Hosting

- **Dominio:** rcelbosque.com
- **IP del Servidor:** 82.180.172.122
- **Servidor:** server759
- **Ubicación:** North America (USA AZ)

---

## 📁 Método 1: File Manager (Más Fácil) ⭐ RECOMENDADO

### Paso 1: Acceder al File Manager

1. En el panel de Hostinger (hPanel), busca en el menú lateral:
   - Busca el icono de **"Archivos"** o **"File Manager"**
   - O busca en la barra de búsqueda: "File Manager" o "Administrador de archivos"

2. Haz clic para abrir el File Manager

### Paso 2: Navegar a la Carpeta Correcta

1. En el File Manager, verás una estructura de carpetas
2. Busca y entra a la carpeta **`public_html`** o **`htdocs`**
   - Esta es la carpeta raíz de tu dominio `rcelbosque.com`
   - Todo lo que pongas aquí será accesible desde `https://rcelbosque.com`

### Paso 3: Subir Archivos

**Opción A: Arrastrar y Soltar (Más Fácil)**

1. Abre el explorador de Windows en tu computadora
2. Navega a: `C:\xampp\htdocs\Rcelbosque`
3. Selecciona TODAS las carpetas y archivos:
   - `app/`
   - `public/`
   - `backend/`
   - `sprints/`
   - `rcelbosque.sql`
   - `.htaccess`
   - `README.md`
   - Y todos los demás archivos
4. **Arrastra** todo desde Windows al File Manager de Hostinger
5. Espera a que termine la subida (puede tardar varios minutos)

**Opción B: Botón Subir**

1. En el File Manager, haz clic en el botón **"Subir"** o **"Upload"**
2. Haz clic en **"Seleccionar archivos"** o **"Choose files"**
3. Selecciona todos los archivos y carpetas de tu proyecto
4. Haz clic en **"Subir"** o **"Upload"**
5. Espera a que termine

### Paso 4: Verificar Estructura

Después de subir, deberías ver en `public_html/`:

```
public_html/
├── app/
│   ├── config.php
│   └── ...
├── public/
│   ├── index.php
│   ├── login.php
│   ├── admin.php
│   └── ...
├── backend/
├── rcelbosque.sql
├── .htaccess
└── ...
```

---

## 📤 Método 2: FTP (Más Rápido para Muchos Archivos)

### Paso 1: Obtener Credenciales FTP

1. En hPanel, busca **"FTP"** o **"Cuentas FTP"** en el menú lateral
2. Haz clic para ver tus cuentas FTP
3. Anota o crea una cuenta FTP con estos datos:
   - **Host:** `ftp.rcelbosque.com` o `82.180.172.122`
   - **Usuario:** (tu usuario FTP, puede ser `u919054360` o similar)
   - **Contraseña:** (tu contraseña FTP)
   - **Puerto:** `21`

### Paso 2: Instalar Cliente FTP

**FileZilla (Recomendado - Gratis):**
1. Descarga desde: https://filezilla-project.org/
2. Instala FileZilla

### Paso 3: Conectar con FTP

1. Abre FileZilla
2. En la parte superior, ingresa:
   - **Host:** `ftp.rcelbosque.com` o `82.180.172.122`
   - **Usuario:** (tu usuario FTP)
   - **Contraseña:** (tu contraseña FTP)
   - **Puerto:** `21`
3. Haz clic en **"Conexión rápida"** o **"Quickconnect"**

### Paso 4: Subir Archivos

1. **Panel izquierdo (Local):** Navega a `C:\xampp\htdocs\Rcelbosque`
2. **Panel derecho (Servidor):** Navega a `/public_html` o `/htdocs`
3. Selecciona todos los archivos y carpetas en el panel izquierdo
4. **Arrastra** del panel izquierdo al derecho
5. Espera a que termine la transferencia

---

## ⚙️ Paso 5: Configurar Permisos

Después de subir los archivos:

1. En File Manager, navega a `public/uploads/animals/`
2. Si la carpeta no existe, créala:
   - Haz clic derecho → **"Nueva carpeta"** → Nombre: `animals`
3. Haz clic derecho en la carpeta `animals`
4. Selecciona **"Cambiar permisos"** o **"Change Permissions"**
5. Establece: **`755`** o **`777`**
6. Marca la opción **"Aplicar a subdirectorios"** si está disponible

---

## 🗄️ Paso 6: Importar Base de Datos

### 6.1 Acceder a phpMyAdmin

1. En hPanel, busca **"phpMyAdmin"** en el menú lateral
2. Haz clic para abrir phpMyAdmin

### 6.2 Seleccionar Base de Datos

1. En el panel izquierdo de phpMyAdmin, busca y haz clic en:
   - **`u919054360_rcelbosque`**

### 6.3 Importar el SQL

**Opción A: Importar Archivo**

1. Con la base de datos seleccionada, haz clic en la pestaña **"Importar"**
2. Haz clic en **"Elegir archivo"** o **"Choose File"**
3. Selecciona el archivo `rcelbosque.sql` que subiste a `public_html/`
4. Haz clic en **"Continuar"** o **"Go"** al final
5. Espera a que termine la importación

**Opción B: Copiar y Pegar SQL**

1. Con la base de datos seleccionada, haz clic en la pestaña **"SQL"**
2. Abre el archivo `rcelbosque.sql` en tu computadora
3. Copia TODO el contenido (Ctrl+A, Ctrl+C)
4. Pégalo en el área de texto de phpMyAdmin
5. Haz clic en **"Continuar"** o **"Go"**

---

## ⚙️ Paso 7: Verificar Configuración

### 7.1 Verificar app/config.php

1. En File Manager, navega a `public_html/app/config.php`
2. Haz clic derecho → **"Editar"**
3. Verifica que tenga estas líneas (descomentadas):

```php
$DB_HOST = 'localhost'; // O el host que Hostinger te proporcione
$DB_NAME = 'u919054360_rcelbosque';
$DB_USER = 'u919054360_admin';
$DB_PASS = 'rcelbosque@Admin1';
```

4. Si no están descomentadas, descoméntalas y guarda

### 7.2 Verificar Host de MySQL

1. En hPanel, ve a **"Bases de datos MySQL"**
2. Busca la sección **"Información de conexión"**
3. Anota el **Host** (puede ser `localhost` o algo como `mysql.hostinger.com`)
4. Si es diferente a `localhost`, actualiza `app/config.php`:

```php
$DB_HOST = 'mysql.hostinger.com'; // O el host que te proporcione
```

---

## ✅ Paso 8: Verificar que Todo Funcione

### 8.1 Probar la Conexión

1. Accede a: `https://rcelbosque.com/public/verificar_despliegue.php`
2. Deberías ver una página con verificaciones
3. Revisa que todo esté ✅ (verde)

### 8.2 Probar el Sitio

1. Accede a: `https://rcelbosque.com/public/`
2. Deberías ver la página principal
3. Prueba: `https://rcelbosque.com/public/login.php`

### 8.3 Crear Usuario Administrador

Si no hay usuarios, crea uno desde phpMyAdmin:

1. En phpMyAdmin, selecciona `u919054360_rcelbosque`
2. Ve a la pestaña **"SQL"**
3. Ejecuta:

```sql
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Administrador', 'admin@rcelbosque.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

4. Credenciales:
   - **Email:** `admin@rcelbosque.com`
   - **Contraseña:** `admin123`

---

## 🔒 Paso 9: Seguridad Final

### 9.1 Eliminar Archivos de Prueba

**IMPORTANTE:** Elimina estos archivos después de verificar:

1. `public/verificar_despliegue.php`
2. `public/test_hostinger_connection.php`

**Cómo eliminar:**
- En File Manager, haz clic derecho en el archivo → **"Eliminar"**

### 9.2 Activar SSL/HTTPS

1. En hPanel, busca **"SSL"** o **"Certificados SSL"**
2. Busca tu dominio `rcelbosque.com`
3. Activa el certificado SSL (Hostinger suele tener SSL gratuito)
4. Espera unos minutos a que se active
5. Verifica que `https://rcelbosque.com` funcione

---

## 🐛 Solución de Problemas

### Error 404 - Página no encontrada

**Causa:** Archivos en la carpeta incorrecta

**Solución:**
- Verifica que los archivos estén en `public_html/` y no en otra carpeta
- Verifica que `public/index.php` exista

### Error 500 - Error interno del servidor

**Causa:** Error en `app/config.php` o permisos incorrectos

**Solución:**
1. Revisa `app/config.php` tiene sintaxis correcta
2. Verifica permisos de carpetas (755)
3. Revisa logs de error en hPanel

### No se pueden subir imágenes

**Causa:** Permisos de carpeta incorrectos

**Solución:**
1. Verifica que `public/uploads/animals/` exista
2. Establece permisos 755 o 777
3. Verifica que el servidor web tenga permisos de escritura

### Error de conexión a base de datos

**Causa:** Credenciales incorrectas o host incorrecto

**Solución:**
1. Verifica credenciales en `app/config.php`
2. Verifica el host de MySQL en hPanel
3. Asegúrate de que el usuario tenga permisos en la base de datos

---

## 📋 Checklist Final

Antes de considerar el despliegue completo:

- [ ] Archivos subidos a `public_html/`
- [ ] Estructura de carpetas correcta
- [ ] Permisos de `public/uploads/animals/` configurados (755)
- [ ] Base de datos `u919054360_rcelbosque` seleccionada
- [ ] Archivo `rcelbosque.sql` importado
- [ ] `app/config.php` configurado con credenciales correctas
- [ ] Host de MySQL verificado y configurado
- [ ] Usuario administrador creado
- [ ] Sitio accesible en `https://rcelbosque.com/public/`
- [ ] Login funcionando
- [ ] Archivos de prueba eliminados
- [ ] SSL/HTTPS activado

---

## 🎯 URLs Finales

Una vez configurado, tus URLs serán:

- **Página Principal:** `https://rcelbosque.com/public/`
- **Login:** `https://rcelbosque.com/public/login.php`
- **Registro:** `https://rcelbosque.com/public/register.php`
- **Panel Admin:** `https://rcelbosque.com/public/admin.php`
- **Catálogo:** `https://rcelbosque.com/public/catalogo.php`

---

## 💡 Consejos

1. **Backup:** Antes de hacer cambios, descarga una copia de tus archivos
2. **Pruebas:** Prueba todo localmente antes de subir a producción
3. **Logs:** Si hay errores, revisa los logs en hPanel
4. **Soporte:** Si tienes problemas, contacta al soporte de Hostinger (24/7)

---

**¡Listo!** Sigue estos pasos y tu sitio estará funcionando en `https://rcelbosque.com`

**Última Actualización:** 2025-11-09

