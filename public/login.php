<?php 
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/recaptcha.php';

$RECAPTCHA_SECRET_KEY = '6LdyUvkrAAAAAJkID-Dk18vNfX7Cauf1sV_jnT7p';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  // Verificar reCAPTCHA
  $recaptcha_token = $_POST['recaptcha_token'] ?? '';
  if (!verifyRecaptcha($recaptcha_token, $RECAPTCHA_SECRET_KEY)) {
    $err = 'Verificación de reCAPTCHA fallida. Por favor intenta nuevamente.';
  } else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])){
      $_SESSION['user'] = ['id'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'],'role'=>$u['role'],'farm_id'=>$u['farm_id']??null];
      header('Location: admin.php'); exit;
    } else {
      $err = 'Credenciales inválidas';
    }
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css">
<script src="https://www.google.com/recaptcha/api.js?render=6LdyUvkrAAAAACTJYMpsukU-GB5zQn4d1u8FLRm0" async defer></script>
<title>Login</title></head><body>
<nav class="nav">
  <a href="index.php">AgroGan</a>
  <a href="catalogo.php">Catálogo</a>
  <a href="login.php">Login</a>
  <a href="register.php">Registro</a>
</nav>
<div class="container">
  <div class="card">
    <h2>Iniciar sesión</h2>
    <?php if (!empty($err)): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
    <form method="post" class="grid grid-2" id="loginForm">
      <label>Email <input type="email" name="email" required></label>
      <label>Contraseña <input type="password" name="password" required></label>
      <input type="hidden" name="recaptcha_token" id="recaptcha_token">
      <div><button class="btn">Entrar</button></div>
    </form>
  </div>
</div>

<script>
  // Ejecutar reCAPTCHA antes de enviar el formulario
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Si reCAPTCHA no está cargado, usar token de prueba
    if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
      document.getElementById('recaptcha_token').value = 'test-token';
      document.getElementById('loginForm').submit();
      return;
    }
    
    grecaptcha.ready(function() {
      try {
        grecaptcha.execute('6LdyUvkrAAAAACTJYMpsukU-GB5zQn4d1u8FLRm0', {action: 'login'}).then(function(token) {
          document.getElementById('recaptcha_token').value = token;
          document.getElementById('loginForm').submit();
        }).catch(function(error) {
          console.error('Error al ejecutar reCAPTCHA:', error);
          // Continuar sin reCAPTCHA si hay error (modo desarrollo)
          document.getElementById('recaptcha_token').value = 'test-token';
          document.getElementById('loginForm').submit();
        });
      } catch(error) {
        console.error('Error en reCAPTCHA:', error);
        // Continuar sin reCAPTCHA si hay error (modo desarrollo)
        document.getElementById('recaptcha_token').value = 'test-token';
        document.getElementById('loginForm').submit();
      }
    });
  });
</script>
</body></html>
