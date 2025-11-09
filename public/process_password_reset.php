<?php
/**
 * Procesa la solicitud de recuperación de contraseña
 * Genera un token único y envía un email con el enlace de recuperación
 */

require_once __DIR__ . '/../app/config.php';

header('Content-Type: application/json');

// Array para almacenar logs de debug
$debug_logs = [];

function add_debug_log($message, $type = 'info') {
    global $debug_logs;
    $timestamp = date('Y-m-d H:i:s');
    $debug_logs[] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => $timestamp
    ];
    error_log("🔐 [PASSWORD_RESET] [$timestamp] $message");
}

// Por seguridad, siempre mostrar el mismo mensaje (no revelar si el email existe o no)
$success_message = 'Si el correo electrónico está registrado, recibirás un enlace para restablecer tu contraseña en los próximos minutos.';

add_debug_log('========================================', 'info');
add_debug_log('Iniciando proceso de recuperación de contraseña', 'info');
add_debug_log('Método de solicitud: ' . $_SERVER['REQUEST_METHOD'], 'info');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    add_debug_log('Error: Método no permitido', 'error');
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Método no permitido',
        'debug_logs' => $debug_logs
    ]);
    exit;
}

try {
    add_debug_log('Obteniendo conexión a la base de datos...', 'info');
    $pdo = get_pdo();
    add_debug_log('Conexión a la base de datos establecida', 'success');
    
    // Validar email
    add_debug_log('Validando email recibido...', 'info');
    $email_received = $_POST['email'] ?? '';
    add_debug_log('Email recibido: ' . ($email_received ? substr($email_received, 0, 3) . '***' : 'VACÍO'), 'info');
    
    if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        add_debug_log('Error: Email inválido o vacío', 'error');
        echo json_encode([
            'success' => false, 
            'message' => 'Por favor, ingresa un correo electrónico válido.',
            'debug_logs' => $debug_logs
        ]);
        exit;
    }
    
    $email = trim($_POST['email']);
    add_debug_log('Email validado correctamente: ' . substr($email, 0, 3) . '***', 'success');
    
    // Buscar usuario por email
    add_debug_log('Buscando usuario en la base de datos...', 'info');
    try {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            add_debug_log('Usuario encontrado - ID: ' . $user['id'] . ', Nombre: ' . $user['name'], 'success');
        } else {
            add_debug_log('Usuario NO encontrado en la base de datos', 'warning');
        }
    } catch (PDOException $e) {
        add_debug_log('Error al buscar usuario: ' . $e->getMessage(), 'error');
        throw $e;
    }
    
    if (!$user) {
        // No revelar que el email no existe por seguridad
        add_debug_log('Usuario no encontrado - enviando mensaje genérico por seguridad', 'info');
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'debug_logs' => $debug_logs
        ]);
        exit;
    }
    
    // Generar token único y seguro
    add_debug_log('Generando token de recuperación...', 'info');
    $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
    add_debug_log('Token generado: ' . substr($token, 0, 8) . '...' . substr($token, -8), 'info');
    
    // Calcular fecha de expiración (30 minutos desde ahora)
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    add_debug_log('Token expirará el: ' . $expires_at, 'info');
    
    // Invalidar tokens anteriores del usuario (marcar como usados)
    add_debug_log('Invalidando tokens anteriores del usuario...', 'info');
    try {
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
        $stmt->execute([$user['id']]);
        $affected = $stmt->rowCount();
        add_debug_log("Tokens anteriores invalidados: $affected", 'info');
    } catch (PDOException $e) {
        // Si la tabla no existe, continuar (se creará el primer registro)
        add_debug_log('⚠️ Tabla password_resets puede no existir: ' . $e->getMessage(), 'warning');
    }
    
    // Guardar token en la base de datos
    add_debug_log('Guardando token en la base de datos...', 'info');
    try {
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires_at]);
        add_debug_log('Token guardado exitosamente en la base de datos', 'success');
    } catch (PDOException $e) {
        add_debug_log('❌ Error al insertar token: ' . $e->getMessage(), 'error');
        // Si la tabla no existe, informar al usuario que debe ejecutar el script SQL
        if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false || strpos($e->getMessage(), "table") !== false) {
            add_debug_log('❌ La tabla password_resets no existe. Ejecuta create_password_resets_table.sql', 'error');
            echo json_encode([
                'success' => false, 
                'message' => 'Error del sistema. Por favor, contacta al administrador.',
                'debug_logs' => $debug_logs
            ]);
            exit;
        }
        throw $e; // Re-lanzar si es otro tipo de error
    }
    
    // Construir URL de recuperación
    add_debug_log('Construyendo URL de recuperación...', 'info');
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $reset_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . urlencode($token);
    add_debug_log('URL de recuperación: ' . substr($reset_url, 0, 50) . '...', 'info');
    
    // Preparar email
    add_debug_log('Preparando email de recuperación...', 'info');
    $subject = 'Recuperación de Contraseña - RC El Bosque';
    add_debug_log('Asunto del email: ' . $subject, 'info');
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #2d5a27, #3e7b2e);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 30px;
            }
            .content p {
                margin: 15px 0;
                color: #555;
            }
            .button-container {
                text-align: center;
                margin: 30px 0;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #2d5a27, #3e7b2e);
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                box-shadow: 0 4px 10px rgba(45, 90, 39, 0.3);
            }
            .button:hover {
                background: linear-gradient(135deg, #3e7b2e, #4a9a3d);
            }
            .warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
        }
            .warning p {
                margin: 5px 0;
                color: #856404;
            }
            .footer {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                color: #666;
                font-size: 12px;
            }
            .token-info {
                background: #e9ecef;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Recuperación de Contraseña</h1>
            </div>
            <div class='content'>
                <p>Hola <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                
                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>RC El Bosque</strong>.</p>
                
                <div class='button-container'>
                    <a href='" . htmlspecialchars($reset_url) . "' class='button'>
                        Restablecer Contraseña
                    </a>
                </div>
                
                <p>O copia y pega el siguiente enlace en tu navegador:</p>
                <div class='token-info'>
                    " . htmlspecialchars($reset_url) . "
                </div>
                
                <div class='warning'>
                    <p><strong>⚠️ Importante:</strong></p>
                    <p>• Este enlace expirará en <strong>30 minutos</strong>.</p>
                    <p>• Si no solicitaste este cambio, puedes ignorar este correo.</p>
                    <p>• Por seguridad, no compartas este enlace con nadie.</p>
                </div>
                
                <p>Si el botón no funciona, copia y pega el enlace completo en la barra de direcciones de tu navegador.</p>
            </div>
            <div class='footer'>
                <p>Este es un correo automático del sistema RC El Bosque.</p>
                <p>Por favor, no respondas a este correo.</p>
                <p>© " . date('Y') . " RC El Bosque. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Enviar email
    add_debug_log('Enviando email a: ' . substr($user['email'], 0, 3) . '***', 'info');
    $email_sent = send_email($user['email'], $subject, $body);
    
    if ($email_sent) {
        add_debug_log('✅ Email de recuperación enviado exitosamente', 'success');
        add_debug_log('========================================', 'info');
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'debug_logs' => $debug_logs
        ]);
    } else {
        add_debug_log('❌ Error al enviar email de recuperación', 'error');
        add_debug_log('========================================', 'info');
        // Por seguridad, no revelar el error real
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'debug_logs' => $debug_logs
        ]);
    }
    
} catch (Exception $e) {
    add_debug_log('❌ Excepción capturada: ' . $e->getMessage(), 'error');
    add_debug_log('❌ Tipo: ' . get_class($e), 'error');
    add_debug_log('❌ Archivo: ' . $e->getFile() . ' Línea: ' . $e->getLine(), 'error');
    add_debug_log('❌ Stack trace: ' . substr($e->getTraceAsString(), 0, 500), 'error');
    add_debug_log('========================================', 'info');
    // Por seguridad, no revelar el error real
    echo json_encode([
        'success' => true, 
        'message' => $success_message,
        'debug_logs' => $debug_logs
    ]);
}

