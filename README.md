# Rc El Bosque - Sistema de Gestión Ganadera

Sistema web completo para la gestión integral de ganadería, desarrollado con PHP y MySQL, que permite administrar animales, lotes, veterinaria, usuarios y catálogo de ventas.

🌐 **Sitio Web:** [https://rcelbosque.com](https://rcelbosque.com)

## 📋 Descripción

Rc El Bosque es una aplicación web que facilita la gestión de operaciones ganaderas, incluyendo:

- **Gestión de Animales**: Registro, edición y seguimiento individual de cada animal
- **Gestión de Fincas**: Administración de las fincas asociadas al sistema
- **Sistema de Lotes**: Agrupación de animales para venta o gestión conjunta
- **Catálogo Público**: Visualización de animales y lotes disponibles para compra
- **Módulo Veterinario**: Registro de tratamientos, vacunas y diagnósticos
- **Sistema de Postulaciones**: Control de visibilidad para el catálogo público
- **Gestión de Usuarios**: Sistema de roles con permisos diferenciados
- **Reportes y Analytics**: Histórico sanitario y reportes del sistema

## 🎯 Características Principales

### Funcionalidades Generales
- ✅ Sistema de autenticación con roles
- ✅ Interfaz responsive y moderna
- ✅ Carga de imágenes (hasta 5 por animal)
- ✅ Descripción de imágenes
- ✅ Protección con reCAPTCHA v3
- ✅ Sistema de notificaciones (flash messages)

### Gestión de Usuarios
- **Administrador General**: Acceso total al sistema, crea usuarios de cualquier tipo
- **Administrador de Finca**: Gestiona animales/lotes de su finca, crea veterinarios
- **Veterinario**: Acceso solo al módulo veterinario
- **Usuario**: Acceso al catálogo público

### Sistema de Postulaciones
- Los administradores de finca pueden postular animales/lotes para el catálogo
- Los administradores generales aprueban o rechazan postulaciones
- Control de visibilidad centralizado

## 🛠️ Tecnologías Utilizadas

- **Backend**: PHP 8.0+, MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Base de Datos**: MySQL
- **Estilos**: CSS personalizado con variables CSS
- **Iconos**: Font Awesome
- **Seguridad**: reCAPTCHA v3
- **Hosting**: Compatible con XAMPP (desarrollo) y Hostinger (producción)
- **Servidor Web**: Apache (XAMPP)

## 📁 Estructura del Proyecto

```
Rcelbosque/
├── app/
│   ├── config.php           # Configuración y funciones auxiliares
│   └── recaptcha.php        # Verificación de reCAPTCHA
├── backend/
│   ├── db/
│   │   ├── rcelbosque.sqlite   # Base de datos SQLite para desarrollo
│   │   ├── db.js            # Configuración de SQLite
│   │   └── schema.sql       # Esquema de base de datos
│   ├── routes/              # Rutas API Node.js (veterinaria)
│   ├── middleware/          # Middleware de autenticación
│   └── server.js            # Servidor Node.js
├── frontend/                # Versiones HTML estáticas (desarrollo)
├── public/
│   ├── uploads/             # Imágenes de animales
│   ├── assets/              # CSS y recursos estáticos
│   ├── admin.php            # Panel administrativo
│   ├── catalogo.php         # Catálogo público
│   ├── index.php            # Página principal
│   ├── login.php            # Inicio de sesión
│   ├── register.php         # Registro de usuarios
│   └── get-animal-details.php  # API para detalles de animales
└── rcelbosque.sql              # Esquema completo de MySQL
```

## 📖 Documentación Adicional

- [Guía de Instalación](INSTALLATION.md) - Instrucciones detalladas de instalación
- [Arquitectura del Sistema](ARCHITECTURE.md) - Estructura técnica y diseño
- [Seguridad](SECURITY.md) - Medidas de seguridad implementadas
- [Guía de Usuario](USER_GUIDE.md) - Manual de uso del sistema

## 🚀 Instalación Rápida

### Requisitos Previos
- XAMPP (con PHP 8.0+ y MySQL 5.7+) para desarrollo local
- O Hostinger/hosting compatible para producción
- Navegador web moderno
- Opcional: Node.js para desarrollo del módulo backend

### Instalación Local (XAMPP)

1. **Clonar o copiar el proyecto**
   ```bash
   # Si usas git
   git clone <repository-url> C:/xampp/htdocs/Rcelbosque
   
   # O copiar manualmente la carpeta a C:/xampp/htdocs/
   ```

2. **Crear la base de datos**
   - Abrir phpMyAdmin (http://localhost/phpmyadmin)
   - Crear una nueva base de datos llamada `rcelbosque`
   - Importar el archivo `rcelbosque.sql`

3. **Configurar la conexión**
   - Verificar las credenciales en `app/config.php`
   - Por defecto usa: usuario `root`, sin contraseña

4. **Iniciar servicios**
   - Iniciar Apache y MySQL desde el panel de control de XAMPP

5. **Acceder al sistema**
   - Abrir: http://localhost/Rcelbosque/public/

### Instalación en Hostinger (Producción)

**🌐 Dominio:** [rcelbosque.com](https://rcelbosque.com)

**Guías disponibles:**
- **[QUICK_START_HOSTINGER.md](QUICK_START_HOSTINGER.md)** ⚡ - Inicio rápido (5 minutos)
- **[GUIA_SUBIDA_HOSTINGER.md](GUIA_SUBIDA_HOSTINGER.md)** 📚 - Guía completa paso a paso
- **[HOSTINGER_SETUP.md](HOSTINGER_SETUP.md)** ⚙️ - Configuración de base de datos

**Resumen rápido:**
1. Ejecutar: `php switch_to_hostinger.php` (cambia configuración a Hostinger)
2. Subir archivos a `public_html/` en Hostinger (File Manager o FTP)
3. Importar `rcelbosque.sql` en phpMyAdmin de Hostinger
4. Verificar con `public/verificar_despliegue.php`
5. Crear usuario administrador
6. Eliminar archivos de prueba después de verificar

## 🔐 Credenciales Iniciales

Por defecto, el sistema no incluye usuarios. Debes crear el primer usuario administrador.

### Crear Usuario Administrador Inicial

Ejecutar en phpMyAdmin o desde línea de comandos:

```sql
INSERT INTO users (name, email, password_hash, role) 
VALUES ('Administrador', 'admin@rcelbosque.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin_general');
```

O registrarse normalmente y actualizar el rol manualmente en la base de datos.

## 📝 Uso Básico

### Para Administradores Generales

1. **Crear Fincas**: Ir a "Fincas" en el menú lateral
2. **Crear Usuarios**: Ir a "Usuarios" para agregar administradores de finca, veterinarios, etc.
3. **Crear Especies y Razas**: Sistema pre-configurado con "Bovino" y "Brahman"
4. **Gestionar Postulaciones**: Aprobar o rechazar animales/lotes postulados para el catálogo

### Para Administradores de Finca

1. **Registrar Animales**: Ir a "Gestión de Animales"
2. **Crear Lotes**: Agrupar animales para venta
3. **Postular para Catálogo**: Marcar animales/lotes para postulación
4. **Crear Veterinarios**: Gestión de usuarios veterinarios

### Para Veterinarios

1. **Acceder al Módulo Veterinario**: Solo acceso a tratamientos y diagnósticos
2. **Registrar Tratamientos**: Mantenimiento de historial sanitario
3. **Gestionar Vacunas**: Control de vacunación

## 🐛 Solución de Problemas

### Las imágenes no se muestran
- Verificar que la carpeta `public/uploads/animals/` existe
- Verificar permisos de escritura en la carpeta
- Revisar la configuración de `.htaccess`

### Error de conexión a base de datos
- Verificar que MySQL está corriendo
- Revisar credenciales en `app/config.php`
- Verificar que la base de datos `rcelbosque` existe

### reCAPTCHA no funciona
- Verificar que se ha configurado el sitio en Google reCAPTCHA
- Revisar las keys en `app/recaptcha.php`
- En desarrollo local, el sistema usa un token de prueba

## 🔄 Actualizaciones Futuras

- [ ] Sistema de reportes avanzado
- [ ] Dashboard con gráficos y estadísticas
- [ ] Exportación de datos (Excel, PDF)
- [ ] API REST completa
- [ ] Aplicación móvil
- [ ] Integración con dispositivos IoT
- [ ] Sistema de notificaciones en tiempo real

## 📄 Licencia

Este proyecto es propiedad de [Tu Organización]. Todos los derechos reservados.

## 👥 Desarrollado Por

Sistema desarrollado como proyecto final de gestión ganadera.

## 📧 Contacto

Para soporte técnico o consultas, contactar a: [email de contacto]

---

**Versión**: 1.2.0  
**Última actualización**: Octubre 2025
