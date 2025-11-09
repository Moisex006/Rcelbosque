<?php 
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/turnstile.php';

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE CLOUDFLARE TURNSTILE
// ═══════════════════════════════════════════════════════════════
// IMPORTANTE: Si estás en desarrollo local (localhost), necesitas:
// 1. Ir a Cloudflare Dashboard > Turnstile > Tu widget
// 2. Agregar "localhost" en la lista de dominios permitidos
// 3. O usar las credenciales de prueba (ver abajo)

// Credenciales de PRODUCCIÓN (reemplaza con las tuyas)
// $TURNSTILE_SITE_KEY = '0x4AAAAAAB_gZpJCbeM9C4o';
// $TURNSTILE_SECRET_KEY = '0x4AAAAAAB_gbqY5C6Vtr4GzjzSGVmHqEw';

// Credenciales de PRUEBA para desarrollo local (siempre funcionan)
// Usa estas mientras desarrollas en localhost:
$TURNSTILE_SITE_KEY = '0x4AAAAAAB__gZpJCbeM9C4o';
$TURNSTILE_SECRET_KEY = '1x0000000000000000000000000000000AA';

// Detectar si estamos en localhost
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:80', 'localhost:8080', '127.0.0.1:80', '127.0.0.1:8080']) || 
                strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0 ||
                strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1:') === 0;

