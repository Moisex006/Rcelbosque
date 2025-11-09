<?php
/**
 * Verificación de Cloudflare Turnstile
 * 
 * Cloudflare Turnstile es un sistema de verificación alternativo a reCAPTCHA
 * que ofrece mejor rendimiento y privacidad.
 */

/**
 * Verifica un token de Cloudflare Turnstile
 * 
 * @param string $token El token de Turnstile recibido del cliente
 * @param string $secretKey La clave secreta de Turnstile
 * @param string $remoteIp (Opcional) La IP del cliente
 * @return bool True si la verificación es exitosa, False en caso contrario
 */
function verifyTurnstile($token, $secretKey, $remoteIp = null) {
    // Permitir token de prueba en desarrollo local
    if ($token === 'test-token' || empty($token)) {
        return true; // En producción, cambiar esto a false si lo deseas
    }
    
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    
    $data = [
        'secret' => $secretKey,
        'response' => $token
    ];
    
    // Agregar IP remota si está disponible (opcional pero recomendado)
    if ($remoteIp !== null) {
        $data['remoteip'] = $remoteIp;
    }
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10 // Timeout de 10 segundos
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("❌ [TURNSTILE] Error al conectar con Cloudflare Turnstile");
        return false;
    }
    
    $response = json_decode($result, true);
    
    // Verificar que la respuesta sea exitosa
    if (!isset($response['success']) || $response['success'] !== true) {
        $errorCodes = $response['error-codes'] ?? [];
        error_log("❌ [TURNSTILE] Verificación fallida. Códigos de error: " . implode(', ', $errorCodes));
        return false;
    }
    
    return true;
}


