<?php
// reset_admin.php — Úsalo 1 sola vez y luego bórralo
require __DIR__ . '/../app/config.php';

// Simple “llave” para evitar que cualquiera lo ejecute por accidente.
// Llama a: http://localhost/agrogan/public/reset_admin.php?k=AGR-RESET-123
if (($_GET['k'] ?? '') !== 'AGR-RESET-123') {
  http_response_code(403);
  exit('Forbidden');
}

// Nuevo password en claro:
$newPassword = 'admin123';

// Generar hash con el PHP de tu máquina (bcrypt por defecto)
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

// Actualiza/crea el admin
$sql = "INSERT INTO users (email, password_hash, name, role)
        VALUES ('admin@agrogan.local', :h, 'Administrador', 'admin')
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role='admin'";
$st = $pdo->prepare($sql);
$st->execute([':h' => $hash]);

echo "OK: admin@agrogan.local actualizado.\nHash: $hash\n";
