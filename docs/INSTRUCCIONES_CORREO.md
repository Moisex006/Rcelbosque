# 📧 Instrucciones: Configuración de Correo Electrónico

Este documento explica cómo configurar el sistema de envío de correos para recibir notificaciones cuando un administrador de finca postula un animal.

## 📋 Resumen

Cuando un **administrador de finca** postula un animal para el catálogo, el sistema enviará automáticamente un correo a `rc.elbosque.app@gmail.com` con la información del animal postulado.

## 🔧 Pasos de Configuración

### Paso 1: Obtener Contraseña de Aplicación de Gmail

Sigue la guía detallada: **[GUIA_CONTRASENA_APLICACION_GMAIL.md](GUIA_CONTRASENA_APLICACION_GMAIL.md)**

**Resumen rápido:**
1. Ve a: https://myaccount.google.com/apppasswords
2. Selecciona "Otra (nombre personalizado)" → Escribe: `Rc El Bosque - Sistema`
3. Selecciona "Otro (nombre personalizado)" → Escribe: `Servidor Hostinger`
4. Haz clic en "Generar"
5. **Copia la contraseña de 16 caracteres** (sin espacios)

### Paso 2: Configurar en el Código

1. Abre el archivo: `app/config.php`
2. Busca la línea:
   ```php
   $SMTP_PASS = ''; // ⚠️ CONFIGURA AQUÍ TU CONTRASEÑA DE APLICACIÓN
   ```
3. Pega tu contraseña de aplicación (sin espacios):
   ```php
   $SMTP_PASS = 'abcdefghijklmnop'; // Tu contraseña de 16 caracteres
   ```

### Paso 3: Instalar PHPMailer (Opcional pero Recomendado)

PHPMailer es más confiable que la función `mail()` nativa de PHP. Para instalarlo:

#### Opción A: Usando Composer (Recomendado)

```bash
# En el directorio raíz del proyecto
composer install
```

#### Opción B: Descarga Manual

Si no tienes Composer, puedes descargar PHPMailer manualmente:

1. Descarga desde: https://github.com/PHPMailer/PHPMailer/releases
2. Extrae la carpeta `PHPMailer` en `vendor/phpmailer/phpmailer/`
3. El sistema usará `mail()` nativa como fallback si PHPMailer no está disponible

### Paso 4: Verificar Configuración

El sistema está configurado para:
- **Servidor SMTP:** `smtp.gmail.com`
- **Puerto:** `587` (TLS)
- **Usuario:** `rc.elbosque.app@gmail.com`
- **Destinatario:** `rc.elbosque.app@gmail.com`

## ✅ Funcionamiento

### Cuándo se Envía el Correo

El correo se envía automáticamente cuando:

1. **Un admin_finca agrega un animal nuevo** y marca la casilla "Postular para el catálogo"
2. **Un admin_finca edita un animal** y lo postula para el catálogo
3. **Un admin_finca postula múltiples animales** (solo se envía un correo para el primero, para evitar spam)

### Contenido del Correo

El correo incluye:
- ✅ Código del animal
- ✅ Nombre (si tiene)
- ✅ Especie y raza
- ✅ Género
- ✅ Peso y edad
- ✅ Finca
- ✅ Información del usuario que postuló
- ✅ Descripción (si tiene)
- ✅ Botón para revisar la postulación

## 🧪 Probar el Sistema

### Prueba Manual

1. Inicia sesión como `admin_finca`
2. Agrega o edita un animal
3. Marca la casilla "Postular para el catálogo"
4. Guarda
5. Verifica que llegue el correo a `rc.elbosque.app@gmail.com`

### Verificar Logs

Si el correo no se envía, revisa los logs de PHP:

```bash
# En Hostinger, los logs suelen estar en:
tail -f /home/u919054360/domains/rcelbosque.com/logs/error.log
```

O revisa los logs de error de PHP configurados en `php.ini`.

## ❌ Solución de Problemas

### El correo no se envía

1. **Verifica la contraseña de aplicación:**
   - Asegúrate de que no tenga espacios
   - Verifica que sea la contraseña correcta
   - Revisa que la verificación en dos pasos esté activada

2. **Verifica la configuración SMTP:**
   - Revisa `app/config.php`
   - Asegúrate de que `$SMTP_PASS` tenga un valor

3. **Revisa los logs:**
   - Busca errores en los logs de PHP
   - Los errores se registran con `error_log()`

4. **Verifica permisos del servidor:**
   - Asegúrate de que el servidor pueda conectarse a `smtp.gmail.com:587`
   - Algunos servidores bloquean conexiones SMTP salientes

### PHPMailer no está instalado

Si PHPMailer no está instalado, el sistema usará automáticamente la función `mail()` nativa de PHP como fallback. Esto puede funcionar, pero es menos confiable.

Para instalar PHPMailer:
```bash
composer install
```

### Error: "SMTP connect() failed"

- Verifica que el servidor tenga acceso a Internet
- Verifica que el puerto 587 no esté bloqueado
- Intenta cambiar el puerto a 465 (SSL) en `app/config.php`:
  ```php
  $SMTP_PORT = 465;
  ```
  Y cambia:
  ```php
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // En lugar de ENCRYPTION_STARTTLS
  ```

## 🔒 Seguridad

- ⚠️ **NUNCA** subas `app/config.php` con la contraseña a repositorios públicos
- ⚠️ La contraseña de aplicación es sensible, guárdala de forma segura
- ⚠️ Si sospechas que fue comprometida, revócala y crea una nueva

## 📝 Archivos Relacionados

- `app/config.php` - Configuración SMTP
- `app/email.php` - Funciones de envío de correo
- `public/admin.php` - Lógica de postulación (líneas 285-291, 575-581, 622-630)
- `GUIA_CONTRASENA_APLICACION_GMAIL.md` - Guía detallada para obtener contraseña

## 🎯 Próximos Pasos

1. ✅ Obtener contraseña de aplicación de Gmail
2. ✅ Configurar `$SMTP_PASS` en `app/config.php`
3. ✅ (Opcional) Instalar PHPMailer con Composer
4. ✅ Probar postulando un animal
5. ✅ Verificar que llegue el correo

