# ⚡ Inicio Rápido - Subir a Hostinger

Guía rápida para subir el proyecto a **rcelbosque.com** en Hostinger.

> **📌 Para pasos detallados con capturas, consulta: [PASOS_SUBIR_HOSTINGER.md](PASOS_SUBIR_HOSTINGER.md)**

## 🚀 Pasos Rápidos (5 minutos)

### 1️⃣ Preparar Localmente
```bash
cd C:\xampp\htdocs\Rcelbosque
php switch_to_hostinger.php
```

### 2️⃣ Subir Archivos

**Opción A: File Manager (Más Fácil)**
1. Accede a hPanel → File Manager
2. Ve a `public_html/`
3. Arrastra toda la carpeta `Rcelbosque` o sube archivos individuales

**Opción B: FTP (Más Rápido)**
1. Usa FileZilla o WinSCP
2. Conecta a `ftp.rcelbosque.com`
3. Sube todo a `/public_html/`

### 3️⃣ Importar Base de Datos
1. hPanel → phpMyAdmin
2. Selecciona `u919054360_rcelbosque`
3. Importar → Selecciona `rcelbosque.sql` → Ejecutar

### 4️⃣ Verificar
1. Accede a: `https://rcelbosque.com/public/verificar_despliegue.php`
2. Revisa que todo esté ✅
3. **Elimina** el archivo de verificación después

### 5️⃣ Crear Admin
En phpMyAdmin, ejecuta:
```sql
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Administrador', 'admin@rcelbosque.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

## 📋 Credenciales

- **Base de Datos:** `u919054360_rcelbosque`
- **Usuario:** `u919054360_admin`
- **Contraseña:** `rcelbosque@Admin1`
- **Host:** `localhost` (verificar en Hostinger)

## 🔗 URLs

- Inicio: `https://rcelbosque.com/public/`
- Login: `https://rcelbosque.com/public/login.php`
- Admin: `https://rcelbosque.com/public/admin.php`

## ⚠️ Importante

1. **Permisos:** `public/uploads/animals/` debe tener permisos 755
2. **SSL:** Activa SSL en hPanel para `rcelbosque.com`
3. **Seguridad:** Elimina archivos de prueba después de verificar

## 📚 Guía Completa

Para más detalles, consulta: **[GUIA_SUBIDA_HOSTINGER.md](GUIA_SUBIDA_HOSTINGER.md)**

---

**¿Problemas?** Revisa la sección "Solución de Problemas" en la guía completa.

