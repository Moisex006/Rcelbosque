# Guía de Instalación - Rc El Bosque

## Requisitos
- Windows con XAMPP (Apache + PHP 8.0+ + MySQL 5.7+)
- Navegador web moderno

## Pasos

1. Copiar proyecto
   - Copia la carpeta del proyecto a `C:/xampp/htdocs/Rcelbosque`

2. Crear Base de Datos
   - Abre `http://localhost/phpmyadmin`
   - Crea la base `rcelbosque`
   - Importa `rcelbosque.sql` (esto creará todas las tablas)

3. Configurar conexión
   - `app/config.php` usa por defecto `root` sin contraseña
   - Ajusta si tu MySQL tiene contraseña

4. Permisos de carpeta
   - Asegúrate de que `public/uploads/animals/` existe
   - Concede permisos de escritura al servidor web

5. Configurar reCAPTCHA (opcional en local)
   - Para producción, registra el dominio en Google reCAPTCHA v3
   - Reemplaza las keys en `public/login.php`, `public/register.php` y `app/recaptcha.php`
   - En local, el sistema usa `test-token` automáticamente si el script no carga

6. Iniciar XAMPP
   - Inicia Apache y MySQL

7. Acceder al sistema
   - `http://localhost/Rcelbosque/public/`

## Crear Usuario Admin Inicial

En phpMyAdmin, ejecuta:
```sql
INSERT INTO users (name, email, password_hash, role)
VALUES ('Administrador', 'admin@rcelbosque.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

## Módulo Node.js (opcional)
- En `backend/` hay un esqueleto de API (veterinaria/reportes) con SQLite.
- No es necesario para la versión PHP en XAMPP.

