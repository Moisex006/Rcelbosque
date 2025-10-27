<?php
// app/config.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

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
function get_pdo(){ global $pdo; return $pdo; }

// Funciones para verificación de roles
function has_role($role){ 
  if(!is_logged_in()) return false;
  return ($_SESSION['user']['role'] ?? 'user') === $role; 
}

function require_role($allowed_roles){ 
  if(!is_logged_in()){ 
    header("Location: login.php"); 
    exit; 
  }
  $user_role = $_SESSION['user']['role'] ?? 'user';
  $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
  if(!in_array($user_role, $allowed)){ 
    http_response_code(403); 
    echo 'Acceso restringido. Se requieren los siguientes roles: ' . implode(', ', $allowed); 
    exit; 
  }
}

// Verificar si el usuario tiene uno de los siguientes roles
function is_admin_general(){ return has_role('admin_general'); }
function is_admin_finca(){ return has_role('admin_finca'); }
function is_veterinario(){ return has_role('veterinario'); }
function is_user(){ return has_role('user'); }

// Función legacy para compatibilidad
function require_admin(){ 
  if(!is_logged_in() || !in_array($_SESSION['user']['role']??'user', ['admin_general'])){ 
    http_response_code(403); 
    echo 'Acceso restringido'; 
    exit; 
  } 
}
?>