<?php require __DIR__ . '/../app/config.php';
$err = [];
$ok = null;

if ($_SERVER['REQUEST_METHOD']==='POST'){
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
      // Opcional: redirige directo al login
      // header('Location: login.php'); exit;
    } catch (PDOException $e) {
      $err[] = 'Email ya registrado';
    }
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css"><title>Registro</title></head><body>
<nav class="nav">
  <a href="index.php">AgroGan</a>
  <a href="catalogo.php">Catálogo</a>
  <a href="login.php">Login</a>
  <a href="register.php">Registro</a>
</nav>
<div class="container">
  <div class="card">
    <h2>Crear cuenta</h2>
    <?php if ($err): ?>
      <div class="alert err">
        <?php foreach($err as $e){ echo '<div>'.e($e).'</div>'; } ?>
      </div>
    <?php endif; ?>
    <?php if ($ok): ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>

    <form method="post" class="grid grid-3" autocomplete="off">
      <label>Nombre <input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
      <label>Email <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label>Contraseña <input type="password" name="password" required></label>
      <div style="grid-column:1/-1"><button class="btn">Registrarme</button></div>
    </form>
  </div>
</div>
</body></html>
