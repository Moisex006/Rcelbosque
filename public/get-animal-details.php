<?php
require __DIR__ . '/../app/config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID de animal no proporcionado']);
    exit;
}

$animal_id = $_GET['id'];

try {
    // Obtener información completa del animal
    $stmt = $pdo->prepare("
        SELECT a.*, 
               s.name as species_name, 
               b.name as breed_name, 
               f.name as farm_name,
               f.location as farm_location
        FROM animals a
        LEFT JOIN species s ON s.id = a.species_id
        LEFT JOIN breeds b ON b.id = a.breed_id
        LEFT JOIN farms f ON f.id = a.farm_id
        WHERE a.id = ?
    ");
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch();
    
    if (!$animal) {
        echo json_encode(['error' => 'Animal no encontrado']);
        exit;
    }
    
    // Obtener todas las fotos del animal con descripciones
    $stmt = $pdo->prepare("
        SELECT id, filename, original_name, file_path, description, is_primary, sort_order, uploaded_at
        FROM animal_photos
        WHERE animal_id = ?
        ORDER BY sort_order, uploaded_at
    ");
    $stmt->execute([$animal_id]);
    $photos = $stmt->fetchAll();
    
    // Obtener tratamientos veterinarios (si existen)
    $stmt = $pdo->prepare("
        SELECT t.*, v.name as veterinarian_name
        FROM treatments t
        LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
        WHERE t.animal_id = ?
        ORDER BY t.treatment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$animal_id]);
    $treatments = $stmt->fetchAll();
    
    echo json_encode([
        'animal' => $animal,
        'photos' => $photos,
        'treatments' => $treatments
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al obtener detalles del animal: ' . $e->getMessage()]);
}
?>

