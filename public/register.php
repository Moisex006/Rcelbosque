<?php 
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/recaptcha.php';

$RECAPTCHA_SECRET_KEY = '6LdyUvkrAAAAAJkID-Dk18vNfX7Cauf1sV_jnT7p';

$err = [];
$ok = null;

if ($_SERVER['REQUEST_METHOD']==='POST'){
  // Verificar reCAPTCHA
  $recaptcha_token = $_POST['recaptcha_token'] ?? '';
  if (!verifyRecaptcha($recaptcha_token, $RECAPTCHA_SECRET_KEY)) {
    $err[] = 'Verificación de reCAPTCHA fallida. Por favor intenta nuevamente.';
  } else {
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';

  if (strlen($name) < 2)        { $err[] = 'El nombre debe tener al menos 2 caracteres.'; }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err[] = 'Email inválido.'; }
  if (strlen($password) < 6)    { $err[] = 'La contraseña debe tener al menos 6 caracteres.'; }

  if (!$err) {
    try {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?, 'user')");
      $stmt->execute([$name,$email,$hash]);
      $ok = 'Cuenta creada, ahora puedes iniciar sesión';
    } catch (PDOException $e) {
      $err[] = 'Email ya registrado';
      }
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
  <script src="https://www.google.com/recaptcha/api.js?render=6LdyUvkrAAAAACTJYMpsukU-GB5zQn4d1u8FLRm0" async defer></script>
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
  <title>Crear Cuenta - Rc El Bosque</title>
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
      max-width: 520px;
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
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.5rem;
    }
    
    @media (min-width: 640px) {
      .auth-form {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .auth-form .form-group:last-of-type,
      .auth-form .form-group:nth-last-child(2) {
        grid-column: 1 / -1;
      }
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
      grid-column: 1 / -1;
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
      animation: slideDown 0.4s ease-out;
      grid-column: 1 / -1;
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
    
    .alert.err div {
      margin-bottom: 0.5rem;
    }
    
    .alert.err div:last-child {
      margin-bottom: 0;
    }
    
    .alert.ok {
      background: #d1fae5;
      color: #065f46;
      border-left: 4px solid var(--success);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    /* Link de login */
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
      
      .auth-form {
        grid-template-columns: 1fr;
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
          <i class="fas fa-user-plus"></i>
        </div>
        <h2>Crear Cuenta</h2>
        <p>Únete a nuestra comunidad ganadera</p>
      </div>
      
    <?php if ($err): ?>
      <div class="alert err">
        <i class="fas fa-exclamation-circle"></i>
        <div>
          <?php foreach($err as $e): ?>
            <div><?= e($e) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      
      <?php if ($ok): ?>
      <div class="alert ok">
        <i class="fas fa-check-circle"></i>
        <span><?= e($ok) ?></span>
      </div>
    <?php endif; ?>

      <form method="post" class="auth-form" autocomplete="off" id="registerForm">
        <div class="form-group">
          <label for="register_name">
            <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
            Nombre completo
          </label>
          <input 
            name="name" 
            id="register_name" 
            required 
            value="<?= e($_POST['name'] ?? '') ?>" 
            autocomplete="name"
            placeholder="Juan Pérez"
          >
        </div>
        
        <div class="form-group">
          <label for="register_email">
            <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
            Email
          </label>
          <input 
            type="email" 
            name="email" 
            id="register_email" 
            required 
            value="<?= e($_POST['email'] ?? '') ?>" 
            autocomplete="email"
            placeholder="tu@email.com"
          >
        </div>
        
        <div class="form-group">
          <label for="register_password">
            <i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
            Contraseña
          </label>
          <input 
            type="password" 
            name="password" 
            id="register_password" 
            required 
            autocomplete="new-password"
            placeholder="Mínimo 6 caracteres"
          >
        </div>
        
        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
        
        <button type="submit" class="auth-submit" id="register_submit">
          <i class="fas fa-user-plus"></i>
          <span>Crear Cuenta</span>
        </button>
    </form>
      
      <div class="auth-footer">
        <p>¿Ya tienes una cuenta?</p>
        <a href="login.php">
          <i class="fas fa-sign-in-alt"></i>
          <span>Iniciar sesión</span>
        </a>
      </div>
    </div>
  </div>

<script>
  // Ejecutar reCAPTCHA antes de enviar el formulario
  document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Si reCAPTCHA no está cargado, usar token de prueba
    if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
      document.getElementById('recaptcha_token').value = 'test-token';
      document.getElementById('registerForm').submit();
      return;
    }
    
    grecaptcha.ready(function() {
      try {
        grecaptcha.execute('6LdyUvkrAAAAACTJYMpsukU-GB5zQn4d1u8FLRm0', {action: 'register'}).then(function(token) {
          document.getElementById('recaptcha_token').value = token;
          document.getElementById('registerForm').submit();
        }).catch(function(error) {
          console.error('Error al ejecutar reCAPTCHA:', error);
          // Continuar sin reCAPTCHA si hay error (modo desarrollo)
          document.getElementById('recaptcha_token').value = 'test-token';
          document.getElementById('registerForm').submit();
        });
      } catch(error) {
        console.error('Error en reCAPTCHA:', error);
        // Continuar sin reCAPTCHA si hay error (modo desarrollo)
        document.getElementById('recaptcha_token').value = 'test-token';
        document.getElementById('registerForm').submit();
      }
    });
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
