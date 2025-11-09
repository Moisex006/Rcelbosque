<?php
/**
 * Configuración Local para XAMPP
 * 
 * INSTRUCCIONES:
 * 1. Asegúrate de que MySQL esté corriendo en XAMPP
 * 2. Crea la base de datos 'rcelbosque' en phpMyAdmin
 * 3. Importa el archivo rcelbosque.sql en la base de datos
 * 
 * Para cambiar a producción (Hostinger), actualiza las credenciales abajo
 */

// Configuración de sesión con expiración por inactividad
ini_set('session.gc_maxlifetime', 900); // 15 minutos en segundos
ini_set('session.cookie_lifetime', 900); // 15 minutos en segundos

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Tiempo de inactividad permitido (15 minutos en segundos)
define('SESSION_TIMEOUT', 900); // 15 minutos = 900 segundos

// Verificar y actualizar tiempo de última actividad
if (isset($_SESSION['user'])) {
    // Si existe última actividad, verificar si ha expirado
    if (isset($_SESSION['last_activity'])) {
        $time_since_last_activity = time() - $_SESSION['last_activity'];
        
        // Si han pasado más de 15 minutos, cerrar sesión
        if ($time_since_last_activity > SESSION_TIMEOUT) {
            // Limpiar todas las variables de sesión
            $_SESSION = array();
            
            // Destruir la cookie de sesión
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Destruir la sesión
            session_destroy();
            
            // Redirigir al login con mensaje
            $redirect_url = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/public/') !== false) 
                ? '/public/login.php' 
                : 'login.php';
            
            // Guardar mensaje en sesión nueva si es posible
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['session_expired'] = true;
            $_SESSION['flash_message'] = 'Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.';
            
            header('Location: ' . $redirect_url . '?expired=1');
            exit;
        }
    }
    
    // Actualizar tiempo de última actividad
    $_SESSION['last_activity'] = time();
}

// ═══════════════════════════════════════════════════════════════
// Configuración PRODUCCIÓN (Hostinger)
// ═══════════════════════════════════════════════════════════════
$DB_HOST = 'localhost';
$DB_NAME = 'u919054360_rcelbosque';
$DB_USER = 'u919054360_admin';
$DB_PASS = 'Admin14@moi';
$DB_PORT = 3306;

// ═══════════════════════════════════════════════════════════════
// Para cambiar a LOCAL (XAMPP), descomenta y usa estas:
// ═══════════════════════════════════════════════════════════════
// $DB_HOST = 'localhost';
// $DB_NAME = 'rcelbosque';
// $DB_USER = 'root';
// $DB_PASS = ''; // Contraseña vacía por defecto en XAMPP
// $DB_PORT = 3306;

try {
  // Construir DSN con puerto si es necesario
  $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
  if (isset($DB_PORT) && $DB_PORT != 3306) {
    $dsn .= ";port=$DB_PORT";
  }
  
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 30, // Timeout de 30 segundos para conexiones remotas (Hostinger)
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  // En producción, no mostrar detalles del error directamente
  error_log("DB connection error: " . $e->getMessage());
  echo "Error de conexión a la base de datos. Por favor, contacta al administrador.";
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
    // Si el usuario es normal (user), redirigir al catálogo
    if($user_role === 'user') {
      header("Location: catalogo.php");
      exit;
    }
    // Para otros roles no permitidos, mostrar error
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

// Configuración de correo electrónico (SMTP)
// Para obtener la contraseña de aplicación de Gmail, sigue la guía: GUIA_CONTRASENA_APLICACION_GMAIL.md
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 587;
$SMTP_USER = 'rc.elbosque.app@gmail.com';
$SMTP_PASS = 'dqkwlgvoalurcvzb'; // Contraseña de aplicación de Gmail (16 caracteres, sin espacios)
$SMTP_FROM_EMAIL = 'rc.elbosque.app@gmail.com';
$SMTP_FROM_NAME = 'Rc El Bosque';

// Cargar logger antes de email para que las funciones de correo puedan usarlo
require_once __DIR__ . '/logger.php';

// Cargar funciones de correo
require_once __DIR__ . '/email.php';
?>

