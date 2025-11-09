# Arquitectura del Sistema Rc El Bosque

Este documento describe la arquitectura técnica del proyecto, sus componentes, flujos principales y decisiones de diseño.

## 1. Visión General

- Capa de Presentación: PHP (views controladas en `public/*.php`) + HTML/CSS/JS.
- Capa de Lógica/Aplicación: PHP embebido en `public/admin.php` y helpers en `app/config.php`.
- Capa de Datos: MySQL (esquema definido en `rcelbosque.sql`).
- Módulos opcionales de backend (Node.js + SQLite) para veterinaria/reportes en `backend/` (no requerido para producción PHP en XAMPP).

## 2. Componentes

- `public/index.php`: Landing.
- `public/catalogo.php`: Catálogo público (animales y lotes), modal de detalles vía `get-animal-details.php`.
- `public/admin.php`: Panel administrativo con sidebar y secciones (animales, catálogo, lotes, usuarios, veterinaria, reportes, postulaciones, configuración y fincas). Implementa acciones (POST) en un switch central.
- `public/login.php` / `public/register.php`: Autenticación con reCAPTCHA v3.
- `app/config.php`: Conexión PDO, helpers de sesión/roles, `require_login()`, `require_role()`.
- `app/recaptcha.php`: Verificación de reCAPTCHA v3 (bypass de desarrollo con token `test-token`).
- `rcelbosque.sql`: Esquema de MySQL completo.
- `public/uploads/animals/`: Almacenamiento de fotos (máx. 5 por animal, con descripciones y foto principal).

## 3. Esquema de Base de Datos (resumen)

- `users(id, email, password_hash, name, role ENUM('admin_general','admin_finca','veterinario','user'), farm_id, created_at)`
- `farms(id, name, location)`
- `animals(id, tag_code UNIQUE, name, gender, birth_date, species_id, breed_id, farm_id, color, weight, description, in_cat, created_at, updated_at)`
- `animal_photos(id, animal_id, filename, original_name, file_path, file_size, mime_type, description, is_primary, sort_order, uploaded_at)`
- `lots(id, name, description, total_price, animal_count, lot_type, status, farm_id, created_by, timestamps)`
- `lot_animals(id, lot_id, animal_id, added_at)`
- `nominations(id, item_type('animal'|'lot'), item_id, proposed_by, farm_id, notes, status('pending'|'approved'|'rejected'), reviewed_by, reviewed_at, created_at)`
- Tablas veterinarias: `veterinarians`, `medications`, `treatments`, `treatment_medications`, `health_alerts`, `quarantines`, `health_reports`, `health_metrics` (ver `rcelbosque.sql`).

Claves foráneas y constraints aseguran integridad referencial.

## 4. Flujos Clave

### 4.1 Autenticación
- `login.php` verifica credenciales (`password_verify`) y reCAPTCHA v3.
- Al autenticar, guarda `$_SESSION['user']` con `id,email,name,role,farm_id`.
- `require_login()` protege `admin.php` y módulos restringidos.

### 4.2 RBAC (Roles)
- Helpers: `is_admin_general()`, `is_admin_finca()`, `is_veterinario()`.
- El sidebar y las acciones del switch verifican rol antes de operar.
- `admin_finca` solo crea usuarios `veterinario` y postula catálogo (no publica).

### 4.3 Gestión de Animales
- Crear/editar con validaciones; genera `tag_code` único.
- Subida de 1-5 fotos con validación de tipo/tamaño; define foto principal.
- Los animales son siempre `Bovino/Brahman` a nivel UI.

### 4.4 Catálogo y Postulaciones
- `admin_finca` postula animales/lotes; `admin_general` aprueba/rechaza.
- `catalogo.php` muestra animales `in_cat=1` y lotes `status='disponible'`.
- Modal de "Más Info" consume `get-animal-details.php`.

### 4.5 Lotes
- Crear lotes con nombre, tipo, precio total, descripción y animales asociados.
- Estado del lote (`disponible`, `reservado`, `vendido`).

### 4.6 Fincas (solo admin_general)
- CRUD de `farms` desde `admin.php` (sección Fincas).

## 5. Subida de Archivos
- Directorio: `public/uploads/animals/`.
- Validaciones: tipos (jpeg/jpg/png/gif/webp), tamaño máx 5MB, máx 5 fotos.
- Se guarda metadata en `animal_photos` y una única `is_primary=1` por animal.

## 6. Integración reCAPTCHA v3
- Carga del script con site key.
- En desarrollo, fallback a `test-token` y bypass de verificación en servidor.
- Para producción, registrar dominio en Google reCAPTCHA y desactivar el bypass.

## 7. Integración Node.js (opcional)
- Estructura en `backend/` con rutas para veterinaria y reportes (SQLite).
- No se requiere para el flujo PHP principal; se mantuvo como referencia.

## 8. Decisiones de Diseño
- Panel admin de una sola página con acciones por `switch` (simplicidad en XAMPP).
- RBAC en servidor + renderizado condicional en UI.
- Rutas públicas minimalistas: todo centralizado en `public/`.

## 9. Escalabilidad y Mejoras
- Extraer acciones de `admin.php` a controladores dedicados.
- Añadir CSRF tokens a formularios.
- Implementar API REST para consumo externo.
- Añadir paginación/búsqueda en tablas.
- Cache de listas comunes si escala.


