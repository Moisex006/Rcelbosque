<?php
// Verificación de reCAPTCHA v3

function verifyRecaptcha($token, $secretKey) {
    // Permitir token de prueba en desarrollo local
    if ($token === 'test-token' || empty($token)) {
        return true; // En producción, cambiar esto a false
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = [
        'secret' => $secretKey,
        'response' => $token
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);
    
    return $response['success'] ?? false;
}

