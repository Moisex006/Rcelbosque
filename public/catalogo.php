<?php require __DIR__ . '/../app/config.php'; ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css"><title>Catálogo</title></head><body>
<nav class="nav">
  <a href="index.php">AgroGan</a>
  <a href="catalogo.php">Catálogo</a>
  <?php if(!is_logged_in()): ?>
    <a href="login.php">Login</a>
    <a href="register.php">Registro</a>
  <?php else: ?>
    <a href="admin.php">Admin</a>
    <a href="logout.php">Salir</a>
  <?php endif; ?>
</nav>
<div class="container">
  <h2>Catálogo público</h2>
  <div class="grid grid-3">
  <?php
  $rows = $pdo->query("
    SELECT a.id, a.tag_code, a.name, a.sex, a.color, a.status,
           s.name AS species, b.name AS breed, f.name AS farm
    FROM catalog_items c
    JOIN animals a ON a.id = c.animal_id
    LEFT JOIN species s ON s.id = a.species_id
    LEFT JOIN breeds b ON b.id = a.breed_id
    LEFT JOIN farms f  ON f.id = a.farm_id
    WHERE c.visible = 1
    ORDER BY a.created_at DESC
  ")->fetchAll();
  if (!$rows){ echo '<p>Sin elementos en el catálogo.</p>'; }
  foreach ($rows as $a): ?>
    <div class="card">
      <div class="badge"><?= e($a['species']) ?><?= $a['breed'] ? ' · '.e($a['breed']) : '' ?></div>
      <h3><?= e($a['name'] ?: $a['tag_code']) ?></h3>
      <small class="muted">Sexo: <?= e($a['sex']) ?> · Finca: <?= e($a['farm'] ?: '-') ?></small>
    </div>
  <?php endforeach; ?>
  </div>
</div>
</body></html>
