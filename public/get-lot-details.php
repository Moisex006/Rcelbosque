<?php
require __DIR__ . '/../app/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) {
    echo json_encode(['error' => 'ID de lote inválido']);
    exit;
  }

  // Obtener información del lote
  $stmt = $pdo->prepare("SELECT l.*, f.name AS farm_name
                         FROM lots l
                         LEFT JOIN farms f ON f.id = l.farm_id
                         WHERE l.id = ?");
  $stmt->execute([$id]);
  $lot = $stmt->fetch();

  if (!$lot) {
    echo json_encode(['error' => 'Lote no encontrado']);
    exit;
  }

  // Obtener animales del lote con foto principal si existe
  $stmt = $pdo->prepare("SELECT a.id, a.name, a.tag_code, a.gender, a.weight, a.color, a.birth_date,
                                s.name AS species_name, b.name AS breed_name, f.name AS farm_name,
                                ap.file_path AS primary_photo, ap.filename AS photo_filename
                         FROM lot_animals la
                         JOIN animals a ON a.id = la.animal_id
                         LEFT JOIN species s ON s.id = a.species_id
                         LEFT JOIN breeds b ON b.id = a.breed_id
                         LEFT JOIN farms f ON f.id = a.farm_id
                         LEFT JOIN animal_photos ap ON ap.animal_id = a.id AND ap.is_primary = 1
                         WHERE la.lot_id = ?
                         ORDER BY a.name ASC");
  $stmt->execute([$id]);
  $animals = $stmt->fetchAll();

  echo json_encode([
    'lot' => $lot,
    'animals' => $animals,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error del servidor', 'details' => $e->getMessage()]);
}
?>

