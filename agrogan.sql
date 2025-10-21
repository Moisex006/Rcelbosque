
-- AgroGan MySQL schema
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS agrogan CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE agrogan;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS species (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS breeds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  species_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  UNIQUE (species_id, name),
  FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS farms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(180)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tag_code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120),
  sex ENUM('M','F','U') NOT NULL DEFAULT 'U',
  birth_date DATE NULL,
  species_id INT NULL,
  breed_id INT NULL,
  farm_id INT NULL,
  color VARCHAR(80),
  status VARCHAR(40) NOT NULL DEFAULT 'activo',
  sire_id INT NULL,
  dam_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (species_id) REFERENCES species(id),
  FOREIGN KEY (breed_id) REFERENCES breeds(id),
  FOREIGN KEY (farm_id) REFERENCES farms(id),
  FOREIGN KEY (sire_id) REFERENCES animals(id),
  FOREIGN KEY (dam_id) REFERENCES animals(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animal_weights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  date DATE NULL,
  weight_kg DECIMAL(6,2) NOT NULL,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS animal_vaccinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  date DATE NULL,
  vaccine VARCHAR(120) NOT NULL,
  notes TEXT NULL,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  filename VARCHAR(200) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS catalog_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL UNIQUE,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seeds
INSERT IGNORE INTO species(name) VALUES ('Bovino'), ('Porcino'), ('Ovino');
SET @bovino := (SELECT id FROM species WHERE name='Bovino' LIMIT 1);
INSERT IGNORE INTO breeds(species_id, name) VALUES (@bovino, 'Brahman'), (@bovino, 'Holstein');
INSERT IGNORE INTO farms(name, location) VALUES ('Finca La Esperanza','Antioquia'), ('Finca El Roble','Cundinamarca');

-- Admin por defecto: email admin@agrogan.local / password admin123
INSERT IGNORE INTO users(email, password_hash, name, role)
VALUES ('admin@agrogan.local', '$2y$10$Tknbs5U0wVw1kC2oZg2n5eYx3eAFQ0Zb0EwWmHkQ6tlJc3lZ.4pVO', 'Administrador', 'admin');
