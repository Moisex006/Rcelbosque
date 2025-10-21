<?php require __DIR__ . '/../app/config.php'; require_admin();
$flash = ['ok'=>null,'err'=>null];

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (isset($_POST['action']) && $_POST['action']==='animal_create'){
    $data = [
      trim($_POST['tag_code'] ?? ''),
      ($_POST['name'] ?? null),
      ($_POST['sex'] ?? 'U'),
      ($_POST['birth_date'] ?? null),
      (strlen($_POST['species_id']??'')? $_POST['species_id'] : null),
      (strlen($_POST['breed_id']??'')? $_POST['breed_id'] : null),
      (strlen($_POST['farm_id']??'')? $_POST['farm_id'] : null),
      ($_POST['color'] ?? null),
      ($_POST['status'] ?? 'activo'),
      (strlen($_POST['sire_id']??'')? $_POST['sire_id'] : null),
      (strlen($_POST['dam_id']??'')? $_POST['dam_id'] : null)
    ];
    if ($data[0]===''){ $flash['err']='tag_code requerido'; }
    else {
      try{
        $stmt=$pdo->prepare("INSERT INTO animals(tag_code,name,sex,birth_date,species_id,breed_id,farm_id,color,status,sire_id,dam_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute($data);
        $flash['ok']='Animal creado';
      }catch(PDOException $e){
        $flash['err']= (strpos($e->getMessage(),'Duplicate')!==false?'tag_code debe ser único':$e->getMessage());
      }
    }
  }
  
  if (isset($_POST['action']) && $_POST['action']==='animal_update'){
    $id = (int)($_POST['id'] ?? 0);
    $data = [
      trim($_POST['tag_code'] ?? ''),
      ($_POST['name'] ?? null),
      ($_POST['sex'] ?? 'U'),
      ($_POST['birth_date'] ?? null),
      (strlen($_POST['species_id']??'')? $_POST['species_id'] : null),
      (strlen($_POST['breed_id']??'')? $_POST['breed_id'] : null),
      (strlen($_POST['farm_id']??'')? $_POST['farm_id'] : null),
      ($_POST['color'] ?? null),
      ($_POST['status'] ?? 'activo'),
      (strlen($_POST['sire_id']??'')? $_POST['sire_id'] : null),
      (strlen($_POST['dam_id']??'')? $_POST['dam_id'] : null),
      $id
    ];
    if ($data[0]===''){ $flash['err']='tag_code requerido'; }
    else if ($id <= 0){ $flash['err']='ID de animal inválido'; }
    else {
      try{
        $stmt=$pdo->prepare("UPDATE animals SET tag_code=?,name=?,sex=?,birth_date=?,species_id=?,breed_id=?,farm_id=?,color=?,status=?,sire_id=?,dam_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->execute($data);
        $flash['ok']='Animal actualizado';
      }catch(PDOException $e){
        $flash['err']= (strpos($e->getMessage(),'Duplicate')!==false?'tag_code debe ser único':$e->getMessage());
      }
    }
  }
  if (isset($_POST['action']) && $_POST['action']==='user_role'){
    $id = (int)($_POST['id'] ?? 0);
    $role = ($_POST['role'] ?? 'user')==='admin'?'admin':'user';
    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$id]);
    $flash['ok']='Rol actualizado';
  }
}
if (isset($_GET['catalog']) && $_GET['catalog']==='add' && isset($_GET['id'])){
  $pdo->prepare("INSERT IGNORE INTO catalog_items(animal_id,visible) VALUES (?,1)")->execute([ (int)$_GET['id'] ]);
}
if (isset($_GET['catalog']) && $_GET['catalog']==='remove' && isset($_GET['id'])){
  $pdo->prepare("DELETE FROM catalog_items WHERE animal_id=?")->execute([ (int)$_GET['id'] ]);
}

// Check if editing animal
$editAnimal = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
  $stmt->execute([(int)$_GET['edit']]);
  $editAnimal = $stmt->fetch();
}

