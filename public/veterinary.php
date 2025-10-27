<?php
require_once '../app/config.php';
require_login();

// Obtener datos básicos para el módulo
$pdo = get_pdo();
$user = current_user();

// Obtener estadísticas básicas
$totalAnimals = $pdo->query("SELECT COUNT(*) FROM animals")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$animalsInCatalog = $pdo->query("SELECT COUNT(*) FROM catalog_items WHERE visible = 1")->fetchColumn();

// Obtener animales para los selects
$animals = $pdo->query("SELECT id, tag_code, name FROM animals ORDER BY tag_code")->fetchAll(PDO::FETCH_ASSOC);

// Verificar si las tablas veterinarias existen, si no, crearlas
try {
    $pdo->query("SELECT 1 FROM veterinarians LIMIT 1");
} catch (PDOException $e) {
    // Crear tablas veterinarias si no existen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS veterinarians (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            license_number VARCHAR(50) UNIQUE,
            phone VARCHAR(20),
            email VARCHAR(120),
            specialization VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS medications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            active_ingredient VARCHAR(200),
            dosage_form ENUM('inyeccion','oral','topico','inhalacion') NOT NULL,
            concentration VARCHAR(50),
            manufacturer VARCHAR(120),
            batch_number VARCHAR(50),
            expiration_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS treatments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            animal_id INT NOT NULL,
            veterinarian_id INT,
            treatment_date DATE NOT NULL,
            treatment_type ENUM('preventivo','curativo','quirurgico','reproductivo','nutricional') NOT NULL,
            diagnosis TEXT,
            symptoms TEXT,
            treatment_description TEXT NOT NULL,
            dosage VARCHAR(100),
            duration_days INT,
            cost DECIMAL(10,2),
            status ENUM('en_progreso','completado','cancelado','suspender') DEFAULT 'en_progreso',
            follow_up_date DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
            FOREIGN KEY (veterinarian_id) REFERENCES veterinarians(id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS health_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            animal_id INT,
            alert_type ENUM('vacuna_vencida','tratamiento_pendiente','cuarentena','enfermedad','peso_bajo','peso_alto','revision_periodica') NOT NULL,
            severity ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            alert_date DATE NOT NULL,
            due_date DATE,
            status ENUM('activa','resuelta','cancelada') DEFAULT 'activa',
            resolved_at TIMESTAMP NULL,
            resolved_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
            FOREIGN KEY (resolved_by) REFERENCES users(id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quarantines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            animal_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE,
            reason TEXT NOT NULL,
            location VARCHAR(120),
            restrictions TEXT,
            status ENUM('activa','finalizada','cancelada') DEFAULT 'activa',
            veterinarian_id INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
            FOREIGN KEY (veterinarian_id) REFERENCES veterinarians(id)
        )
    ");
    
    // Insertar datos de ejemplo
    $pdo->exec("
        INSERT IGNORE INTO veterinarians (name, license_number, phone, email, specialization) VALUES
        ('Dr. Carlos Mendoza', 'VET-001-2023', '300-123-4567', 'carlos.mendoza@vet.com', 'Medicina Interna'),
        ('Dra. Ana García', 'VET-002-2023', '300-234-5678', 'ana.garcia@vet.com', 'Cirugía'),
        ('Dr. Luis Rodríguez', 'VET-003-2023', '300-345-6789', 'luis.rodriguez@vet.com', 'Reproducción')
    ");
    
    $pdo->exec("
        INSERT IGNORE INTO medications (name, active_ingredient, dosage_form, concentration, manufacturer, expiration_date) VALUES
        ('Penicilina G', 'Penicilina', 'inyeccion', '1,000,000 UI', 'Farmacéutica ABC', '2025-12-31'),
        ('Oxitetraciclina', 'Oxitetraciclina', 'inyeccion', '200mg/ml', 'VetCorp', '2025-06-30'),
        ('Ivermectina', 'Ivermectina', 'inyeccion', '1%', 'AgroVet', '2025-09-15'),
        ('Vitamina B12', 'Cianocobalamina', 'inyeccion', '1000mcg/ml', 'NutriVet', '2025-03-20'),
        ('Antiinflamatorio', 'Meloxicam', 'oral', '15mg', 'MediVet', '2025-08-10')
    ");
}

// Obtener veterinarios
$veterinarians = $pdo->query("SELECT id, name FROM veterinarians ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Obtener medicamentos
$medications = $pdo->query("SELECT id, name, active_ingredient, dosage_form, concentration, manufacturer, expiration_date FROM medications ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Obtener tratamientos
$treatments = $pdo->query("
    SELECT t.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM treatments t
    LEFT JOIN animals a ON a.id = t.animal_id
    LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
    ORDER BY t.treatment_date DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener alertas activas
$alerts = $pdo->query("
    SELECT ha.*, a.tag_code, a.name as animal_name
    FROM health_alerts ha
    LEFT JOIN animals a ON a.id = ha.animal_id
    WHERE ha.status = 'activa'
    ORDER BY ha.severity DESC, ha.alert_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener cuarentenas activas
$quarantines = $pdo->query("
    SELECT q.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM quarantines q
    LEFT JOIN animals a ON a.id = q.animal_id
    LEFT JOIN veterinarians v ON v.id = q.veterinarian_id
    WHERE q.status = 'activa'
    ORDER BY q.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas veterinarias
$totalTreatments = $pdo->query("SELECT COUNT(*) FROM treatments")->fetchColumn();
$activeAlerts = $pdo->query("SELECT COUNT(*) FROM health_alerts WHERE status = 'activa'")->fetchColumn();
$activeQuarantines = $pdo->query("SELECT COUNT(*) FROM quarantines WHERE status = 'activa'")->fetchColumn();
$totalVeterinarians = $pdo->query("SELECT COUNT(*) FROM veterinarians")->fetchColumn();

// Procesar formularios
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_treatment':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO treatments (
                            animal_id, veterinarian_id, treatment_date, treatment_type, 
                            diagnosis, symptoms, treatment_description, dosage, 
                            duration_days, cost, follow_up_date, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $_POST['animal_id'],
                        $_POST['veterinarian_id'] ?: null,
                        $_POST['treatment_date'],
                        $_POST['treatment_type'],
                        $_POST['diagnosis'] ?: null,
                        $_POST['symptoms'] ?: null,
                        $_POST['treatment_description'],
                        $_POST['dosage'] ?: null,
                        $_POST['duration_days'] ?: null,
                        $_POST['cost'] ?: null,
                        $_POST['follow_up_date'] ?: null,
                        $_POST['notes'] ?: null
                    ]);
                    
                    $_SESSION['flash_message'] = 'Tratamiento registrado exitosamente';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: veterinary.php');
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = 'Error al registrar tratamiento: ' . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                break;
                
            case 'create_quarantine':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO quarantines (
                            animal_id, start_date, end_date, reason, location, 
                            restrictions, veterinarian_id, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $_POST['quarantine_animal_id'],
                        $_POST['quarantine_start_date'],
                        $_POST['quarantine_end_date'] ?: null,
                        $_POST['quarantine_reason'],
                        $_POST['quarantine_location'] ?: null,
                        $_POST['quarantine_restrictions'] ?: null,
                        $_POST['quarantine_veterinarian_id'] ?: null,
                        $_POST['quarantine_notes'] ?: null
                    ]);
                    
                    $_SESSION['flash_message'] = 'Cuarentena iniciada exitosamente';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: veterinary.php');
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = 'Error al iniciar cuarentena: ' . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                break;
                
            case 'create_medication':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO medications (
                            name, active_ingredient, dosage_form, concentration, 
                            manufacturer, batch_number, expiration_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $_POST['medication_name'],
                        $_POST['active_ingredient'] ?: null,
                        $_POST['dosage_form'],
                        $_POST['concentration'] ?: null,
                        $_POST['manufacturer'] ?: null,
                        $_POST['batch_number'] ?: null,
                        $_POST['expiration_date'] ?: null
                    ]);
                    
                    $_SESSION['flash_message'] = 'Medicamento registrado exitosamente';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: veterinary.php');
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = 'Error al registrar medicamento: ' . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                break;
                
            case 'resolve_alert':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE health_alerts 
                        SET status = 'resuelta', resolved_at = NOW(), resolved_by = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([$user['id'], $_POST['alert_id']]);
                    
                    $_SESSION['flash_message'] = 'Alerta resuelta exitosamente';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: veterinary.php');
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = 'Error al resolver alerta: ' . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                break;
        }
    }
}

// Obtener mensaje flash
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Módulo Veterinario - AgroGan</title>
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

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      background: linear-gradient(135deg, var(--bg-green) 0%, #ffffff 100%);
      color: var(--text-dark);
      line-height: 1.6;
    }

    /* Navigation */
    .vet-nav {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--gray-200);
      padding: 1rem 2rem;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vet-nav-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      max-width: 1400px;
      margin: 0 auto;
    }

    .vet-nav h1 {
      margin: 0;
      color: var(--primary-green);
      font-size: 1.5rem;
      font-weight: 700;
    }

    .vet-nav-links {
      display: flex;
      gap: 1.5rem;
      align-items: center;
    }

    .vet-nav-links a {
      color: var(--gray-600);
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s ease;
    }

    .vet-nav-links a:hover {
      color: var(--primary-green);
    }

    .logout-btn {
      background: var(--error);
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-block;
    }

    .logout-btn:hover {
      background: #dc2626;
      transform: translateY(-1px);
    }

    /* Main Container */
    .vet-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem;
    }

    /* Flash Messages */
    .flash-message {
      padding: 1rem 1.5rem;
      border-radius: 8px;
      margin-bottom: 2rem;
      font-weight: 500;
      animation: slideIn 0.3s ease-out;
    }

    .flash-message.success {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
      border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .flash-message.error {
      background: rgba(239, 68, 68, 0.1);
      color: var(--error);
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Tab Navigation */
    .tab-nav {
      background: white;
      border-radius: 16px;
      padding: 0.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .tab-btn {
      flex: 1;
      min-width: 150px;
      background: none;
      border: none;
      padding: 1rem 1.5rem;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      color: var(--gray-600);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      justify-content: center;
    }

    .tab-btn:hover {
      background: var(--gray-50);
      color: var(--primary-green);
    }

    .tab-btn.active {
      background: var(--primary-green);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(45, 90, 39, 0.3);
    }

    /* Tab Content */
    .tab-content {
      display: none;
      animation: fadeIn 0.3s ease-in;
    }

    .tab-content.active {
      display: block;
    }

    /* Cards */
    .vet-card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      margin-bottom: 2rem;
      transition: all 0.2s ease;
    }

    .vet-card:hover {
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .vet-card h3 {
      margin: 0 0 1.5rem 0;
      color: var(--primary-green);
      font-size: 1.25rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Forms */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      color: var(--gray-700);
      margin-bottom: 0.5rem;
      font-size: 0.875rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 0.75rem;
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      font-size: 1rem;
      transition: all 0.2s ease;
      background: white;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
    }

    .form-group textarea {
      min-height: 100px;
      resize: vertical;
    }

    /* Buttons */
    .btn {
      background: var(--primary-green);
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      font-size: 0.875rem;
    }

    .btn:hover {
      background: var(--accent-green);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(45, 90, 39, 0.3);
    }

    .btn-secondary {
      background: var(--gray-100);
      color: var(--gray-700);
    }

    .btn-secondary:hover {
      background: var(--gray-200);
      color: var(--gray-800);
    }

    .btn-success {
      background: var(--success);
    }

    .btn-success:hover {
      background: #059669;
    }

    .btn-warning {
      background: var(--warning);
    }

    .btn-warning:hover {
      background: #d97706;
    }

    .btn-danger {
      background: var(--error);
    }

    .btn-danger:hover {
      background: #dc2626;
    }

    .btn-info {
      background: var(--info);
    }

    .btn-info:hover {
      background: #2563eb;
    }

    .btn-sm {
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
    }

    /* Tables */
    .table-container {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .table-header {
      background: var(--gray-50);
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--gray-200);
    }

    .table-header h4 {
      margin: 0;
      color: var(--gray-800);
      font-weight: 600;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 1rem 1.5rem;
      text-align: left;
      border-bottom: 1px solid var(--gray-200);
    }

    th {
      background: var(--gray-50);
      font-weight: 600;
      color: var(--gray-700);
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    td {
      color: var(--gray-600);
    }

    tr:hover {
      background: var(--gray-50);
    }

    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .badge-success {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
    }

    .badge-warning {
      background: rgba(245, 158, 11, 0.1);
      color: var(--warning);
    }

    .badge-info {
      background: rgba(59, 130, 246, 0.1);
      color: var(--info);
    }

    .badge-danger {
      background: rgba(239, 68, 68, 0.1);
      color: var(--error);
    }

    .badge-gray {
      background: var(--gray-100);
      color: var(--gray-600);
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    /* Alert Cards */
    .alert-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      border-left: 4px solid;
      transition: all 0.2s ease;
    }

    .alert-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .alert-card.critical {
      border-left-color: var(--error);
    }

    .alert-card.high {
      border-left-color: var(--warning);
    }

    .alert-card.medium {
      border-left-color: var(--info);
    }

    .alert-card.low {
      border-left-color: var(--success);
    }

    .alert-card h5 {
      margin: 0 0 0.5rem 0;
      font-weight: 600;
    }

    .alert-card p {
      margin: 0;
      color: var(--gray-600);
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      transition: all 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-card .icon {
      width: 3rem;
      height: 3rem;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }

    .stat-card .icon.treatments {
      background: rgba(45, 90, 39, 0.1);
      color: var(--primary-green);
    }

    .stat-card .icon.alerts {
      background: rgba(239, 68, 68, 0.1);
      color: var(--error);
    }

    .stat-card .icon.quarantines {
      background: rgba(245, 158, 11, 0.1);
      color: var(--warning);
    }

    .stat-card .icon.reports {
      background: rgba(59, 130, 246, 0.1);
      color: var(--info);
    }

    .stat-card .number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--gray-800);
      margin-bottom: 0.25rem;
    }

    .stat-card .label {
      color: var(--gray-600);
      font-size: 0.875rem;
      font-weight: 500;
    }

    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .vet-container {
        padding: 1rem;
      }

      .tab-nav {
        flex-direction: column;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .action-buttons {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="vet-nav">
    <div class="vet-nav-content">
      <h1><i class="fas fa-stethoscope"></i> Módulo Veterinario</h1>
      <div class="vet-nav-links">
        <a href="index.php"><i class="fas fa-home"></i> Inicio</a>
        <a href="catalogo.php"><i class="fas fa-list"></i> Catálogo</a>
        <a href="admin.php"><i class="fas fa-cog"></i> Admin</a>
        <a href="veterinary.php" class="active"><i class="fas fa-stethoscope"></i> Veterinario</a>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a>
      </div>
    </div>
  </nav>

  <div class="vet-container">
    <!-- Flash Messages -->
    <?php if ($flashMessage): ?>
      <div class="flash-message <?= $flashType ?>">
        <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($flashMessage) ?>
      </div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="icon treatments">
          <i class="fas fa-pills"></i>
        </div>
        <div class="number"><?= $totalTreatments ?></div>
        <div class="label">Tratamientos</div>
      </div>
      <div class="stat-card">
        <div class="icon alerts">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="number"><?= $activeAlerts ?></div>
        <div class="label">Alertas Activas</div>
      </div>
      <div class="stat-card">
        <div class="icon quarantines">
          <i class="fas fa-shield-virus"></i>
        </div>
        <div class="number"><?= $activeQuarantines ?></div>
        <div class="label">Cuarentenas</div>
      </div>
      <div class="stat-card">
        <div class="icon reports">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="number"><?= $totalVeterinarians ?></div>
        <div class="label">Veterinarios</div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
      <button class="tab-btn active" onclick="showTab('treatments')">
        <i class="fas fa-pills"></i> Tratamientos
      </button>
      <button class="tab-btn" onclick="showTab('alerts')">
        <i class="fas fa-exclamation-triangle"></i> Alertas
      </button>
      <button class="tab-btn" onclick="showTab('quarantines')">
        <i class="fas fa-shield-virus"></i> Cuarentenas
      </button>
      <button class="tab-btn" onclick="showTab('medications')">
        <i class="fas fa-prescription-bottle"></i> Medicamentos
      </button>
    </div>

    <!-- Treatments Tab -->
    <div id="treatments-tab" class="tab-content active">
      <div class="vet-card">
        <h3><i class="fas fa-plus-circle"></i> Registrar Nuevo Tratamiento</h3>
        
        <form method="POST" class="form-grid">
          <input type="hidden" name="action" value="create_treatment">
          
          <div class="form-group">
            <label for="animal_id">Animal *</label>
            <select id="animal_id" name="animal_id" required>
              <option value="">Seleccionar animal...</option>
              <?php foreach ($animals as $animal): ?>
                <option value="<?= $animal['id'] ?>"><?= htmlspecialchars($animal['tag_code']) ?> - <?= htmlspecialchars($animal['name'] ?: 'Sin nombre') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="veterinarian_id">Veterinario</label>
            <select id="veterinarian_id" name="veterinarian_id">
              <option value="">Seleccionar veterinario...</option>
              <?php foreach ($veterinarians as $vet): ?>
                <option value="<?= $vet['id'] ?>"><?= htmlspecialchars($vet['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="treatment_date">Fecha del Tratamiento *</label>
            <input type="date" id="treatment_date" name="treatment_date" required value="<?= date('Y-m-d') ?>">
          </div>
          
          <div class="form-group">
            <label for="treatment_type">Tipo de Tratamiento *</label>
            <select id="treatment_type" name="treatment_type" required>
              <option value="">Seleccionar tipo...</option>
              <option value="preventivo">Preventivo</option>
              <option value="curativo">Curativo</option>
              <option value="quirurgico">Quirúrgico</option>
              <option value="reproductivo">Reproductivo</option>
              <option value="nutricional">Nutricional</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="diagnosis">Diagnóstico</label>
            <textarea id="diagnosis" name="diagnosis" placeholder="Descripción del diagnóstico..."></textarea>
          </div>
          
          <div class="form-group">
            <label for="symptoms">Síntomas</label>
            <textarea id="symptoms" name="symptoms" placeholder="Síntomas observados..."></textarea>
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="treatment_description">Descripción del Tratamiento *</label>
            <textarea id="treatment_description" name="treatment_description" required placeholder="Descripción detallada del tratamiento..."></textarea>
          </div>
          
          <div class="form-group">
            <label for="dosage">Dosis</label>
            <input type="text" id="dosage" name="dosage" placeholder="Ej: 10ml cada 8 horas">
          </div>
          
          <div class="form-group">
            <label for="duration_days">Duración (días)</label>
            <input type="number" id="duration_days" name="duration_days" min="1" placeholder="7">
          </div>
          
          <div class="form-group">
            <label for="cost">Costo</label>
            <input type="number" id="cost" name="cost" step="0.01" placeholder="0.00">
          </div>
          
          <div class="form-group">
            <label for="follow_up_date">Fecha de Seguimiento</label>
            <input type="date" id="follow_up_date" name="follow_up_date">
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="notes">Notas Adicionales</label>
            <textarea id="notes" name="notes" placeholder="Notas adicionales..."></textarea>
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-save"></i> Registrar Tratamiento
            </button>
          </div>
        </form>
      </div>

      <div class="vet-card">
        <h3><i class="fas fa-list"></i> Historial de Tratamientos</h3>
        
        <div class="table-container">
          <div class="table-header">
            <h4>Tratamientos Registrados</h4>
          </div>
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Animal</th>
                <th>Tipo</th>
                <th>Veterinario</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($treatments)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 2rem; color: var(--gray-500);">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No hay tratamientos registrados
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($treatments as $treatment): ?>
                  <tr>
                    <td><?= htmlspecialchars($treatment['treatment_date']) ?></td>
                    <td><strong><?= htmlspecialchars($treatment['tag_code']) ?></strong><br><small><?= htmlspecialchars($treatment['animal_name'] ?: 'Sin nombre') ?></small></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($treatment['treatment_type']) ?></span></td>
                    <td><?= htmlspecialchars($treatment['veterinarian_name'] ?: 'No asignado') ?></td>
                    <td><span class="badge <?= $treatment['status'] === 'completado' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($treatment['status']) ?></span></td>
                    <td>
                      <div class="action-buttons">
                        <button class="btn btn-sm btn-secondary" onclick="showTreatmentDetails(<?= $treatment['id'] ?>)">
                          <i class="fas fa-eye"></i> Ver
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Alerts Tab -->
    <div id="alerts-tab" class="tab-content">
      <div class="vet-card">
        <h3><i class="fas fa-exclamation-triangle"></i> Alertas Sanitarias</h3>
        
        <div id="alertsContainer">
          <?php if (empty($alerts)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
              <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              No hay alertas activas
            </div>
          <?php else: ?>
            <?php foreach ($alerts as $alert): ?>
              <div class="alert-card <?= $alert['severity'] ?>">
                <h5><?= htmlspecialchars($alert['title']) ?></h5>
                <p><?= htmlspecialchars($alert['description']) ?></p>
                <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                  <div>
                    <small><strong>Animal:</strong> <?= htmlspecialchars($alert['tag_code'] ?: 'General') ?></small><br>
                    <small><strong>Fecha:</strong> <?= htmlspecialchars($alert['alert_date']) ?></small>
                    <?php if ($alert['due_date']): ?>
                      <br><small><strong>Vence:</strong> <?= htmlspecialchars($alert['due_date']) ?></small>
                    <?php endif; ?>
                  </div>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="resolve_alert">
                    <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success">
                      <i class="fas fa-check"></i> Resolver
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Quarantines Tab -->
    <div id="quarantines-tab" class="tab-content">
      <div class="vet-card">
        <h3><i class="fas fa-plus-circle"></i> Nueva Cuarentena</h3>
        
        <form method="POST" class="form-grid">
          <input type="hidden" name="action" value="create_quarantine">
          
          <div class="form-group">
            <label for="quarantine_animal_id">Animal *</label>
            <select id="quarantine_animal_id" name="quarantine_animal_id" required>
              <option value="">Seleccionar animal...</option>
              <?php foreach ($animals as $animal): ?>
                <option value="<?= $animal['id'] ?>"><?= htmlspecialchars($animal['tag_code']) ?> - <?= htmlspecialchars($animal['name'] ?: 'Sin nombre') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="quarantine_start_date">Fecha de Inicio *</label>
            <input type="date" id="quarantine_start_date" name="quarantine_start_date" required value="<?= date('Y-m-d') ?>">
          </div>
          
          <div class="form-group">
            <label for="quarantine_end_date">Fecha de Fin</label>
            <input type="date" id="quarantine_end_date" name="quarantine_end_date">
          </div>
          
          <div class="form-group">
            <label for="quarantine_veterinarian_id">Veterinario Responsable</label>
            <select id="quarantine_veterinarian_id" name="quarantine_veterinarian_id">
              <option value="">Seleccionar veterinario...</option>
              <?php foreach ($veterinarians as $vet): ?>
                <option value="<?= $vet['id'] ?>"><?= htmlspecialchars($vet['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="quarantine_reason">Motivo de Cuarentena *</label>
            <textarea id="quarantine_reason" name="quarantine_reason" required placeholder="Descripción detallada del motivo..."></textarea>
          </div>
          
          <div class="form-group">
            <label for="quarantine_location">Ubicación</label>
            <input type="text" id="quarantine_location" name="quarantine_location" placeholder="Ej: Corral A, Área de aislamiento">
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="quarantine_restrictions">Restricciones</label>
            <textarea id="quarantine_restrictions" name="quarantine_restrictions" placeholder="Restricciones específicas..."></textarea>
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="quarantine_notes">Notas</label>
            <textarea id="quarantine_notes" name="quarantine_notes" placeholder="Notas adicionales..."></textarea>
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <button type="submit" class="btn btn-warning">
              <i class="fas fa-shield-virus"></i> Iniciar Cuarentena
            </button>
          </div>
        </form>
      </div>

      <div class="vet-card">
        <h3><i class="fas fa-list"></i> Cuarentenas Activas</h3>
        
        <div class="table-container">
          <div class="table-header">
            <h4>Cuarentenas en Curso</h4>
          </div>
          <table>
            <thead>
              <tr>
                <th>Animal</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Motivo</th>
                <th>Veterinario</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($quarantines)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 2rem; color: var(--gray-500);">
                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No hay cuarentenas activas
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($quarantines as $quarantine): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($quarantine['tag_code']) ?></strong><br><small><?= htmlspecialchars($quarantine['animal_name'] ?: 'Sin nombre') ?></small></td>
                    <td><?= htmlspecialchars($quarantine['start_date']) ?></td>
                    <td><?= htmlspecialchars($quarantine['end_date'] ?: 'Indefinida') ?></td>
                    <td><?= htmlspecialchars(mb_substr($quarantine['reason'], 0, 50)) ?><?= mb_strlen($quarantine['reason']) > 50 ? '...' : '' ?></td>
                    <td><?= htmlspecialchars($quarantine['veterinarian_name'] ?: 'No asignado') ?></td>
                    <td>
                      <div class="action-buttons">
                        <button class="btn btn-sm btn-secondary" onclick="showQuarantineDetails(<?= $quarantine['id'] ?>)">
                          <i class="fas fa-eye"></i> Ver
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Medications Tab -->
    <div id="medications-tab" class="tab-content">
      <div class="vet-card">
        <h3><i class="fas fa-plus-circle"></i> Registrar Medicamento</h3>
        
        <form method="POST" class="form-grid">
          <input type="hidden" name="action" value="create_medication">
          
          <div class="form-group">
            <label for="medication_name">Nombre del Medicamento *</label>
            <input type="text" id="medication_name" name="medication_name" required placeholder="Ej: Penicilina G">
          </div>
          
          <div class="form-group">
            <label for="active_ingredient">Principio Activo</label>
            <input type="text" id="active_ingredient" name="active_ingredient" placeholder="Ej: Penicilina">
          </div>
          
          <div class="form-group">
            <label for="dosage_form">Forma de Dosificación *</label>
            <select id="dosage_form" name="dosage_form" required>
              <option value="">Seleccionar forma...</option>
              <option value="inyeccion">Inyección</option>
              <option value="oral">Oral</option>
              <option value="topico">Tópico</option>
              <option value="inhalacion">Inhalación</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="concentration">Concentración</label>
            <input type="text" id="concentration" name="concentration" placeholder="Ej: 1,000,000 UI">
          </div>
          
          <div class="form-group">
            <label for="manufacturer">Fabricante</label>
            <input type="text" id="manufacturer" name="manufacturer" placeholder="Ej: Farmacéutica ABC">
          </div>
          
          <div class="form-group">
            <label for="batch_number">Número de Lote</label>
            <input type="text" id="batch_number" name="batch_number" placeholder="Ej: LOT-2024-001">
          </div>
          
          <div class="form-group">
            <label for="expiration_date">Fecha de Vencimiento</label>
            <input type="date" id="expiration_date" name="expiration_date">
          </div>
          
          <div class="form-group" style="grid-column: 1 / -1;">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-save"></i> Registrar Medicamento
            </button>
          </div>
        </form>
      </div>

      <div class="vet-card">
        <h3><i class="fas fa-list"></i> Inventario de Medicamentos</h3>
        
        <div class="table-container">
          <div class="table-header">
            <h4>Medicamentos Registrados</h4>
          </div>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Principio Activo</th>
                <th>Forma</th>
                <th>Concentración</th>
                <th>Fabricante</th>
                <th>Vencimiento</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($medications)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 2rem; color: var(--gray-500);">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No hay medicamentos registrados
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($medications as $medication): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($medication['name']) ?></strong></td>
                    <td><?= htmlspecialchars($medication['active_ingredient'] ?: 'No especificado') ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($medication['dosage_form']) ?></span></td>
                    <td><?= htmlspecialchars($medication['concentration'] ?: 'No especificada') ?></td>
                    <td><?= htmlspecialchars($medication['manufacturer'] ?: 'No especificado') ?></td>
                    <td><?= htmlspecialchars($medication['expiration_date'] ?: 'No especificada') ?></td>
                    <td>
                      <div class="action-buttons">
                        <button class="btn btn-sm btn-secondary" onclick="showMedicationDetails(<?= $medication['id'] ?>)">
                          <i class="fas fa-eye"></i> Ver
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Tab functionality
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

    // Show treatment details
    function showTreatmentDetails(treatmentId) {
      alert('Función de detalles de tratamiento en desarrollo');
    }

    // Show quarantine details
    function showQuarantineDetails(quarantineId) {
      alert('Función de detalles de cuarentena en desarrollo');
    }

    // Show medication details
    function showMedicationDetails(medicationId) {
      alert('Función de detalles de medicamento en desarrollo');
    }

    // Auto-hide flash messages
    setTimeout(function() {
      const flashMessage = document.querySelector('.flash-message');
      if (flashMessage) {
        flashMessage.style.opacity = '0';
        setTimeout(() => flashMessage.remove(), 300);
      }
    }, 5000);
  </script>
</body>
</html>
