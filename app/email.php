<?php
/**
 * Sistema de envío de correos electrónicos
 * Usa PHPMailer para enviar correos a través de Gmail SMTP
 */

// Verificar si PHPMailer está disponible
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Intentar cargar PHPMailer desde Composer
    $composer_autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        require_once $composer_autoload;
    } else {
        // Intentar cargar PHPMailer manualmente (sin Composer)
        $phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/PHPMailer.php';
        if (file_exists($phpmailer_path)) {
            require_once $phpmailer_path;
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/SMTP.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/Exception.php';
        } else {
            // Si no está instalado, usar la función mail() nativa de PHP como fallback
            error_log("PHPMailer no está instalado. Usando función mail() nativa.");
            if (function_exists('browser_log')) {
                browser_log("⚠️ PHPMailer no está instalado. Ejecuta: php install_phpmailer.php", 'warning');
            }
        }
    }
}

// Usar las clases de PHPMailer si están disponibles
// Nota: use statements deben estar al inicio del archivo, pero las clases se cargan condicionalmente arriba

/**
 * Envía un correo electrónico usando PHPMailer o mail() como fallback
 * 
 * @param string $to Dirección de correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del correo (HTML)
 * @param string $altBody Versión de texto plano (opcional)
 * @return bool True si se envió correctamente, False en caso contrario
 */
function send_email($to, $subject, $body, $altBody = '') {
    // Las variables de configuración SMTP ya están disponibles globalmente desde config.php
    // No necesitamos cargar config.php de nuevo para evitar problemas de carga circular
    
    // Usar browser_log si está disponible, sino error_log normal
    if (function_exists('browser_log')) {
        browser_log("📧 [EMAIL] Iniciando envío de correo a: $to", 'info');
        browser_log("📧 [EMAIL] Asunto: $subject", 'info');
    }
    error_log("📧 [EMAIL] Iniciando envío de correo a: $to");
    error_log("📧 [EMAIL] Asunto: $subject");
    
    // Si PHPMailer está disponible, usarlo
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        if (function_exists('browser_log')) browser_log("📧 [EMAIL] Usando PHPMailer para enviar correo", 'info');
        error_log("📧 [EMAIL] Usando PHPMailer para enviar correo");
        $result = send_email_phpmailer($to, $subject, $body, $altBody);
    } else {
        if (function_exists('browser_log')) browser_log("📧 [EMAIL] PHPMailer no disponible, usando función mail() nativa", 'warning');
        error_log("📧 [EMAIL] PHPMailer no disponible, usando función mail() nativa");
        $result = send_email_native($to, $subject, $body, $altBody);
    }
    
    if ($result) {
        if (function_exists('browser_log')) browser_log("✅ [EMAIL] Correo enviado exitosamente a: $to", 'success');
        error_log("✅ [EMAIL] Correo enviado exitosamente a: $to");
    } else {
        if (function_exists('browser_log')) browser_log("❌ [EMAIL] Error al enviar correo a: $to", 'error');
        error_log("❌ [EMAIL] Error al enviar correo a: $to");
    }
    
    return $result;
}

/**
 * Envía correo usando PHPMailer con SMTP de Gmail
 */
