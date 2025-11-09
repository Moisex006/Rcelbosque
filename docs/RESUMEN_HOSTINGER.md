# ✅ Configuración para Hostinger - Completada

## 📋 Resumen de Cambios

Se ha configurado el proyecto para trabajar con Hostinger. Aquí está todo lo que se ha preparado:

## 📁 Archivos Creados

### 1. **HOSTINGER_SETUP.md**
   - Guía completa paso a paso para configurar en Hostinger
   - Instrucciones detalladas de cada proceso
   - Solución de problemas comunes
   - Checklist de despliegue

### 2. **app/config.production.php**
   - Configuración lista para producción en Hostinger
   - Credenciales pre-configuradas:
     - Host: `localhost`
     - Base de datos: `u919054360_rcelbosque`
     - Usuario: `u919054360_admin`
     - Contraseña: `rcelbosque@Admin1`

### 3. **app/config.hostinger.php**
   - Archivo de referencia con solo las credenciales
   - Para consulta rápida

### 4. **switch_to_hostinger.php**
   - Script para cambiar automáticamente a configuración de Hostinger
   - Hace backup automático
   - Fácil de usar

### 5. **switch_to_local.php**
   - Script para volver a configuración local (XAMPP)
   - Útil para desarrollo

### 6. **public/test_hostinger_connection.php**
   - Script de prueba de conexión
   - Muestra estado de conexión, tablas, usuarios, etc.
   - **IMPORTANTE:** Eliminar después de verificar

## 🔧 Archivos Modificados

### 1. **app/config.php**
   - Agregadas líneas comentadas con configuración de Hostinger
   - Fácil cambio entre desarrollo y producción
   - Instrucciones incluidas en comentarios

### 2. **README.md**
   - Agregada sección de instalación en Hostinger
   - Referencia a HOSTINGER_SETUP.md

## 🚀 Pasos para Desplegar en Hostinger

### Paso 1: Crear Base de Datos en Hostinger
1. Accede a hPanel de Hostinger
2. Ve a "Bases de datos MySQL"
3. Crea:
   - Base de datos: `rcelbosque` → `u919054360_rcelbosque`
   - Usuario: `admin` → `u919054360_admin`
   - Contraseña: `rcelbosque@Admin1`

### Paso 2: Importar Esquema
1. Accede a phpMyAdmin desde Hostinger
2. Selecciona la base de datos `u919054360_rcelbosque`
3. Importa el archivo `rcelbosque.sql`

### Paso 3: Configurar Aplicación

**Opción Rápida (Recomendada):**
```bash
php switch_to_hostinger.php
```

**Opción Manual:**
1. Edita `app/config.php`
2. Descomenta las líneas de Hostinger
3. Comenta las líneas de desarrollo local

### Paso 4: Subir Archivos
1. Sube todos los archivos a tu hosting
2. Mantén la estructura de carpetas
3. Asegúrate de que `public/uploads/animals/` tenga permisos 755

### Paso 5: Verificar Conexión
1. Accede a: `tu-dominio.com/public/test_hostinger_connection.php`
2. Verifica que la conexión sea exitosa
3. **ELIMINA** este archivo después de verificar

### Paso 6: Crear Usuario Administrador
```sql
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Administrador', 'admin@rcelbosque.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

## 📝 Credenciales Configuradas

Según la imagen que proporcionaste:

- **Host:** `localhost` (verificar en Hostinger si es diferente)
- **Base de Datos:** `u919054360_rcelbosque`
- **Usuario:** `u919054360_admin`
- **Contraseña:** `rcelbosque@Admin1`

## ⚠️ Notas Importantes

1. **Host de MySQL:** En Hostinger suele ser `localhost`, pero verifica en el panel de Hostinger si te proporcionan un host diferente (ej: `mysql.hostinger.com`)

2. **Permisos de Carpetas:**
   - `public/uploads/animals/` debe tener permisos 755 o 777
   - Verifica desde el administrador de archivos de Hostinger

3. **Seguridad:**
   - Elimina `test_hostinger_connection.php` después de verificar
   - Configura reCAPTCHA v3 para producción
   - Oculta errores de PHP en producción

4. **Backup:**
   - Los scripts de cambio hacen backup automático
   - Los backups se guardan como `config.php.backup.YYYY-MM-DD_HHMMSS`

## 🔄 Cambiar Entre Desarrollo y Producción

**Para cambiar a Hostinger:**
```bash
php switch_to_hostinger.php
```

**Para volver a desarrollo local:**
```bash
php switch_to_local.php
```

## 📚 Documentación

- **Guía Completa:** [HOSTINGER_SETUP.md](HOSTINGER_SETUP.md)
- **README Principal:** [README.md](README.md)
- **Configuración de Producción:** [app/config.production.php](app/config.production.php)

## ✅ Checklist de Despliegue

- [ ] Base de datos creada en Hostinger
- [ ] Usuario de base de datos creado y asociado
- [ ] Esquema `rcelbosque.sql` importado
- [ ] Configuración cambiada a Hostinger (`switch_to_hostinger.php`)
- [ ] Host verificado (puede ser `localhost` o diferente)
- [ ] Archivos subidos al servidor
- [ ] Permisos de `public/uploads/animals/` configurados (755)
- [ ] Conexión verificada con `test_hostinger_connection.php`
- [ ] Usuario administrador creado
- [ ] Archivo de prueba eliminado
- [ ] reCAPTCHA v3 configurado (opcional pero recomendado)
- [ ] Errores de PHP ocultos en producción

---

**Estado:** ✅ Todo listo para desplegar en Hostinger  
**Última Actualización:** 2025-11-09

