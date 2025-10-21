<?php
require __DIR__ . '/../app/config.php';

$email = 'admin@agrogan.local';
$pass  = 'admin123';

try {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if (!$u) { die("No existe usuario $email en BD"); }
  echo "Usuario encontrado: {$u['email']} / role={$u['role']}<br>";
  echo "Hash len=" . strlen($u['password_hash']) . "<br>";
  echo "password_verify: " . (password_verify($pass, $u['password_hash']) ? "OK" : "FAIL");
} catch (Throwable $e) {
  echo "Error: " . $e->getMessage();
}
