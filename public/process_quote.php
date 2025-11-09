<?php
/**
 * Procesa las solicitudes de cotización del catálogo
 */

require_once __DIR__ . '/../app/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Validar campos requeridos
$required_fields = ['name', 'email', 'phone', 'type', 'item_id'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "El campo {$field} es requerido"]);
        exit;
    }
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$message = trim($_POST['message'] ?? '');
$type = $_POST['type']; // 'animal' o 'lot'
$item_id = intval($_POST['item_id']);

// Validar email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El email no es válido']);
    exit;
}

// Validar teléfono (básico)
if (strlen($phone) < 7) {
    echo json_encode(['success' => false, 'message' => 'El teléfono debe tener al menos 7 dígitos']);
    exit;
}

try {
    $pdo = get_pdo();
    
    // Obtener información del item (animal o lote)
    if ($type === 'animal') {
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as species_name, b.name as breed_name, f.name as farm_name
            FROM animals a
            LEFT JOIN species s ON s.id = a.species_id
            LEFT JOIN breeds b ON b.id = a.breed_id
            LEFT JOIN farms f ON f.id = a.farm_id
            WHERE a.id = ? AND a.in_cat = 1
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            throw new Exception('Animal no encontrado');
        }
        
        $item_name = $item['tag_code'] . ($item['name'] ? ' - ' . $item['name'] : '');
        $item_type_name = 'Animal';
        $item_details = "Especie: " . ($item['species_name'] ?? 'N/A') . "\n";
        $item_details .= "Raza: " . ($item['breed_name'] ?? 'N/A') . "\n";
        if ($item['weight']) $item_details .= "Peso: " . $item['weight'] . " kg\n";
        if ($item['age_months']) $item_details .= "Edad: " . $item['age_months'] . " meses\n";
        $item_details .= "Finca: " . ($item['farm_name'] ?? 'N/A');
        
    } else if ($type === 'lot') {
        $stmt = $pdo->prepare("
            SELECT l.*, f.name as farm_name, COUNT(la.animal_id) as animal_count
            FROM lots l
            LEFT JOIN farms f ON f.id = l.farm_id
            LEFT JOIN lot_animals la ON la.lot_id = l.id
            WHERE l.id = ? AND l.status = 'disponible'
            GROUP BY l.id
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            throw new Exception('Lote no encontrado');
        }
        
        $item_name = $item['name'];
        $item_type_name = 'Lote';
        $item_details = "Descripción: " . ($item['description'] ?? 'N/A') . "\n";
        $item_details .= "Cantidad de animales: " . ($item['animal_count'] ?? 0) . "\n";
        $item_details .= "Precio total: $" . number_format($item['total_price'] ?? 0, 2) . "\n";
        $item_details .= "Finca: " . ($item['farm_name'] ?? 'N/A');
        
    } else {
        throw new Exception('Tipo de item inválido');
    }
    
    // Crear tabla de cotizaciones si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_type ENUM('animal', 'lot') NOT NULL,
            item_id INT NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            customer_email VARCHAR(120) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_message TEXT,
            status ENUM('pendiente', 'en_proceso', 'respondida') NOT NULL DEFAULT 'pendiente',
            created_by INT NULL,
            updated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    
    // Guardar la cotización en la base de datos
    $user_id = is_logged_in() ? current_user()['id'] : null;
    $stmt = $pdo->prepare("
        INSERT INTO quotes (item_type, item_id, customer_name, customer_email, customer_phone, customer_message, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $type,
        $item_id,
        $name,
        $email,
        $phone,
        $message ?: null,
        $user_id
    ]);
    $quote_id = $pdo->lastInsertId();
    
    // Enviar correo de confirmación al usuario
    $user_subject = "Confirmación de Solicitud de Cotización - Rc El Bosque";
    $user_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a4720; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 3px solid #1a4720; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✅ Solicitud de Cotización Recibida</h2>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>" . htmlspecialchars($name) . "</strong>,</p>
                
                <p>Hemos recibido tu solicitud de cotización para el siguiente item:</p>
                
                <div class='info-box'>
                    <strong>Tipo:</strong> " . htmlspecialchars($item_type_name) . "<br>
                    <strong>Item:</strong> " . htmlspecialchars($item_name) . "<br>
                    <strong>ID:</strong> #" . htmlspecialchars($item_id) . "
                </div>
                
                <p>Nuestro equipo se pondrá en contacto contigo a la brevedad posible en:</p>
                <ul>
                    <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
                    <li><strong>Teléfono:</strong> " . htmlspecialchars($phone) . "</li>
                </ul>
                
                " . ($message ? "<p><strong>Tu mensaje:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>" : "") . "
                
                <p>Gracias por tu interés en nuestros productos.</p>
            </div>
            <div class='footer'>
                <p>Este es un correo automático del sistema Rc El Bosque.</p>
                <p>Por favor, no respondas a este correo.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Enviar correo informativo a rc.elbosque.app@gmail.com
    $admin_subject = "Nueva Solicitud de Cotización - " . $item_type_name . " #" . $item_id;
    $admin_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a4720; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 3px solid #1a4720; }
            .contact-info { background: #e8f5e9; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .button { display: inline-block; padding: 10px 20px; background: #1a4720; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📋 Nueva Solicitud de Cotización</h2>
            </div>
            <div class='content'>
                <p>Se ha recibido una nueva solicitud de cotización:</p>
                
                <div class='info-box'>
                    <strong>Tipo:</strong> " . htmlspecialchars($item_type_name) . "<br>
                    <strong>Item:</strong> " . htmlspecialchars($item_name) . "<br>
                    <strong>ID:</strong> #" . htmlspecialchars($item_id) . "<br><br>
                    <strong>Detalles:</strong><br>
                    <pre style='white-space: pre-wrap; font-family: Arial;'>" . htmlspecialchars($item_details) . "</pre>
                </div>
                
                <div class='contact-info'>
                    <h3>Información del Cliente:</h3>
                    <p><strong>Nombre:</strong> " . htmlspecialchars($name) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                    <p><strong>Teléfono:</strong> " . htmlspecialchars($phone) . "</p>
                    " . ($message ? "<p><strong>Mensaje:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>" : "") . "
                </div>
                
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='https://rcelbosque.com/public/admin.php' class='button'>Ver en Panel de Administración</a>
                </div>
            </div>
            <div class='footer'>
                <p>Este es un correo automático del sistema Rc El Bosque.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Cargar funciones de correo
    require_once __DIR__ . '/../app/email.php';
    
    // Enviar ambos correos
    $user_email_sent = send_email($email, $user_subject, $user_body);
    $admin_email_sent = send_email('rc.elbosque.app@gmail.com', $admin_subject, $admin_body);
    
    if ($user_email_sent && $admin_email_sent) {
        echo json_encode([
            'success' => true,
            'message' => '¡Gracias por contactar con nosotros! Nos pondremos en contacto para cotizar contigo lo más pronto posible.'
        ]);
    } else if ($admin_email_sent) {
        // Si solo se envió el correo al admin, aún es exitoso
        echo json_encode([
            'success' => true,
            'message' => '¡Gracias por contactar con nosotros! Nos pondremos en contacto para cotizar contigo lo más pronto posible.'
        ]);
    } else {
        throw new Exception('Error al enviar los correos');
    }
    
} catch (Exception $e) {
    error_log("Error en process_quote.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la solicitud. Por favor, intenta nuevamente.'
    ]);
}

