# 📦 Instalar PHPMailer Manualmente

PHPMailer es necesario para enviar correos electrónicos desde el sistema. Sigue estos pasos:

## 🚀 Opción 1: Usando el Script Automático (Recomendado)

1. Abre una terminal en el directorio del proyecto
2. Ejecuta:
   ```bash
   php install_phpmailer.php
   ```

## 🚀 Opción 2: Usando Composer (Si lo tienes instalado)

1. Abre una terminal en el directorio del proyecto
2. Ejecuta:
   ```bash
   composer install
   ```

## 🚀 Opción 3: Descarga Manual

1. Descarga PHPMailer desde: https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip
2. Extrae el archivo ZIP
3. Copia estos archivos a `vendor/phpmailer/phpmailer/`:
   - `src/PHPMailer.php`
   - `src/SMTP.php`
   - `src/Exception.php`

## ✅ Verificar Instalación

Después de instalar, verifica que existan estos archivos:
- `vendor/phpmailer/phpmailer/PHPMailer.php`
- `vendor/phpmailer/phpmailer/SMTP.php`
- `vendor/phpmailer/phpmailer/Exception.php`

## 🔄 Después de Instalar

1. Recarga la página de administración
2. Intenta postular un animal nuevamente
3. Los logs en la consola deberían mostrar: "📧 [EMAIL] Usando PHPMailer para enviar correo"

