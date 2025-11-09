<?php
require_once '../app/config.php';
require_once '../app/logger.php';
require_login(); // Ensure user is logged in
require_role(['admin_general', 'admin_finca', 'veterinario']); // Allow admin_general, admin_finca, or veterinario

// Database connection
$pdo = get_pdo();

// Obtener logs para mostrar en consola del navegador
$browser_logs = get_browser_logs();

// Ensure dynamic lot types support
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS lot_types (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE)");
} catch (Exception $e) {}
// Try to migrate lots.lot_type to VARCHAR if it was ENUM
try { $pdo->exec("ALTER TABLE lots MODIFY lot_type VARCHAR(50) NOT NULL"); } catch (Exception $e) {}
// Seed defaults if empty
try {
  $cnt = $pdo->query("SELECT COUNT(*) AS c FROM lot_types")->fetch();
  if ((int)($cnt['c'] ?? 0) === 0) {
    $stmt = $pdo->prepare("INSERT INTO lot_types(name) VALUES (?), (?), (?), (?)");
    $stmt->execute(['venta','reproduccion','engorde','leche']);
  }
} catch (Exception $e) {}
// Load lot types
$lotTypes = [];
try { $lotTypes = $pdo->query("SELECT name FROM lot_types ORDER BY name")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { $lotTypes = ['venta','reproduccion','engorde','leche']; }

// Fetch data
$animals = $pdo->query("
  SELECT a.*, s.name as species_name, b.name as breed_name, f.name as farm_name
  FROM animals a
  LEFT JOIN species s ON s.id = a.species_id
  LEFT JOIN breeds b ON b.id = a.breed_id
  LEFT JOIN farms f ON f.id = a.farm_id
  ORDER BY a.created_at DESC
")->fetchAll();
// Obtener usuarios según el rol
if (is_admin_finca()) {
  // Admin finca solo ve veterinarios
  $users = $pdo->query("SELECT * FROM users WHERE role = 'veterinario' ORDER BY created_at DESC")->fetchAll();
} else {
  // Admin general ve todos los usuarios
  $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
}
$farms = $pdo->query("SELECT * FROM farms ORDER BY name")->fetchAll();
$lots = $pdo->query("
  SELECT l.*, f.name as farm_name, u.name as created_by_name,
         COUNT(la.animal_id) as actual_animal_count
  FROM lots l
  LEFT JOIN farms f ON f.id = l.farm_id
  LEFT JOIN users u ON u.id = l.created_by
  LEFT JOIN lot_animals la ON la.lot_id = l.id
  GROUP BY l.id
  ORDER BY l.created_at DESC
")->fetchAll();
$species = $pdo->query("SELECT * FROM species ORDER BY name")->fetchAll();
$breeds = $pdo->query("SELECT * FROM breeds ORDER BY name")->fetchAll();

// Public catalog datasets
$publishedAnimals = array_values(array_filter($animals, function($a){ return !empty($a['in_cat']); }));
$publishedLots = $pdo->query("
  SELECT l.*, f.name as farm_name,
         COUNT(la.animal_id) as animal_count
  FROM lots l
  LEFT JOIN farms f ON f.id = l.farm_id
  LEFT JOIN lot_animals la ON la.lot_id = l.id
  WHERE l.status = 'disponible'
    AND EXISTS (
      SELECT 1 FROM nominations n
      WHERE n.item_type = 'lot'
        AND n.item_id = l.id
        AND n.status = 'approved'
    )
  GROUP BY l.id
  ORDER BY l.created_at DESC
")->fetchAll();

// Lot publication status helpers
$approvedLotIds = $pdo->query("SELECT item_id FROM nominations WHERE item_type = 'lot' AND status = 'approved'")->fetchAll(PDO::FETCH_COLUMN);
$pendingLotIds = $pdo->query("SELECT item_id FROM nominations WHERE item_type = 'lot' AND status = 'pending'")->fetchAll(PDO::FETCH_COLUMN);

// Edit lot context
$editLotId = isset($_GET['edit_lot']) ? (int)$_GET['edit_lot'] : 0;
$editLot = null;
$editLotAnimals = [];
if ($editLotId > 0) {
  $stmt = $pdo->prepare("SELECT l.*, f.name as farm_name FROM lots l LEFT JOIN farms f ON f.id = l.farm_id WHERE l.id = ?");
  $stmt->execute([$editLotId]);
  $editLot = $stmt->fetch();
  if ($editLot) {
    $stmt = $pdo->prepare("SELECT a.* FROM lot_animals la JOIN animals a ON a.id = la.animal_id WHERE la.lot_id = ? ORDER BY a.name");
    $stmt->execute([$editLotId]);
    $editLotAnimals = $stmt->fetchAll();
  }
}

// Obtener postulaciones
if (is_admin_general()) {
  // Admin general ve todas las postulaciones
  $nominations = $pdo->query("
    SELECT n.*, 
           u.name as proposed_by_name, 
           f.name as farm_name,
           a.name as animal_name,
           l.name as lot_name
    FROM nominations n
    LEFT JOIN users u ON u.id = n.proposed_by
    LEFT JOIN farms f ON f.id = n.farm_id
    LEFT JOIN animals a ON (n.item_type = 'animal' AND a.id = n.item_id)
    LEFT JOIN lots l ON (n.item_type = 'lot' AND l.id = n.item_id)
    WHERE n.status = 'pending'
    ORDER BY n.created_at DESC
  ")->fetchAll();
} else if (is_admin_finca()) {
  // Admin finca ve solo sus postulaciones
  $stmt = $pdo->prepare("
    SELECT n.*, 
           u.name as proposed_by_name, 
           f.name as farm_name,
           a.name as animal_name,
           l.name as lot_name
    FROM nominations n
    LEFT JOIN users u ON u.id = n.proposed_by
    LEFT JOIN farms f ON f.id = n.farm_id
    LEFT JOIN animals a ON (n.item_type = 'animal' AND a.id = n.item_id)
    LEFT JOIN lots l ON (n.item_type = 'lot' AND l.id = n.item_id)
    WHERE n.proposed_by = ?
    ORDER BY n.created_at DESC
  ");
  $stmt->execute([$_SESSION['user']['id']]);
  $nominations = $stmt->fetchAll();
} else {
  $nominations = [];
}

// Obtener cotizaciones (solo para admin_general)
$quotes = [];
if (is_admin_general()) {
  try {
    // Crear tabla si no existe
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
    
    $quotes = $pdo->query("
      SELECT q.*,
             u1.name as created_by_name,
             u2.name as updated_by_name,
             a.tag_code as animal_tag_code,
             a.name as animal_name,
             l.name as lot_name
      FROM quotes q
      LEFT JOIN users u1 ON u1.id = q.created_by
      LEFT JOIN users u2 ON u2.id = q.updated_by
      LEFT JOIN animals a ON (q.item_type = 'animal' AND a.id = q.item_id)
      LEFT JOIN lots l ON (q.item_type = 'lot' AND l.id = q.item_id)
      ORDER BY q.created_at DESC
    ")->fetchAll();
  } catch (Exception $e) {
    error_log("Error al obtener cotizaciones: " . $e->getMessage());
    $quotes = [];
  }
}

// Obtener imágenes del carrusel (solo para admin_general)
$carousel_images = [];
if (is_admin_general()) {
  try {
    // Crear tabla si no existe
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS carousel_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        title VARCHAR(200),
        description TEXT,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
      ) ENGINE=InnoDB
    ");
    
    $carousel_images = $pdo->query("
      SELECT c.*, u.name as created_by_name
      FROM carousel_images c
      LEFT JOIN users u ON u.id = c.created_by
      ORDER BY c.sort_order ASC, c.created_at DESC
    ")->fetchAll();
  } catch (Exception $e) {
    error_log("Error al obtener imágenes del carrusel: " . $e->getMessage());
    $carousel_images = [];
  }
}

// Get current user
$user = current_user();

// Flash messages
$flash = [
  'ok' => $_SESSION['flash_ok'] ?? null,
  'err' => $_SESSION['flash_err'] ?? null
];
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
      case 'add_animal':
        // Validar que tag_code sea obligatorio
        if (empty($_POST['tag_code']) || trim($_POST['tag_code']) === '') {
          $_SESSION['flash_err'] = 'El código de animal es obligatorio.';
          break;
        }
        
        // Validar peso si se proporciona
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        if ($weight !== null && ($weight < 20 || $weight > 1000)) {
          $_SESSION['flash_err'] = 'El peso debe estar entre 20 kg y 1000 kg.';
          break;
        }
        
        // Obtener y limpiar el tag_code
        $tag_code = trim($_POST['tag_code']);
        
        // Verificar que el tag_code no exista
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM animals WHERE tag_code = ?");
        $check_stmt->execute([$tag_code]);
        $exists = $check_stmt->fetch();
        
        if ($exists['count'] > 0) {
          $_SESSION['flash_err'] = 'El código de animal ya existe. Por favor, usa un código diferente.';
          break;
        }
        
        // Validar que la fecha de nacimiento no sea futura
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        if ($birth_date && $birth_date > date('Y-m-d')) {
          $_SESSION['flash_err'] = 'La fecha de nacimiento no puede ser posterior a la fecha actual.';
          break;
        }
        
        $stmt = $pdo->prepare("INSERT INTO animals (tag_code, name, species_id, breed_id, birth_date, gender, weight, color, farm_id, in_cat, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
          $tag_code,
          !empty($_POST['name']) ? trim($_POST['name']) : null,
          !empty($_POST['species_id']) ? $_POST['species_id'] : null,
          !empty($_POST['breed_id']) ? $_POST['breed_id'] : null,
          $birth_date,
          !empty($_POST['gender']) ? $_POST['gender'] : 'indefinido',
          $weight,
          !empty($_POST['color']) ? $_POST['color'] : null,
          !empty($_POST['farm_id']) ? $_POST['farm_id'] : null,
          isset($_POST['in_cat']) ? 1 : 0,
          !empty($_POST['description']) ? $_POST['description'] : null
        ]);
        
        $animal_id = $pdo->lastInsertId();
        
        // Manejar fotos si se subieron
        if (!empty($_FILES['photos']['name'][0])) {
          $uploaded_files = [];
          
          // Crear directorio si no existe
          $uploadDir = __DIR__ . '/uploads/animals/';
          if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }
          
          $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
          $maxFileSize = 5 * 1024 * 1024; // 5MB
          $maxPhotos = 5;
          
          $fileCount = count($_FILES['photos']['name']);
          if ($fileCount > $maxPhotos) {
            $_SESSION['flash_err'] = "Máximo {$maxPhotos} fotos permitidas";
            break;
          }
          
          foreach ($_FILES['photos']['name'] as $key => $filename) {
            if (empty($filename)) continue;
            
            $tmpName = $_FILES['photos']['tmp_name'][$key];
            $fileSize = $_FILES['photos']['size'][$key];
            $fileType = $_FILES['photos']['type'][$key];
            
            // Validar tipo de archivo
            if (!in_array($fileType, $allowedTypes)) {
              $_SESSION['flash_err'] = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
              break 2;
            }
            
            // Validar tamaño
            if ($fileSize > $maxFileSize) {
              $_SESSION['flash_err'] = 'El archivo es demasiado grande (máximo 5MB)';
              break 2;
            }
            
            // Generar nombre único
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $newFilename = 'animal_' . $animal_id . '_' . time() . '_' . $key . '.' . $extension;
            $filePath = $uploadDir . $newFilename;
            
            // Mover archivo
            if (move_uploaded_file($tmpName, $filePath)) {
              $uploaded_files[] = [
                'filename' => $newFilename,
                'original_name' => $filename,
                'file_path' => 'uploads/animals/' . $newFilename,
                'file_size' => $fileSize,
                'mime_type' => $fileType,
                'sort_order' => $key
              ];
            }
          }
          
          // Guardar información en base de datos
          if (!empty($uploaded_files)) {
            $stmt = $pdo->prepare("INSERT INTO animal_photos (animal_id, filename, original_name, file_path, file_size, mime_type, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($uploaded_files as $index => $file) {
              $stmt->execute([
                $animal_id,
                $file['filename'],
                $file['original_name'],
                $file['file_path'],
                $file['file_size'],
                $file['mime_type'],
                $file['sort_order']
              ]);
              
              // Marcar la primera foto como principal
              if ($index === 0) {
                $photoId = $pdo->lastInsertId();
                $pdo->prepare("UPDATE animal_photos SET is_primary = 1 WHERE id = ?")->execute([$photoId]);
              }
            }
          }
        }
        
        // Si admin_finca marcó la casilla de postular, crear la postulación
        if (is_admin_finca() && isset($_POST['in_cat']) && $_POST['in_cat']) {
          $stmt = $pdo->prepare("SELECT farm_id FROM animals WHERE id = ?");
          $stmt->execute([$animal_id]);
          $animal = $stmt->fetch();
          
          $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status) VALUES ('animal', ?, ?, ?, 'pending')");
          $stmt->execute([$animal_id, $_SESSION['user']['id'], $animal['farm_id'] ?? null]);
          
          // Enviar correo de notificación
          if (function_exists('send_nomination_email')) {
            try {
              $email_sent = send_nomination_email($animal_id, $_SESSION['user']['id'], $pdo);
              if (!$email_sent) {
                error_log("No se pudo enviar el correo de postulación para el animal ID: $animal_id");
              }
            } catch (Exception $e) {
              error_log("Error al enviar correo de postulación (animal ID: $animal_id): " . $e->getMessage());
              error_log("Stack trace: " . $e->getTraceAsString());
            } catch (Throwable $e) {
              error_log("Error fatal al enviar correo de postulación (animal ID: $animal_id): " . $e->getMessage());
              error_log("Stack trace: " . $e->getTraceAsString());
            }
          } else {
            error_log("ERROR: La función send_nomination_email no está disponible. Verificar que app/email.php esté cargado correctamente.");
          }
          
          $_SESSION['flash_ok'] = 'Animal registrado exitosamente' . (!empty($uploaded_files) ? ' con ' . count($uploaded_files) . ' foto(s)' : '') . ' y postulado para el catálogo';
        } else {
          $_SESSION['flash_ok'] = 'Animal registrado exitosamente' . (!empty($uploaded_files) ? ' con ' . count($uploaded_files) . ' foto(s)' : '');
        }
        break;
        
      case 'edit_animal':
        // Validar que tag_code sea obligatorio
        if (empty($_POST['tag_code']) || trim($_POST['tag_code']) === '') {
          $_SESSION['flash_err'] = 'El código de animal es obligatorio.';
          break;
        }
        
        // Validar peso si se proporciona
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        if ($weight !== null && ($weight < 20 || $weight > 1000)) {
          $_SESSION['flash_err'] = 'El peso debe estar entre 20 kg y 1000 kg.';
          break;
        }
        
        // Verificar que el tag_code no esté en uso por otro animal
        $tag_code = trim($_POST['tag_code']);
        $check_stmt = $pdo->prepare("SELECT id FROM animals WHERE tag_code = ? AND id != ?");
        $check_stmt->execute([$tag_code, $_POST['animal_id']]);
        $exists = $check_stmt->fetch();
        
        if ($exists) {
          $_SESSION['flash_err'] = 'El código de animal ya está en uso por otro animal. Por favor, usa un código diferente.';
          break;
        }
        
        // Validar que la fecha de nacimiento no sea futura
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        if ($birth_date && $birth_date > date('Y-m-d')) {
          $_SESSION['flash_err'] = 'La fecha de nacimiento no puede ser posterior a la fecha actual.';
          break;
        }
        
        $stmt = $pdo->prepare("UPDATE animals SET tag_code=?, name=?, species_id=?, breed_id=?, birth_date=?, gender=?, weight=?, color=?, farm_id=?, in_cat=?, description=? WHERE id=?");
        $stmt->execute([
          $tag_code,
          !empty($_POST['name']) ? trim($_POST['name']) : null,
          !empty($_POST['species_id']) ? $_POST['species_id'] : null,
          !empty($_POST['breed_id']) ? $_POST['breed_id'] : null,
          $birth_date,
          !empty($_POST['gender']) ? $_POST['gender'] : 'indefinido',
          $weight,
          !empty($_POST['color']) ? $_POST['color'] : null,
          !empty($_POST['farm_id']) ? $_POST['farm_id'] : null,
          isset($_POST['in_cat']) ? 1 : 0,
          !empty($_POST['description']) ? $_POST['description'] : null,
          $_POST['animal_id']
        ]);
        
        $animal_id = $_POST['animal_id'];
        
        // Manejar fotos si se subieron durante la edición
        if (!empty($_FILES['photos']['name'][0])) {
          $uploaded_files = [];
          
          // Crear directorio si no existe
          $uploadDir = __DIR__ . '/uploads/animals/';
          if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
          }
          
          $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
          $maxFileSize = 5 * 1024 * 1024; // 5MB
          $maxPhotos = 5;
          
          // Contar fotos existentes
          $existingPhotos = $pdo->prepare("SELECT COUNT(*) as count FROM animal_photos WHERE animal_id = ?");
          $existingPhotos->execute([$animal_id]);
          $existingCount = $existingPhotos->fetch()['count'];
          
          $fileCount = count($_FILES['photos']['name']);
          if (($existingCount + $fileCount) > $maxPhotos) {
            $_SESSION['flash_err'] = "Máximo {$maxPhotos} fotos permitidas. Ya tienes {$existingCount} fotos.";
            break;
          }
          
          foreach ($_FILES['photos']['name'] as $key => $filename) {
            if (empty($filename)) continue;
            
            $tmpName = $_FILES['photos']['tmp_name'][$key];
            $fileSize = $_FILES['photos']['size'][$key];
            $fileType = $_FILES['photos']['type'][$key];
            
            // Validar tipo de archivo
            if (!in_array($fileType, $allowedTypes)) {
              $_SESSION['flash_err'] = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
              break 2;
            }
            
            // Validar tamaño
            if ($fileSize > $maxFileSize) {
              $_SESSION['flash_err'] = 'El archivo es demasiado grande (máximo 5MB)';
              break 2;
            }
            
            // Generar nombre único
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $newFilename = 'animal_' . $animal_id . '_' . time() . '_' . $key . '.' . $extension;
            $filePath = $uploadDir . $newFilename;
            
            // Mover archivo
            if (move_uploaded_file($tmpName, $filePath)) {
              $uploaded_files[] = [
                'filename' => $newFilename,
                'original_name' => $filename,
                'file_path' => 'uploads/animals/' . $newFilename,
                'file_size' => $fileSize,
                'mime_type' => $fileType,
                'sort_order' => $existingCount + $key
              ];
            }
          }
          
          // Guardar información en base de datos
          if (!empty($uploaded_files)) {
            $stmt = $pdo->prepare("INSERT INTO animal_photos (animal_id, filename, original_name, file_path, file_size, mime_type, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($uploaded_files as $file) {
              $stmt->execute([
                $animal_id,
                $file['filename'],
                $file['original_name'],
                $file['file_path'],
                $file['file_size'],
                $file['mime_type'],
                $file['sort_order']
              ]);
            }
          }
        }
        
        $_SESSION['flash_ok'] = 'Animal actualizado exitosamente' . (!empty($uploaded_files) ? ' con ' . count($uploaded_files) . ' foto(s) adicionales' : '');
        break;
        
      case 'delete_animal':
        $stmt = $pdo->prepare("DELETE FROM animals WHERE id = ?");
        $stmt->execute([$_POST['animal_id']]);
        $_SESSION['flash_ok'] = 'Animal eliminado exitosamente';
        break;
        
        
      case 'add_user':
        // Si es admin_finca, solo puede crear veterinarios
        $role = $_POST['role'];
        if (is_admin_finca() && $role !== 'veterinario') {
          $_SESSION['flash_err'] = 'No tienes permiso para crear usuarios con ese rol';
          break;
        }
        
        // Validar y normalizar farm_id
        $farm_id = !empty($_POST['farm_id']) && trim($_POST['farm_id']) !== '' ? intval($_POST['farm_id']) : null;
        
        // Si se proporciona un farm_id, verificar que existe
        if ($farm_id !== null) {
          $check_stmt = $pdo->prepare("SELECT id FROM farms WHERE id = ?");
          $check_stmt->execute([$farm_id]);
          if (!$check_stmt->fetch()) {
            $_SESSION['flash_err'] = 'La finca seleccionada no existe';
            break;
          }
        }
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, farm_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
          $_POST['name'],
          $_POST['email'],
          password_hash($_POST['password'], PASSWORD_DEFAULT),
          $role,
          $farm_id
        ]);
        $_SESSION['flash_ok'] = 'Usuario creado exitosamente';
        break;
        
      case 'edit_user':
        // Validar y normalizar farm_id
        $farm_id = !empty($_POST['farm_id']) && trim($_POST['farm_id']) !== '' ? intval($_POST['farm_id']) : null;
        
        // Si se proporciona un farm_id, verificar que existe
        if ($farm_id !== null) {
          $check_stmt = $pdo->prepare("SELECT id FROM farms WHERE id = ?");
          $check_stmt->execute([$farm_id]);
          if (!$check_stmt->fetch()) {
            $_SESSION['flash_err'] = 'La finca seleccionada no existe';
            break;
          }
        }
        
        if (!empty($_POST['password'])) {
          // Si hay nueva contraseña, actualizar también el password
          $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password_hash=?, role=?, farm_id=? WHERE id=?");
          $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['role'],
            $farm_id,
            $_POST['user_id']
          ]);
        } else {
          // Si no hay nueva contraseña, mantener la actual
          $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, farm_id=? WHERE id=?");
          $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['role'],
            $farm_id,
            $_POST['user_id']
          ]);
        }
        
        $_SESSION['flash_ok'] = 'Usuario actualizado exitosamente';
        break;
        
      case 'delete_user':
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_POST['user_id']]);
        $_SESSION['flash_ok'] = 'Usuario eliminado exitosamente';
        break;
        
      case 'add_farm':
        if (!is_admin_general()) { $_SESSION['flash_err'] = 'Permiso denegado'; break; }
        $stmt = $pdo->prepare("INSERT INTO farms (name, location) VALUES (?, ?)");
        $stmt->execute([
          $_POST['name'],
          !empty($_POST['location']) ? $_POST['location'] : null
        ]);
        $_SESSION['flash_ok'] = 'Finca creada exitosamente';
        break;
        
      case 'edit_farm':
        if (!is_admin_general()) { $_SESSION['flash_err'] = 'Permiso denegado'; break; }
        $stmt = $pdo->prepare("UPDATE farms SET name=?, location=? WHERE id=?");
        $stmt->execute([
          $_POST['name'],
          !empty($_POST['location']) ? $_POST['location'] : null,
          $_POST['farm_id']
        ]);
        $_SESSION['flash_ok'] = 'Finca actualizada exitosamente';
        break;
        
      case 'delete_farm':
        if (!is_admin_general()) { $_SESSION['flash_err'] = 'Permiso denegado'; break; }
        
        // Verificar si la finca tiene registros asociados
        $checkStmt = $pdo->prepare("SELECT 
          (SELECT COUNT(*) FROM animals WHERE farm_id = ?) as animal_count,
          (SELECT COUNT(*) FROM users WHERE farm_id = ?) as user_count,
          (SELECT COUNT(*) FROM lots WHERE farm_id = ?) as lot_count");
        $checkStmt->execute([$_POST['farm_id'], $_POST['farm_id'], $_POST['farm_id']]);
        $counts = $checkStmt->fetch();
        
        $totalAssociations = $counts['animal_count'] + $counts['user_count'] + $counts['lot_count'];
        
        // Eliminar la finca (las claves foráneas con ON DELETE SET NULL se encargarán de desasociar)
        try {
          $stmt = $pdo->prepare("DELETE FROM farms WHERE id = ?");
          $stmt->execute([$_POST['farm_id']]);
          
          if ($totalAssociations > 0) {
            $_SESSION['flash_ok'] = "Finca eliminada exitosamente. Se desasociaron $totalAssociations registro(s) (animales, usuarios, lotes) automáticamente.";
          } else {
            $_SESSION['flash_ok'] = 'Finca eliminada exitosamente.';
          }
        } catch (PDOException $e) {
          // Si aún hay error de clave foránea, necesitamos actualizar las claves foráneas en la BD
          $_SESSION['flash_err'] = 'Error al eliminar la finca. Es posible que necesites actualizar las claves foráneas en la base de datos. Error: ' . htmlspecialchars($e->getMessage());
        }
        break;

      case 'add_lot_type':
        if (!is_admin_general()) { $_SESSION['flash_err'] = 'Permiso denegado'; break; }
        $name = trim(strtolower($_POST['name'] ?? ''));
        if ($name === '') { $_SESSION['flash_err'] = 'Nombre de tipo requerido'; break; }
        $stmt = $pdo->prepare("INSERT IGNORE INTO lot_types(name) VALUES (?)");
        $stmt->execute([$name]);
        $_SESSION['flash_ok'] = 'Tipo de lote agregado';
        break;

      case 'delete_lot_type':
        if (!is_admin_general()) { $_SESSION['flash_err'] = 'Permiso denegado'; break; }
        $stmt = $pdo->prepare("DELETE FROM lot_types WHERE name = ?");
        $stmt->execute([$_POST['name']]);
        $_SESSION[ 'flash_ok'] = 'Tipo de lote eliminado';
        break;
        
      case 'toggle_catalog_animal':
        // Si es admin_finca, crear postulación en lugar de agregar directamente
        if (is_admin_finca()) {
          $animal_id = $_POST['animal_id'];
          $in_cat = $_POST['in_cat'];
          
          // Verificar si ya existe una postulación pendiente
          $stmt = $pdo->prepare("SELECT id, status FROM nominations WHERE item_type = 'animal' AND item_id = ?");
          $stmt->execute([$animal_id]);
          $existing = $stmt->fetch();
          
          if ($in_cat) {
            // Postular el animal
            if ($existing) {
              if ($existing['status'] !== 'pending') {
                // Actualizar postulación rechazada a pendiente
                $stmt = $pdo->prepare("UPDATE nominations SET status = 'pending', proposed_by = ?, notes = 'Republicación' WHERE id = ?");
                $stmt->execute([$_SESSION['user']['id'], $existing['id']]);
                $_SESSION['flash_ok'] = "Animal republicado para aprobación";
              } else {
                $_SESSION['flash_err'] = "Ya existe una postulación pendiente para este animal";
              }
            } else {
              // Crear nueva postulación
              $stmt = $pdo->prepare("SELECT farm_id FROM animals WHERE id = ?");
              $stmt->execute([$animal_id]);
              $animal = $stmt->fetch();
              
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status) VALUES ('animal', ?, ?, ?, 'pending')");
              $stmt->execute([$animal_id, $_SESSION['user']['id'], $animal['farm_id'] ?? null]);
              
              // Enviar correo de notificación
              try {
                send_nomination_email($animal_id, $_SESSION['user']['id'], $pdo);
              } catch (Exception $e) {
                error_log("Error al enviar correo de postulación: " . $e->getMessage());
                // No interrumpir el flujo si falla el correo
              }
              
              $_SESSION['flash_ok'] = "Animal postulado para aprobación";
            }
          } else {
            // Quitar del catálogo (solo admin_general puede hacer esto directamente)
            $_SESSION['flash_err'] = "No tienes permisos para quitar animales del catálogo";
          }
        } else if (is_admin_general()) {
          // Admin general puede agregar/quitar directamente
          $stmt = $pdo->prepare("UPDATE animals SET in_cat = ? WHERE id = ?");
          $stmt->execute([$_POST['in_cat'], $_POST['animal_id']]);
          $action = $_POST['in_cat'] ? 'agregado al' : 'quitado del';
          $_SESSION['flash_ok'] = "Animal {$action} catálogo exitosamente";
        }
        break;
        
      case 'bulk_toggle_animals':
        $animal_ids = $_POST['animal_ids'] ?? [];
        $visibility = $_POST['visibility'] ?? 0;
        
        if (!empty($animal_ids)) {
          if (is_admin_general()) {
            // Admin general puede actualizar directamente
            $placeholders = str_repeat('?,', count($animal_ids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE animals SET in_cat = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$visibility], $animal_ids));
            
            $action = $visibility ? 'mostrados' : 'ocultados';
            $count = count($animal_ids);
            $_SESSION['flash_ok'] = "{$count} animales {$action} en el catálogo exitosamente";
          } else if (is_admin_finca()) {
            // Admin finca debe postular
            foreach ($animal_ids as $animal_id) {
              $stmt = $pdo->prepare("SELECT farm_id FROM animals WHERE id = ?");
              $stmt->execute([$animal_id]);
              $animal = $stmt->fetch();
              
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status) VALUES ('animal', ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending'");
              $stmt->execute([$animal_id, $_SESSION['user']['id'], $animal['farm_id'] ?? null]);
              
              // Enviar correo de notificación (solo para la primera postulación para evitar spam)
              if ($animal_id === $animal_ids[0]) {
                try {
                  send_nomination_email($animal_id, $_SESSION['user']['id'], $pdo);
                } catch (Exception $e) {
                  error_log("Error al enviar correo de postulación: " . $e->getMessage());
                  // No interrumpir el flujo si falla el correo
                }
              }
            }
            $count = count($animal_ids);
            $_SESSION['flash_ok'] = "{$count} animales postulados para aprobación";
          }
        }
        break;
        
      case 'approve_nomination':
        $nomination_id = $_POST['nomination_id'];
        
        // Obtener información de la postulación
        $stmt = $pdo->prepare("SELECT * FROM nominations WHERE id = ?");
        $stmt->execute([$nomination_id]);
        $nomination = $stmt->fetch();
        
        if ($nomination && $nomination['status'] === 'pending') {
          // Actualizar la postulación a aprobada
          $stmt = $pdo->prepare("UPDATE nominations SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
          $stmt->execute([$_SESSION['user']['id'], $nomination_id]);
          
          // Agregar el item al catálogo
          if ($nomination['item_type'] === 'animal') {
            $stmt = $pdo->prepare("UPDATE animals SET in_cat = 1 WHERE id = ?");
            $stmt->execute([$nomination['item_id']]);
            $_SESSION['flash_ok'] = "Animal aprobado y agregado al catálogo";
          } else if ($nomination['item_type'] === 'lot') {
            // Para lotes, la visibilidad del catálogo depende de la aprobación
            // (el catálogo muestra solo lotes con postulación aprobada)
            $_SESSION['flash_ok'] = "Lote aprobado para mostrar en catálogo";
          }
        }
        break;
        
      case 'reject_nomination':
        $nomination_id = $_POST['nomination_id'];
        $reason = $_POST['reason'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE nominations SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id'], $reason, $nomination_id]);
        $_SESSION['flash_ok'] = "Postulación rechazada";
        break;
        
      case 'add_lot':
        // Calcular automáticamente el número de animales basándose en los seleccionados
        $animal_count = 0;
        if (!empty($_POST['animal_ids']) && is_array($_POST['animal_ids'])) {
          $animal_count = count($_POST['animal_ids']);
        }
        
        $stmt = $pdo->prepare("INSERT INTO lots (name, description, total_price, animal_count, lot_type, status, farm_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
          $_POST['name'],
          $_POST['description'] ?? null,
          0,
          $animal_count,
          $_POST['lot_type'],
          $_POST['status'] ?? 'disponible',
          $_POST['farm_id'] ?? null,
          $user['id']
        ]);
        $lot_id = $pdo->lastInsertId();
        
        // Agregar animales al lote si se especificaron
        if (!empty($_POST['animal_ids']) && is_array($_POST['animal_ids'])) {
          foreach ($_POST['animal_ids'] as $animal_id) {
            $stmt = $pdo->prepare("INSERT INTO lot_animals (lot_id, animal_id) VALUES (?, ?)");
            $stmt->execute([$lot_id, $animal_id]);
          }
        }
        
        // Checkbox: publicar/postular inmediatamente
        if (!empty($_POST['publish_or_postulate'])) {
          if (is_admin_general()) {
            // Crear postulación aprobada para cumplir con el filtro del catálogo
            try {
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status) VALUES ('lot', ?, ?, ?, 'approved')");
              $stmt->execute([$lot_id, $_SESSION['user']['id'], $_POST['farm_id']]);
              $_SESSION['flash_ok'] = 'Lote creado y publicado en catálogo';
            } catch (Throwable $e) {
              $_SESSION['flash_ok'] = 'Lote creado (no se pudo publicar: ' . e($e->getMessage()) . ')';
            }
          } else if (is_admin_finca()) {
            // Crear postulación pendiente
            try {
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status) VALUES ('lot', ?, ?, ?, 'pending')");
              $stmt->execute([$lot_id, $_SESSION['user']['id'], $_POST['farm_id']]);
              $_SESSION['flash_ok'] = 'Lote creado y postulado para aprobación';
            } catch (Throwable $e) {
              $_SESSION['flash_ok'] = 'Lote creado (no se pudo postular: ' . e($e->getMessage()) . ')';
            }
          }
        } else {
          $_SESSION['flash_ok'] = 'Lote creado exitosamente';
        }
        break;

      case 'toggle_catalog_lot':
        $lot_id = (int)($_POST['lot_id'] ?? 0);
        $publish = (int)($_POST['publish'] ?? 0);
        if ($lot_id <= 0) { $_SESSION['flash_err'] = 'Lote inválido'; break; }

        if ($publish) {
          if (is_admin_general()) {
            // Publicar: crear/aprobar nominación
            $stmt = $pdo->prepare("SELECT id FROM nominations WHERE item_type = 'lot' AND item_id = ?");
            $stmt->execute([$lot_id]);
            $nom = $stmt->fetch();
            if ($nom) {
              $stmt = $pdo->prepare("UPDATE nominations SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
              $stmt->execute([$_SESSION['user']['id'], $nom['id']]);
            } else {
              // Obtener farm del lote
              $stmt = $pdo->prepare("SELECT farm_id FROM lots WHERE id = ?");
              $stmt->execute([$lot_id]);
              $lotFarm = $stmt->fetch();
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status, reviewed_by, reviewed_at) VALUES ('lot', ?, ?, ?, 'approved', ?, NOW())");
              $stmt->execute([$lot_id, $_SESSION['user']['id'], $lotFarm['farm_id'] ?? null, $_SESSION['user']['id']]);
            }
            $_SESSION['flash_ok'] = 'Lote publicado en catálogo';
          } else if (is_admin_finca()) {
            // Postular pendiente
            $stmt = $pdo->prepare("SELECT id, status FROM nominations WHERE item_type = 'lot' AND item_id = ?");
            $stmt->execute([$lot_id]);
            $nom = $stmt->fetch();
            if ($nom) {
              if ($nom['status'] !== 'pending') {
                $stmt = $pdo->prepare("UPDATE nominations SET status = 'pending', proposed_by = ?, reviewed_by = NULL, reviewed_at = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['user']['id'], $nom['id']]);
                $_SESSION['flash_ok'] = 'Lote republicado para aprobación';
              } else {
                $_SESSION['flash_err'] = 'El lote ya tiene una postulación pendiente';
              }
            } else {
              $stmt = $pdo->prepare("SELECT farm_id FROM lots WHERE id = ?");
              $stmt->execute([$lot_id]);
              $lotFarm = $stmt->fetch();
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type, item_id, proposed_by, farm_id, status) VALUES ('lot', ?, ?, ?, 'pending')");
              $stmt->execute([$lot_id, $_SESSION['user']['id'], $lotFarm['farm_id'] ?? null]);
              $_SESSION['flash_ok'] = 'Lote postulado para aprobación';
            }
          }
        } else {
          // Solicitud de ocultar: solo admin_general
          if (is_admin_general()) {
            // Opción simple: marcar nominación como rechazada (o eliminarla)
            $stmt = $pdo->prepare("UPDATE nominations SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), notes = 'Ocultado por admin' WHERE item_type='lot' AND item_id = ?");
            $stmt->execute([$_SESSION['user']['id'], $lot_id]);
            $_SESSION['flash_ok'] = 'Lote ocultado del catálogo';
          } else {
            $_SESSION['flash_err'] = 'No tienes permisos para ocultar lotes';
          }
        }
        break;

      case 'remove_animal_from_lot':
        $lot_id = (int)($_POST['lot_id'] ?? 0);
        $animal_id = (int)($_POST['animal_id'] ?? 0);
        if ($lot_id <= 0 || $animal_id <= 0) { $_SESSION['flash_err'] = 'Datos inválidos'; break; }
        $stmt = $pdo->prepare("DELETE FROM lot_animals WHERE lot_id = ? AND animal_id = ?");
        $stmt->execute([$lot_id, $animal_id]);
        $_SESSION['flash_ok'] = 'Animal removido del lote';
        // Redirigir a la edición del mismo lote para refrescar
        header('Location: admin.php?edit_lot=' . $lot_id);
        exit;

      case 'bulk_toggle_lots':
        if (!is_admin_general()) { $_SESSION['flash_err'] = 'No autorizado'; break; }
        $publish = (int)($_POST['publish'] ?? 0);
        $lot_ids = $_POST['lot_ids'] ?? [];
        if (empty($lot_ids)) {
          // fallback: usar todos los lotes existentes
          $ids = array_map(fn($l) => $l['id'], $lots);
          $lot_ids = $ids;
        }
        if ($publish) {
          // Aprobar/crear nominaciones aprobadas para estos lotes
          foreach ($lot_ids as $lid) {
            $lid = (int)$lid;
            if ($lid <= 0) continue;
            $stmt = $pdo->prepare("SELECT id FROM nominations WHERE item_type='lot' AND item_id=?");
            $stmt->execute([$lid]);
            $nom = $stmt->fetch();
            if ($nom) {
              $stmt = $pdo->prepare("UPDATE nominations SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
              $stmt->execute([$_SESSION['user']['id'], $nom['id']]);
            } else {
              $stmt = $pdo->prepare("SELECT farm_id FROM lots WHERE id=?");
              $stmt->execute([$lid]);
              $lotFarm = $stmt->fetch();
              $stmt = $pdo->prepare("INSERT INTO nominations (item_type,item_id,proposed_by,farm_id,status,reviewed_by,reviewed_at) VALUES ('lot',?,?,?,?, 'approved', NOW())");
              $stmt->execute([$lid, $_SESSION['user']['id'], $lotFarm['farm_id'] ?? null, $_SESSION['user']['id']]);
            }
          }
          $_SESSION['flash_ok'] = 'Lotes publicados en catálogo';
        } else {
          // Marcar nominaciones como rechazadas para ocultar
          foreach ($lot_ids as $lid) {
            $lid = (int)$lid;
            if ($lid <= 0) continue;
            $stmt = $pdo->prepare("UPDATE nominations SET status='rejected', reviewed_by=?, reviewed_at=NOW(), notes='Ocultado por admin' WHERE item_type='lot' AND item_id=?");
            $stmt->execute([$_SESSION['user']['id'], $lid]);
          }
          $_SESSION['flash_ok'] = 'Lotes ocultados del catálogo';
        }
        break;
        
      case 'edit_lot':
        $stmt = $pdo->prepare("UPDATE lots SET name=?, description=?, total_price=?, lot_type=?, status=?, farm_id=? WHERE id=?");
        $stmt->execute([
          $_POST['name'],
          $_POST['description'] ?? null,
          0,
          $_POST['lot_type'],
          $_POST['status'],
          $_POST['farm_id'] ?? null,
          $_POST['lot_id']
        ]);
        $_SESSION['flash_ok'] = 'Lote actualizado exitosamente';
        break;
        
      case 'delete_lot':
        $stmt = $pdo->prepare("DELETE FROM lots WHERE id = ?");
        $stmt->execute([$_POST['lot_id']]);
        $_SESSION['flash_ok'] = 'Lote eliminado exitosamente';
        break;
        
      case 'upload_photos':
        $animal_id = $_POST['animal_id'];
        $uploaded_files = [];
        
        // Verificar que se subieron archivos
        if (empty($_FILES['photos']['name'][0])) {
          $_SESSION['flash_err'] = 'Debe seleccionar al menos una foto';
          break;
        }
        
        // Crear directorio si no existe
        $uploadDir = __DIR__ . '/uploads/animals/';
        if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $maxPhotos = 5;
        
        $fileCount = count($_FILES['photos']['name']);
        if ($fileCount > $maxPhotos) {
          $_SESSION['flash_err'] = "Máximo {$maxPhotos} fotos permitidas";
          break;
        }
        
        foreach ($_FILES['photos']['name'] as $key => $filename) {
          if (empty($filename)) continue;
          
          $tmpName = $_FILES['photos']['tmp_name'][$key];
          $fileSize = $_FILES['photos']['size'][$key];
          $fileType = $_FILES['photos']['type'][$key];
          
          // Validar tipo de archivo
          if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['flash_err'] = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
            break 2;
          }
          
          // Validar tamaño
          if ($fileSize > $maxFileSize) {
            $_SESSION['flash_err'] = 'El archivo es demasiado grande (máximo 5MB)';
            break 2;
          }
          
          // Generar nombre único
          $extension = pathinfo($filename, PATHINFO_EXTENSION);
          $newFilename = 'animal_' . $animal_id . '_' . time() . '_' . $key . '.' . $extension;
          $filePath = $uploadDir . $newFilename;
          
          // Mover archivo
          if (move_uploaded_file($tmpName, $filePath)) {
            $uploaded_files[] = [
              'filename' => $newFilename,
              'original_name' => $filename,
              'file_path' => 'uploads/animals/' . $newFilename,
              'file_size' => $fileSize,
              'mime_type' => $fileType,
              'sort_order' => $key
            ];
          }
        }
        
        // Guardar información en base de datos
        if (!empty($uploaded_files)) {
          $photos_description = !empty($_POST['photos_description']) ? $_POST['photos_description'] : null;
          $stmt = $pdo->prepare("INSERT INTO animal_photos (animal_id, filename, original_name, file_path, file_size, mime_type, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          
          foreach ($uploaded_files as $index => $file) {
            $stmt->execute([
              $animal_id,
              $file['filename'],
              $file['original_name'],
              $file['file_path'],
              $file['file_size'],
              $file['mime_type'],
              $photos_description,
              $file['sort_order']
            ]);
            
            // Marcar la primera foto como principal
            if ($index === 0) {
              $photoId = $pdo->lastInsertId();
              $pdo->prepare("UPDATE animal_photos SET is_primary = 1 WHERE id = ?")->execute([$photoId]);
            }
          }
          
          $_SESSION['flash_ok'] = count($uploaded_files) . ' foto(s) subida(s) exitosamente';
        }
        break;
        
      case 'delete_photo':
        $photo_id = $_POST['photo_id'];
        
        // Obtener información del archivo
        $stmt = $pdo->prepare("SELECT file_path FROM animal_photos WHERE id = ?");
        $stmt->execute([$photo_id]);
        $photo = $stmt->fetch();
        
        if ($photo) {
          // Eliminar archivo físico
          $filePath = __DIR__ . '/' . $photo['file_path'];
          if (file_exists($filePath)) {
            unlink($filePath);
          }
          
          // Eliminar registro de base de datos
          $stmt = $pdo->prepare("DELETE FROM animal_photos WHERE id = ?");
          $stmt->execute([$photo_id]);
          
          $_SESSION['flash_ok'] = 'Foto eliminada exitosamente';
        }
        break;
        
      case 'set_primary_photo':
        $photo_id = $_POST['photo_id'];
        $animal_id = $_POST['animal_id'];
        
        // Quitar primary de todas las fotos del animal
        $stmt = $pdo->prepare("UPDATE animal_photos SET is_primary = 0 WHERE animal_id = ?");
        $stmt->execute([$animal_id]);
        
        // Marcar nueva foto como primary
        $stmt = $pdo->prepare("UPDATE animal_photos SET is_primary = 1 WHERE id = ?");
        $stmt->execute([$photo_id]);
        
        $_SESSION['flash_ok'] = 'Foto principal actualizada';
        break;
        
      case 'update_quote_status':
        if (!is_admin_general()) {
          $_SESSION['flash_err'] = 'No tienes permisos para realizar esta acción';
          break;
        }
        
        $quote_id = intval($_POST['quote_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        
        if (!in_array($new_status, ['pendiente', 'en_proceso', 'respondida'])) {
          $_SESSION['flash_err'] = 'Estado inválido';
          break;
        }
        
        // Obtener información de la cotización
        $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch();
        
        if (!$quote) {
          $_SESSION['flash_err'] = 'Cotización no encontrada';
          break;
        }
        
        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE quotes SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $_SESSION['user']['id'], $quote_id]);
        
        // Enviar correo de notificación al cliente
        require_once __DIR__ . '/../app/email.php';
        
        $status_names = [
          'pendiente' => 'Pendiente',
          'en_proceso' => 'En Proceso',
          'respondida' => 'Respondida'
        ];
        
        $status_messages = [
          'pendiente' => 'Tu solicitud de cotización está pendiente de revisión.',
          'en_proceso' => 'Estamos procesando tu solicitud de cotización y te contactaremos pronto.',
          'respondida' => 'Hemos respondido a tu solicitud de cotización. Por favor, revisa los detalles a continuación.'
        ];
        
        $email_subject = "Actualización de Estado - Cotización #$quote_id - Rc El Bosque";
        $email_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a4720; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .status-box { background: white; padding: 15px; margin: 15px 0; border-left: 3px solid #1a4720; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>📋 Actualización de Cotización</h2>
                </div>
                <div class='content'>
                    <p>Estimado/a <strong>" . htmlspecialchars($quote['customer_name']) . "</strong>,</p>
                    
                    <p>Te informamos que el estado de tu solicitud de cotización ha sido actualizado:</p>
                    
                    <div class='status-box'>
                        <strong>Nuevo Estado:</strong> " . htmlspecialchars($status_names[$new_status]) . "<br>
                        <strong>Cotización #:</strong> $quote_id<br>
                        <strong>Fecha de Actualización:</strong> " . date('d/m/Y H:i') . "
                    </div>
                    
                    <p>" . htmlspecialchars($status_messages[$new_status]) . "</p>
                    
                    <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                </div>
                <div class='footer'>
                    <p>Este es un correo automático del sistema Rc El Bosque.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $email_sent = send_email($quote['customer_email'], $email_subject, $email_body);
        
        if ($email_sent) {
          $_SESSION['flash_ok'] = "Estado de cotización actualizado y correo enviado al cliente";
        } else {
          $_SESSION['flash_ok'] = "Estado de cotización actualizado (error al enviar correo)";
        }
        break;
        
      case 'add_carousel_image':
        if (!is_admin_general()) {
          $_SESSION['flash_err'] = 'No tienes permisos para realizar esta acción';
          break;
        }
        
        if (empty($_FILES['carousel_image']['name'])) {
          $_SESSION['flash_err'] = 'Debes seleccionar una imagen';
          break;
        }
        
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        // Crear directorio si no existe
        $uploadDir = __DIR__ . '/uploads/carousel/';
        if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        $file = $_FILES['carousel_image'];
        
        if (!in_array($file['type'], $allowedTypes)) {
          $_SESSION['flash_err'] = 'Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP)';
          break;
        }
        
        if ($file['size'] > $maxFileSize) {
          $_SESSION['flash_err'] = 'El archivo es demasiado grande. Máximo 10MB';
          break;
        }
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'carousel_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
          $relativePath = 'uploads/carousel/' . $filename;
          
          $stmt = $pdo->prepare("
            INSERT INTO carousel_images (filename, file_path, title, description, sort_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
          ");
          $stmt->execute([
            $filename,
            $relativePath,
            $title ?: null,
            $description ?: null,
            $sort_order,
            $_SESSION['user']['id']
          ]);
          
          $_SESSION['flash_ok'] = 'Imagen del carrusel agregada exitosamente';
        } else {
          $_SESSION['flash_err'] = 'Error al subir la imagen';
        }
        break;
        
      case 'delete_carousel_image':
        if (!is_admin_general()) {
          $_SESSION['flash_err'] = 'No tienes permisos para realizar esta acción';
          break;
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        
        // Obtener información de la imagen
        $stmt = $pdo->prepare("SELECT file_path FROM carousel_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $image = $stmt->fetch();
        
        if ($image) {
          // Eliminar archivo físico
          $filePath = __DIR__ . '/' . $image['file_path'];
          if (file_exists($filePath)) {
            unlink($filePath);
          }
          
          // Eliminar registro de BD
          $stmt = $pdo->prepare("DELETE FROM carousel_images WHERE id = ?");
          $stmt->execute([$image_id]);
          
          $_SESSION['flash_ok'] = 'Imagen del carrusel eliminada exitosamente';
        } else {
          $_SESSION['flash_err'] = 'Imagen no encontrada';
        }
        break;
        
      case 'update_carousel_image':
        if (!is_admin_general()) {
          $_SESSION['flash_err'] = 'No tienes permisos para realizar esta acción';
          break;
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("
          UPDATE carousel_images 
          SET title = ?, description = ?, sort_order = ?, is_active = ?, updated_at = NOW()
          WHERE id = ?
        ");
        $stmt->execute([$title ?: null, $description ?: null, $sort_order, $is_active, $image_id]);
        
        $_SESSION['flash_ok'] = 'Imagen del carrusel actualizada exitosamente';
        break;
    }
    
    header('Location: admin.php');
    exit;
    
  } catch (Exception $e) {
    $_SESSION['flash_err'] = 'Error: ' . $e->getMessage();
  }
}

// Get animal for editing
$editAnimal = null;
if (isset($_GET['edit_animal'])) {
  $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
  $stmt->execute([$_GET['edit_animal']]);
  $editAnimal = $stmt->fetch();
}

// Get user for editing
$editUser = null;
if (isset($_GET['edit_user'])) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$_GET['edit_user']]);
  $editUser = $stmt->fetch();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel Administrativo - Rc El Bosque</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <!-- Corrección de errores de validación CSS de Font Awesome -->
  <style>
    /* Corrección para errores de validación CSS del W3C */
    /* Estos estilos corrigen los valores problemáticos sin cambiar la apariencia visual */
    .fa-beat,
    .fa-bounce,
    .fa-beat-fade,
    .fa-fade,
    .fa-flip,
    .fa-shake,
    .fa-spin {
      animation-delay: 0s;
    }
    
    .fa-rotate-by {
      transform: rotate(0deg);
    }
  </style>
  <style>
    :root {
      --primary-green: #2d5a27;
      --accent-green: #3e7b2e;
      --light-green: #4a9a3d;
      --bg-green: #f0f8ec;
      --text-dark: #1a3315;
      --success: #10b981;
      --warning: #f59e0b;
      --error: #ef4444;
      --info: #3b82f6;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-400: #9ca3af;
      --gray-500: #6b7280;
      --gray-600: #4b5563;
      --gray-700: #374151;
      --gray-800: #1f2937;
      --gray-900: #111827;
      --sidebar-width: 280px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      background: var(--gray-50);
      color: var(--text-dark);
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* Layout */
    .admin-layout {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar */
    .admin-sidebar {
      width: var(--sidebar-width);
      background: white;
      border-right: 1px solid var(--gray-200);
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar-header {
      padding: 2rem 1.5rem;
      border-bottom: 1px solid var(--gray-200);
      background: var(--primary-green);
      color: white;
    }

    .sidebar-header h1 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .sidebar-header .user-info {
      margin-top: 0.5rem;
      font-size: 0.875rem;
      opacity: 0.9;
    }

    .sidebar-nav {
      padding: 1rem 0;
    }

    .nav-section {
      margin-bottom: 2rem;
    }

    .nav-section-title {
      padding: 0.5rem 1.5rem;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--gray-500);
      margin-bottom: 0.5rem;
    }

    .nav-item {
      display: block;
      padding: 0.75rem 1.5rem;
      color: var(--gray-700);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s ease;
      border-left: 3px solid transparent;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .nav-item:hover {
      background: var(--gray-50);
      color: var(--primary-green);
      border-left-color: var(--primary-green);
    }

    .nav-item.active {
      background: rgba(45, 90, 39, 0.1);
      color: var(--primary-green);
      border-left-color: var(--primary-green);
    }

    .nav-item i {
      width: 1.25rem;
      text-align: center;
      font-size: 1rem;
    }

    /* Main Content */
    .admin-main {
      flex: 1;
      margin-left: var(--sidebar-width);
      background: var(--gray-50);
      min-height: 100vh;
    }

    .main-header {
      background: white;
      border-bottom: 1px solid var(--gray-200);
      padding: 1.5rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .main-header h2 {
      margin: 0;
      color: var(--gray-800);
      font-size: 1.5rem;
      font-weight: 600;
    }

    .main-content {
      padding: 2rem;
    }

    /* Flash Messages */
    .flash-messages {
      position: fixed;
      top: 100px;
      right: 2rem;
      z-index: 1000;
      max-width: 400px;
    }

    .flash-message {
      background: white;
      border: 1px solid var(--gray-200);
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      animation: slideInRight 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .flash-message.success {
      border-left: 4px solid var(--success);
    }

    .flash-message.error {
      border-left: 4px solid var(--error);
    }

    .flash-message.warning {
      border-left: 4px solid var(--warning);
    }

    .flash-message.info {
      border-left: 4px solid var(--info);
    }

    @keyframes slideInRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    /* Content Sections */
    .content-section {
      display: none;
      animation: fadeIn 0.3s ease;
    }

    .content-section.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      transition: all 0.2s ease;
      text-align: center;
    }

    .stat-card:hover {
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .stat-card .icon {
      width: 3rem;
      height: 3rem;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      font-size: 1.25rem;
    }

    .stat-card .icon.animals { background: rgba(16, 185, 129, 0.1); color: var(--success); }
    .stat-card .icon.users { background: rgba(59, 130, 246, 0.1); color: var(--info); }
    .stat-card .icon.catalog { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
    .stat-card .icon.lots { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }
    .stat-card .icon.farms { background: rgba(45, 90, 39, 0.1); color: var(--primary-green); }

    .stat-card .number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--gray-800);
      margin-bottom: 0.5rem;
    }

    .stat-card .label {
      color: var(--gray-600);
      font-weight: 500;
    }

    /* Cards */
    .admin-card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      margin-bottom: 2rem;
      transition: all 0.2s ease;
    }

    .admin-card:hover {
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .admin-card h3 {
      margin: 0 0 1.5rem 0;
      color: var(--primary-green);
      font-size: 1.25rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Forms */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      color: var(--gray-700);
      margin-bottom: 0.5rem;
      font-size: 0.875rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 0.75rem;
      border: 1px solid var(--gray-300);
      border-radius: 8px;
      font-size: 0.875rem;
      transition: all 0.2s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s ease;
      font-size: 0.875rem;
    }

    .btn-primary {
      background: var(--primary-green);
      color: white;
    }

    .btn-primary:hover {
      background: var(--accent-green);
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: var(--gray-100);
      color: var(--gray-700);
    }

    .btn-secondary:hover {
      background: var(--gray-200);
    }

    .btn-danger {
      background: var(--error);
      color: white;
    }

    .btn-danger:hover {
      background: #dc2626;
    }

    .btn-sm {
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
    }

    /* Tables */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .data-table th,
    .data-table td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid var(--gray-200);
    }

    .data-table th {
      background: var(--gray-50);
      font-weight: 600;
      color: var(--gray-700);
      font-size: 0.875rem;
    }

    .data-table tr:hover {
      background: var(--gray-50);
    }

    .data-table tr:last-child td {
      border-bottom: none;
    }

    /* Checkboxes */
    .animal-checkbox {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: var(--primary-green);
    }

    #select-all {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: var(--primary-green);
    }

    /* Status indicators */
    .status-visible {
      color: var(--success);
      font-weight: 600;
    }

    .status-hidden {
      color: var(--gray-400);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .admin-sidebar.open {
        transform: translateX(0);
      }
      
      .admin-main {
        margin-left: 0;
      }
      
      .main-content {
        padding: 1rem;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
      <div class="sidebar-header">
        <h1><i class="fas fa-shield-alt"></i> Rc El Bosque Admin</h1>
        <div class="user-info">
          <i class="fas fa-user"></i> <?= e($user['name']) ?>
          <br>
          <small><?= e($user['role']) ?></small>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-section-title">Gestión Principal</div>
          <a href="#" class="nav-item active" onclick="showSection('dashboard')">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
          </a>
          <?php if(is_admin_general() || is_admin_finca()): ?>
          <a href="#" class="nav-item" onclick="showSection('animals')">
            <i class="fas fa-cow"></i>
            Gestión de Animales
          </a>
          <a href="#" class="nav-item" onclick="showSection('catalog')">
            <i class="fas fa-store"></i>
            Catálogo
          </a>
          <a href="#" class="nav-item" onclick="showSection('lots')">
            <i class="fas fa-layer-group"></i>
            Lotes
          </a>
          <?php endif; ?>
          <?php if(is_admin_general()): ?>
          <a href="#" class="nav-item" onclick="showSection('users')">
            <i class="fas fa-users"></i>
            Usuarios
          </a>
          <a href="#" class="nav-item" onclick="showSection('farms')">
            <i class="fas fa-home"></i>
            Fincas
          </a>
          <a href="#" class="nav-item" onclick="showSection('nominations')">
            <i class="fas fa-paper-plane"></i>
            Postulaciones
          </a>
          <a href="#" class="nav-item" onclick="showSection('quotes')">
            <i class="fas fa-calculator"></i>
            Cotizaciones
          </a>
          <?php endif; ?>
          <?php if(is_admin_finca()): ?>
          <a href="#" class="nav-item" onclick="showSection('users')">
            <i class="fas fa-user-md"></i>
            Veterinarios
          </a>
          <a href="#" class="nav-item" onclick="showSection('nominations')">
            <i class="fas fa-paper-plane"></i>
            Mis Postulaciones
          </a>
          <?php endif; ?>
        </div>
        
        <div class="nav-section">
          <div class="nav-section-title">Módulos Especializados</div>
          <?php if(is_veterinario() || is_admin_general() || is_admin_finca()): ?>
          <a href="#" class="nav-item" onclick="showSection('veterinary')">
            <i class="fas fa-stethoscope"></i>
            Veterinario
          </a>
          <?php endif; ?>
          <?php if(is_admin_general()): ?>
          <a href="#" class="nav-item" onclick="showSection('reports')">
            <i class="fas fa-chart-line"></i>
            Reportes
          </a>
          <a href="#" class="nav-item" onclick="showSection('carousel')">
            <i class="fas fa-images"></i>
            Carrusel Principal
          </a>
          <a href="#" class="nav-item" onclick="showSection('settings')">
            <i class="fas fa-cog"></i>
            Configuración
          </a>
          <?php endif; ?>
        </div>
        
        <div class="nav-section">
          <div class="nav-section-title">Navegación</div>
          <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            Inicio
          </a>
          <a href="catalogo.php" class="nav-item">
            <i class="fas fa-list"></i>
            Catálogo Público
          </a>
          <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            Cerrar Sesión
          </a>
        </div>
</nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
      <header class="main-header">
        <h2 id="section-title">Dashboard</h2>
        <div class="header-actions">
          <button class="btn btn-sm" onclick="refreshData()">
            <i class="fas fa-sync-alt"></i> Actualizar
          </button>
        </div>
      </header>
      
      <div class="main-content">
        <!-- Flash Messages -->
        <div class="flash-messages">
          <?php if($flash['err']): ?>
            <div class="flash-message error">
              <button class="close" onclick="this.parentElement.remove()">&times;</button>
              <i class="fas fa-exclamation-circle"></i> <?= e($flash['err']) ?>
            </div>
          <?php endif; ?>
          <?php if($flash['ok']): ?>
            <div class="flash-message success">
              <button class="close" onclick="this.parentElement.remove()">&times;</button>
              <i class="fas fa-check-circle"></i> <?= e($flash['ok']) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard-section" class="content-section active">
          <!-- Stats Overview -->
          <div class="stats-grid">
            <div class="stat-card">
              <div class="icon animals">
                <i class="fas fa-cow"></i>
              </div>
              <div class="number"><?= count($animals) ?></div>
              <div class="label">Total Animales</div>
            </div>
            <div class="stat-card">
              <div class="icon users">
                <i class="fas fa-users"></i>
              </div>
              <div class="number"><?= count($users) ?></div>
              <div class="label">Usuarios</div>
            </div>
            <div class="stat-card">
              <div class="icon catalog">
                <i class="fas fa-eye"></i>
              </div>
              <div class="number"><?= count(array_filter($animals, fn($a) => $a['in_cat'])) ?></div>
              <div class="label">En Catálogo</div>
            </div>
            <div class="stat-card">
              <div class="icon lots">
                <i class="fas fa-layer-group"></i>
              </div>
              <div class="number"><?= count($lots) ?></div>
              <div class="label">Lotes</div>
            </div>
            <div class="stat-card">
              <div class="icon farms">
                <i class="fas fa-home"></i>
              </div>
              <div class="number"><?= count($farms) ?></div>
              <div class="label">Fincas</div>
            </div>
          </div>
          
          <div class="admin-card">
            <h3><i class="fas fa-tachometer-alt"></i> Resumen del Sistema</h3>
            <p>Bienvenido al panel administrativo de Rc El Bosque. Desde aquí puedes gestionar todos los aspectos del sistema.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-cow" style="font-size: 2rem; color: var(--primary-green); margin-bottom: 1rem;"></i>
                <h4>Gestión de Animales</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Administra el inventario de animales</p>
                <button class="btn btn-sm" onclick="showSection('animals')">
                  <i class="fas fa-arrow-right"></i> Ir a Animales
                </button>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-store" style="font-size: 2rem; color: var(--info); margin-bottom: 1rem;"></i>
                <h4>Catálogo</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Gestiona productos y servicios</p>
                <button class="btn btn-sm" onclick="showSection('catalog')">
                  <i class="fas fa-arrow-right"></i> Ir a Catálogo
                </button>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-stethoscope" style="font-size: 2rem; color: var(--warning); margin-bottom: 1rem;"></i>
                <h4>Módulo Veterinario</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Tratamientos y salud animal</p>
                <button class="btn btn-sm" onclick="showSection('veterinary')">
                  <i class="fas fa-arrow-right"></i> Ir a Veterinario
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Animals Section -->
        <div id="animals-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-plus-circle"></i> <?= $editAnimal ? 'Editar Animal' : 'Registrar Nuevo Animal' ?></h3>
      <?php if ($editAnimal): ?>
              <div style="margin-bottom: 1rem;">
                <a href="admin.php" class="btn btn-secondary">
                  <i class="fas fa-arrow-left"></i> Cancelar Edición
                </a>
        </div>
      <?php endif; ?>
            
            <form method="POST" class="form-grid" enctype="multipart/form-data" id="animal_form">
              <input type="hidden" name="action" value="<?= $editAnimal ? 'edit_animal' : 'add_animal' ?>">
        <?php if ($editAnimal): ?>
                <input type="hidden" name="animal_id" value="<?= $editAnimal['id'] ?>">
        <?php endif; ?>
              
              <div class="form-group">
                <label for="tag_code">Código de Animal *</label>
                <input type="text" id="tag_code" name="tag_code" value="<?= e($editAnimal['tag_code'] ?? '') ?>" 
                       required placeholder="Ej: ANIMAL-001" maxlength="80"
                       oninput="validateTagCode(this)">
                <small id="tag-code-error" style="color: var(--danger); display: none; margin-top: 0.25rem;"></small>
                <small style="color: var(--gray-500); font-size: 0.85em; display: block; margin-top: 0.25rem;">
                  Código único de identificación del animal (obligatorio)
                </small>
              </div>
              
              <div class="form-group">
                <label for="animal_name">Nombre del Animal</label>
                <input type="text" id="animal_name" name="name" value="<?= e($editAnimal['name'] ?? '') ?>" 
                       placeholder="Opcional">
              </div>
              
              <!-- Especie y Raza fijas: Bovino - Brahman -->
              <?php 
              // Obtener ID de bovino y brahman
              $stmt = $pdo->query("SELECT id FROM species WHERE name = 'Bovino' LIMIT 1");
              $bovino = $stmt->fetch();
              $stmt = $pdo->query("SELECT id FROM breeds WHERE name = 'Brahman' LIMIT 1");
              $brahman = $stmt->fetch();
              ?>
              <input type="hidden" name="species_id" value="<?= $bovino['id'] ?? '' ?>">
              <input type="hidden" name="breed_id" value="<?= $brahman['id'] ?? '' ?>">
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px; border: 1px solid var(--gray-200);">
                  <strong style="color: var(--primary-green);"><i class="fas fa-tag"></i> Información de Especie y Raza:</strong>
                  <p style="margin: 0.5rem 0 0 0; color: var(--gray-600);">
                    Todos los animales registrados son <strong>Bovinos</strong> de raza <strong>Brahman</strong>
                  </p>
                </div>
              </div>
              
              <div class="form-group">
                <label for="birth_date">Fecha de Nacimiento</label>
                <input type="date" id="birth_date" name="birth_date" value="<?= e($editAnimal['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                <small style="color: var(--gray-600); font-size: 0.875rem;">No se pueden registrar fechas futuras</small>
              </div>
              
              <div class="form-group">
                <label for="gender">Género</label>
                <select id="gender" name="gender">
                  <option value="">Seleccionar género</option>
                  <option value="macho" <?= ($editAnimal['gender'] ?? '') == 'macho' ? 'selected' : '' ?>>Macho</option>
                  <option value="hembra" <?= ($editAnimal['gender'] ?? '') == 'hembra' ? 'selected' : '' ?>>Hembra</option>
                  <option value="indefinido" <?= ($editAnimal['gender'] ?? '') == 'indefinido' ? 'selected' : '' ?>>Indefinido</option>
          </select>
              </div>
              
              <div class="form-group">
                <label for="weight">Peso (kg) <span style="color: var(--gray-500); font-size: 0.85em;">(entre 20 y 1000 kg)</span></label>
                <input type="number" id="weight" name="weight" step="0.1" min="20" max="1000" value="<?= e($editAnimal['weight'] ?? '') ?>" 
                       oninput="validateWeight(this)">
                <small id="weight-error" style="color: var(--danger); display: none; margin-top: 0.25rem;"></small>
              </div>
              
              <div class="form-group">
                <label for="color">Color</label>
                <input type="text" id="color" name="color" value="<?= e($editAnimal['color'] ?? '') ?>">
              </div>
              
              <div class="form-group">
                <label for="animal_farm_id">Finca</label>
                <select id="animal_farm_id" name="farm_id">
                  <option value="">Seleccionar finca</option>
                  <?php foreach ($farms as $farm): ?>
                    <option value="<?= $farm['id'] ?>" <?= ($editAnimal['farm_id'] ?? '') == $farm['id'] ? 'selected' : '' ?>>
                      <?= e($farm['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <label>
                  <input type="checkbox" name="in_cat" <?= ($editAnimal['in_cat'] ?? 0) ? 'checked' : '' ?>>
                  <?php if (is_admin_general()): ?>
                    Incluir en catálogo público
                  <?php else: ?>
                    Postular para el catálogo
                  <?php endif; ?>
        </label>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="description">Descripción</label>
                <textarea id="description" name="description" rows="3"><?= e($editAnimal['description'] ?? '') ?></textarea>
              </div>
              
              <!-- Photo Upload Field -->
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="photos">Fotos del Animal (opcional, máximo 5)</label>
                <input type="file" id="photos" name="photos[]" multiple accept="image/*">
                <small style="color: var(--gray-500);">Formatos permitidos: JPG, PNG, GIF, WebP. Máximo 5MB por archivo.</small>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary" id="animal_submit">
                  <i class="fas fa-save"></i> <?= $editAnimal ? 'Actualizar Animal' : 'Registrar Animal' ?>
                </button>
        </div>
      </form>
            
            <?php if ($editAnimal): ?>
            <!-- Photo Upload Section -->
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--gray-200);">
              <h4><i class="fas fa-camera"></i> Gestión de Fotos</h4>
              <p style="color: var(--gray-600); margin-bottom: 1rem;">Puedes agregar entre 1 y 5 fotos del animal. La primera foto será la principal.</p>
              
              <!-- Upload Form -->
              <form method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem;">
                <input type="hidden" name="action" value="upload_photos">
                <input type="hidden" name="animal_id" value="<?= $editAnimal['id'] ?>">
                
                <div class="form-group">
                  <label for="photos">Seleccionar Fotos (máximo 5)</label>
                  <input type="file" id="photos" name="photos[]" multiple accept="image/*" required>
                  <small style="color: var(--gray-500);">Formatos permitidos: JPG, PNG, GIF, WebP. Máximo 5MB por archivo.</small>
    </div>

                <div class="form-group">
                  <label for="photos_description">Descripción de las fotos (opcional)</label>
                  <textarea id="photos_description" name="photos_description" rows="2" placeholder="Descripción general para todas las fotos subidas"></textarea>
                  <small style="color: var(--gray-500);">Esta descripción se aplicará a todas las fotos que subas</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-upload"></i> Subir Fotos
                </button>
              </form>
              
              <!-- Current Photos -->
              <?php
              $stmt = $pdo->prepare("SELECT * FROM animal_photos WHERE animal_id = ? ORDER BY sort_order, uploaded_at");
              $stmt->execute([$editAnimal['id']]);
              $photos = $stmt->fetchAll();
              ?>
              
              <?php if (!empty($photos)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                  <?php foreach ($photos as $photo): ?>
                    <div style="position: relative; border: 1px solid var(--gray-200); border-radius: 8px; overflow: hidden;">
                      <img src="/Rcelbosque/public/uploads/animals/<?= basename($photo['file_path']) ?>" alt="Foto del animal" style="width: 100%; height: 150px; object-fit: cover;">
                      
                      <?php if ($photo['is_primary']): ?>
                        <div style="position: absolute; top: 0.5rem; left: 0.5rem; background: var(--success); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                          <i class="fas fa-star"></i> Principal
                        </div>
                      <?php endif; ?>
                      
                      <div style="padding: 1rem;">
                        <p style="margin: 0; font-size: 0.875rem; color: var(--gray-600);">
                          <?= e($photo['original_name']) ?>
                        </p>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--gray-500);">
                          <?= number_format($photo['file_size'] / 1024, 1) ?> KB
                        </p>
                        
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                          <?php if (!$photo['is_primary']): ?>
                            <form method="POST" style="display: inline;">
                              <input type="hidden" name="action" value="set_primary_photo">
                              <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                              <input type="hidden" name="animal_id" value="<?= $editAnimal['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-secondary" title="Marcar como principal">
                                <i class="fas fa-star"></i>
                              </button>
                            </form>
                          <?php endif; ?>
                          
                          <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta foto?')">
                            <input type="hidden" name="action" value="delete_photo">
                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar foto">
                              <i class="fas fa-trash"></i>
                            </button>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--gray-500); border: 2px dashed var(--gray-300); border-radius: 8px;">
                  <i class="fas fa-camera" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                  <p>No hay fotos subidas para este animal</p>
                </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          
          <div class="admin-card">
            <h3><i class="fas fa-list"></i> Lista de Animales</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Género</th>
                  <th>Peso</th>
                  <th>En Catálogo</th>
                  <th>Acciones</th>
                </tr>
              </thead>
        <tbody>
                <?php foreach ($animals as $animal): ?>
                  <tr>
                    <td><?= $animal['id'] ?></td>
                    <td><?= e($animal['name']) ?></td>
                    <td><?= e($animal['gender'] ?? 'N/A') ?></td>
                    <td><?= $animal['weight'] ? $animal['weight'] . ' kg' : 'N/A' ?></td>
                    <td>
                      <?php if ($animal['in_cat']): ?>
                        <span style="color: var(--success);"><i class="fas fa-check"></i> Sí</span>
                <?php else: ?>
                        <span style="color: var(--gray-400);"><i class="fas fa-times"></i> No</span>
                <?php endif; ?>
                    </td>
                    <td>
                      <a href="admin.php?edit_animal=<?= $animal['id'] ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-edit"></i> Editar
                      </a>
                      <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este animal?')">
                        <input type="hidden" name="action" value="delete_animal">
                        <input type="hidden" name="animal_id" value="<?= $animal['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
              </div>
        </div>

        <!-- Catalog Section -->
        <div id="catalog-section" class="content-section">
          <!-- Animals Visibility Control -->
          <div class="admin-card">
            <?php if (is_admin_general()): ?>
            <h3><i class="fas fa-eye"></i> Control de Visibilidad del Catálogo</h3>
            <p>Selecciona qué animales son visibles en el catálogo público. Los animales marcados como visibles aparecerán automáticamente en el catálogo.</p>
            <?php else: ?>
            <h3><i class="fas fa-paper-plane"></i> Postular Animales al Catálogo</h3>
            <p>Selecciona los animales que deseas postular para que aparezcan en el catálogo público. Las postulaciones serán revisadas por el administrador general.</p>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <?php if (is_admin_general()): ?>
            <div style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
              <button class="btn btn-sm" onclick="toggleAllAnimals(true)">
                <i class="fas fa-eye"></i> Hacer Todos Visibles
              </button>
              <button class="btn btn-sm" onclick="toggleAllAnimals(false)">
                <i class="fas fa-eye-slash"></i> Ocultar Todos
              </button>
              
            </div>
            <?php endif; ?>
            
            <table class="data-table">
              <thead>
                <tr>
                  <th style="width: 50px;">
                    <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()">
                  </th>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Género</th>
                  <th>Peso</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($animals as $animal): ?>
                  <tr>
                    <td>
                      <input type="checkbox" class="animal-checkbox" data-animal-id="<?= $animal['id'] ?>" <?= $animal['in_cat'] ? 'checked' : '' ?>>
                    </td>
                    <td><?= $animal['id'] ?></td>
                    <td><?= e($animal['name']) ?></td>
                    <td><?= e($animal['gender'] ?? 'N/A') ?></td>
                    <td><?= $animal['weight'] ? $animal['weight'] . ' kg' : 'N/A' ?></td>
                    <td>
                      <?php if (is_admin_general()): ?>
                        <?php if ($animal['in_cat']): ?>
                          <span style="color: var(--success); font-weight: 600;"><i class="fas fa-eye"></i> Visible</span>
                        <?php else: ?>
                          <span style="color: var(--gray-400);"><i class="fas fa-eye-slash"></i> Oculto</span>
                        <?php endif; ?>
                      <?php else: ?>
                        <?php if ($animal['in_cat']): ?>
                          <span style="color: var(--info); font-weight: 600;"><i class="fas fa-check-circle"></i> Aprobado</span>
                        <?php else: ?>
                          <span style="color: var(--warning);"><i class="fas fa-clock"></i> No postulado</span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (is_admin_general()): ?>
                      <button class="btn btn-sm <?= $animal['in_cat'] ? 'btn-secondary' : 'btn-primary' ?>" 
                              onclick="toggleAnimalVisibility(<?= $animal['id'] ?>, <?= $animal['in_cat'] ? 'false' : 'true' ?>)">
                        <i class="fas fa-<?= $animal['in_cat'] ? 'eye-slash' : 'eye' ?>"></i> 
                        <?= $animal['in_cat'] ? 'Ocultar' : 'Mostrar' ?>
                      </button>
                      <?php else: ?>
                      <button class="btn btn-sm <?= $animal['in_cat'] ? 'btn-secondary' : 'btn-primary' ?>" 
                              onclick="toggleAnimalVisibility(<?= $animal['id'] ?>, <?= $animal['in_cat'] ? 'false' : 'true' ?>)">
                        <i class="fas fa-paper-plane"></i> 
                        <?= $animal['in_cat'] ? 'Ya postulado' : 'Postular' ?>
                      </button>
                      <?php endif; ?>
                      <a href="admin.php?edit_animal=<?= $animal['id'] ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-edit"></i> Editar
                      </a>
                      <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este animal? Esta acción no se puede deshacer.')">
                        <input type="hidden" name="action" value="delete_animal">
                        <input type="hidden" name="animal_id" value="<?= $animal['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
            
            
          </div>
          
          <!-- Lots Visibility Control (moved inside Catalog section) -->
          <div class="admin-card" style="margin-top: 2rem;">
            <?php if (is_admin_general()): ?>
            <h3><i class="fas fa-layer-group"></i> Control de Visibilidad de Lotes</h3>
            <p>Publica u oculta lotes del catálogo público.</p>
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="bulk_toggle_lots">
                <input type="hidden" name="publish" value="1">
                <?php foreach ($lots as $lot): ?>
                  <input type="hidden" name="lot_ids[]" value="<?= $lot['id'] ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-sm">
                  <i class="fas fa-eye"></i> Hacer Todos Visibles
                </button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="bulk_toggle_lots">
                <input type="hidden" name="publish" value="0">
                <?php foreach ($lots as $lot): ?>
                  <input type="hidden" name="lot_ids[]" value="<?= $lot['id'] ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-sm">
                  <i class="fas fa-eye-slash"></i> Ocultar Todos
                </button>
              </form>
            </div>
            <?php else: ?>
            <h3><i class="fas fa-paper-plane"></i> Postular Lotes al Catálogo</h3>
            <p>Postula tus lotes para revisión del administrador general.</p>
            <?php endif; ?>

            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Animales</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lots as $lot): ?>
                <tr>
                  <td><?= $lot['id'] ?></td>
                  <td><?= e($lot['name']) ?></td>
                  <td><?= ucfirst(e($lot['lot_type'])) ?></td>
                  <td><?= (int)($lot['actual_animal_count'] ?? 0) ?></td>
                  <td>
                    <?php if (in_array($lot['id'], $approvedLotIds)): ?>
                      <span style="color: var(--success); font-weight: 600;"><i class="fas fa-eye"></i> Visible</span>
                    <?php elseif (in_array($lot['id'], $pendingLotIds)): ?>
                      <span style="color: var(--warning);"><i class="fas fa-clock"></i> Pendiente</span>
                    <?php else: ?>
                      <span style="color: var(--gray-400);"><i class="fas fa-eye-slash"></i> No publicado</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (is_admin_general()): ?>
                      <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="action" value="toggle_catalog_lot">
                        <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                        <input type="hidden" name="publish" value="<?= in_array($lot['id'], $approvedLotIds) ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm <?= in_array($lot['id'], $approvedLotIds) ? 'btn-secondary' : 'btn-primary' ?>">
                          <i class="fas fa-<?= in_array($lot['id'], $approvedLotIds) ? 'eye-slash' : 'eye' ?>"></i>
                          <?= in_array($lot['id'], $approvedLotIds) ? 'Ocultar' : 'Publicar' ?>
                        </button>
                      </form>
                      <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Eliminar este lote? Esta acción no se puede deshacer.')">
                        <input type="hidden" name="action" value="delete_lot">
                        <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="action" value="toggle_catalog_lot">
                        <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                        <input type="hidden" name="publish" value="1">
                        <button type="submit" class="btn btn-sm <?= in_array($lot['id'], $pendingLotIds) ? 'btn-secondary' : 'btn-primary' ?>" <?= in_array($lot['id'], $pendingLotIds) ? 'disabled' : '' ?>>
                          <i class="fas fa-paper-plane"></i> <?= in_array($lot['id'], $pendingLotIds) ? 'Postulado' : 'Postular' ?>
                        </button>
                      </form>
                      <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Eliminar este lote? Esta acción no se puede deshacer.')">
                        <input type="hidden" name="action" value="delete_lot">
                        <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Published in Catalog Overview -->
        <div class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-store"></i> Publicados en Catálogo</h3>
            <div class="grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              <div>
                <h4 style="margin-top:0; color: var(--primary-green);"><i class="fas fa-cow"></i> Animales (<?= count($publishedAnimals) ?>)</h4>
                <?php if (empty($publishedAnimals)): ?>
                  <p style="color: var(--gray-600);">No hay animales publicados.</p>
                <?php else: ?>
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Género</th>
                      <th>Finca</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($publishedAnimals as $pa): ?>
                    <tr>
                      <td><?= $pa['id'] ?></td>
                      <td><?= e($pa['name']) ?></td>
                      <td><?= e($pa['gender'] ?? 'N/A') ?></td>
                      <td><?= e($pa['farm_name'] ?? 'N/A') ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </div>
              <div>
                <h4 style="margin-top:0; color: #8B5CF6;"><i class="fas fa-layer-group"></i> Lotes (<?= count($publishedLots) ?>)</h4>
                <?php if (empty($publishedLots)): ?>
                  <p style="color: var(--gray-600);">No hay lotes publicados.</p>
                <?php else: ?>
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Tipo</th>
                      <th>Animales</th>
                      <th>Finca</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($publishedLots as $pl): ?>
                    <tr>
                      <td><?= $pl['id'] ?></td>
                      <td><?= e($pl['name']) ?></td>
                      <td><?= ucfirst(e($pl['lot_type'])) ?></td>
                      <td><?= (int)($pl['animal_count'] ?? 0) ?></td>
                      <td><?= e($pl['farm_name'] ?? 'N/A') ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        

        <!-- Lots Section -->
        <div id="lots-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-plus-circle"></i> Crear Nuevo Lote</h3>
            <p>Agrupa múltiples animales para venta como un solo lote.</p>
            
            <form method="POST" class="form-grid">
              <input type="hidden" name="action" value="add_lot">
              
              <div class="form-group">
                <label for="lot_name">Nombre del Lote *</label>
                <input type="text" id="lot_name" name="name" required placeholder="Ej: Lote de Vacas Holstein">
              </div>
              
              <div class="form-group">
              <label for="lot_type">Tipo de Lote *</label>
              <select id="lot_type" name="lot_type" required>
                <option value="">Seleccionar tipo</option>
                <?php foreach (($lotTypes ?? []) as $type): ?>
                  <option value="<?= e($type) ?>"><?= ucfirst(e($type)) ?></option>
                <?php endforeach; ?>
              </select>
              </div>
              
              <div class="form-group">
                <label for="lot_farm_id">Finca</label>
                <select id="lot_farm_id" name="farm_id">
                  <option value="">Seleccionar finca</option>
                  <?php foreach ($farms as $farm): ?>
                    <option value="<?= $farm['id'] ?>"><?= e($farm['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="lot_description">Descripción del Lote</label>
                <textarea id="lot_description" name="description" rows="3" placeholder="Describe las características del lote..."></textarea>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1; display:flex; align-items:center; gap:.5rem;">
                <input type="checkbox" id="publish_or_postulate" name="publish_or_postulate" value="1">
                <?php if (is_admin_general()): ?>
                  <label for="publish_or_postulate" style="margin:0;">Publicar inmediatamente en catálogo</label>
                <?php else: ?>
                  <label for="publish_or_postulate" style="margin:0;">Postular para catálogo tras crear</label>
                <?php endif; ?>
              </div>

              <div class="form-group" style="grid-column: 1 / -1;">
                <label>Animales Disponibles para el Lote</label>
                <div style="margin-bottom: 0.5rem; padding: 0.75rem; background: var(--primary-green); color: white; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                  <i class="fas fa-cow"></i>
                  <span id="selected-animals-count">0</span> <span id="selected-animals-label">animales seleccionados</span>
                </div>
                
                <!-- Búsqueda y Filtros -->
                <div style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-50); border-radius: 8px; border: 1px solid var(--gray-200);">
                  <div style="margin-bottom: 1rem;">
                    <label for="animal-search" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                      <i class="fas fa-search"></i> Buscar Animal
                    </label>
                    <input type="text" id="animal-search" placeholder="Buscar por nombre, arete, especie, raza..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.95rem;" onkeyup="filterAnimals()">
                  </div>
                  
                  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <div>
                      <label for="filter-species" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Especie</label>
                      <select id="filter-species" onchange="filterAnimals()" style="width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.9rem;">
                        <option value="">Todas</option>
                        <?php 
                        $uniqueSpecies = array_unique(array_column($animals, 'species_name'));
                        foreach ($uniqueSpecies as $spec): 
                          if (!empty($spec)):
                        ?>
                          <option value="<?= e($spec) ?>"><?= e($spec) ?></option>
                        <?php 
                          endif;
                        endforeach; 
                        ?>
                      </select>
                    </div>
                    
                    <div>
                      <label for="filter-breed" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Raza</label>
                      <select id="filter-breed" onchange="filterAnimals()" style="width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.9rem;">
                        <option value="">Todas</option>
                        <?php 
                        $uniqueBreeds = array_unique(array_column($animals, 'breed_name'));
                        foreach ($uniqueBreeds as $br): 
                          if (!empty($br)):
                        ?>
                          <option value="<?= e($br) ?>"><?= e($br) ?></option>
                        <?php 
                          endif;
                        endforeach; 
                        ?>
                      </select>
                    </div>
                    
                    <div>
                      <label for="filter-gender" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Género</label>
                      <select id="filter-gender" onchange="filterAnimals()" style="width: 100%; padding: 0.5rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.9rem;">
                        <option value="">Todos</option>
                        <option value="macho">Macho</option>
                        <option value="hembra">Hembra</option>
                        <option value="indefinido">Indefinido</option>
                      </select>
                    </div>
                    
                    <div style="display: flex; align-items: flex-end;">
                      <button type="button" onclick="clearAnimalFilters()" class="btn btn-secondary" style="width: 100%; padding: 0.5rem;">
                        <i class="fas fa-times"></i> Limpiar Filtros
                      </button>
                    </div>
                  </div>
                </div>
                
                <!-- Lista de Animales -->
                <div id="animals-container" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--gray-300); border-radius: 8px; padding: 1rem;">
                  <?php foreach ($animals as $animal): ?>
                    <label class="animal-item" style="display: block; margin-bottom: 0.5rem; padding: 0.5rem; border-radius: 4px; transition: background 0.2s;" 
                           data-name="<?= strtolower(e($animal['name'])) ?>"
                           data-tag-code="<?= strtolower(e($animal['tag_code'] ?? '')) ?>"
                           data-species="<?= strtolower(e($animal['species_name'] ?? '')) ?>"
                           data-breed="<?= strtolower(e($animal['breed_name'] ?? '')) ?>"
                           data-gender="<?= strtolower(e($animal['gender'] ?? '')) ?>">
                      <input type="checkbox" name="animal_ids[]" value="<?= $animal['id'] ?>" class="lot-animal-checkbox" onchange="updateAnimalCount()">
                      <span style="margin-left: 0.5rem;">
                        <strong><?= e($animal['name']) ?></strong>
                        <?php if (!empty($animal['tag_code'])): ?>
                          <span style="color: var(--gray-600); font-size: 0.85rem;">(Arete: <?= e($animal['tag_code']) ?>)</span>
                        <?php endif; ?>
                        - <?= e($animal['species_name'] ?? 'N/A') ?> 
                        <?php if (!empty($animal['breed_name'])): ?>
                          · <?= e($animal['breed_name']) ?>
                        <?php endif; ?>
                        (<?= e($animal['gender'] ?? 'N/A') ?>)
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div id="no-animals-message" style="display: none; padding: 1rem; text-align: center; color: var(--gray-600); background: var(--gray-50); border-radius: 8px; margin-top: 0.5rem;">
                  <i class="fas fa-info-circle"></i> No se encontraron animales con los filtros aplicados.
                </div>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Crear Lote
                </button>
              </div>
            </form>
          </div>
          <?php if ($editLot): ?>
          <div class="admin-card">
            <h3><i class="fas fa-edit"></i> Editar Lote: <?= e($editLot['name']) ?> (ID <?= $editLot['id'] ?>)</h3>
            <p style="color: var(--gray-600);">Remueve animales del conjunto del lote.</p>

            <h4 style="margin-top:1rem;">Animales en el lote (<?= count($editLotAnimals) ?>)</h4>
            <?php if (empty($editLotAnimals)): ?>
              <p style="color: var(--gray-600);">No hay animales en este lote.</p>
            <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Género</th>
                  <th>Peso</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($editLotAnimals as $ela): ?>
                <tr>
                  <td><?= $ela['id'] ?></td>
                  <td><?= e($ela['name']) ?></td>
                  <td><?= e($ela['gender'] ?? 'N/A') ?></td>
                  <td><?= $ela['weight'] ? $ela['weight'] . ' kg' : 'N/A' ?></td>
                  <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Quitar este animal del lote?')">
                      <input type="hidden" name="action" value="remove_animal_from_lot">
                      <input type="hidden" name="lot_id" value="<?= $editLot['id'] ?>">
                      <input type="hidden" name="animal_id" value="<?= $ela['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fas fa-minus-circle"></i> Quitar
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="admin-card">
            <h3><i class="fas fa-list"></i> Lotes Existentes</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Animales</th>
                  <th>Estado</th>
                  <th>Finca</th>
                  <th>Acciones</th>
                </tr>
              </thead>
        <tbody>
                <?php foreach ($lots as $lot): ?>
                  <tr>
                    <td><?= $lot['id'] ?></td>
                    <td><?= e($lot['name']) ?></td>
                    <td>
                      <span style="background: <?= $lot['lot_type'] == 'venta' ? 'var(--primary-green)' : 'var(--info)' ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                        <?= ucfirst(e($lot['lot_type'])) ?>
                      </span>
                    </td>
                    <td><?= $lot['actual_animal_count'] ?> animales</td>
                    <td>
                      <span style="background: <?= $lot['status'] == 'disponible' ? 'var(--success)' : ($lot['status'] == 'vendido' ? 'var(--error)' : 'var(--warning)') ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                        <?= ucfirst(e($lot['status'])) ?>
                      </span>
                    </td>
                    <td><?= e($lot['farm_name'] ?? 'N/A') ?></td>
                    <td>
                      <button class="btn btn-sm btn-secondary" onclick="viewLotDetails(<?= $lot['id'] ?>)">
                        <i class="fas fa-eye"></i> Ver
                      </button>
                      <a href="admin.php?edit_lot=<?= $lot['id'] ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-edit"></i> Editar
                      </a>
                      <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este lote?')">
                        <input type="hidden" name="action" value="delete_lot">
                        <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Users Section -->
        <div id="users-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-plus-circle"></i> 
              <?php if (is_admin_finca()): ?>
                <?= $editUser ? 'Editar Veterinario' : 'Crear Nuevo Veterinario' ?>
              <?php else: ?>
                <?= $editUser ? 'Editar Usuario' : 'Crear Nuevo Usuario' ?>
              <?php endif; ?>
            </h3>
            <?php if ($editUser): ?>
              <div style="margin-bottom: 1rem;">
                <a href="admin.php" class="btn btn-secondary">
                  <i class="fas fa-arrow-left"></i> Cancelar Edición
                </a>
              </div>
            <?php endif; ?>
            
            <form method="POST" class="form-grid">
              <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
              <?php if ($editUser): ?>
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
              <?php endif; ?>
              
              <div class="form-group">
                <label for="user_name">Nombre Completo *</label>
                <input type="text" id="user_name" name="name" value="<?= e($editUser['name'] ?? '') ?>" required>
              </div>
              
              <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" value="<?= e($editUser['email'] ?? '') ?>" required>
              </div>
              
              <div class="form-group">
                <label for="password"><?= $editUser ? 'Nueva Contraseña (dejar vacío para mantener la actual)' : 'Contraseña *' ?></label>
                <input type="password" id="password" name="password" <?= $editUser ? '' : 'required' ?>>
              </div>
              
              <div class="form-group">
                <label for="role">Rol *</label>
                <?php if (is_admin_finca()): ?>
                <select id="role" name="role" required>
                  <option value="veterinario" <?= ($editUser['role'] ?? '') == 'veterinario' ? 'selected' : '' ?>>Veterinario</option>
                </select>
                <input type="hidden" name="role" value="veterinario">
                <?php else: ?>
                <select id="role" name="role" required>
                  <option value="">Seleccionar rol</option>
                  <option value="admin_general" <?= ($editUser['role'] ?? '') == 'admin_general' ? 'selected' : '' ?>>Administrador General</option>
                  <option value="admin_finca" <?= ($editUser['role'] ?? '') == 'admin_finca' ? 'selected' : '' ?>>Administrador de Finca</option>
                  <option value="veterinario" <?= ($editUser['role'] ?? '') == 'veterinario' ? 'selected' : '' ?>>Veterinario</option>
                  <option value="user" <?= ($editUser['role'] ?? '') == 'user' ? 'selected' : '' ?>>Usuario</option>
                </select>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <label for="user_farm_id">Finca</label>
                <select id="user_farm_id" name="farm_id">
                  <option value="">Sin finca asignada</option>
                  <?php foreach ($farms as $farm): ?>
                    <option value="<?= $farm['id'] ?>" <?= ($editUser['farm_id'] ?? '') == $farm['id'] ? 'selected' : '' ?>>
                      <?= e($farm['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> <?= $editUser ? 'Actualizar Usuario' : 'Crear Usuario' ?>
                </button>
              </div>
              </form>
          </div>
          
          <div class="admin-card">
            <h3><i class="fas fa-list"></i> 
              <?php if (is_admin_finca()): ?>
                Lista de Veterinarios
              <?php else: ?>
                Lista de Usuarios
              <?php endif; ?>
            </h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Email</th>
                  <th>Rol</th>
                  <th>Finca</th>
                  <th>Fecha Registro</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $userItem): ?>
                  <tr>
                    <td><?= $userItem['id'] ?></td>
                    <td><?= e($userItem['name']) ?></td>
                    <td><?= e($userItem['email']) ?></td>
                    <td>
                      <span style="background: <?= $userItem['role'] == 'admin' ? 'var(--primary-green)' : 'var(--gray-400)' ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                        <?= e($userItem['role']) ?>
                      </span>
                    </td>
                    <td><?= e($userItem['farm_name'] ?? 'Sin asignar') ?></td>
                    <td><?= date('d/m/Y', strtotime($userItem['created_at'])) ?></td>
                    <td>
                      <a href="?edit_user=<?= $userItem['id'] ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-edit"></i> Editar
                      </a>
                      <?php if ($userItem['id'] != $user['id']): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Eliminar
                          </button>
                        </form>
                      <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

        <!-- Farms Section -->
        <div id="farms-section" class="content-section">
          <?php 
          // Obtener fincas
          $stmt = $pdo->query("SELECT * FROM farms ORDER BY name");
          $allFarms = $stmt->fetchAll();
          
          // Variables para edición
          $editFarm = null;
          if (isset($_GET['edit_farm'])) {
            $editStmt = $pdo->prepare("SELECT * FROM farms WHERE id = ?");
            $editStmt->execute([$_GET['edit_farm']]);
            $editFarm = $editStmt->fetch();
          }
          ?>
          
          <div class="admin-card">
            <h3><i class="fas fa-plus-circle"></i> <?= $editFarm ? 'Editar Finca' : 'Registrar Nueva Finca' ?></h3>
            <?php if ($editFarm): ?>
              <div style="margin-bottom: 1rem;">
                <a href="admin.php" class="btn btn-secondary">
                  <i class="fas fa-arrow-left"></i> Cancelar Edición
                </a>
</div>
            <?php endif; ?>
            
            <form method="POST" class="form-grid">
              <input type="hidden" name="action" value="<?= $editFarm ? 'edit_farm' : 'add_farm' ?>">
              <?php if ($editFarm): ?>
                <input type="hidden" name="farm_id" value="<?= $editFarm['id'] ?>">
              <?php endif; ?>
              
              <div class="form-group">
                <label for="farm_name">Nombre de la Finca *</label>
                <input type="text" id="farm_name" name="name" value="<?= e($editFarm['name'] ?? '') ?>" required>
              </div>
              
              <div class="form-group">
                <label for="farm_location">Ubicación</label>
                <input type="text" id="farm_location" name="location" value="<?= e($editFarm['location'] ?? '') ?>" placeholder="Ciudad, Departamento">
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> <?= $editFarm ? 'Actualizar Finca' : 'Crear Finca' ?>
                </button>
              </div>
            </form>
          </div>
          
          <div class="admin-card">
            <h3><i class="fas fa-list"></i> Lista de Fincas</h3>
            <?php if (empty($allFarms)): ?>
              <p style="color: var(--gray-600); text-align: center; padding: 2rem;">No hay fincas registradas</p>
            <?php else: ?>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Ubicación</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allFarms as $farm): ?>
                    <tr>
                      <td><?= $farm['id'] ?></td>
                      <td><?= e($farm['name']) ?></td>
                      <td><?= e($farm['location'] ?? 'No especificada') ?></td>
                      <td>
                        <a href="?edit_farm=<?= $farm['id'] ?>" class="btn btn-sm btn-secondary">
                          <i class="fas fa-edit"></i> Editar
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta finca? Los animales asociados no se eliminarán, pero perderán la relación con la finca.')">
                          <input type="hidden" name="action" value="delete_farm">
                          <input type="hidden" name="farm_id" value="<?= $farm['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Eliminar
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Veterinary Section -->
        <div id="veterinary-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-stethoscope"></i> Módulo Veterinario</h3>
            <p style="color: var(--gray-600); margin-bottom: 1.5rem;">
              Acceso rápido a las funcionalidades veterinarias del sistema.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-pills" style="font-size: 2rem; color: var(--primary-green); margin-bottom: 1rem;"></i>
                <h4>Tratamientos</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Registrar y consultar tratamientos veterinarios</p>
                <a href="veterinary.php" class="btn btn-sm">
                  <i class="fas fa-external-link-alt"></i> Ir al Módulo
                </a>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--warning); margin-bottom: 1rem;"></i>
                <h4>Alertas Sanitarias</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Gestionar alertas automáticas</p>
                <a href="veterinary.php" class="btn btn-sm">
                  <i class="fas fa-external-link-alt"></i> Ver Alertas
                </a>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-shield-virus" style="font-size: 2rem; color: var(--error); margin-bottom: 1rem;"></i>
                <h4>Cuarentenas</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Administrar cuarentenas</p>
                <a href="veterinary.php" class="btn btn-sm">
                  <i class="fas fa-external-link-alt"></i> Ver Cuarentenas
                </a>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-chart-line" style="font-size: 2rem; color: var(--info); margin-bottom: 1rem;"></i>
                <h4>Reportes</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Generar reportes sanitarios</p>
                <a href="veterinary.php" class="btn btn-sm">
                  <i class="fas fa-external-link-alt"></i> Generar Reportes
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Reports Section -->
        <div id="reports-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-chart-line"></i> Reportes y Estadísticas</h3>
            <p>Genera reportes detallados del sistema.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-file-alt" style="font-size: 2rem; color: var(--primary-green); margin-bottom: 1rem;"></i>
                <h4>Reporte de Animales</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Inventario completo de animales</p>
                <button class="btn btn-sm" onclick="generateReport('animals')">
                  <i class="fas fa-download"></i> Generar
                </button>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-users" style="font-size: 2rem; color: var(--info); margin-bottom: 1rem;"></i>
                <h4>Reporte de Usuarios</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Lista de usuarios registrados</p>
                <button class="btn btn-sm" onclick="generateReport('users')">
                  <i class="fas fa-download"></i> Generar
                </button>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-store" style="font-size: 2rem; color: var(--warning); margin-bottom: 1rem;"></i>
                <h4>Reporte de Catálogo</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Productos disponibles</p>
                <button class="btn btn-sm" onclick="generateReport('catalog')">
                  <i class="fas fa-download"></i> Generar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Nominations Section -->
        <?php if (is_admin_general() || is_admin_finca()): ?>
        <div id="nominations-section" class="content-section">
          <div class="admin-card">
            <h3>
              <i class="fas fa-paper-plane"></i> 
              <?= is_admin_general() ? 'Postulaciones al Catálogo' : 'Mis Postulaciones' ?>
            </h3>
            <p><?= is_admin_general() ? 'Revisa y aprueba las postulaciones de animales y lotes al catálogo público' : 'Visualiza el estado de tus postulaciones al catálogo' ?></p>
            
            <?php if (empty($nominations)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
              <i class="fas fa-inbox" style="font-size: 4rem; margin-bottom: 1rem;"></i>
              <p>No hay postulaciones pendientes</p>
            </div>
            <?php else: ?>
            <div style="margin-top: 2rem;">
              <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <thead style="background: var(--primary-green); color: white;">
                  <tr>
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #1a4720; font-weight: 600;">Fecha</th>
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #1a4720; font-weight: 600;">Tipo</th>
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #1a4720; font-weight: 600;">Item</th>
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #1a4720; font-weight: 600;">Finca</th>
                    <?php if (is_admin_general()): ?>
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #1a4720; font-weight: 600;">Postulado por</th>
                    <?php endif; ?>
                    <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #1a4720; font-weight: 600;">Estado</th>
                    <?php if (is_admin_general()): ?>
                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #1a4720; font-weight: 600;">Acciones</th>
                    <?php else: ?>
                    <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #1a4720; font-weight: 600;">Detalles</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($nominations as $nom): ?>
                  <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 1rem; border-right: 1px solid #f3f4f6; color: var(--gray-700); font-size: 0.875rem;">
                      <?= date('d/m/Y H:i', strtotime($nom['created_at'])) ?>
                    </td>
                    <td style="padding: 1rem; border-right: 1px solid #f3f4f6;">
                      <span style="padding: 0.4rem 0.75rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; display: inline-block; 
                        <?php if ($nom['item_type'] === 'animal'): ?>
                        background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd;
                        <?php else: ?>
                        background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;
                        <?php endif; ?>">
                        <i class="fas fa-<?= $nom['item_type'] === 'animal' ? 'cow' : 'layer-group' ?>"></i>
                        <?= $nom['item_type'] === 'animal' ? 'Animal' : 'Lote' ?>
                      </span>
                    </td>
                    <td style="padding: 1rem; border-right: 1px solid #f3f4f6; font-weight: 500; color: var(--gray-800);">
                      <?= e($nom['animal_name'] ?? $nom['lot_name'] ?? 'N/A') ?>
                    </td>
                    <td style="padding: 1rem; border-right: 1px solid #f3f4f6; color: var(--gray-700);">
                      <i class="fas fa-home" style="margin-right: 0.5rem; color: var(--gray-400);"></i>
                      <?= e($nom['farm_name'] ?? 'N/A') ?>
                    </td>
                    <?php if (is_admin_general()): ?>
                    <td style="padding: 1rem; border-right: 1px solid #f3f4f6; color: var(--gray-700);">
                      <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--gray-400);"></i>
                      <?= e($nom['proposed_by_name'] ?? 'N/A') ?>
                    </td>
                    <?php endif; ?>
                    <td style="padding: 1rem; border-right: 1px solid #f3f4f6;">
                      <span style="padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 600; display: inline-block;
                        <?php if ($nom['status'] === 'pending'): ?>
                        background: #fef3c7; color: #92400e; border: 1px solid #fbbf24;
                        <?php elseif ($nom['status'] === 'approved'): ?>
                        background: #d1fae5; color: #065f46; border: 1px solid #34d399;
                        <?php else: ?>
                        background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
                        <?php endif; ?>">
                        <i class="fas fa-<?= $nom['status'] === 'pending' ? 'clock' : ($nom['status'] === 'approved' ? 'check-circle' : 'times-circle') ?>"></i>
                        <?= ucfirst($nom['status']) ?>
                      </span>
                    </td>
                    <td style="padding: 1rem; text-align: center;">
                      <div style="display: inline-flex; gap: 0.5rem;">
                      <?php if (is_admin_general() && $nom['status'] === 'pending'): ?>
                      <button class="btn btn-sm btn-success" onclick="approveNomination(<?= $nom['id'] ?>)" style="border-radius: 6px;">
                        <i class="fas fa-check"></i> Aprobar
                      </button>
                      <button class="btn btn-sm btn-danger" onclick="rejectNomination(<?= $nom['id'] ?>)" style="border-radius: 6px;">
                        <i class="fas fa-times"></i> Rechazar
                      </button>
                      <?php else: ?>
                      <button class="btn btn-sm btn-info" onclick="viewNominationDetails(<?= $nom['id'] ?>)" style="border-radius: 6px;">
                        <i class="fas fa-eye"></i> Ver
                      </button>
                      <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Quotes Section -->
        <?php if(is_admin_general()): ?>
        <div id="quotes-section" class="content-section">
          <div class="admin-card">
            <h3>
              <i class="fas fa-calculator"></i> 
              Gestión de Cotizaciones
            </h3>
            <p>Gestiona las solicitudes de cotización de clientes. Cambia el estado y notifica automáticamente al cliente.</p>
            
            <?php if (empty($quotes)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
              <i class="fas fa-inbox" style="font-size: 4rem; margin-bottom: 1rem;"></i>
              <p>No hay cotizaciones registradas</p>
            </div>
            <?php else: ?>
            <div style="margin-top: 2rem;">
              <style>
                .quotes-table-container {
                  overflow-x: auto;
                  -webkit-overflow-scrolling: touch;
                  margin: 0 -1rem;
                  padding: 0 1rem;
                }
                .quotes-table {
                  width: 100%;
                  min-width: 1000px;
                  border-collapse: collapse;
                  background: white;
                  border-radius: 8px;
                  overflow: hidden;
                  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .quotes-table th,
                .quotes-table td {
                  padding: 0.75rem;
                  text-align: left;
                  border-right: 1px solid #f3f4f6;
                }
                .quotes-table th {
                  background: var(--primary-green);
                  color: white;
                  border-bottom: 2px solid #1a4720;
                  font-weight: 600;
                  white-space: nowrap;
                }
                .quotes-table td {
                  color: var(--gray-700);
                  font-size: 0.875rem;
                }
                .quotes-table tbody tr {
                  border-bottom: 1px solid #e5e7eb;
                }
                .quotes-table tbody tr:hover {
                  background: #f9fafb;
                }
                @media (max-width: 1200px) {
                  .quotes-table {
                    min-width: 900px;
                  }
                  .quotes-table th,
                  .quotes-table td {
                    padding: 0.6rem 0.5rem;
                    font-size: 0.8125rem;
                  }
                }
                @media (max-width: 768px) {
                  .quotes-table {
                    min-width: 800px;
                  }
                  .quotes-table th,
                  .quotes-table td {
                    padding: 0.5rem 0.4rem;
                    font-size: 0.75rem;
                  }
                }
                .quote-status-badge {
                  padding: 0.4rem 0.75rem;
                  border-radius: 6px;
                  font-size: 0.75rem;
                  font-weight: 600;
                  display: inline-block;
                  white-space: nowrap;
                }
                .quote-type-badge {
                  padding: 0.3rem 0.6rem;
                  border-radius: 6px;
                  font-size: 0.75rem;
                  font-weight: 600;
                  display: inline-block;
                  white-space: nowrap;
                }
              </style>
              <div class="quotes-table-container">
                <table class="quotes-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Fecha</th>
                      <th>Tipo</th>
                      <th>Item</th>
                      <th>Cliente</th>
                      <th>Email</th>
                      <th>Teléfono</th>
                      <th>Estado</th>
                      <th style="text-align: center;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($quotes as $quote): ?>
                    <tr>
                      <td style="font-weight: 600; color: var(--gray-700);">
                        #<?= $quote['id'] ?>
                      </td>
                      <td style="font-size: 0.8125rem;">
                        <?= date('d/m/Y H:i', strtotime($quote['created_at'])) ?>
                      </td>
                      <td>
                        <span class="quote-type-badge" style="
                          <?php if ($quote['item_type'] === 'animal'): ?>
                          background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd;
                          <?php else: ?>
                          background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;
                          <?php endif; ?>">
                          <i class="fas fa-<?= $quote['item_type'] === 'animal' ? 'cow' : 'layer-group' ?>"></i>
                          <?= $quote['item_type'] === 'animal' ? 'Animal' : 'Lote' ?>
                        </span>
                      </td>
                      <td style="font-weight: 500; color: var(--gray-800); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php if ($quote['item_type'] === 'animal'): ?>
                          <?= e($quote['animal_tag_code'] ?? 'N/A') . ($quote['animal_name'] ? ' - ' . e($quote['animal_name']) : '') ?>
                        <?php else: ?>
                          <?= e($quote['lot_name'] ?? 'N/A') ?>
                        <?php endif; ?>
                      </td>
                      <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis;">
                        <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--gray-400);"></i>
                        <span style="white-space: nowrap;"><?= e($quote['customer_name']) ?></span>
                      </td>
                      <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                        <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--gray-400);"></i>
                        <span style="white-space: nowrap;"><?= e($quote['customer_email']) ?></span>
                      </td>
                      <td>
                        <i class="fas fa-phone" style="margin-right: 0.5rem; color: var(--gray-400);"></i>
                        <?= e($quote['customer_phone']) ?>
                      </td>
                      <td>
                        <span class="quote-status-badge" style="
                          <?php if ($quote['status'] === 'pendiente'): ?>
                          background: #fef3c7; color: #92400e; border: 1px solid #fbbf24;
                          <?php elseif ($quote['status'] === 'en_proceso'): ?>
                          background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd;
                          <?php else: ?>
                          background: #d1fae5; color: #065f46; border: 1px solid #34d399;
                          <?php endif; ?>">
                          <i class="fas fa-<?= $quote['status'] === 'pendiente' ? 'clock' : ($quote['status'] === 'en_proceso' ? 'spinner' : 'check-circle') ?>"></i>
                          <?= ucfirst(str_replace('_', ' ', $quote['status'])) ?>
                        </span>
                      </td>
                      <td style="text-align: center; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                          <select onchange="updateQuoteStatus(<?= $quote['id'] ?>, this.value)" 
                                  style="padding: 0.4rem 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.8125rem; cursor: pointer; background: white; min-width: 120px;">
                            <option value="pendiente" <?= $quote['status'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="en_proceso" <?= $quote['status'] === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                            <option value="respondida" <?= $quote['status'] === 'respondida' ? 'selected' : '' ?>>Respondida</option>
                          </select>
                          <?php if ($quote['customer_message']): ?>
                          <button class="btn btn-sm btn-secondary" onclick="showQuoteMessage(<?= $quote['id'] ?>, '<?= addslashes(e($quote['customer_message'])) ?>')" 
                                  style="border-radius: 6px; padding: 0.4rem 0.6rem;" title="Ver mensaje">
                            <i class="fas fa-comment"></i>
                          </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Farms Section -->
        <?php if(is_admin_general()): ?>
        <div id="farms-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-home"></i> Gestión de Fincas</h3>
            
            <!-- Add Farm Form -->
            <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
              <h4 style="margin-bottom: 1rem;">Agregar Nueva Finca</h4>
              <form method="POST" class="grid grid-2">
                <input type="hidden" name="action" value="add_farm">
                <label>
                  Nombre de la Finca *
                  <input type="text" name="name" required>
                </label>
                <label>
                  Ubicación
                  <input type="text" name="location" placeholder="Ciudad, Departamento">
                </label>
                <div>
                  <button type="submit" class="btn"><i class="fas fa-plus"></i> Agregar Finca</button>
                </div>
              </form>
            </div>

            <!-- Farms List -->
            <h4 style="margin-bottom: 1rem;">Fincas Registradas</h4>
            <?php
            $stmt = $pdo->query("SELECT f.*, 
                                 COUNT(DISTINCT a.id) as animal_count, 
                                 COUNT(DISTINCT u.id) as user_count,
                                 COUNT(DISTINCT l.id) as lot_count
                                 FROM farms f 
                                 LEFT JOIN animals a ON a.farm_id = f.id 
                                 LEFT JOIN users u ON u.farm_id = f.id
                                 LEFT JOIN lots l ON l.farm_id = f.id
                                 GROUP BY f.id 
                                 ORDER BY f.name");
            $farms = $stmt->fetchAll();
            
            if (empty($farms)):
            ?>
              <p style="color: var(--gray-500); text-align: center; padding: 2rem;">
                <i class="fas fa-info-circle"></i> No hay fincas registradas
              </p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Ubicación</th>
                      <th>Animales</th>
                      <th>Usuarios</th>
                      <th>Lotes</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($farms as $farm): ?>
                      <tr>
                        <td><?= e($farm['id']) ?></td>
                        <td><strong><?= e($farm['name']) ?></strong></td>
                        <td><?= e($farm['location'] ?? 'No especificada') ?></td>
                        <td>
                          <span class="badge badge-info">
                            <i class="fas fa-cow"></i> <?= e($farm['animal_count']) ?>
                          </span>
                        </td>
                        <td>
                          <span class="badge badge-info">
                            <i class="fas fa-users"></i> <?= e($farm['user_count']) ?>
                          </span>
                        </td>
                        <td>
                          <span class="badge badge-info">
                            <i class="fas fa-boxes"></i> <?= e($farm['lot_count']) ?>
                          </span>
                        </td>
                        <td>
                          <button class="btn btn-sm btn-secondary" onclick="editFarm(<?= $farm['id'] ?>, '<?= e($farm['name']) ?>', '<?= e($farm['location'] ?? '') ?>')">
                            <i class="fas fa-edit"></i> Editar
                          </button>
                          <?php if($farm['animal_count'] == 0 && $farm['user_count'] == 0 && $farm['lot_count'] == 0): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta finca?')">
                              <input type="hidden" name="action" value="delete_farm">
                              <input type="hidden" name="farm_id" value="<?= $farm['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Eliminar
                              </button>
                            </form>
                          <?php else: ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta finca? Los registros asociados (animales, usuarios, lotes) se desasociarán automáticamente.')">
                              <input type="hidden" name="action" value="delete_farm">
                              <input type="hidden" name="farm_id" value="<?= $farm['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-danger" title="Eliminar (se desasociarán <?= e($farm['animal_count'] + $farm['user_count'] + $farm['lot_count']) ?> registro(s) asociado(s))">
                                <i class="fas fa-trash"></i> Eliminar
                              </button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Edit Farm Modal -->
          <div id="editFarmModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
              <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-edit"></i> Editar Finca</h3>
              <form method="POST" id="editFarmForm">
                <input type="hidden" name="action" value="edit_farm">
                <input type="hidden" name="farm_id" id="editFarmId">
                <label style="margin-bottom: 1rem; display: block;">
                  Nombre de la Finca *
                  <input type="text" name="name" id="editFarmName" required style="width: 100%;">
                </label>
                <label style="margin-bottom: 1.5rem; display: block;">
                  Ubicación
                  <input type="text" name="location" id="editFarmLocation" style="width: 100%;">
                </label>
                <div style="display: flex; gap: 1rem;">
                  <button type="submit" class="btn" style="flex: 1;"><i class="fas fa-save"></i> Guardar</button>
                  <button type="button" class="btn btn-secondary" onclick="closeEditFarmModal()" style="flex: 1;">Cancelar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Carousel Section -->
        <?php if(is_admin_general()): ?>
        <div id="carousel-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-images"></i> Gestión del Carrusel Principal</h3>
            <p>Gestiona las imágenes del carrusel que se muestra en la página principal (index.php).</p>
            
            <!-- Formulario para agregar nueva imagen -->
            <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
              <h4 style="margin-bottom: 1rem;"><i class="fas fa-plus-circle"></i> Agregar Nueva Imagen</h4>
              <form method="POST" enctype="multipart/form-data" class="grid grid-2">
                <input type="hidden" name="action" value="add_carousel_image">
                
                <div class="form-group" style="grid-column: 1 / -1;">
                  <label for="carousel_image">Imagen *</label>
                  <input type="file" id="carousel_image" name="carousel_image" accept="image/*" required>
                  <small style="color: var(--gray-600);">Formatos permitidos: JPG, PNG, GIF, WEBP. Máximo 10MB</small>
                </div>
                
                <div class="form-group">
                  <label for="carousel_title">Título</label>
                  <input type="text" id="carousel_title" name="title" placeholder="Ej: Holstein Premium en Venta">
                </div>
                
                <div class="form-group">
                  <label for="carousel_sort_order">Orden</label>
                  <input type="number" id="carousel_sort_order" name="sort_order" value="0" min="0">
                  <small style="color: var(--gray-600);">Menor número = aparece primero</small>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                  <label for="carousel_description">Descripción</label>
                  <textarea id="carousel_description" name="description" rows="3" placeholder="Ej: Exemplares certificados disponibles en nuestro catálogo"></textarea>
                </div>
                
                <div style="grid-column: 1 / -1;">
                  <button type="submit" class="btn"><i class="fas fa-upload"></i> Subir Imagen</button>
                </div>
              </form>
            </div>
            
            <!-- Lista de imágenes existentes -->
            <h4 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Imágenes del Carrusel</h4>
            <?php if (empty($carousel_images)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
              <i class="fas fa-images" style="font-size: 4rem; margin-bottom: 1rem;"></i>
              <p>No hay imágenes en el carrusel. Agrega la primera imagen arriba.</p>
            </div>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
              <?php foreach ($carousel_images as $img): ?>
              <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="position: relative; width: 100%; height: 200px; overflow: hidden; background: #f3f4f6;">
                  <img src="<?= e($img['file_path']) ?>" alt="<?= e($img['title'] ?? 'Imagen del carrusel') ?>" 
                       style="width: 100%; height: 100%; object-fit: cover;">
                  <?php if (!$img['is_active']): ?>
                  <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;">
                    <span style="background: #ef4444; color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600;">INACTIVA</span>
                  </div>
                  <?php endif; ?>
                </div>
                <div style="padding: 1rem;">
                  <form method="POST" style="margin-bottom: 0.5rem;">
                    <input type="hidden" name="action" value="update_carousel_image">
                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                    
                    <div style="margin-bottom: 0.75rem;">
                      <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">Título</label>
                      <input type="text" name="title" value="<?= e($img['title'] ?? '') ?>" 
                             style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem;">
                    </div>
                    
                    <div style="margin-bottom: 0.75rem;">
                      <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">Descripción</label>
                      <textarea name="description" rows="2" 
                                style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem; resize: vertical;"><?= e($img['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                      <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">Orden</label>
                        <input type="number" name="sort_order" value="<?= $img['sort_order'] ?>" min="0"
                               style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem;">
                      </div>
                      <div style="display: flex; align-items: end;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; background: <?= $img['is_active'] ? '#d1fae5' : '#fee2e2' ?>; width: 100%;">
                          <input type="checkbox" name="is_active" <?= $img['is_active'] ? 'checked' : '' ?> style="margin: 0;">
                          <span style="font-size: 0.875rem; font-weight: 600;"><?= $img['is_active'] ? 'Activa' : 'Inactiva' ?></span>
                        </label>
                      </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                      <button type="submit" class="btn btn-sm" style="flex: 1;">
                        <i class="fas fa-save"></i> Guardar
                      </button>
                      <button type="button" class="btn btn-sm btn-danger" 
                              onclick="if(confirm('¿Estás seguro de eliminar esta imagen?')) { const form = document.createElement('form'); form.method = 'POST'; form.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'delete_carousel_image\'><input type=\'hidden\' name=\'image_id\' value=\'<?= $img['id'] ?>\'>'; document.body.appendChild(form); form.submit(); }"
                              style="flex: 1;">
                        <i class="fas fa-trash"></i> Eliminar
                      </button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Settings Section -->
        <div id="settings-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-cog"></i> Configuración del Sistema</h3>
            <p>Configuración general del sistema Rc El Bosque.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px;">
                <h4><i class="fas fa-database"></i> Base de Datos</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Gestión de la base de datos</p>
                <button class="btn btn-sm" onclick="backupDatabase()">
                  <i class="fas fa-download"></i> Respaldo
                </button>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px;">
                <h4><i class="fas fa-shield-alt"></i> Seguridad</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Configuración de seguridad</p>
                <button class="btn btn-sm" onclick="showSecuritySettings()">
                  <i class="fas fa-cog"></i> Configurar
                </button>
              </div>
              
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px;">
                <h4><i class="fas fa-bell"></i> Notificaciones</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Configurar alertas</p>
                <button class="btn btn-sm" onclick="showNotificationSettings()">
                  <i class="fas fa-cog"></i> Configurar
                </button>
              </div>

              <?php if (is_admin_general()): ?>
              <div style="background: var(--gray-50); padding: 1.5rem; border-radius: 12px;">
                <h4><i class="fas fa-layer-group"></i> Tipos de Lote</h4>
                <p style="color: var(--gray-600); margin-bottom: 1rem;">Gestiona los tipos disponibles para crear lotes</p>
                <form method="POST" class="form-grid" style="grid-template-columns: 1fr auto; gap: 0.75rem; align-items: end;">
                  <input type="hidden" name="action" value="add_lot_type">
                  <label>Nuevo tipo
                    <input name="name" placeholder="p.ej. mejoramiento" required>
                  </label>
                  <button class="btn btn-sm" type="submit"><i class="fas fa-plus"></i> Agregar</button>
                </form>
                <div style="margin-top: 1rem;">
                  <?php if (!empty($lotTypes)): ?>
                  <table class="data-table">
                    <thead><tr><th>Tipo</th><th style="width:120px">Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach ($lotTypes as $t): ?>
                        <tr>
                          <td><?= e(ucfirst($t)) ?></td>
                          <td>
                            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar tipo de lote?')">
                              <input type="hidden" name="action" value="delete_lot_type">
                              <input type="hidden" name="name" value="<?= e($t) ?>">
                              <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  <?php else: ?>
                    <p style="color: var(--gray-600);">No hay tipos definidos.</p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Section navigation
    function showSection(sectionName) {
      // Hide all sections
      document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
      });
      
      // Remove active class from all nav items
      document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
      });
      
      // Show selected section
      const targetSection = document.getElementById(sectionName + '-section');
      if (targetSection) {
        targetSection.classList.add('active');
      } else {
        console.warn('Sección no encontrada:', sectionName + '-section');
        // Mostrar dashboard por defecto
        const dashboardSection = document.getElementById('dashboard-section');
        if (dashboardSection) {
          dashboardSection.classList.add('active');
        }
      }
      
      // Add active class to clicked nav item
      if (event && event.target) {
        event.target.classList.add('active');
      }
      
      // Update section title
      const titles = {
        'dashboard': 'Dashboard',
        'animals': 'Gestión de Animales',
        'catalog': 'Catálogo',
        'lots': 'Gestión de Lotes',
        'users': 'Usuarios',
        'farms': 'Gestión de Fincas',
        'nominations': 'Postulaciones',
        'quotes': 'Cotizaciones',
        'veterinary': 'Módulo Veterinario',
        'reports': 'Reportes',
        'carousel': 'Carrusel Principal',
        'settings': 'Configuración'
      };
      document.getElementById('section-title').textContent = titles[sectionName] || 'Panel Administrativo';
    }

    // Refresh data
    function refreshData() {
      location.reload();
    }

    // Generate reports
    function generateReport(type) {
      alert('Función de generación de reportes en desarrollo para: ' + type);
    }

    // Database backup
    function backupDatabase() {
      alert('Función de respaldo en desarrollo');
    }

    // Security settings
    function showSecuritySettings() {
      alert('Configuración de seguridad en desarrollo');
    }

    // Notification settings
    function showNotificationSettings() {
      alert('Configuración de notificaciones en desarrollo');
    }

    // Catalog visibility functions
    function toggleAnimalVisibility(animalId, visibility) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input type="hidden" name="action" value="toggle_catalog_animal">
        <input type="hidden" name="animal_id" value="${animalId}">
        <input type="hidden" name="in_cat" value="${visibility ? '1' : '0'}">
      `;
      document.body.appendChild(form);
      form.submit();
    }

    function toggleAllCheckboxes() {
      const selectAll = document.getElementById('select-all');
      const checkboxes = document.querySelectorAll('.animal-checkbox');
      
      checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
      });
    }

    function toggleAllAnimals(visibility) {
      if (confirm(`¿Estás seguro de ${visibility ? 'mostrar' : 'ocultar'} todos los animales en el catálogo?`)) {
        const checkboxes = document.querySelectorAll('.animal-checkbox');
        const animalIds = Array.from(checkboxes).map(cb => cb.dataset.animalId);
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="bulk_toggle_animals">
          <input type="hidden" name="visibility" value="${visibility ? '1' : '0'}">
          ${animalIds.map(id => `<input type="hidden" name="animal_ids[]" value="${id}">`).join('')}
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    

    function bulkToggleVisibility(visibility) {
      const checkedBoxes = document.querySelectorAll('.animal-checkbox:checked');
      
      if (checkedBoxes.length === 0) {
        alert('Por favor selecciona al menos un animal.');
        return;
      }
      
      const action = visibility ? 'mostrar' : 'ocultar';
      if (confirm(`¿Estás seguro de ${action} los ${checkedBoxes.length} animales seleccionados?`)) {
        const animalIds = Array.from(checkedBoxes).map(cb => cb.dataset.animalId);
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="bulk_toggle_animals">
          <input type="hidden" name="visibility" value="${visibility ? '1' : '0'}">
          ${animalIds.map(id => `<input type="hidden" name="animal_ids[]" value="${id}">`).join('')}
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function clearSelection() {
      const checkboxes = document.querySelectorAll('.animal-checkbox');
      checkboxes.forEach(checkbox => checkbox.checked = false);
      document.getElementById('select-all').checked = false;
    }

    function viewLotDetails(lotId) {
      alert('Ver detalles del lote ' + lotId);
    }

    function editLot(lotId) {
      alert('Editar lote ' + lotId);
    }

    // Nomination management functions
    function approveNomination(nominationId) {
      if (confirm('¿Estás seguro de aprobar esta postulación?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="approve_nomination">
          <input type="hidden" name="nomination_id" value="${nominationId}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function rejectNomination(nominationId) {
      const reason = prompt('¿Por qué deseas rechazar esta postulación? (opcional)');
      if (reason !== null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="reject_nomination">
          <input type="hidden" name="nomination_id" value="${nominationId}">
          <input type="hidden" name="reason" value="${reason || ''}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }

    function updateQuoteStatus(quoteId, newStatus) {
      if (confirm('¿Estás seguro de cambiar el estado de esta cotización? Se enviará un correo de notificación al cliente.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="update_quote_status">
          <input type="hidden" name="quote_id" value="${quoteId}">
          <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
      } else {
        // Restaurar el valor anterior del select
        location.reload();
      }
    }

    function showQuoteMessage(quoteId, message) {
      alert('Mensaje del Cliente (Cotización #' + quoteId + '):\n\n' + message);
    }

    function viewNominationDetails(nominationId) {
      alert('Ver detalles de la postulación ' + nominationId);
    }

    // Auto-hide flash messages
    setTimeout(() => {
      document.querySelectorAll('.flash-message').forEach(msg => {
        msg.style.animation = 'slideInRight 0.3s ease-out reverse';
        setTimeout(() => msg.remove(), 300);
      });
    }, 5000);

    // Mobile sidebar toggle
    function toggleSidebar() {
      document.querySelector('.admin-sidebar').classList.toggle('open');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
      if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle');
        
        if (!sidebar.contains(e.target) && !toggleBtn?.contains(e.target)) {
          sidebar.classList.remove('open');
        }
      }
    });

    // Validación de código de animal en tiempo real
    function validateTagCode(input) {
      const tagCode = input.value.trim();
      const errorElement = document.getElementById('tag-code-error');
      
      if (tagCode === '') {
        errorElement.textContent = 'El código de animal es obligatorio.';
        errorElement.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
        return false;
      }
      
      if (tagCode.length > 80) {
        errorElement.textContent = 'El código no puede tener más de 80 caracteres.';
        errorElement.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
        return false;
      }
      
      // Código válido
      errorElement.style.display = 'none';
      input.style.borderColor = 'var(--success)';
      return true;
    }
    
    // Validación de peso en tiempo real
    function validateWeight(input) {
      const weight = parseFloat(input.value);
      const errorElement = document.getElementById('weight-error');
      
      if (input.value === '' || input.value === null) {
        // Si está vacío, está bien (es opcional)
        errorElement.style.display = 'none';
        input.style.borderColor = '';
        return true;
      }
      
      if (isNaN(weight)) {
        errorElement.textContent = 'Por favor ingresa un número válido.';
        errorElement.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
        return false;
      }
      
      if (weight < 20) {
        errorElement.textContent = 'El peso mínimo es 20 kg.';
        errorElement.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
        return false;
      }
      
      if (weight > 1000) {
        errorElement.textContent = 'El peso máximo es 1000 kg.';
        errorElement.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
        return false;
      }
      
      // Peso válido
      errorElement.style.display = 'none';
      input.style.borderColor = 'var(--success)';
      return true;
    }
    
    // Auto-open animals section if editing an animal
    window.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('edit_animal')) {
        showSection('animals');
      }
      // Inicializar contador de animales al cargar la página
      updateAnimalCount();
      
      // Validar formulario antes de enviar
      const animalForm = document.querySelector('form[method="POST"]');
      if (animalForm) {
        animalForm.addEventListener('submit', function(e) {
          let isValid = true;
          let firstErrorField = null;
          
          // Validar código de animal
          const tagCodeInput = document.getElementById('tag_code');
          if (tagCodeInput && !validateTagCode(tagCodeInput)) {
            isValid = false;
            if (!firstErrorField) firstErrorField = tagCodeInput;
          }
          
          // Validar peso si existe
          const weightInput = document.getElementById('weight');
          if (weightInput && !validateWeight(weightInput)) {
            isValid = false;
            if (!firstErrorField) firstErrorField = weightInput;
          }
          
          if (!isValid) {
            e.preventDefault();
            if (firstErrorField) firstErrorField.focus();
            return false;
          }
        });
      }
    });

    // Actualizar contador de animales seleccionados en el formulario de lotes
    function updateAnimalCount() {
      // Contar solo los checkboxes marcados (visibles o no)
      const checkboxes = document.querySelectorAll('.lot-animal-checkbox:checked');
      const count = checkboxes.length;
      const countElement = document.getElementById('selected-animals-count');
      const labelElement = document.getElementById('selected-animals-label');
      
      if (countElement) {
        countElement.textContent = count;
      }
      
      if (labelElement) {
        labelElement.textContent = count === 1 ? 'animal seleccionado' : 'animales seleccionados';
      }
    }

    // Filtrar animales según búsqueda y filtros
    function filterAnimals() {
      const searchTerm = document.getElementById('animal-search').value.toLowerCase().trim();
      const filterSpecies = document.getElementById('filter-species').value.toLowerCase();
      const filterBreed = document.getElementById('filter-breed').value.toLowerCase();
      const filterGender = document.getElementById('filter-gender').value.toLowerCase();
      
      const animalItems = document.querySelectorAll('.animal-item');
      let visibleCount = 0;
      
      animalItems.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const tagCode = item.getAttribute('data-tag-code') || '';
        const species = item.getAttribute('data-species') || '';
        const breed = item.getAttribute('data-breed') || '';
        const gender = item.getAttribute('data-gender') || '';
        
        // Combinar nombre y tag code para búsqueda
        const searchableText = name + ' ' + tagCode + ' ' + species + ' ' + breed;
        
        // Aplicar filtros
        const matchesSearch = !searchTerm || searchableText.includes(searchTerm);
        const matchesSpecies = !filterSpecies || species === filterSpecies;
        const matchesBreed = !filterBreed || breed === filterBreed;
        const matchesGender = !filterGender || gender === filterGender;
        
        if (matchesSearch && matchesSpecies && matchesBreed && matchesGender) {
          item.style.display = 'block';
          item.style.background = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });
      
      // Mostrar/ocultar mensaje de "no encontrados"
      const noAnimalsMsg = document.getElementById('no-animals-message');
      if (noAnimalsMsg) {
        noAnimalsMsg.style.display = visibleCount === 0 ? 'block' : 'none';
      }
      
      // Actualizar contador después de filtrar
      updateAnimalCount();
    }

    // Limpiar todos los filtros
    function clearAnimalFilters() {
      document.getElementById('animal-search').value = '';
      document.getElementById('filter-species').value = '';
      document.getElementById('filter-breed').value = '';
      document.getElementById('filter-gender').value = '';
      filterAnimals();
    }
    
    // Mostrar logs del servidor en la consola del navegador
    <?php if (!empty($browser_logs)): ?>
    console.group('📧 Logs del Sistema de Correo');
    <?php foreach ($browser_logs as $log): ?>
      <?php
      $type = $log['type'] ?? 'info';
      $message = addslashes($log['message']);
      $timestamp = $log['timestamp'] ?? '';
      
      if ($type === 'error') {
        echo "console.error('❌ [$timestamp] $message');";
      } elseif ($type === 'success') {
        echo "console.log('%c✅ [$timestamp] $message', 'color: green; font-weight: bold');";
      } elseif ($type === 'warning') {
        echo "console.warn('⚠️ [$timestamp] $message');";
      } else {
        echo "console.log('📧 [$timestamp] $message');";
      }
      ?>
    <?php endforeach; ?>
    console.groupEnd();
    <?php endif; ?>
  </script>
</body>
</html>