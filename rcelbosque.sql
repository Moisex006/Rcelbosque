-- Rc El Bosque MySQL schema
-- Orden corregido: tablas base primero, luego tablas con foreign keys
-- 
-- IMPORTANTE: Este archivo está diseñado para ejecutarse en Hostinger
-- La base de datos u919054360_rcelbosque ya debe estar creada
-- Selecciona la base de datos en phpMyAdmin antes de ejecutar este script
--
-- NOTA: En Hostinger, NO tienes permisos para crear bases de datos
-- Solo puedes usar la base de datos que ya creaste: u919054360_rcelbosque

-- Deshabilitar temporalmente las verificaciones de foreign keys para evitar errores durante la creación
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- TABLAS BASE (Sin dependencias)
-- ============================================

-- Tabla de especies (debe crearse primero)
CREATE TABLE IF NOT EXISTS species (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Tabla de fincas (debe crearse antes que users)
CREATE TABLE IF NOT EXISTS farms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(180)
) ENGINE=InnoDB;

-- ============================================
-- TABLAS CON DEPENDENCIAS
-- ============================================

-- Tabla de usuarios (depende de farms)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('admin_general','admin_finca','veterinario','user') NOT NULL DEFAULT 'user',
  farm_id INT NULL COMMENT 'Finca asociada al usuario (para admin_finca)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de razas (depende de species)
CREATE TABLE IF NOT EXISTS breeds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  species_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  UNIQUE (species_id, name),
  FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de animales (depende de species, breeds, farms, y animals - auto-referencia)
CREATE TABLE IF NOT EXISTS animals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tag_code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120),
  gender ENUM('macho','hembra','indefinido') NOT NULL DEFAULT 'indefinido',
  birth_date DATE NULL,
  species_id INT NULL,
  breed_id INT NULL,
  farm_id INT NULL,
  color VARCHAR(80),
  status VARCHAR(40) NOT NULL DEFAULT 'activo',
  sire_id INT NULL,
  dam_id INT NULL,
  weight DECIMAL(6,2) NULL COMMENT 'Peso del animal en kg (20-1000kg)',
  description TEXT NULL COMMENT 'Descripción del animal',
  in_cat TINYINT(1) DEFAULT 0 COMMENT 'Indica si el animal está visible en el catálogo público',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (species_id) REFERENCES species(id),
  FOREIGN KEY (breed_id) REFERENCES breeds(id),
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
  FOREIGN KEY (sire_id) REFERENCES animals(id),
  FOREIGN KEY (dam_id) REFERENCES animals(id)
) ENGINE=InnoDB;

-- Tabla para lotes de animales (depende de farms y users)
CREATE TABLE IF NOT EXISTS lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL COMMENT 'Nombre del lote',
  description TEXT NULL COMMENT 'Descripción del lote',
  total_price DECIMAL(12,2) NOT NULL COMMENT 'Precio total del lote',
  animal_count INT NOT NULL DEFAULT 0 COMMENT 'Número de animales en el lote',
  lot_type ENUM('venta','reproduccion','engorde','leche') NOT NULL DEFAULT 'venta' COMMENT 'Tipo de lote',
  status ENUM('disponible','vendido','reservado') NOT NULL DEFAULT 'disponible' COMMENT 'Estado del lote',
  farm_id INT NULL COMMENT 'Finca de origen',
  created_by INT NOT NULL COMMENT 'Usuario que creó el lote',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabla de relación lotes-animales (depende de lots y animals)
CREATE TABLE IF NOT EXISTS lot_animals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lot_id INT NOT NULL COMMENT 'ID del lote',
  animal_id INT NOT NULL COMMENT 'ID del animal',
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  UNIQUE KEY unique_lot_animal (lot_id, animal_id)
) ENGINE=InnoDB;

-- Tabla para fotos de animales (depende de animals)
CREATE TABLE IF NOT EXISTS animal_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL COMMENT 'ID del animal',
  filename VARCHAR(255) NOT NULL COMMENT 'Nombre del archivo',
  original_name VARCHAR(255) NOT NULL COMMENT 'Nombre original del archivo',
  file_path VARCHAR(500) NOT NULL COMMENT 'Ruta completa del archivo',
  file_size INT NOT NULL COMMENT 'Tamaño del archivo en bytes',
  mime_type VARCHAR(100) NOT NULL COMMENT 'Tipo MIME del archivo',
  description TEXT NULL COMMENT 'Descripción de la foto',
  is_primary TINYINT(1) DEFAULT 0 COMMENT 'Indica si es la foto principal',
  sort_order INT DEFAULT 0 COMMENT 'Orden de visualización',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  INDEX idx_animal_photos (animal_id, sort_order)
) ENGINE=InnoDB;

-- Tabla de pesos históricos (depende de animals)
CREATE TABLE IF NOT EXISTS animal_weights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  date DATE NULL,
  weight_kg DECIMAL(6,2) NOT NULL,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de vacunaciones (depende de animals)
CREATE TABLE IF NOT EXISTS animal_vaccinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  date DATE NULL,
  vaccine VARCHAR(120) NOT NULL,
  notes TEXT NULL,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de fotos (legacy, depende de animals)
CREATE TABLE IF NOT EXISTS photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  filename VARCHAR(200) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de items del catálogo (depende de animals)
CREATE TABLE IF NOT EXISTS catalog_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL UNIQUE,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla para postulaciones al catálogo (depende de users y farms)
CREATE TABLE IF NOT EXISTS nominations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('animal','lot') NOT NULL COMMENT 'Tipo de item postulado',
  item_id INT NOT NULL COMMENT 'ID del animal o lote',
  proposed_by INT NOT NULL COMMENT 'Usuario que postuló',
  farm_id INT NULL COMMENT 'Finca del item postulado',
  notes TEXT NULL COMMENT 'Notas de la postulación',
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL COMMENT 'Admin general que revisó',
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (proposed_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id),
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_item (item_type, item_id)
) ENGINE=InnoDB;

