# Guía: Obtener Contraseña de Aplicación de Gmail

Esta guía te ayudará a obtener una contraseña de aplicación para usar con `rc.elbosque.app@gmail.com` y poder enviar correos desde tu aplicación.

## 📋 Requisitos Previos

1. ✅ Tener acceso a la cuenta de Gmail: `rc.elbosque.app@gmail.com`
2. ✅ Tener un dispositivo con acceso a esa cuenta
3. ✅ Tener habilitada la verificación en dos pasos (2FA) en la cuenta de Google

## 🔐 Paso 1: Habilitar Verificación en Dos Pasos

Si aún no tienes la verificación en dos pasos activada:

1. Ve a tu cuenta de Google: https://myaccount.google.com/
2. En el menú lateral, haz clic en **"Seguridad"**
3. Busca la sección **"Verificación en dos pasos"**
4. Haz clic en **"Empezar"** y sigue las instrucciones
5. Configura la verificación usando tu teléfono

**⚠️ IMPORTANTE:** La verificación en dos pasos DEBE estar activada para poder crear contraseñas de aplicación.

## 🔑 Paso 2: Crear Contraseña de Aplicación

Una vez que tengas la verificación en dos pasos activada:

### Opción A: Desde la Web (Recomendado)

1. Ve a: https://myaccount.google.com/apppasswords
   - O ve a: https://myaccount.google.com/ → **Seguridad** → **Contraseñas de aplicaciones**

2. Si te pide verificar tu identidad, ingresa tu contraseña de Google

3. En la sección **"Seleccionar aplicación"**, elige:
   - **"Otra (nombre personalizado)"**
   - Escribe: `Rc El Bosque - Sistema`

4. En la sección **"Seleccionar dispositivo"**, elige:
   - **"Otro (nombre personalizado)"**
   - Escribe: `Servidor Hostinger`

5. Haz clic en **"Generar"**

6. **Google te mostrará una contraseña de 16 caracteres** (sin espacios)
   - Ejemplo: `abcd efgh ijkl mnop`
   - **CÓPIALA INMEDIATAMENTE** - solo se muestra una vez
   - Esta es la contraseña que usarás en tu aplicación

### Opción B: Si no ves la opción "Contraseñas de aplicaciones"

Si no aparece la opción, puede ser porque:
- La verificación en dos pasos no está activada
- Tu cuenta es una cuenta de organización con restricciones

**Solución:**
1. Asegúrate de que la verificación en dos pasos esté activada
2. Intenta acceder directamente: https://myaccount.google.com/apppasswords
3. Si aún no funciona, contacta al administrador de la cuenta

## 📝 Paso 3: Guardar la Contraseña de Aplicación

La contraseña que obtuviste se verá así:
```
abcd efgh ijkl mnop
```

**IMPORTANTE:** 
- Elimina los espacios cuando la uses en el código
- La contraseña correcta sería: `abcdefghijklmnop`
- Guárdala en un lugar seguro (no la compartas públicamente)

## 🔧 Paso 4: Configurar en la Aplicación

Una vez que tengas la contraseña, deberás configurarla en:

1. **Archivo:** `app/config.php`
2. **Variables a configurar:**
   ```php
   $SMTP_EMAIL = 'rc.elbosque.app@gmail.com';
   $SMTP_PASSWORD = 'TU_CONTRASEÑA_DE_APLICACION_AQUI'; // Sin espacios
   ```

## ⚠️ Seguridad

- **NUNCA** compartas tu contraseña de aplicación públicamente
- **NUNCA** la subas a repositorios públicos (GitHub, GitLab, etc.)
- Si sospechas que fue comprometida, revócala inmediatamente y crea una nueva
- Cada contraseña de aplicación es única y solo funciona para la aplicación específica

## 🔄 Revocar una Contraseña de Aplicación

Si necesitas revocar una contraseña:

1. Ve a: https://myaccount.google.com/apppasswords
2. Busca la contraseña que quieres revocar
3. Haz clic en el ícono de **"Eliminar"** (🗑️)
4. Confirma la eliminación

## ❓ Solución de Problemas

### "No puedo ver la opción de Contraseñas de aplicaciones"
- Verifica que la verificación en dos pasos esté activada
- Intenta acceder directamente: https://myaccount.google.com/apppasswords
- Asegúrate de estar usando una cuenta personal (no corporativa con restricciones)

### "La contraseña no funciona"
- Verifica que eliminaste todos los espacios
- Asegúrate de estar usando la contraseña correcta (cópiala de nuevo si es necesario)
- Verifica que la cuenta de Gmail tenga la verificación en dos pasos activada

### "El correo no se envía"
- Verifica que la contraseña de aplicación sea correcta
- Revisa que el servidor SMTP de Gmail esté accesible desde tu servidor
- Revisa los logs de errores de PHP

## 📧 Información SMTP de Gmail

Para referencia, aquí están los datos SMTP de Gmail:

- **Servidor SMTP:** `smtp.gmail.com`
- **Puerto:** `587` (TLS) o `465` (SSL)
- **Seguridad:** TLS/SSL
- **Usuario:** `rc.elbosque.app@gmail.com`
- **Contraseña:** Tu contraseña de aplicación (16 caracteres, sin espacios)