if ($_SERVER['REQUEST_METHOD']==='POST'){
  // Verificar Cloudflare Turnstile
  $turnstile_token = $_POST['cf-turnstile-response'] ?? '';
  $remote_ip = $_SERVER['REMOTE_ADDR'] ?? null;
  
  if (!verifyTurnstile($turnstile_token, $TURNSTILE_SECRET_KEY, $remote_ip)) {
    $err = 'Verificación de seguridad fallida. Por favor intenta nuevamente.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])){
      $_SESSION['user'] = ['id'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'],'role'=>$u['role'],'farm_id'=>$u['farm_id']??null];
      $_SESSION['last_activity'] = time(); // Establecer tiempo de última actividad
      // Redirigir según el rol del usuario
      if ($u['role'] === 'user') {
        header('Location: catalogo.php'); exit;
      } else {
        header('Location: admin.php'); exit;
      }
    } else {
      $err = 'Credenciales inválidas';
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <!-- Cloudflare Turnstile -->
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
  <title>Iniciar Sesión - Rc El Bosque</title>
  <style>
    :root {
      --primary-green: #2d5a27;
      --accent-green: #3e7b2e;
      --light-green: #4a9a3d;
      --bg-green: #f0f8ec;
      --text-dark: #1a3315;
      --error: #dc3545;
      --success: #28a745;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: linear-gradient(135deg, #f0f8ec 0%, #e8f5e9 50%, #f0f8ec 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Efectos de fondo animados */
    body::before {
      content: '';
      position: fixed;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: 
        radial-gradient(circle at 20% 30%, rgba(45, 90, 39, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(62, 123, 46, 0.08) 0%, transparent 50%);
      animation: float 20s ease-in-out infinite;
      pointer-events: none;
      z-index: 0;
    }
    
    @keyframes float {
      0%, 100% { transform: translate(0, 0) rotate(0deg); }
      33% { transform: translate(30px, -30px) rotate(120deg); }
      66% { transform: translate(-20px, 20px) rotate(240deg); }
    }
    
    /* ============================================
       NAVBAR RESPONSIVE CON MENÚ HAMBURGUESA
       ============================================ */
    .nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
      box-shadow: 0 4px 20px rgba(45, 90, 39, 0.2);
      position: sticky;
      top: 0;
      z-index: 1000;
      flex-wrap: wrap;
    }

    .nav-brand {
      display: flex;
      align-items: center;
      z-index: 1001;
    }

    .nav-menu {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }

    .nav-menu a {
      color: white;
      text-decoration: none;
      font-weight: 600;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      white-space: nowrap;
    }

    .nav-menu a:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }

    .nav-menu a i {
      font-size: 1rem;
    }

    /* Botón hamburguesa */
    .nav-toggle {
      display: none;
      flex-direction: column;
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 0.5rem;
      gap: 4px;
      z-index: 1002;
    }

    .nav-toggle span {
      width: 25px;
      height: 3px;
      background: white;
      border-radius: 3px;
      transition: all 0.3s ease;
      display: block;
    }

    .nav-toggle.active span:nth-child(1) {
      transform: rotate(45deg) translate(5px, 5px);
    }

    .nav-toggle.active span:nth-child(2) {
      opacity: 0;
    }

    .nav-toggle.active span:nth-child(3) {
      transform: rotate(-45deg) translate(7px, -6px);
    }
    
    /* Contenedor principal */
    .auth-container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      position: relative;
      z-index: 1;
      min-height: calc(100vh - 80px);
    }
    
    /* Card de autenticación */
    .auth-card {
      background: white;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(45, 90, 39, 0.15);
      padding: 3rem;
      width: 100%;
      max-width: 480px;
      position: relative;
      overflow: hidden;
      animation: slideUp 0.6s ease-out;
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .auth-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, var(--primary-green), var(--accent-green));
    }
    
    /* Header del card */
    .auth-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }
    
    .auth-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      box-shadow: 0 10px 30px rgba(45, 90, 39, 0.2);
      animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); box-shadow: 0 10px 30px rgba(45, 90, 39, 0.2); }
      50% { transform: scale(1.05); box-shadow: 0 15px 40px rgba(45, 90, 39, 0.3); }
    }
    
    .auth-icon i {
      font-size: 2.5rem;
      color: white;
    }
    
    .auth-header h2 {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary-green);
      margin-bottom: 0.5rem;
      background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .auth-header p {
      color: var(--text-dark);
      opacity: 0.7;
      font-size: 1rem;
    }
    
    /* Formulario */
    .auth-form {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }
    
    .form-group {
      position: relative;
    }
    
    .form-group label {
      display: block;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }
    
    .form-group input {
      width: 100%;
      padding: 1rem 1.25rem;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background: #f9fafb;
      color: var(--text-dark);
    }
    
    .form-group input:focus {
      outline: none;
      border-color: var(--primary-green);
      background: white;
      box-shadow: 0 0 0 4px rgba(45, 90, 39, 0.1);
      transform: translateY(-2px);
    }
    
    .form-group input::placeholder {
      color: #9ca3af;
    }
    
    /* Botón de submit */
    .auth-submit {
      background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
      color: white;
      border: none;
      padding: 1.1rem 2rem;
      border-radius: 12px;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 8px 20px rgba(45, 90, 39, 0.2);
      margin-top: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }
    
    .auth-submit:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(45, 90, 39, 0.3);
    }
    
    .auth-submit:active {
      transform: translateY(-1px);
    }
    
    .auth-submit i {
      font-size: 1.2rem;
    }
    
    /* Alertas */
    .alert {
      padding: 1rem 1.25rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: slideDown 0.4s ease-out;
    }
    
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .alert.err {
      background: #fee2e2;
      color: #991b1b;
      border-left: 4px solid var(--error);
    }
    
    .alert.ok {
      background: #d1fae5;
      color: #065f46;
      border-left: 4px solid var(--success);
    }
    
    /* Link de registro */
    .auth-footer {
      text-align: center;
      margin-top: 2rem;
      padding-top: 2rem;
      border-top: 1px solid #e5e7eb;
    }
    
    .auth-footer p {
      color: var(--text-dark);
      opacity: 0.7;
      margin-bottom: 0.75rem;
    }
    
    .auth-footer a {
      color: var(--primary-green);
      text-decoration: none;
      font-weight: 600;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .auth-footer a:hover {
      color: var(--accent-green);
      transform: translateX(5px);
    }
    
    /* Responsive */
    /* ============================================
       RESPONSIVE DESIGN
       ============================================ */
    @media (max-width: 768px) {
      /* Navbar móvil */
      .nav {
        padding: 1rem;
        position: relative;
      }
      
      .nav-toggle {
        display: flex;
      }
      
      .nav-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
        flex-direction: column;
        padding: 1rem;
        box-shadow: 0 8px 25px rgba(45, 90, 39, 0.3);
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease, padding 0.3s ease;
        gap: 0.5rem;
      }
      
      .nav-menu.active {
        max-height: 500px;
        padding: 1.5rem 1rem;
      }
      
      .nav-menu a {
        width: 100%;
        padding: 1rem;
        justify-content: flex-start;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      }
      
      .nav-menu a:last-child {
        border-bottom: none;
      }
      
      /* Animación para el menú móvil */
      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      .nav-menu.active a {
        animation: slideDown 0.3s ease forwards;
      }
      
      .nav-menu.active a:nth-child(1) { animation-delay: 0.05s; }
      .nav-menu.active a:nth-child(2) { animation-delay: 0.1s; }
      .nav-menu.active a:nth-child(3) { animation-delay: 0.15s; }
      .nav-menu.active a:nth-child(4) { animation-delay: 0.2s; }
      .nav-menu.active a:nth-child(5) { animation-delay: 0.25s; }
      
      .nav a {
        font-size: 0.9rem;
        padding: 0.4rem 0.75rem;
      }
      
      .auth-container {
        padding: 1rem;
      }
      
      .auth-card {
        padding: 2rem 1.5rem;
        border-radius: 20px;
      }
      
      .auth-header h2 {
        font-size: 1.75rem;
      }
      
      .auth-icon {
        width: 70px;
        height: 70px;
      }
      
      .auth-icon i {
        font-size: 2rem;
      }
    }
    
    @media (max-width: 480px) {
      .auth-card {
        padding: 1.5rem 1rem;
      }
      
      .auth-header h2 {
        font-size: 1.5rem;
      }
      
      .form-group input {
        padding: 0.875rem 1rem;
        font-size: 0.95rem;
      }
      
      .auth-submit {
        padding: 1rem 1.5rem;
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <nav class="nav">
    <div class="nav-brand">
      <a href="index.php" style="display:flex;align-items:center;gap:.7rem;font-size:1.3rem;font-weight:bold;text-decoration:none;color:inherit;">
        <img src="assets/images/logo-rc-el-bosque.png" alt="Logo RC El Bosque" style="height:40px;width:auto;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <span>RC El Bosque</span>
      </a>
    </div>
    
    <!-- Botón hamburguesa para móviles -->
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
      <span></span>
      <span></span>
      <span></span>
    </button>
    
    <!-- Menú de navegación -->
    <div class="nav-menu" id="navMenu">
      <a href="catalogo.php"><i class="fas fa-list"></i> <span>Catálogo</span></a>
      <?php if(!is_logged_in()): ?>
        <a href="login.php"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
        <a href="register.php"><i class="fas fa-user-plus"></i> <span>Registro</span></a>
      <?php else: 
        $current_user = current_user();
        $user_role = $current_user['role'] ?? 'user';
        ?>
        <?php if($user_role === 'user'): ?>
          <a href="catalogo.php" style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-user-circle" style="font-size: 1.2rem;"></i>
            <span><?= e($current_user['name'] ?? 'Usuario') ?></span>
          </a>
        <?php else: ?>
          <a href="admin.php"><i class="fas fa-cogs"></i> <span>Admin</span></a>
        <?php endif; ?>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Salir</span></a>
      <?php endif; ?>
    </div>
  </nav>
  
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <div class="auth-icon">
          <i class="fas fa-sign-in-alt"></i>
        </div>
        <h2>Iniciar Sesión</h2>
        <p>Accede a tu cuenta para continuar</p>
      </div>
      
      <?php if (!empty($err)): ?>
      <div class="alert err">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= e($err) ?></span>
      </div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['session_expired']) || isset($_GET['expired'])): ?>
      <div class="alert err" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
        <i class="fas fa-clock"></i>
        <span>Tu sesión ha expirado por inactividad (15 minutos). Por favor, inicia sesión nuevamente.</span>
      </div>
      <?php 
        unset($_SESSION['session_expired']);
        unset($_SESSION['flash_message']);
      endif; ?>
      
      <?php if (isset($_SESSION['flash_message'])): ?>
      <div class="alert" style="background: #d1fae5; color: #065f46; border: 1px solid #34d399;">
        <i class="fas fa-info-circle"></i>
        <span><?= e($_SESSION['flash_message']) ?></span>
      </div>
      <?php unset($_SESSION['flash_message']); endif; ?>
      
      <form method="post" class="auth-form" id="loginForm" data-testid="login-form">
        <div class="form-group">
          <label for="login_email" data-testid="login-email-label">
            <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
            Email
          </label>
          <input 
            type="email" 
            name="email" 
            id="login_email" 
            data-testid="login-email-input" 
            required 
            autocomplete="email"
            placeholder="tu@email.com"
          >
        </div>
        
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <label for="login_password" data-testid="login-password-label" style="margin-bottom: 0;">
              <i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
              Contraseña
            </label>
            <a href="#" onclick="event.preventDefault(); showForgotPasswordModal();" style="color: var(--primary-green); text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: color 0.3s ease;">
              <i class="fas fa-question-circle"></i> ¿Olvidaste tu contraseña?
            </a>
          </div>
          <input 
            type="password" 
            name="password" 
            id="login_password" 
            data-testid="login-password-input" 
            required 
            autocomplete="current-password"
            placeholder="••••••••"
          >
        </div>
        
        <!-- Cloudflare Turnstile Widget -->
        <?php if ($is_localhost): ?>
          <!-- Modo desarrollo: mostrar mensaje si no está configurado -->
          <div style="margin: 1rem 0; padding: 1rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404; font-size: 0.9rem;">
            <strong>⚠️ Desarrollo Local:</strong> Asegúrate de agregar "localhost" como dominio permitido en Cloudflare Turnstile, o usa las credenciales de prueba.
          </div>
        <?php endif; ?>
        <div class="cf-turnstile" 
             data-sitekey="<?= htmlspecialchars($TURNSTILE_SITE_KEY) ?>" 
             data-theme="light"
             data-size="normal"
             data-language="es"
             style="margin: 1rem 0; display: flex; justify-content: center;"></div>
        
        <button type="submit" class="auth-submit" id="login_submit" data-testid="login-submit-button">
          <i class="fas fa-sign-in-alt"></i>
          <span>Iniciar Sesión</span>
        </button>
      </form>
      
      <div class="auth-footer">
        <p>¿No tienes una cuenta?</p>
        <a href="register.php">
          <i class="fas fa-user-plus"></i>
          <span>Crear cuenta nueva</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Modal de Recuperación de Contraseña -->
  <div id="forgotPasswordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 20px; padding: 2rem; max-width: 450px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); position: relative; animation: slideDown 0.3s ease-out;">
      <button onclick="closeForgotPasswordModal()" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--gray-500); cursor: pointer; padding: 0.5rem; line-height: 1; transition: color 0.3s ease;">
        <i class="fas fa-times"></i>
      </button>
      
      <div style="text-align: center; margin-bottom: 1.5rem;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-green), var(--accent-green)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 8px 20px rgba(45, 90, 39, 0.3);">
          <i class="fas fa-key" style="font-size: 2rem; color: white;"></i>
        </div>
        <h2 style="color: var(--primary-green); margin-bottom: 0.5rem; font-size: 1.75rem;">Recuperar Contraseña</h2>
        <p style="color: var(--gray-600); font-size: 0.95rem;">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
      </div>
      
      <form id="forgotPasswordForm" onsubmit="handleForgotPassword(event); return false;">
        <div class="form-group">
          <label for="reset_email">
            <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
            Correo Electrónico
          </label>
          <input 
            type="email" 
            name="email" 
            id="reset_email" 
            required 
            autocomplete="email"
            placeholder="tu@email.com"
            style="width: 100%; padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 12px; font-size: 1rem; transition: border-color 0.3s ease;"
          >
        </div>
        
        <div id="forgotPasswordMessage" style="margin: 1rem 0; padding: 0.75rem; border-radius: 8px; display: none;"></div>
        
        <button type="submit" class="auth-submit" style="width: 100%; margin-top: 1rem;">
          <i class="fas fa-paper-plane"></i>
          <span>Enviar Enlace de Recuperación</span>
        </button>
      </form>
      
      <div style="text-align: center; margin-top: 1.5rem;">
        <a href="#" onclick="event.preventDefault(); closeForgotPasswordModal();" style="color: var(--primary-green); text-decoration: none; font-size: 0.875rem;">
          <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
        </a>
      </div>
    </div>
  </div>