-- ============================================
-- MÓDULO VETERINARIO
-- ============================================

-- Tabla de veterinarios (sin dependencias)
CREATE TABLE IF NOT EXISTS veterinarians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  license_number VARCHAR(50) UNIQUE,
  phone VARCHAR(20),
  email VARCHAR(120),
  specialization VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de medicamentos (sin dependencias)
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
) ENGINE=InnoDB;

-- Tabla de tratamientos (depende de animals y veterinarians)
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
) ENGINE=InnoDB;

-- Tabla de relación tratamientos-medicamentos (depende de treatments y medications)
CREATE TABLE IF NOT EXISTS treatment_medications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  treatment_id INT NOT NULL,
  medication_id INT NOT NULL,
  dosage_amount DECIMAL(8,2),
  dosage_unit VARCHAR(20),
  frequency_per_day INT,
  administration_route VARCHAR(50),
  FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE,
  FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de alertas sanitarias (depende de animals y users)
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
) ENGINE=InnoDB;

-- Tabla de cuarentenas (depende de animals y veterinarians)
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
) ENGINE=InnoDB;

-- Tabla de reportes sanitarios (depende de animals, farms y users)
CREATE TABLE IF NOT EXISTS health_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('individual','lote','general','sanitario','reproductivo','nutricional') NOT NULL,
  animal_id INT,
  farm_id INT,
  report_date DATE NOT NULL,
  period_start DATE,
  period_end DATE,
  generated_by INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  summary TEXT,
  findings TEXT,
  recommendations TEXT,
  status ENUM('borrador','finalizado','archivado') DEFAULT 'borrador',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL,
  FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tabla de métricas de salud (depende de animals y veterinarians)
CREATE TABLE IF NOT EXISTS health_metrics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  metric_date DATE NOT NULL,
  temperature DECIMAL(4,1),
  heart_rate INT,
  respiratory_rate INT,
  weight DECIMAL(6,2),
  body_condition_score DECIMAL(2,1),
  notes TEXT,
  veterinarian_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (veterinarian_id) REFERENCES veterinarians(id)
) ENGINE=InnoDB;

-- Tabla de cotizaciones (depende de animals, lots y users)
CREATE TABLE IF NOT EXISTS quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_type ENUM('animal', 'lot') NOT NULL,
  item_id INT NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_email VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  customer_message TEXT,
  status ENUM('pendiente', 'en_proceso', 'respondida') NOT NULL DEFAULT 'pendiente',
  created_by INT COMMENT 'Usuario que creó la cotización (si estaba logueado)',
  updated_by INT COMMENT 'Usuario que actualizó el estado',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de imágenes del carrusel principal
CREATE TABLE IF NOT EXISTS carousel_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  title VARCHAR(200) COMMENT 'Título que aparece en el carrusel',
  description TEXT COMMENT 'Descripción que aparece debajo del título',
  sort_order INT DEFAULT 0 COMMENT 'Orden de visualización (menor número = primero)',
  is_active TINYINT(1) DEFAULT 1 COMMENT 'Si está activa (1) o no (0)',
  created_by INT COMMENT 'Usuario que creó la imagen',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de recuperación de contraseñas
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  used TINYINT(1) DEFAULT 0 COMMENT 'Si el token ya fue usado (1) o no (0)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Reactivar verificaciones de foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- DATOS INICIALES (SEEDS)
-- ============================================

-- Especies
INSERT IGNORE INTO species(name) VALUES ('Bovino'), ('Porcino'), ('Ovino');

-- Razas
SET @bovino := (SELECT id FROM species WHERE name='Bovino' LIMIT 1);
INSERT IGNORE INTO breeds(species_id, name) VALUES (@bovino, 'Brahman'), (@bovino, 'Holstein');

-- Fincas
INSERT IGNORE INTO farms(name, location) VALUES ('Finca La Esperanza','Antioquia'), ('Finca El Roble','Cundinamarca');

-- Admin por defecto: email admin@rcelbosque.local / password admin123
INSERT IGNORE INTO users(email, password_hash, name, role)
VALUES ('admin@rcelbosque.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin_general');

-- Datos de ejemplo para veterinarios
INSERT IGNORE INTO veterinarians(name, license_number, phone, email, specialization) VALUES
('Dr. Carlos Mendoza', 'VET-001-2023', '300-123-4567', 'carlos.mendoza@vet.com', 'Medicina Interna'),
('Dra. Ana García', 'VET-002-2023', '300-234-5678', 'ana.garcia@vet.com', 'Cirugía'),
('Dr. Luis Rodríguez', 'VET-003-2023', '300-345-6789', 'luis.rodriguez@vet.com', 'Reproducción');

-- Datos de ejemplo para medicamentos
INSERT IGNORE INTO medications(name, active_ingredient, dosage_form, concentration, manufacturer, expiration_date) VALUES
('Penicilina G', 'Penicilina', 'inyeccion', '1,000,000 UI', 'Farmacéutica ABC', '2025-12-31'),
('Oxitetraciclina', 'Oxitetraciclina', 'inyeccion', '200mg/ml', 'VetCorp', '2025-06-30'),
('Ivermectina', 'Ivermectina', 'inyeccion', '1%', 'AgroVet', '2025-09-15'),
('Vitamina B12', 'Cianocobalamina', 'inyeccion', '1000mcg/ml', 'NutriVet', '2025-03-20'),
('Antiinflamatorio', 'Meloxicam', 'oral', '15mg', 'MediVet', '2025-08-10');

