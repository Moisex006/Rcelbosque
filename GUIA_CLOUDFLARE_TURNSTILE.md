# Guía: Configurar Cloudflare Turnstile

Esta guía te ayudará a configurar Cloudflare Turnstile para reemplazar Google reCAPTCHA en el sistema de login y registro.

## 📋 Requisitos Previos

1. ✅ Tener una cuenta en Cloudflare (gratuita)
2. ✅ Tener acceso al dashboard de Cloudflare
3. ✅ Dominio configurado en Cloudflare (opcional, pero recomendado)

## 🚀 Paso 1: Crear un sitio en Cloudflare Turnstile

1. **Accede al Dashboard de Cloudflare:**
   - Ve a: https://dash.cloudflare.com/
   - Inicia sesión con tu cuenta

2. **Navega a Turnstile:**
   - En el menú lateral, busca **"Security"** o **"Seguridad"**
   - Haz clic en **"Turnstile"**
   - Si no lo ves, busca en el menú o usa la búsqueda

3. **Crear un nuevo sitio:**
   - Haz clic en **"Add Site"** o **"Agregar Sitio"**
   - Completa el formulario:
     - **Site name (Nombre del sitio):** `Rc El Bosque` (o el nombre que prefieras)
     - **Domain (Dominio):** 
       - Para producción: `rcelbosque.com`
       - Para desarrollo local: `localhost` o déjalo vacío
     - **Widget Mode (Modo del widget):**
       - **Recomendado:** `Managed` (automático, invisible cuando es posible)
       - O `Non-interactive` (siempre invisible)
       - O `Interactive` (siempre visible)

4. **Obtener las credenciales:**
   - Después de crear el sitio, verás dos claves:
     - **Site Key** (Clave del sitio) - Pública, va en el HTML
     - **Secret Key** (Clave secreta) - Privada, va en el servidor

## 🔧 Paso 2: Configurar las credenciales en el código

1. **Abrir `public/login.php`:**
   - Busca las líneas:
     ```php
     $TURNSTILE_SITE_KEY = 'TU_SITE_KEY_AQUI';
     $TURNSTILE_SECRET_KEY = 'TU_SECRET_KEY_AQUI';
     ```

2. **Reemplazar las credenciales:**
   ```php
   $TURNSTILE_SITE_KEY = '0x4AAAAAAABkMYinukVqmMc'; // Tu Site Key real
   $TURNSTILE_SECRET_KEY = '0x4AAAAAAABkMYinukVqmMc_xxxxxxxxxxxxx'; // Tu Secret Key real
   ```

3. **Hacer lo mismo en `public/register.php`** (si también quieres Turnstile en registro)

## 🎨 Paso 3: Personalizar el widget (Opcional)

En `public/login.php`, puedes personalizar el widget de Turnstile:

```html
<div class="cf-turnstile" 
     data-sitekey="TU_SITE_KEY" 
     data-theme="light"        <!-- "light" o "dark" -->
     data-size="normal"         <!-- "normal" o "compact" -->
     data-language="es"         <!-- Código de idioma (opcional) -->
     style="margin: 1rem 0; display: flex; justify-content: center;">
</div>
```

### Opciones disponibles:

- **data-theme:** `light` o `dark` (tema del widget)
- **data-size:** `normal` o `compact` (tamaño del widget)
- **data-language:** Código de idioma (ej: `es`, `en`, `fr`)

## ✅ Paso 4: Verificar que funciona

1. **Abrir la página de login:**
   - Ve a `http://localhost/Rcelbosque/public/login.php` (local)
   - O `https://rcelbosque.com/public/login.php` (producción)

2. **Verificar que aparece el widget:**
   - Deberías ver el widget de Cloudflare Turnstile
   - Si está en modo "Managed", puede ser invisible hasta que sea necesario

3. **Probar el login:**
   - Ingresa credenciales válidas
   - El widget debería validarse automáticamente
   - Si hay error, revisa la consola del navegador (F12)

## 🔍 Paso 5: Solución de problemas

### El widget no aparece:
- Verifica que el Site Key sea correcto
- Revisa la consola del navegador (F12) para ver errores
- Asegúrate de que el script de Turnstile esté cargado:
  ```html
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  ```

### Error "Verificación de seguridad fallida":
- Verifica que el Secret Key sea correcto
- Revisa los logs del servidor para ver el error específico
- Asegúrate de que el dominio esté configurado correctamente en Cloudflare

### El widget aparece pero no valida:
- Verifica que el dominio en Cloudflare coincida con el dominio real
- Para desarrollo local, asegúrate de agregar `localhost` como dominio permitido

## 📝 Notas importantes

1. **Credenciales de prueba:**
   - Cloudflare proporciona credenciales de prueba para desarrollo
   - Site Key de prueba: `1x00000000000000000000AA`
   - Secret Key de prueba: `1x0000000000000000000000000000000AA`
   - Estas siempre devuelven éxito, útiles para desarrollo

2. **Seguridad:**
   - **NUNCA** compartas tu Secret Key públicamente
   - Mantén el Secret Key solo en el servidor (archivos PHP)
   - El Site Key puede estar en el HTML sin problemas

3. **Modo de desarrollo:**
   - En el código actual, si no hay token, se permite el envío (modo desarrollo)
   - En producción, descomenta las líneas que bloquean el envío sin token

## 🔄 Migrar también el registro

Si quieres usar Turnstile también en el registro, sigue los mismos pasos pero en `public/register.php`:

1. Cambia `require __DIR__ . '/../app/recaptcha.php';` por `require __DIR__ . '/../app/turnstile.php';`
2. Reemplaza la verificación de reCAPTCHA por Turnstile
3. Agrega el widget de Turnstile en el formulario
4. Actualiza el JavaScript

## 📚 Recursos adicionales

- Documentación oficial: https://developers.cloudflare.com/turnstile/
- Dashboard de Cloudflare: https://dash.cloudflare.com/
- Ejemplos de código: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/

## ✅ Checklist de implementación

- [ ] Crear sitio en Cloudflare Turnstile
- [ ] Obtener Site Key y Secret Key
- [ ] Configurar credenciales en `login.php`
- [ ] Verificar que el widget aparece
- [ ] Probar login con credenciales válidas
- [ ] Verificar que la validación funciona
- [ ] (Opcional) Configurar también en `register.php`
- [ ] (Opcional) Personalizar tema y tamaño del widget


