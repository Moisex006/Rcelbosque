<?php
require_once '../app/config.php';
require_login(); // Ensure user is logged in
require_role(['admin_general', 'admin_finca', 'veterinario']); // Allow admin_general, admin_finca, or veterinario

// Database connection
$pdo = get_pdo();

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
        // Generar un tag_code único si no se proporciona
        $tag_code = !empty($_POST['tag_code']) ? $_POST['tag_code'] : null;
        if (empty($tag_code)) {
          // Generar un tag_code automático basado en timestamp y ID
          $tag_code = 'ANIMAL-' . date('Ymd') . '-' . time();
        }
        
        // Verificar que el tag_code no exista
        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM animals WHERE tag_code = ?");
        $check_stmt->execute([$tag_code]);
        $exists = $check_stmt->fetch();
        
        if ($exists['count'] > 0) {
          // Si existe, agregar un sufijo numérico
          $counter = 1;
          $original_tag = $tag_code;
          while ($exists['count'] > 0) {
            $tag_code = $original_tag . '-' . $counter;
            $check_stmt->execute([$tag_code]);
            $exists = $check_stmt->fetch();
            $counter++;
          }
        }
        
        $stmt = $pdo->prepare("INSERT INTO animals (tag_code, name, species_id, breed_id, birth_date, gender, weight, color, farm_id, in_cat, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
          $tag_code,
          $_POST['name'],
          !empty($_POST['species_id']) ? $_POST['species_id'] : null,
          !empty($_POST['breed_id']) ? $_POST['breed_id'] : null,
          !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
          !empty($_POST['gender']) ? $_POST['gender'] : 'indefinido',
          !empty($_POST['weight']) ? $_POST['weight'] : null,
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
            $_SESSION['flash_error'] = "Máximo {$maxPhotos} fotos permitidas";
            break;
          }
          
          foreach ($_FILES['photos']['name'] as $key => $filename) {
            if (empty($filename)) continue;
            
            $tmpName = $_FILES['photos']['tmp_name'][$key];
            $fileSize = $_FILES['photos']['size'][$key];
            $fileType = $_FILES['photos']['type'][$key];
            
            // Validar tipo de archivo
            if (!in_array($fileType, $allowedTypes)) {
              $_SESSION['flash_error'] = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
              break 2;
            }
            
            // Validar tamaño
            if ($fileSize > $maxFileSize) {
              $_SESSION['flash_error'] = 'El archivo es demasiado grande (máximo 5MB)';
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
          
          $_SESSION['flash_ok'] = 'Animal registrado exitosamente' . (!empty($uploaded_files) ? ' con ' . count($uploaded_files) . ' foto(s)' : '') . ' y postulado para el catálogo';
        } else {
          $_SESSION['flash_ok'] = 'Animal registrado exitosamente' . (!empty($uploaded_files) ? ' con ' . count($uploaded_files) . ' foto(s)' : '');
        }
        break;
        
      case 'edit_animal':
        $stmt = $pdo->prepare("UPDATE animals SET name=?, species_id=?, breed_id=?, birth_date=?, gender=?, weight=?, color=?, farm_id=?, in_cat=?, description=? WHERE id=?");
        $stmt->execute([
          $_POST['name'],
          !empty($_POST['species_id']) ? $_POST['species_id'] : null,
          !empty($_POST['breed_id']) ? $_POST['breed_id'] : null,
          !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
          !empty($_POST['gender']) ? $_POST['gender'] : 'indefinido',
          !empty($_POST['weight']) ? $_POST['weight'] : null,
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
            $_SESSION['flash_error'] = "Máximo {$maxPhotos} fotos permitidas. Ya tienes {$existingCount} fotos.";
            break;
          }
          
          foreach ($_FILES['photos']['name'] as $key => $filename) {
            if (empty($filename)) continue;
            
            $tmpName = $_FILES['photos']['tmp_name'][$key];
            $fileSize = $_FILES['photos']['size'][$key];
            $fileType = $_FILES['photos']['type'][$key];
            
            // Validar tipo de archivo
            if (!in_array($fileType, $allowedTypes)) {
              $_SESSION['flash_error'] = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
              break 2;
            }
            
            // Validar tamaño
            if ($fileSize > $maxFileSize) {
              $_SESSION['flash_error'] = 'El archivo es demasiado grande (máximo 5MB)';
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
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, farm_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
          $_POST['name'],
          $_POST['email'],
          password_hash($_POST['password'], PASSWORD_DEFAULT),
          $role,
          $_POST['farm_id'] ?? null
        ]);
        $_SESSION['flash_ok'] = 'Usuario creado exitosamente';
        break;
        
      case 'edit_user':
        $updateData = [
          $_POST['name'],
          $_POST['email'],
          $_POST['role'],
          $_POST['farm_id'] ?? null,
          $_POST['user_id']
        ];
        
        if (!empty($_POST['password'])) {
          $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=?, farm_id=? WHERE id=?");
          $updateData[2] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        } else {
          $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, farm_id=? WHERE id=?");
          array_splice($updateData, 2, 1); // Remove password from array
        }
        
        $stmt->execute($updateData);
        $_SESSION['flash_ok'] = 'Usuario actualizado exitosamente';
        break;
        
      case 'delete_user':
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_POST['user_id']]);
        $_SESSION['flash_ok'] = 'Usuario eliminado exitosamente';
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
            // Los lotes ya están visibles por defecto
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
        $stmt = $pdo->prepare("INSERT INTO lots (name, description, total_price, animal_count, lot_type, status, farm_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
          $_POST['name'],
          $_POST['description'] ?? null,
          $_POST['total_price'],
          $_POST['animal_count'] ?? 0,
          $_POST['lot_type'],
          $_POST['status'] ?? 'disponible',
          $_POST['farm_id'] ?? null,
          $user['id']
        ]);
        $lot_id = $pdo->lastInsertId();
        
        // Agregar animales al lote si se especificaron
        if (!empty($_POST['animal_ids'])) {
          foreach ($_POST['animal_ids'] as $animal_id) {
            $stmt = $pdo->prepare("INSERT INTO lot_animals (lot_id, animal_id) VALUES (?, ?)");
            $stmt->execute([$lot_id, $animal_id]);
          }
        }
        
        $_SESSION['flash_ok'] = 'Lote creado exitosamente';
        break;
        
      case 'edit_lot':
        $stmt = $pdo->prepare("UPDATE lots SET name=?, description=?, total_price=?, lot_type=?, status=?, farm_id=? WHERE id=?");
        $stmt->execute([
          $_POST['name'],
          $_POST['description'] ?? null,
          $_POST['total_price'],
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
          $_SESSION['flash_error'] = 'Debe seleccionar al menos una foto';
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
          $_SESSION['flash_error'] = "Máximo {$maxPhotos} fotos permitidas";
          break;
        }
        
        foreach ($_FILES['photos']['name'] as $key => $filename) {
          if (empty($filename)) continue;
          
          $tmpName = $_FILES['photos']['tmp_name'][$key];
          $fileSize = $_FILES['photos']['size'][$key];
          $fileType = $_FILES['photos']['type'][$key];
          
          // Validar tipo de archivo
          if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['flash_error'] = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
            break 2;
          }
          
          // Validar tamaño
          if ($fileSize > $maxFileSize) {
            $_SESSION['flash_error'] = 'El archivo es demasiado grande (máximo 5MB)';
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
  <title>Panel Administrativo - AgroGan</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        <h1><i class="fas fa-shield-alt"></i> AgroGan Admin</h1>
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
            <p>Bienvenido al panel administrativo de AgroGan. Desde aquí puedes gestionar todos los aspectos del sistema.</p>
            
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
            
            <form method="POST" class="form-grid" enctype="multipart/form-data">
              <input type="hidden" name="action" value="<?= $editAnimal ? 'edit_animal' : 'add_animal' ?>">
              <?php if ($editAnimal): ?>
                <input type="hidden" name="animal_id" value="<?= $editAnimal['id'] ?>">
              <?php endif; ?>
              
              <div class="form-group">
                <label for="name">Nombre del Animal *</label>
                <input type="text" id="name" name="name" value="<?= e($editAnimal['name'] ?? '') ?>" required>
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
                <input type="date" id="birth_date" name="birth_date" value="<?= e($editAnimal['birth_date'] ?? '') ?>">
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
                <label for="weight">Peso (kg)</label>
                <input type="number" id="weight" name="weight" step="0.1" value="<?= e($editAnimal['weight'] ?? '') ?>">
              </div>
              
              <div class="form-group">
                <label for="color">Color</label>
                <input type="text" id="color" name="color" value="<?= e($editAnimal['color'] ?? '') ?>">
              </div>
              
              <div class="form-group">
                <label for="farm_id">Finca</label>
                <select id="farm_id" name="farm_id">
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
                <button type="submit" class="btn btn-primary">
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
              <button class="btn btn-sm" onclick="toggleAnimalsWithPrice()">
                <i class="fas fa-dollar-sign"></i> Solo con Precio
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
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            
            <!-- Bulk Actions -->
            <?php if (is_admin_general()): ?>
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--gray-200);">
              <h4><i class="fas fa-tasks"></i> Acciones en Lote</h4>
              <p style="color: var(--gray-600); margin-bottom: 1rem;">Selecciona múltiples animales usando las casillas de verificación y aplica acciones en lote.</p>
              
              <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button class="btn btn-sm" onclick="bulkToggleVisibility(true)">
                  <i class="fas fa-eye"></i> Mostrar Seleccionados
                </button>
                <button class="btn btn-sm" onclick="bulkToggleVisibility(false)">
                  <i class="fas fa-eye-slash"></i> Ocultar Seleccionados
                </button>
                <button class="btn btn-sm" onclick="clearSelection()">
                  <i class="fas fa-times"></i> Limpiar Selección
                </button>
              </div>
            </div>
            <?php else: ?>
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--gray-200);">
              <h4><i class="fas fa-paper-plane"></i> Acciones en Lote</h4>
              <p style="color: var(--gray-600); margin-bottom: 1rem;">Selecciona múltiples animales usando las casillas de verificación y postúlalos para el catálogo público.</p>
              
              <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button class="btn btn-sm btn-primary" onclick="bulkToggleVisibility(true)">
                  <i class="fas fa-paper-plane"></i> Postular Seleccionados
                </button>
                <button class="btn btn-sm" onclick="clearSelection()">
                  <i class="fas fa-times"></i> Limpiar Selección
                </button>
              </div>
            </div>
            <?php endif; ?>
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
                <label for="name">Nombre del Lote *</label>
                <input type="text" id="name" name="name" required placeholder="Ej: Lote de Vacas Holstein">
              </div>
              
              <div class="form-group">
                <label for="lot_type">Tipo de Lote *</label>
                <select id="lot_type" name="lot_type" required>
                  <option value="">Seleccionar tipo</option>
                  <option value="venta">Venta</option>
                  <option value="reproduccion">Reproducción</option>
                  <option value="engorde">Engorde</option>
                  <option value="leche">Leche</option>
                </select>
              </div>
              
              <div class="form-group">
                <label for="total_price">Precio Total del Lote *</label>
                <input type="number" id="total_price" name="total_price" step="0.01" required placeholder="0.00">
              </div>
              
              <div class="form-group">
                <label for="animal_count">Número de Animales</label>
                <input type="number" id="animal_count" name="animal_count" min="1" placeholder="Cantidad de animales">
              </div>
              
              <div class="form-group">
                <label for="farm_id">Finca</label>
                <select id="farm_id" name="farm_id">
                  <option value="">Seleccionar finca</option>
                  <?php foreach ($farms as $farm): ?>
                    <option value="<?= $farm['id'] ?>"><?= e($farm['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="description">Descripción del Lote</label>
                <textarea id="description" name="description" rows="3" placeholder="Describe las características del lote..."></textarea>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <label>Animales Disponibles para el Lote</label>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--gray-300); border-radius: 8px; padding: 1rem;">
                  <?php foreach ($animals as $animal): ?>
                    <label style="display: block; margin-bottom: 0.5rem;">
                      <input type="checkbox" name="animal_ids[]" value="<?= $animal['id'] ?>">
                      <?= e($animal['name']) ?> - <?= e($animal['species_name'] ?? 'N/A') ?> (<?= e($animal['gender'] ?? 'N/A') ?>)
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
              
              <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Crear Lote
                </button>
              </div>
            </form>
          </div>
          
          <div class="admin-card">
            <h3><i class="fas fa-list"></i> Lotes Existentes</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Precio Total</th>
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
                    <td>$<?= number_format($lot['total_price'], 2) ?></td>
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
                      <button class="btn btn-sm btn-secondary" onclick="editLot(<?= $lot['id'] ?>)">
                        <i class="fas fa-edit"></i> Editar
                      </button>
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
                <label for="name">Nombre Completo *</label>
                <input type="text" id="name" name="name" value="<?= e($editUser['name'] ?? '') ?>" required>
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
                <label for="farm_id">Finca</label>
                <select id="farm_id" name="farm_id">
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
            $stmt = $pdo->query("SELECT f.*, COUNT(DISTINCT a.id) as animal_count, COUNT(DISTINCT u.id) as user_count 
                                 FROM farms f 
                                 LEFT JOIN animals a ON a.farm_id = f.id 
                                 LEFT JOIN users u ON u.farm_id = f.id
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
                          <button class="btn btn-sm btn-secondary" onclick="editFarm(<?= $farm['id'] ?>, '<?= e($farm['name']) ?>', '<?= e($farm['location'] ?? '') ?>')">
                            <i class="fas fa-edit"></i> Editar
                          </button>
                          <?php if($farm['animal_count'] == 0 && $farm['user_count'] == 0): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta finca?')">
                              <input type="hidden" name="action" value="delete_farm">
                              <input type="hidden" name="farm_id" value="<?= $farm['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Eliminar
                              </button>
                            </form>
                          <?php else: ?>
                            <button class="btn btn-sm btn-danger" disabled title="No se puede eliminar: tiene animales o usuarios asociados">
                              <i class="fas fa-trash"></i> Eliminar
                            </button>
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

        <!-- Settings Section -->
        <div id="settings-section" class="content-section">
          <div class="admin-card">
            <h3><i class="fas fa-cog"></i> Configuración del Sistema</h3>
            <p>Configuración general del sistema AgroGan.</p>
            
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
        'users': 'Usuarios',
        'veterinary': 'Módulo Veterinario',
        'reports': 'Reportes',
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

    function toggleAnimalsWithPrice() {
      if (confirm('¿Mostrar solo los animales que tienen precio asignado?')) {
        const checkboxes = document.querySelectorAll('.animal-checkbox');
        const rows = document.querySelectorAll('tbody tr');
        
        // Uncheck all first
        checkboxes.forEach(checkbox => checkbox.checked = false);
        document.getElementById('select-all').checked = false;
        
        // Check only animals with price
        rows.forEach((row, index) => {
          const priceCell = row.cells[7]; // Price column
          if (priceCell && !priceCell.textContent.includes('Sin precio')) {
            const checkbox = row.querySelector('.animal-checkbox');
            if (checkbox) checkbox.checked = true;
          }
        });
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

    // Auto-open animals section if editing an animal
    window.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('edit_animal')) {
        showSection('animals');
      }
    });
  </script>
</body>
</html>