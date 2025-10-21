<?php
// app/config.php
session_start();

$DB_HOST = '127.0.0.1';
$DB_NAME = 'agrogan';
$DB_USER = 'root';
$DB_PASS = '';

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo "DB connection error: " . htmlspecialchars($e->getMessage());
  exit;
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function is_logged_in(){ return isset($_SESSION['user']); }
function current_user(){ return $_SESSION['user'] ?? null; }
function require_login(){ if(!is_logged_in()){ header("Location: login.php"); exit; } }
function require_admin(){ if(!is_logged_in() || ($_SESSION['user']['role']??'user')!=='admin'){ http_response_code(403); echo 'Acceso restringido'; exit; } }
?>