<script>
  // Funciones para el modal de recuperación de contraseña
  function showForgotPasswordModal() {
    document.getElementById('forgotPasswordModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  
  function closeForgotPasswordModal() {
    document.getElementById('forgotPasswordModal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('forgotPasswordForm').reset();
    document.getElementById('forgotPasswordMessage').style.display = 'none';
  }
  
  // Cerrar modal al hacer clic fuera
  document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeForgotPasswordModal();
    }
  });
  
  // Manejar envío del formulario de recuperación
  async function handleForgotPassword(event) {
    event.preventDefault();
    const form = event.target;
    const email = document.getElementById('reset_email').value;
    const messageDiv = document.getElementById('forgotPasswordMessage');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Enviando...</span>';
    messageDiv.style.display = 'none';
    
    try {
      const response = await fetch('process_password_reset.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `email=${encodeURIComponent(email)}`
      });
      
      const data = await response.json();
      
      // Mostrar logs de debug en la consola
      if (data.debug_logs && Array.isArray(data.debug_logs)) {
        console.group('🔐 Logs de Recuperación de Contraseña');
        data.debug_logs.forEach(log => {
          const timestamp = log.timestamp || '';
          const message = log.message || '';
          const type = log.type || 'info';
          
          if (type === 'error') {
            console.error(`❌ [${timestamp}] ${message}`);
          } else if (type === 'success') {
            console.log(`%c✅ [${timestamp}] ${message}`, 'color: green; font-weight: bold');
          } else if (type === 'warning') {
            console.warn(`⚠️ [${timestamp}] ${message}`);
          } else {
            console.log(`📧 [${timestamp}] ${message}`);
          }
        });
        console.groupEnd();
      }
      
      if (data.success) {
        messageDiv.className = 'alert ok';
        messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
        messageDiv.style.display = 'block';
        form.reset();
        
        // Cerrar modal después de 3 segundos
        setTimeout(() => {
          closeForgotPasswordModal();
        }, 3000);
      } else {
        messageDiv.className = 'alert err';
        messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
        messageDiv.style.display = 'block';
      }
    } catch (error) {
      messageDiv.className = 'alert err';
      messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error al procesar la solicitud. Por favor, intenta nuevamente.';
      messageDiv.style.display = 'block';
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Enviar Enlace de Recuperación</span>';
    }
  }
  
  // Cloudflare Turnstile se maneja automáticamente
  // El widget genera el token automáticamente cuando el usuario interactúa
  // Solo verificamos que el token esté presente antes de enviar
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    // Buscar el input hidden que Turnstile crea automáticamente
    const turnstileResponse = document.querySelector('input[name="cf-turnstile-response"]');
    
    // En modo desarrollo, permitir envío sin token (comentar en producción)
    if (!turnstileResponse || !turnstileResponse.value) {
      // Descomenta las siguientes líneas en producción para requerir Turnstile:
      // e.preventDefault();
      // alert('Por favor, completa la verificación de seguridad de Cloudflare.');
      // return false;
      console.warn('⚠️ Turnstile token no encontrado. En producción, esto debería bloquear el envío.');
    }
    
    // El formulario se enviará normalmente con el token de Turnstile
    // El servidor verificará el token en el backend usando verifyTurnstile()
  });
  
  // ============================================
  // NAVBAR MOBILE TOGGLE
  // ============================================
  const navToggle = document.getElementById('navToggle');
  const navMenu = document.getElementById('navMenu');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
      navToggle.classList.toggle('active');
      navMenu.classList.toggle('active');
    });
    
    // Cerrar menú al hacer clic en un enlace (móvil)
    const navLinks = navMenu.querySelectorAll('a');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
          navToggle.classList.remove('active');
          navMenu.classList.remove('active');
        }
      });
    });
    
    // Cerrar menú al hacer clic fuera (móvil)
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 768) {
        if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
          navToggle.classList.remove('active');
          navMenu.classList.remove('active');
        }
      }
    });
    
    // Ajustar menú al cambiar tamaño de ventana
    window.addEventListener('resize', () => {
      if (window.innerWidth > 768) {
        navToggle.classList.remove('active');
        navMenu.classList.remove('active');
      }
    });
  }
</script>
</body></html>