// List data for UI
$q = '%' . ($_GET['q'] ?? '') . '%';
$stmt = $pdo->prepare("
  SELECT a.id, a.tag_code, a.name, s.name as species,
         (SELECT COUNT(*) FROM catalog_items c WHERE c.animal_id=a.id) as in_cat
  FROM animals a LEFT JOIN species s ON s.id=a.species_id
  WHERE a.tag_code LIKE ? OR a.name LIKE ? ORDER BY a.created_at DESC LIMIT 50
");
$stmt->execute([$q,$q]);
$animals = $stmt->fetchAll();
$users = $pdo->query("SELECT id,name,email,role FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css"><title>Admin</title></head><body>
<nav class="nav">
  <a href="index.php">AgroGan</a>
  <a href="catalogo.php">Catálogo</a>
  <a href="admin.php">Admin</a>
  <a href="logout.php" style="margin-left:auto" class="btn gray">Salir</a>
</nav>
<div class="container">
  <h2>Panel administrador</h2>
  <?php if($flash['err']): ?><div class="alert err"><?= e($flash['err']) ?></div><?php endif; ?>
  <?php if($flash['ok']): ?><div class="alert ok"><?= e($flash['ok']) ?></div><?php endif; ?>

  <div class="grid grid-2">
    <div class="card">
      <h3><?= $editAnimal ? 'Editar animal' : 'Registrar animal' ?></h3>
      <?php if ($editAnimal): ?>
        <div style="margin-bottom:1rem">
          <a href="admin.php" class="btn gray">« Cancelar edición</a>
        </div>
      <?php endif; ?>
      <form method="post" class="grid grid-3">
        <input type="hidden" name="action" value="<?= $editAnimal ? 'animal_update' : 'animal_create' ?>">
        <?php if ($editAnimal): ?>
          <input type="hidden" name="id" value="<?= e($editAnimal['id']) ?>">
        <?php endif; ?>
        <label>Tag/Arete <input name="tag_code" required value="<?= e($editAnimal['tag_code'] ?? '') ?>"></label>
        <label>Nombre <input name="name" value="<?= e($editAnimal['name'] ?? '') ?>"></label>
        <label>Sexo
          <select name="sex">
            <option value="U" <?= ($editAnimal['sex']??'U')==='U'?'selected':'' ?>>Desconocido</option>
            <option value="M" <?= ($editAnimal['sex']??'')==='M'?'selected':'' ?>>Macho</option>
            <option value="F" <?= ($editAnimal['sex']??'')==='F'?'selected':'' ?>>Hembra</option>
          </select>
        </label>
        <label>Nacimiento <input type="date" name="birth_date" value="<?= e($editAnimal['birth_date'] ?? '') ?>"></label>
        <label>Especie ID <input type="number" name="species_id" min="1" value="<?= e($editAnimal['species_id'] ?? '') ?>"></label>
        <label>Raza ID <input type="number" name="breed_id" min="1" value="<?= e($editAnimal['breed_id'] ?? '') ?>"></label>
        <label>Finca ID <input type="number" name="farm_id" min="1" value="<?= e($editAnimal['farm_id'] ?? '') ?>"></label>
        <label>Color <input name="color" value="<?= e($editAnimal['color'] ?? '') ?>"></label>
        <label>Estado <input name="status" value="<?= e($editAnimal['status'] ?? 'activo') ?>"></label>
        <div style="grid-column:1/-1">
          <button class="btn"><?= $editAnimal ? 'Actualizar' : 'Crear' ?></button>
        </div>
      </form>
    </div>

    <div class="card">
      <h3>Seleccionar animales para catálogo</h3>
      <form method="get" action="admin.php"><input type="text" name="q" placeholder="tag o nombre" value="<?= e($_GET['q'] ?? '') ?>"></form>
      <table>
        <thead><tr><th>ID</th><th>Tag</th><th>Nombre</th><th>Especie</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if(!$animals){ echo '<tr><td colspan=5>Sin resultados</td></tr>'; } ?>
          <?php foreach($animals as $a): ?>
          <tr>
            <td><?= e($a['id']) ?></td>
            <td><?= e($a['tag_code']) ?></td>
            <td><?= e($a['name']) ?></td>
            <td><?= e($a['species']) ?></td>
            <td>
              <div class="actions-group">
                <a class="btn secondary small" href="admin.php?edit=<?= e($a['id']) ?>">Editar</a>
                <?php if ($a['in_cat']): ?>
                  <a class="btn gray small" href="admin.php?catalog=remove&id=<?= e($a['id']) ?>">Quitar</a>
                <?php else: ?>
                  <a class="btn small" href="admin.php?catalog=add&id=<?= e($a['id']) ?>">Agregar</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="grid-column:1/-1">
      <h3>Usuarios</h3>
      <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Acción</th></tr></thead>
        <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td><?= e($u['id']) ?></td>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge"><?= e($u['role']) ?></span></td>
            <td>
              <form method="post" style="display:inline-flex;gap:.4rem;align-items:center">
                <input type="hidden" name="action" value="user_role">
                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                <select name="role">
                  <option value="user" <?= $u['role']==='user'?'selected':''; ?>>user</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':''; ?>>admin</option>
                </select>
                <button class="btn">Guardar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body></html>
