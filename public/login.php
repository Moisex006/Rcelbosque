<?php require __DIR__ . '/../app/config.php';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if ($u && password_verify($password, $u['password_hash'])){
    $_SESSION['user'] = ['id'=>$u['id'],'email'=>$u['email'],'name'=>$u['name'],'role'=>$u['role']];
    header('Location: admin.php'); exit;
  } else {
    $err = 'Credenciales inválidas';
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css"><title>Login</title></head><body>
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
    <form method="post" class="grid grid-2">
      <label>Email <input type="email" name="email" required></label>
      <label>Contraseña <input type="password" name="password" required></label>
      <div><button class="btn">Entrar</button></div>
    </form>
  </div>
</div>
</body></html>
