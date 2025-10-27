<?php 
require __DIR__ . '/../app/config.php';

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
  GROUP BY l.id
  ORDER BY l.created_at DESC
")->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Catálogo - AgroGan</title>
  <link rel="stylesheet" href="assets/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

    .nav {
      background: white;
      border-bottom: 1px solid var(--gray-200);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      gap: 2rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .nav a {
      color: var(--gray-700);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s ease;
    }

    .nav a:hover {
      color: var(--primary-green);
    }

    .nav a:first-child {
      font-weight: 700;
      color: var(--primary-green);
      font-size: 1.25rem;
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

    @media (max-width: 768px) {
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
    <a href="index.php"><i class="fas fa-home"></i> AgroGan</a>
    <a href="catalogo.php" class="active"><i class="fas fa-list"></i> Catálogo</a>
    <?php if(!is_logged_in()): ?>
      <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
      <a href="register.php"><i class="fas fa-user-plus"></i> Registro</a>
    <?php else: ?>
      <a href="admin.php"><i class="fas fa-cog"></i> Admin</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
    <?php endif; ?>
  </nav>

  <div class="container">
    <div class="catalog-header">
      <h1><i class="fas fa-cow"></i> Catálogo de Animales AgroGan</h1>
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
                <button class="btn btn-secondary" onclick="contactAnimal(<?= $animal['id'] ?>)">
                  <i class="fas fa-phone"></i> Contactar
                </button>
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
                  <strong>Precio Total:</strong><br>
                  <span style="color: var(--success); font-weight: 600; font-size: 1.1rem;">$<?= number_format($lot['total_price'], 2) ?></span>
                </div>
                
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
                <button class="btn btn-primary" onclick="contactLot(<?= $lot['id'] ?>)">
                  <i class="fas fa-phone"></i> Contactar
                </button>
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

  <script>
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

    function contactLot(lotId) {
      alert('Contactar sobre el lote ' + lotId + '\n\nInformación de contacto:\nTeléfono: +57 300 123 4567\nEmail: ventas@agrogan.com');
    }

    function viewLotDetails(lotId) {
      alert('Ver detalles del lote ' + lotId + '\n\nEsta funcionalidad mostrará:\n- Lista de animales en el lote\n- Características detalladas\n- Historial veterinario\n- Fotos y documentos');
    }

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

    function contactAnimal(animalId) {
      alert('Contactar sobre el animal ' + animalId + '\n\nInformación de contacto:\nTeléfono: +57 300 123 4567\nEmail: ventas@agrogan.com');
    }
  </script>
</body>
</html>