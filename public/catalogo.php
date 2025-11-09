<?php 
require __DIR__ . '/../app/config.php';

// Determinar si el usuario puede ver el botón de cotizar
// Solo usuarios normales (rol 'user') o no logueados pueden cotizar
$can_show_quote_button = true;
if (is_logged_in()) {
  $current_user = current_user();
  $user_role = $current_user['role'] ?? 'user';
  // Usuarios con roles administrativos NO pueden cotizar
  if (in_array($user_role, ['admin_general', 'admin_finca', 'veterinario'])) {
    $can_show_quote_button = false;
  }
}

// Obtener animales visibles en el catálogo con sus fotos
$animals = $pdo->query("
  SELECT a.*, s.name as species_name, b.name as breed_name, f.name as farm_name,
         ap.file_path as primary_photo, ap.filename as photo_filename
  FROM animals a
  LEFT JOIN species s ON s.id = a.species_id
  LEFT JOIN breeds b ON b.id = a.breed_id
  LEFT JOIN farms f ON f.id = a.farm_id
  LEFT JOIN animal_photos ap ON ap.animal_id = a.id AND ap.is_primary = 1
  WHERE a.in_cat = 1
  ORDER BY a.created_at DESC
")->fetchAll();

// Obtener lotes disponibles
$lots = $pdo->query("
  SELECT l.*, f.name as farm_name,
         COUNT(la.animal_id) as animal_count
  FROM lots l
  LEFT JOIN farms f ON f.id = l.farm_id
  LEFT JOIN lot_animals la ON la.lot_id = l.id
  WHERE l.status = 'disponible'
    AND EXISTS (
      SELECT 1 FROM nominations n
      WHERE n.item_type = 'lot'
        AND n.item_id = l.id
        AND n.status = 'approved'
    )
  GROUP BY l.id
  ORDER BY l.created_at DESC
")->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Catálogo - Rc El Bosque</title>
  <link rel="stylesheet" href="assets/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
  <style>
    :root {
      --primary-green: #2d5a27;
      --accent-green: #3e7b2e;
      --light-green: #4a9a3d;
      --bg-green: #f0f8ec;
      --text-dark: #1a3315;
      --success: #10b981;
      --warning: #f59e0b;
      --error: #ef4444;
      --info: #3b82f6;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-400: #9ca3af;
      --gray-500: #6b7280;
      --gray-600: #4b5563;
      --gray-700: #374151;
      --gray-800: #1f2937;
      --gray-900: #111827;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      background: var(--gray-50);
      color: var(--text-dark);
      line-height: 1.6;
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

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }

    .catalog-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .catalog-header h1 {
      color: var(--primary-green);
      font-size: 2.5rem;
      margin-bottom: 1rem;
    }

    .catalog-header p {
      color: var(--gray-600);
      font-size: 1.125rem;
    }

    .catalog-tabs {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 2rem;
      background: white;
      padding: 0.5rem;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .tab-btn {
      flex: 1;
      padding: 1rem 1.5rem;
      border: none;
      background: transparent;
      color: var(--gray-600);
      font-weight: 600;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .tab-btn:hover {
      background: var(--gray-50);
      color: var(--primary-green);
    }

    .tab-btn.active {
      background: var(--primary-green);
      color: white;
      box-shadow: 0 2px 8px rgba(45, 90, 39, 0.3);
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    .catalog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
    }

    .catalog-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      transition: all 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .catalog-card:hover {
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .catalog-card.animal {
      border-left: 4px solid var(--success);
    }

    .catalog-card.lot {
      border-left: 4px solid #8B5CF6;
    }

    .card-badge.lot {
      background: #8B5CF6;
    }

    .card-icon.lot {
      background: rgba(139, 92, 246, 0.1);
      color: #8B5CF6;
    }

    .card-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: var(--primary-green);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .card-badge.animal {
      background: var(--success);
    }

    .card-badge.product {
      background: var(--info);
    }

    .card-icon {
      width: 3rem;
      height: 3rem;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      font-size: 1.5rem;
    }

    .card-icon.animal {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
    }

    .card-icon.product {
      background: rgba(59, 130, 246, 0.1);
      color: var(--info);
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--gray-800);
      margin-bottom: 0.5rem;
    }

    .card-subtitle {
      color: var(--gray-600);
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .card-details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .card-detail {
      font-size: 0.875rem;
      color: var(--gray-600);
    }

    .card-detail strong {
      color: var(--gray-800);
    }

    .card-price {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary-green);
      text-align: center;
      margin-bottom: 1rem;
    }

    .card-description {
      color: var(--gray-600);
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .card-actions {
      display: flex;
      gap: 0.5rem;
    }

    .btn {
      flex: 1;
      padding: 0.75rem 1rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
      font-size: 0.875rem;
    }

    .btn-primary {
      background: var(--primary-green);
      color: white;
    }

    .btn-primary:hover {
      background: var(--accent-green);
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: var(--gray-100);
      color: var(--gray-700);
    }

    .btn-secondary:hover {
      background: var(--gray-200);
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--gray-500);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }

    .card-photo {
      margin-bottom: 1rem;
      border-radius: 8px;
      overflow: hidden;
    }

    .card-photo img {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 8px;
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.7);
      animation: fadeIn 0.3s ease;
    }

    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      max-width: 900px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      animation: slideUp 0.3s ease;
      position: relative;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-200);
    }

    .modal-header h2 {
      color: var(--primary-green);
      margin: 0;
    }

    .close-modal {
      background: none;
      border: none;
      font-size: 2rem;
      color: var(--gray-400);
      cursor: pointer;
      padding: 0;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s ease;
    }

    .close-modal:hover {
      background: var(--gray-100);
      color: var(--error);
    }

    .modal-photos {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .modal-photo {
      position: relative;
      border-radius: 8px;
      overflow: hidden;
      aspect-ratio: 1;
      cursor: pointer;
      transition: transform 0.2s ease;
    }

    .modal-photo:hover {
      transform: scale(1.05);
    }

    .modal-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .modal-photo-description {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
      color: white;
      padding: 0.75rem;
      font-size: 0.875rem;
    }

    .modal-info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .modal-info-item {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }

    .modal-info-item strong {
      color: var(--primary-green);
      font-size: 0.875rem;
      text-transform: uppercase;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
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
      
      .container {
        padding: 1rem;
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
      
      .container {
        padding: 1rem;
      }
      
      .catalog-grid {
        grid-template-columns: 1fr;
      }
      
      .catalog-tabs {
        flex-direction: column;
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

<div class="container">
    <div class="catalog-header">
      <h1><i class="fas fa-cow"></i> Catálogo de Animales Rc El Bosque</h1>
      <p>Descubre nuestros animales disponibles para venta</p>
    </div>

        <div class="catalog-tabs">
          <button class="tab-btn active" onclick="showTab('animals')">
            <i class="fas fa-cow"></i> Animales Disponibles
          </button>
          <button class="tab-btn" onclick="showTab('lots')">
            <i class="fas fa-layer-group"></i> Lotes Disponibles
          </button>
        </div>

    <!-- Animals Tab -->
    <div id="animals-tab" class="tab-content active">
      <div class="catalog-grid">
  <?php
        // Obtener animales con fotos primarias
        $stmt = $pdo->prepare("
          SELECT a.*, s.name as species_name, b.name as breed_name, f.name as farm_name,
                 ap.file_path as primary_photo, ap.filename as photo_filename
          FROM animals a
    LEFT JOIN species s ON s.id = a.species_id
    LEFT JOIN breeds b ON b.id = a.breed_id
          LEFT JOIN farms f ON f.id = a.farm_id
          LEFT JOIN animal_photos ap ON ap.animal_id = a.id AND ap.is_primary = 1
          WHERE a.in_cat = 1
    ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $animals = $stmt->fetchAll();
        
        if (empty($animals)): ?>
          <div class="empty-state">
            <i class="fas fa-cow"></i>
            <h3>No hay animales disponibles</h3>
            <p>Pronto tendremos animales disponibles en el catálogo.</p>
          </div>
        <?php else:
          foreach ($animals as $animal): ?>
            <div class="catalog-card animal">
              <div class="card-badge animal">Animal</div>
              
              <!-- Animal Photo -->
              <?php if (!empty($animal['primary_photo'])): ?>
                <div class="card-photo">
                  <img src="/Rcelbosque/public/uploads/animals/<?= basename($animal['primary_photo']) ?>" alt="<?= e($animal['name']) ?>" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
                </div>
              <?php else: ?>
                <div class="card-photo" style="background: var(--gray-100); height: 200px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                  <i class="fas fa-camera" style="font-size: 3rem; color: var(--gray-400);"></i>
                </div>
              <?php endif; ?>
              
              <div class="card-icon animal">
                <i class="fas fa-cow"></i>
              </div>
              <h3 class="card-title"><?= e($animal['name']) ?></h3>
              <p class="card-subtitle">
                <?= e($animal['species_name'] ?? 'Especie no especificada') ?>
                <?php if ($animal['breed_name']): ?>
                  · <?= e($animal['breed_name']) ?>
                <?php endif; ?>
              </p>
              
              <div class="card-details">
                <div class="card-detail">
                  <strong>Género:</strong><br>
                  <?= e($animal['gender'] ?? 'No especificado') ?>
                </div>
                <div class="card-detail">
                  <strong>Peso:</strong><br>
                  <?= $animal['weight'] ? $animal['weight'] . ' kg' : 'No especificado' ?>
                </div>
                <div class="card-detail">
                  <strong>Color:</strong><br>
                  <?= e($animal['color'] ?? 'No especificado') ?>
                </div>
              <?php if (!empty($animal['birth_date'])): ?>
              <div class="card-detail">
                <strong>Edad:</strong><br>
                <?php 
                  try {
                    $birth = new DateTime($animal['birth_date']);
                    $today = new DateTime('today');
                    $diff = $birth->diff($today);
                    echo (int)$diff->y . ' años, ' . (int)$diff->m . ' meses, ' . (int)$diff->d . ' días';
                  } catch (Throwable $e) {
                    echo 'N/D';
                  }
                ?>
              </div>
              <?php endif; ?>
                <div class="card-detail">
                  <strong>Finca:</strong><br>
                  <?= e($animal['farm_name'] ?? 'No especificada') ?>
                </div>
              </div>
              
              <?php if ($animal['description']): ?>
                <div class="card-description"><?= e($animal['description']) ?></div>
              <?php endif; ?>
              
              <div class="card-actions">
                <button class="btn btn-primary" onclick="viewAnimalDetails(<?= $animal['id'] ?>)">
                  <i class="fas fa-info-circle"></i> Más Info
                </button>
                <?php if ($can_show_quote_button): ?>
                <button class="btn btn-secondary" onclick="handleQuoteClick('animal', <?= $animal['id'] ?>, '<?= htmlspecialchars($animal['tag_code'] . ($animal['name'] ? ' - ' . $animal['name'] : ''), ENT_QUOTES) ?>')">
                  <i class="fas fa-calculator"></i> Cotizar
                </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach;
        endif; ?>
      </div>
    </div>

    <!-- Lots Tab -->
    <div id="lots-tab" class="tab-content">
      <div class="catalog-grid">
        <?php if (empty($lots)): ?>
          <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--gray-500);">
            <i class="fas fa-layer-group" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No hay lotes disponibles</h3>
            <p>Los lotes aparecerán aquí cuando estén disponibles para la venta.</p>
          </div>
        <?php else: ?>
          <?php foreach ($lots as $lot): ?>
            <div class="catalog-card lot">
              <div class="card-badge lot">
                <i class="fas fa-layer-group"></i> Lote
              </div>
              
              <div class="card-icon lot">
                <i class="fas fa-layer-group"></i>
              </div>
              
              <h3><?= e($lot['name']) ?></h3>
              <p class="card-description"><?= e($lot['description'] ?? 'Sin descripción') ?></p>
              
              <div class="card-details">
                <div class="card-detail">
                  <strong>Animales:</strong><br>
                  <?= $lot['animal_count'] ?> animales
                </div>
                
                <div class="card-detail">
                  <strong>Tipo:</strong><br>
                  <?= ucfirst(e($lot['lot_type'])) ?>
                </div>
                
                <?php if ($lot['farm_name']): ?>
                <div class="card-detail">
                  <strong>Finca:</strong><br>
                  <?= e($lot['farm_name']) ?>
                </div>
                <?php endif; ?>
              </div>
              
              <div class="card-actions">
                <?php if ($can_show_quote_button): ?>
                <button class="btn btn-primary" onclick="handleQuoteClick('lot', <?= $lot['id'] ?>, '<?= htmlspecialchars($lot['name'], ENT_QUOTES) ?>')">
                  <i class="fas fa-calculator"></i> Cotizar
                </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="viewLotDetails(<?= $lot['id'] ?>)">
                  <i class="fas fa-info-circle"></i> Ver Detalles
                </button>
              </div>
    </div>
  <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Animal Details Modal -->
  <div id="animalModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalAnimalName"><i class="fas fa-cow"></i> Detalles del Animal</h2>
        <button class="close-modal" onclick="closeAnimalModal()">&times;</button>
      </div>
      <div id="modalContent">
        <p>Cargando...</p>
      </div>
  </div>
</div>

  <!-- Lot Details Modal -->
  <div id="lotModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalLotTitle"><i class="fas fa-layer-group"></i> Detalles del Lote</h2>
        <button class="close-modal" onclick="closeLotModal()">&times;</button>
      </div>
      <div id="lotModalContent">
        <p>Cargando...</p>
      </div>
    </div>
  </div>

  <!-- Quote Modal -->
  <div id="quoteModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <h2><i class="fas fa-calculator"></i> Solicitar Cotización</h2>
        <button class="close-modal" onclick="closeQuoteModal()">&times;</button>
      </div>
      <div id="quoteModalContent">
        <form id="quoteForm" onsubmit="submitQuote(event)">
          <input type="hidden" id="quoteType" name="type">
          <input type="hidden" id="quoteItemId" name="item_id">
          
          <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="margin: 0;"><strong>Item:</strong> <span id="quoteItemName"></span></p>
          </div>
          
          <div style="margin-bottom: 15px;">
            <label for="quoteName" style="display: block; margin-bottom: 5px; font-weight: 600;">Nombre completo *</label>
            <input type="text" id="quoteName" name="name" required 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                   placeholder="Ingresa tu nombre completo">
          </div>
          
          <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email para recibir la cotización *</label>
            <?php if(is_logged_in()): 
              $current_user = current_user();
              $user_email = $current_user['email'] ?? '';
            ?>
              <div style="margin-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                  <input type="radio" name="email_option" value="registered" id="emailRegistered" checked onchange="toggleEmailInput()" style="margin: 0;">
                  <span><i class="fas fa-envelope"></i> Usar mi correo registrado: <strong><?= e($user_email) ?></strong></span>
                </label>
              </div>
              <div style="margin-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                  <input type="radio" name="email_option" value="custom" id="emailCustom" onchange="toggleEmailInput()" style="margin: 0;">
                  <span><i class="fas fa-edit"></i> Usar otro correo</span>
                </label>
              </div>
              <input type="hidden" id="quoteEmailRegistered" value="<?= e($user_email) ?>">
            <?php endif; ?>
            <div id="customEmailContainer" style="<?= is_logged_in() ? 'display: none;' : '' ?>">
              <input type="email" id="quoteEmail" name="email" <?= is_logged_in() ? '' : 'required' ?>
                     style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                     placeholder="tu@email.com">
            </div>
          </div>
          
          <div style="margin-bottom: 15px;">
            <label for="quotePhone" style="display: block; margin-bottom: 5px; font-weight: 600;">Teléfono *</label>
            <input type="tel" id="quotePhone" name="phone" required 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem;"
                   placeholder="3132280538">
          </div>
          
          <div style="margin-bottom: 20px;">
            <label for="quoteMessage" style="display: block; margin-bottom: 5px; font-weight: 600;">Mensaje (opcional)</label>
            <textarea id="quoteMessage" name="message" rows="4" 
                      style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; resize: vertical;"
                      placeholder="Escribe aquí cualquier información adicional o pregunta..."></textarea>
          </div>
          
          <div id="quoteMessageDiv" style="margin-bottom: 15px; display: none;"></div>
          
          <div style="display: flex; gap: 10px;">
            <button type="button" onclick="closeQuoteModal()" 
                    style="flex: 1; padding: 12px; border: 1px solid #ddd; background: white; border-radius: 5px; cursor: pointer; font-size: 1rem;">
              Cancelar
            </button>
            <button type="submit" id="quoteSubmitBtn"
                    style="flex: 1; padding: 12px; background: var(--primary-green); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 600;">
              <i class="fas fa-paper-plane"></i> Enviar Solicitud
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function formatAgeFromDateString(dateStr) {
      if (!dateStr) return null;
      const birth = new Date(dateStr);
      if (isNaN(birth.getTime())) return null;
      const today = new Date();

      let years = today.getFullYear() - birth.getFullYear();
      let months = today.getMonth() - birth.getMonth();
      let days = today.getDate() - birth.getDate();

      if (days < 0) {
        months -= 1;
        const prevMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        const daysInPrevMonth = prevMonth.getDate();
        days += daysInPrevMonth;
      }
      if (months < 0) {
        years -= 1;
        months += 12;
      }

      return years + ' años, ' + months + ' meses, ' + days + ' días';
    }
    function showTab(tabName) {
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Remove active class from all buttons
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
      });
      
      // Show selected tab
      document.getElementById(tabName + '-tab').classList.add('active');
      
      // Add active class to clicked button
      event.target.classList.add('active');
    }

    // Variable global para saber si el usuario está logueado
    const isUserLoggedIn = <?= is_logged_in() ? 'true' : 'false' ?>;
    
    // Función para manejar el clic en el botón de cotizar
    function handleQuoteClick(type, itemId, itemName) {
      // Si el usuario no está logueado, redirigir a login
      if (!isUserLoggedIn) {
        // Guardar la información del item en sessionStorage para después de login
        sessionStorage.setItem('quote_after_login', JSON.stringify({
          type: type,
          itemId: itemId,
          itemName: itemName
        }));
        // Redirigir a login con parámetro de retorno
        window.location.href = 'login.php?redirect=catalogo.php&action=quote';
        return;
      }
      // Si está logueado, mostrar el modal normalmente
      showQuoteModal(type, itemId, itemName);
    }
    
    // Función para mostrar el modal de cotización
    function showQuoteModal(type, itemId, itemName) {
      const modal = document.getElementById('quoteModal');
      const quoteType = document.getElementById('quoteType');
      const quoteItemId = document.getElementById('quoteItemId');
      const quoteItemName = document.getElementById('quoteItemName');
      const quoteForm = document.getElementById('quoteForm');
      const quoteMessageDiv = document.getElementById('quoteMessageDiv');
      
      quoteType.value = type;
      quoteItemId.value = itemId;
      quoteItemName.textContent = itemName;
      quoteForm.reset();
      quoteMessageDiv.style.display = 'none';
      quoteMessageDiv.innerHTML = '';
      
      // Resetear opciones de email si el usuario está logueado
      const emailRegistered = document.getElementById('emailRegistered');
      const emailCustom = document.getElementById('emailCustom');
      const customEmailContainer = document.getElementById('customEmailContainer');
      const quoteEmail = document.getElementById('quoteEmail');
      
      if (emailRegistered) {
        emailRegistered.checked = true;
        if (emailCustom) {
          emailCustom.checked = false;
        }
        if (customEmailContainer) {
          customEmailContainer.style.display = 'none';
        }
        if (quoteEmail) {
          quoteEmail.removeAttribute('required');
        }
      }
      
      modal.classList.add('active');
    }
    
    // Función para alternar entre email registrado y personalizado
    function toggleEmailInput() {
      const emailRegistered = document.getElementById('emailRegistered');
      const emailCustom = document.getElementById('emailCustom');
      const customEmailContainer = document.getElementById('customEmailContainer');
      const quoteEmail = document.getElementById('quoteEmail');
      
      if (!emailRegistered || !emailCustom || !customEmailContainer || !quoteEmail) {
        return; // Si no existen los elementos, el usuario no está logueado
      }
      
      if (emailRegistered.checked) {
        customEmailContainer.style.display = 'none';
        quoteEmail.removeAttribute('required');
        quoteEmail.value = '';
      } else if (emailCustom.checked) {
        customEmailContainer.style.display = 'block';
        quoteEmail.setAttribute('required', 'required');
        quoteEmail.focus();
      }
    }
    
    function closeQuoteModal() {
      const modal = document.getElementById('quoteModal');
      modal.classList.remove('active');
    }
    
    async function submitQuote(event) {
      event.preventDefault();
      
      const form = event.target;
      const formData = new FormData(form);
      const submitBtn = document.getElementById('quoteSubmitBtn');
      const quoteMessageDiv = document.getElementById('quoteMessageDiv');
      
      // Obtener el email correcto según la opción seleccionada
      const emailRegistered = document.getElementById('emailRegistered');
      const emailCustom = document.getElementById('emailCustom');
      const quoteEmailRegistered = document.getElementById('quoteEmailRegistered');
      const quoteEmail = document.getElementById('quoteEmail');
      
      if (emailRegistered && emailCustom) {
        // Usuario logueado - usar el email según la opción seleccionada
        if (emailRegistered.checked && quoteEmailRegistered) {
          formData.set('email', quoteEmailRegistered.value);
        } else if (emailCustom.checked && quoteEmail) {
          formData.set('email', quoteEmail.value);
        }
        // Eliminar email_option del formData ya que no lo necesitamos en el backend
        formData.delete('email_option');
      }
      // Si no está logueado, el email ya está en el formData
      
      // Deshabilitar botón y mostrar loading
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
      quoteMessageDiv.style.display = 'none';
      
      try {
        const response = await fetch('process_quote.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          quoteMessageDiv.style.display = 'block';
          quoteMessageDiv.innerHTML = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
          form.reset();
          
          // Cerrar modal después de 3 segundos
          setTimeout(() => {
            closeQuoteModal();
          }, 3000);
        } else {
          quoteMessageDiv.style.display = 'block';
          quoteMessageDiv.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;"><i class="fas fa-exclamation-circle"></i> ' + result.message + '</div>';
        }
      } catch (error) {
        quoteMessageDiv.style.display = 'block';
        quoteMessageDiv.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;"><i class="fas fa-exclamation-circle"></i> Error al enviar la solicitud. Por favor, intenta nuevamente.</div>';
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Solicitud';
      }
    }
    
    // Cerrar modal al hacer clic fuera
    document.getElementById('quoteModal')?.addEventListener('click', function(e) {
      if (e.target === this) {
        closeQuoteModal();
      }
    });

    async function viewLotDetails(lotId) {
      const modal = document.getElementById('lotModal');
      const modalContent = document.getElementById('lotModalContent');
      const modalLotTitle = document.getElementById('modalLotTitle');

      modalContent.innerHTML = '<p style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><br>Cargando información del lote...</p>';
      modal.classList.add('active');

      try {
        const response = await fetch('get-lot-details.php?id=' + lotId);
        const data = await response.json();

        if (data.error) {
          modalContent.innerHTML = '<p style="color: var(--error);">Error: ' + data.error + '</p>';
          return;
        }

        const lot = data.lot;
        const animals = data.animals || [];

        modalLotTitle.innerHTML = '<i class="fas fa-layer-group"></i> ' + (lot.name || ('Lote #' + lot.id));

        let html = '';

        // Información del lote
        html += '<div class="modal-info-grid">';
        html += '<div class="modal-info-item"><strong>Tipo</strong><span>' + (lot.lot_type ? (String(lot.lot_type).charAt(0).toUpperCase() + String(lot.lot_type).slice(1)) : 'No especificado') + '</span></div>';
        if (lot.farm_name) html += '<div class="modal-info-item"><strong>Finca</strong><span>' + lot.farm_name + '</span></div>';
        if (lot.created_at) html += '<div class="modal-info-item"><strong>Creado</strong><span>' + new Date(lot.created_at).toLocaleDateString('es-ES') + '</span></div>';
        html += '</div>';

        if (lot.description) {
          html += '<div style="margin-bottom: 1.5rem;"><strong style="color: var(--primary-green);">Descripción:</strong><p style="margin-top: 0.5rem;">' + lot.description + '</p></div>';
        }

        // Lista de animales
        html += '<h3 style="margin: 1rem 0; color: var(--gray-800);"><i class="fas fa-cow"></i> Animales en este lote (' + animals.length + ')</h3>';

        if (animals.length === 0) {
          html += '<p class="card-description">Este lote no tiene animales asociados.</p>';
        } else {
          html += '<div class="catalog-grid">';
          animals.forEach(animal => {
            html += '<div class="catalog-card animal">';
            // Foto
            if (animal.photo_filename) {
              html += '<div class="card-photo"><img src="/Rcelbosque/public/uploads/animals/' + animal.photo_filename + '" alt="' + (animal.name || ('Animal #' + animal.id)) + '"></div>';
            } else {
              html += '<div class="card-photo" style="background: var(--gray-100); height: 200px; display: flex; align-items: center; justify-content: center; border-radius: 8px;"><i class="fas fa-camera" style="font-size: 3rem; color: var(--gray-400);"></i></div>';
            }

            html += '<div class="card-icon animal"><i class="fas fa-cow"></i></div>';
            html += '<h3 class="card-title">' + (animal.name || ('Animal #' + animal.id)) + '</h3>';
            html += '<p class="card-subtitle">' + (animal.species_name || 'Especie no especificada') + (animal.breed_name ? (' · ' + animal.breed_name) : '') + '</p>';

            html += '<div class="card-details">';
            html += '<div class="card-detail"><strong>Arete</strong><br>' + (animal.tag_code || 'N/D') + '</div>';
            html += '<div class="card-detail"><strong>Género</strong><br>' + (animal.gender || 'N/D') + '</div>';
            html += '<div class="card-detail"><strong>Peso</strong><br>' + (animal.weight ? (animal.weight + ' kg') : 'N/D') + '</div>';
            html += '<div class="card-detail"><strong>Color</strong><br>' + (animal.color || 'N/D') + '</div>';
            if (animal.birth_date) {
              const ageStr = formatAgeFromDateString(animal.birth_date);
              if (ageStr) {
                html += '<div class="card-detail"><strong>Edad</strong><br>' + ageStr + '</div>';
              }
            }
            html += '</div>';

            html += '</div>';
          });
          html += '</div>';
        }

        modalContent.innerHTML = html;
      } catch (error) {
        modalContent.innerHTML = '<p style="color: var(--error);">Error: ' + error.message + '</p>';
      }
    }

    function closeLotModal() {
      document.getElementById('lotModal').classList.remove('active');
    }

    document.addEventListener('click', function(e) {
      if (e.target === document.getElementById('lotModal')) {
        closeLotModal();
      }
    });

    async function viewAnimalDetails(animalId) {
      const modal = document.getElementById('animalModal');
      const modalContent = document.getElementById('modalContent');
      const modalAnimalName = document.getElementById('modalAnimalName');
      
      modalContent.innerHTML = '<p style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><br>Cargando información...</p>';
      modal.classList.add('active');
      
      try {
        const response = await fetch('get-animal-details.php?id=' + animalId);
        const data = await response.json();
        
        if (data.error) {
          modalContent.innerHTML = '<p style="color: var(--error);">Error: ' + data.error + '</p>';
          return;
        }
        
        const animal = data.animal;
        const photos = data.photos || [];
        
        modalAnimalName.innerHTML = '<i class="fas fa-cow"></i> ' + (animal.name || 'Animal #' + animal.id);
        
        let html = '';
        
        // Fotos
        if (photos.length > 0) {
          html += '<div class="modal-photos">';
          photos.forEach(photo => {
            html += '<div class="modal-photo"><img src="/Rcelbosque/public/uploads/animals/' + photo.filename + '" alt="' + photo.original_name + '">' + (photo.description ? '<div class="modal-photo-description">' + photo.description + '</div>' : '') + '</div>';
          });
          html += '</div>';
        }
        
        // Información
        html += '<div class="modal-info-grid">';
        html += '<div class="modal-info-item"><strong>Especie</strong><span>' + (animal.species_name || 'Bovino') + '</span></div>';
        html += '<div class="modal-info-item"><strong>Raza</strong><span>' + (animal.breed_name || 'Brahman') + '</span></div>';
        html += '<div class="modal-info-item"><strong>Género</strong><span>' + (animal.gender || 'No especificado') + '</span></div>';
        html += '<div class="modal-info-item"><strong>Peso</strong><span>' + (animal.weight ? animal.weight + ' kg' : 'No especificado') + '</span></div>';
        html += '<div class="modal-info-item"><strong>Color</strong><span>' + (animal.color || 'No especificado') + '</span></div>';
        if (animal.birth_date) {
          const ageStr = formatAgeFromDateString(animal.birth_date);
          if (ageStr) {
            html += '<div class="modal-info-item"><strong>Edad</strong><span>' + ageStr + '</span></div>';
          }
        }
        if (animal.birth_date) {
          html += '<div class="modal-info-item"><strong>Fecha de Nacimiento</strong><span>' + new Date(animal.birth_date).toLocaleDateString('es-ES') + '</span></div>';
        }
        html += '<div class="modal-info-item"><strong>Finca</strong><span>' + (animal.farm_name || 'No especificada') + '</span></div>';
        html += '</div>';
        
        if (animal.description) {
          html += '<div style="margin-bottom: 1.5rem;"><strong style="color: var(--primary-green);">Descripción:</strong><p style="margin-top: 0.5rem;">' + animal.description + '</p></div>';
        }
        
        modalContent.innerHTML = html;
        
      } catch (error) {
        modalContent.innerHTML = '<p style="color: var(--error);">Error: ' + error.message + '</p>';
      }
    }
    
    function closeAnimalModal() {
      document.getElementById('animalModal').classList.remove('active');
    }
    
    document.addEventListener('click', function(e) {
      if (e.target === document.getElementById('animalModal')) {
        closeAnimalModal();
      }
    });
    
    // Verificar si el usuario viene de hacer login y quiere cotizar
    document.addEventListener('DOMContentLoaded', function() {
      // Verificar si hay información guardada de una cotización pendiente
      const quoteData = sessionStorage.getItem('quote_after_login');
      if (quoteData && isUserLoggedIn) {
        try {
          const data = JSON.parse(quoteData);
          // Limpiar sessionStorage
          sessionStorage.removeItem('quote_after_login');
          // Abrir el modal de cotización automáticamente
          setTimeout(function() {
            showQuoteModal(data.type, data.itemId, data.itemName);
          }, 500); // Pequeño delay para asegurar que todo esté cargado
        } catch (e) {
          console.error('Error al procesar cotización pendiente:', e);
        }
      }
    });

  </script>

  <!-- JavaScript para el menú hamburguesa -->
  <script>
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
</body>
</html>