function send_email_phpmailer($to, $subject, $body, $altBody = '') {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
    
    $password_status = !empty($SMTP_PASS) ? 'Sí (' . strlen($SMTP_PASS) . ' caracteres)' : 'NO - ERROR';
    
    if (function_exists('browser_log')) {
        browser_log("📧 [PHPMailer] Configurando SMTP...", 'info');
        browser_log("📧 [PHPMailer] Host: " . ($SMTP_HOST ?? 'smtp.gmail.com'), 'info');
        browser_log("📧 [PHPMailer] Puerto: " . ($SMTP_PORT ?? 587), 'info');
        browser_log("📧 [PHPMailer] Usuario: " . ($SMTP_USER ?? 'rc.elbosque.app@gmail.com'), 'info');
        browser_log("📧 [PHPMailer] Contraseña configurada: $password_status", !empty($SMTP_PASS) ? 'info' : 'error');
    }
    error_log("📧 [PHPMailer] Configurando SMTP...");
    error_log("📧 [PHPMailer] Host: " . ($SMTP_HOST ?? 'smtp.gmail.com'));
    error_log("📧 [PHPMailer] Puerto: " . ($SMTP_PORT ?? 587));
    error_log("📧 [PHPMailer] Usuario: " . ($SMTP_USER ?? 'rc.elbosque.app@gmail.com'));
    error_log("📧 [PHPMailer] Contraseña configurada: $password_status");
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $SMTP_HOST ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $SMTP_USER ?? 'rc.elbosque.app@gmail.com';
        $mail->Password = $SMTP_PASS ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $SMTP_PORT ?? 587;
        $mail->CharSet = 'UTF-8';
        
        // Habilitar debug (solo para logging)
        $mail->SMTPDebug = 0; // 0 = off, 1 = client, 2 = client and server
        $mail->Debugoutput = function($str, $level) {
            error_log("📧 [PHPMailer Debug] $str");
        };
        
        // Remitente
        $mail->setFrom(
            $SMTP_FROM_EMAIL ?? 'rc.elbosque.app@gmail.com',
            $SMTP_FROM_NAME ?? 'Rc El Bosque'
        );
        
        // Destinatario
        $mail->addAddress($to);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        if (function_exists('browser_log')) browser_log("📧 [PHPMailer] Intentando enviar correo...", 'info');
        error_log("📧 [PHPMailer] Intentando enviar correo...");
        
        // Enviar
        $result = $mail->send();
        
        if ($result) {
            if (function_exists('browser_log')) browser_log("✅ [PHPMailer] Correo enviado exitosamente", 'success');
            error_log("✅ [PHPMailer] Correo enviado exitosamente");
        } else {
            if (function_exists('browser_log')) browser_log("❌ [PHPMailer] send() retornó false", 'error');
            error_log("❌ [PHPMailer] send() retornó false");
        }
        
        return $result;
        
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        $error_info = $mail->ErrorInfo ?? 'N/A';
        if (function_exists('browser_log')) {
            browser_log("❌ [PHPMailer] Excepción al enviar correo: $error_msg", 'error');
            browser_log("❌ [PHPMailer] ErrorInfo: $error_info", 'error');
        }
        error_log("❌ [PHPMailer] Excepción al enviar correo: $error_msg");
        error_log("❌ [PHPMailer] ErrorInfo: $error_info");
        error_log("❌ [PHPMailer] Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Envía correo usando la función mail() nativa de PHP (fallback)
 */
function send_email_native($to, $subject, $body, $altBody = '') {
    global $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
    
    if (function_exists('browser_log')) {
        browser_log("📧 [mail()] Usando función mail() nativa de PHP", 'warning');
        browser_log("⚠️ [mail()] NOTA: mail() nativa en XAMPP local generalmente no funciona. Se recomienda instalar PHPMailer.", 'warning');
    }
    error_log("📧 [mail()] Usando función mail() nativa de PHP");
    error_log("⚠️ [mail()] NOTA: mail() nativa en XAMPP local generalmente no funciona. Se recomienda instalar PHPMailer.");
    
    $from_email = $SMTP_FROM_EMAIL ?? 'rc.elbosque.app@gmail.com';
    $from_name = $SMTP_FROM_NAME ?? 'Rc El Bosque';
    
    if (function_exists('browser_log')) {
        browser_log("📧 [mail()] De: $from_name <$from_email>", 'info');
        browser_log("📧 [mail()] Para: $to", 'info');
    }
    error_log("📧 [mail()] De: $from_name <$from_email>");
    error_log("📧 [mail()] Para: $to");
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if (function_exists('browser_log')) browser_log("📧 [mail()] Intentando enviar...", 'info');
    error_log("📧 [mail()] Intentando enviar...");
    
    // Limpiar cualquier error previo
    error_clear_last();
    
    $result = mail($to, $subject, $body, implode("\r\n", $headers));
    
    if ($result) {
        if (function_exists('browser_log')) browser_log("✅ [mail()] mail() retornó true", 'success');
        error_log("✅ [mail()] mail() retornó true");
    } else {
        $last_error = error_get_last();
        $error_details = $last_error ? print_r($last_error, true) : 'No hay información de error disponible';
        
        if (function_exists('browser_log')) {
            browser_log("❌ [mail()] mail() retornó false - Error al enviar correo", 'error');
            browser_log("❌ [mail()] Error: $error_details", 'error');
            browser_log("💡 [mail()] SOLUCIÓN: Instala PHPMailer ejecutando: composer install", 'warning');
        }
        error_log("❌ [mail()] mail() retornó false - Error al enviar correo");
        error_log("❌ [mail()] Último error PHP: $error_details");
        error_log("💡 [mail()] SOLUCIÓN: Instala PHPMailer ejecutando: composer install");
    }
    
    return $result;
}

/**
 * Envía notificación de postulación de animal
 * 
 * @param int $animal_id ID del animal postulado
 * @param int $user_id ID del usuario que postuló
 * @param object $pdo Conexión PDO a la base de datos
 * @return bool True si se envió correctamente
 */
function send_nomination_email($animal_id, $user_id, $pdo) {
    if (function_exists('browser_log')) {
        browser_log("📧 [NOMINATION] ========================================", 'info');
        browser_log("📧 [NOMINATION] Iniciando envío de correo de postulación", 'info');
        browser_log("📧 [NOMINATION] Animal ID: $animal_id", 'info');
        browser_log("📧 [NOMINATION] Usuario ID: $user_id", 'info');
    }
    error_log("📧 [NOMINATION] ========================================");
    error_log("📧 [NOMINATION] Iniciando envío de correo de postulación");
    error_log("📧 [NOMINATION] Animal ID: $animal_id");
    error_log("📧 [NOMINATION] Usuario ID: $user_id");
    
    try {
        // Obtener información del animal
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Obteniendo información del animal...", 'info');
        error_log("📧 [NOMINATION] Obteniendo información del animal...");
        
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as species_name, b.name as breed_name, f.name as farm_name
            FROM animals a
            LEFT JOIN species s ON a.species_id = s.id
            LEFT JOIN breeds b ON a.breed_id = b.id
            LEFT JOIN farms f ON a.farm_id = f.id
            WHERE a.id = ?
        ");
        
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Ejecutando consulta SQL para obtener animal...", 'info');
        error_log("📧 [NOMINATION] Ejecutando consulta SQL para obtener animal...");
        
        $stmt->execute([$animal_id]);
        $animal = $stmt->fetch();
        
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Consulta ejecutada, verificando resultado...", 'info');
        error_log("📧 [NOMINATION] Consulta ejecutada, verificando resultado...");
        
        if (!$animal) {
            if (function_exists('browser_log')) browser_log("❌ [NOMINATION] No se encontró el animal con ID: $animal_id", 'error');
            error_log("❌ [NOMINATION] No se encontró el animal con ID: $animal_id");
            return false;
        }
        
        $animal_tag = $animal['tag_code'] ?? 'N/A';
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Animal encontrado: $animal_tag", 'info');
        error_log("📧 [NOMINATION] Animal encontrado: $animal_tag");
        
        // Obtener información del usuario que postuló
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Obteniendo información del usuario que postuló...", 'info');
        error_log("📧 [NOMINATION] Obteniendo información del usuario que postuló...");
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $postulator = $stmt->fetch();
        
        if (!$postulator) {
            if (function_exists('browser_log')) browser_log("❌ [NOMINATION] No se encontró el usuario con ID: $user_id", 'error');
            error_log("❌ [NOMINATION] No se encontró el usuario con ID: $user_id");
            return false;
        }
        
        $postulator_name = $postulator['name'] ?? 'N/A';
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Usuario encontrado: $postulator_name", 'info');
        error_log("📧 [NOMINATION] Usuario encontrado: $postulator_name");
        
        // Obtener fotos del animal
        $stmt = $pdo->prepare("SELECT file_path FROM animal_photos WHERE animal_id = ? AND is_primary = 1 LIMIT 1");
        $stmt->execute([$animal_id]);
        $photo = $stmt->fetch();
        $photo_url = $photo ? '/uploads/' . basename($photo['file_path']) : null;
        
        // Construir el cuerpo del correo
        $subject = "Nueva Postulación de Animal - Rc El Bosque";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a4720; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .info-row { margin: 10px 0; padding: 10px; background: white; border-left: 3px solid #1a4720; }
                .label { font-weight: bold; color: #1a4720; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 10px 20px; background: #1a4720; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🐄 Nueva Postulación de Animal</h2>
                </div>
                <div class='content'>
                    <p>Se ha recibido una nueva postulación de animal para el catálogo:</p>
                    
                    <div class='info-row'>
                        <span class='label'>Código de Animal:</span> " . htmlspecialchars($animal['tag_code'] ?? 'N/A') . "
                    </div>
                    
                    " . ($animal['name'] ? "<div class='info-row'><span class='label'>Nombre:</span> " . htmlspecialchars($animal['name']) . "</div>" : "") . "
                    
                    <div class='info-row'>
                        <span class='label'>Especie:</span> " . htmlspecialchars($animal['species_name'] ?? 'N/A') . "
                    </div>
                    
                    " . ($animal['breed_name'] ? "<div class='info-row'><span class='label'>Raza:</span> " . htmlspecialchars($animal['breed_name']) . "</div>" : "") . "
                    
                    <div class='info-row'>
                        <span class='label'>Género:</span> " . htmlspecialchars($animal['gender'] ?? 'N/A') . "
                    </div>
                    
                    " . ($animal['weight'] ? "<div class='info-row'><span class='label'>Peso:</span> " . htmlspecialchars($animal['weight']) . " kg</div>" : "") . "
                    
                    " . ($animal['age_months'] ? "<div class='info-row'><span class='label'>Edad:</span> " . htmlspecialchars($animal['age_months']) . " meses</div>" : "") . "
                    
                    <div class='info-row'>
                        <span class='label'>Finca:</span> " . htmlspecialchars($animal['farm_name'] ?? 'N/A') . "
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Postulado por:</span> " . htmlspecialchars($postulator['name'] ?? 'N/A') . " (" . htmlspecialchars($postulator['email'] ?? 'N/A') . ")
                    </div>
                    
                    " . ($animal['description'] ? "<div class='info-row'><span class='label'>Descripción:</span> " . nl2br(htmlspecialchars($animal['description'])) . "</div>" : "") . "
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='https://rcelbosque.com/public/admin.php#nominations' class='button'>Revisar Postulación</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Este es un correo automático del sistema Rc El Bosque.</p>
                    <p>Por favor, no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Enviar correo
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Construyendo correo...", 'info');
        error_log("📧 [NOMINATION] Construyendo correo...");
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Asunto: $subject", 'info');
        error_log("📧 [NOMINATION] Asunto: $subject");
        $recipient = 'rc.elbosque.app@gmail.com';
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Destinatario: $recipient", 'info');
        error_log("📧 [NOMINATION] Destinatario: $recipient");
        
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] Llamando a send_email()...", 'info');
        error_log("📧 [NOMINATION] Llamando a send_email()...");
        $result = send_email($recipient, $subject, $body);
        
        if ($result) {
            if (function_exists('browser_log')) browser_log("✅ [NOMINATION] Correo de postulación enviado exitosamente", 'success');
            error_log("✅ [NOMINATION] Correo de postulación enviado exitosamente");
        } else {
            if (function_exists('browser_log')) browser_log("❌ [NOMINATION] Error al enviar correo de postulación", 'error');
            error_log("❌ [NOMINATION] Error al enviar correo de postulación");
        }
        
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] ========================================", 'info');
        error_log("📧 [NOMINATION] ========================================");
        return $result;
        
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        $error_trace = $e->getTraceAsString();
        if (function_exists('browser_log')) {
            browser_log("❌ [NOMINATION] Excepción capturada: $error_msg", 'error');
            browser_log("❌ [NOMINATION] Tipo: " . get_class($e), 'error');
            browser_log("❌ [NOMINATION] Archivo: " . $e->getFile() . " Línea: " . $e->getLine(), 'error');
        }
        error_log("❌ [NOMINATION] Excepción al enviar correo de postulación: $error_msg");
        error_log("❌ [NOMINATION] Tipo: " . get_class($e));
        error_log("❌ [NOMINATION] Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        error_log("❌ [NOMINATION] Stack trace: $error_trace");
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] ========================================", 'info');
        error_log("📧 [NOMINATION] ========================================");
        return false;
    } catch (Throwable $e) {
        $error_msg = $e->getMessage();
        $error_trace = $e->getTraceAsString();
        if (function_exists('browser_log')) {
            browser_log("❌ [NOMINATION] Error fatal capturado: $error_msg", 'error');
            browser_log("❌ [NOMINATION] Tipo: " . get_class($e), 'error');
            browser_log("❌ [NOMINATION] Archivo: " . $e->getFile() . " Línea: " . $e->getLine(), 'error');
        }
        error_log("❌ [NOMINATION] Error fatal al enviar correo de postulación: $error_msg");
        error_log("❌ [NOMINATION] Tipo: " . get_class($e));
        error_log("❌ [NOMINATION] Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        error_log("❌ [NOMINATION] Stack trace: $error_trace");
        if (function_exists('browser_log')) browser_log("📧 [NOMINATION] ========================================", 'info');
        error_log("📧 [NOMINATION] ========================================");
        return false;
    }
}

