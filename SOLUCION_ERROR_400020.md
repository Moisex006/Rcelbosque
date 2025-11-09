# Solución: Error 400020 de Cloudflare Turnstile

## 🔴 Problema
El error `400020` significa que la Site Key es inválida o el dominio no está configurado correctamente en Cloudflare.

## ✅ Soluciones

### Opción 1: Configurar localhost en Cloudflare (Recomendado para desarrollo)

1. **Ve al Dashboard de Cloudflare:**
   - https://dash.cloudflare.com/
   - Navega a: **Security** > **Turnstile**
   - Haz clic en tu widget creado

2. **Edita la configuración del widget:**
   - Busca la sección **"Domains"** o **"Dominios"**
   - Haz clic en **"Edit"** o **"Editar"**

3. **Agrega localhost:**
   - En el campo de dominios, agrega:
     - `localhost`
     - `127.0.0.1`
     - O el puerto que uses: `localhost:8080` (si usas puerto 8080)
   - Guarda los cambios

4. **Espera unos minutos** para que los cambios se propaguen

5. **Recarga la página** de login y prueba nuevamente

### Opción 2: Usar credenciales de prueba (Solo para desarrollo)

Si no quieres configurar el dominio en Cloudflare, puedes usar las credenciales de prueba que siempre funcionan:

1. **Abre `public/login.php`**

2. **Comenta las credenciales de producción:**
   ```php
   // $TURNSTILE_SITE_KEY = '0x4AAAAAAB_gZpJCbeM9C4o';
   // $TURNSTILE_SECRET_KEY = '0x4AAAAAAB_gbqY5C6Vtr4GzjzSGVmHqEw';
   ```

3. **Descomenta las credenciales de prueba:**
   ```php
   $TURNSTILE_SITE_KEY = '1x00000000000000000000AA';
   $TURNSTILE_SECRET_KEY = '1x0000000000000000000000000000000AA';
   ```

4. **Guarda y recarga** la página

⚠️ **Nota:** Las credenciales de prueba solo funcionan en desarrollo. Para producción, debes usar tus credenciales reales y configurar el dominio correcto.

### Opción 3: Verificar que las credenciales sean correctas

1. **Ve a Cloudflare Dashboard:**
   - Security > Turnstile > Tu widget

2. **Verifica las credenciales:**
   - Copia nuevamente el **Site Key** y **Secret Key**
   - Asegúrate de que no haya espacios extra
   - Compara con las que tienes en `login.php`

3. **Si las credenciales son diferentes:**
   - Actualiza `login.php` con las credenciales correctas

## 🔍 Verificación

Después de aplicar una solución:

1. **Abre la consola del navegador** (F12)
2. **Recarga la página** de login
3. **Verifica que no aparezca el error 400020**
4. **Deberías ver el widget de Turnstile** funcionando correctamente

## 📝 Para Producción

Cuando subas el sitio a producción (Hostinger):

1. **Usa las credenciales de producción** (las que obtuviste de Cloudflare)
2. **Configura el dominio real** en Cloudflare:
   - Ejemplo: `rcelbosque.com`
   - O `www.rcelbosque.com`
3. **Asegúrate de que el dominio esté en la lista** de dominios permitidos del widget

## 🆘 Si el problema persiste

1. **Limpia la caché del navegador** (Ctrl+Shift+Delete)
2. **Prueba en modo incógnito**
3. **Verifica que no haya extensiones del navegador** bloqueando Turnstile
4. **Revisa la consola del navegador** para ver si hay otros errores

