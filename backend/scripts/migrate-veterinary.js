import {openDb} from '../db/db.js';

async function migrate() {
  console.log('Iniciando migración de base de datos...');
  
  const db = await openDb();
  
  try {
    // RF5: Tablas para tratamientos veterinarios
    console.log('Creando tabla veterinarians...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS veterinarians (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        license_number TEXT UNIQUE,
        phone TEXT,
        email TEXT,
        specialization TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `);

    console.log('Creando tabla medications...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS medications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        active_ingredient TEXT,
        dosage_form TEXT CHECK (dosage_form IN ('inyeccion','oral','topico','inhalacion')) NOT NULL,
        concentration TEXT,
        manufacturer TEXT,
        batch_number TEXT,
        expiration_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    `);

    console.log('Creando tabla treatments...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS treatments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        animal_id INTEGER NOT NULL,
        veterinarian_id INTEGER,
        treatment_date DATE NOT NULL,
        treatment_type TEXT CHECK (treatment_type IN ('preventivo','curativo','quirurgico','reproductivo','nutricional')) NOT NULL,
        diagnosis TEXT,
        symptoms TEXT,
        treatment_description TEXT NOT NULL,
        dosage TEXT,
        duration_days INTEGER,
        cost REAL,
        status TEXT CHECK (status IN ('en_progreso','completado','cancelado','suspender')) DEFAULT 'en_progreso',
        follow_up_date DATE,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME,
        FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
        FOREIGN KEY (veterinarian_id) REFERENCES veterinarians(id)
      )
    `);

    console.log('Creando tabla treatment_medications...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS treatment_medications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        treatment_id INTEGER NOT NULL,
        medication_id INTEGER NOT NULL,
        dosage_amount REAL,
        dosage_unit TEXT,
        frequency_per_day INTEGER,
        administration_route TEXT,
        FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE CASCADE,
        FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE CASCADE
      )
    `);

    // RF6: Tablas para alertas sanitarias
    console.log('Creando tabla health_alerts...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS health_alerts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        animal_id INTEGER,
        alert_type TEXT CHECK (alert_type IN ('vacuna_vencida','tratamiento_pendiente','cuarentena','enfermedad','peso_bajo','peso_alto','revision_periodica')) NOT NULL,
        severity TEXT CHECK (severity IN ('baja','media','alta','critica')) NOT NULL DEFAULT 'media',
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        alert_date DATE NOT NULL,
        due_date DATE,
        status TEXT CHECK (status IN ('activa','resuelta','cancelada')) DEFAULT 'activa',
        resolved_at DATETIME,
        resolved_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
        FOREIGN KEY (resolved_by) REFERENCES users(id)
      )
    `);

    console.log('Creando tabla quarantines...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS quarantines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        animal_id INTEGER NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        reason TEXT NOT NULL,
        location TEXT,
        restrictions TEXT,
        status TEXT CHECK (status IN ('activa','finalizada','cancelada')) DEFAULT 'activa',
        veterinarian_id INTEGER,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
        FOREIGN KEY (veterinarian_id) REFERENCES veterinarians(id)
      )
    `);

    // RF7: Tablas para reportes sanitarios
    console.log('Creando tabla health_reports...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS health_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        report_type TEXT CHECK (report_type IN ('individual','lote','general','sanitario','reproductivo','nutricional')) NOT NULL,
        animal_id INTEGER,
        farm_id INTEGER,
        report_date DATE NOT NULL,
        period_start DATE,
        period_end DATE,
        generated_by INTEGER NOT NULL,
        title TEXT NOT NULL,
        summary TEXT,
        findings TEXT,
        recommendations TEXT,
        status TEXT CHECK (status IN ('borrador','finalizado','archivado')) DEFAULT 'borrador',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
        FOREIGN KEY (farm_id) REFERENCES farms(id),
        FOREIGN KEY (generated_by) REFERENCES users(id)
      )
    `);

    console.log('Creando tabla health_metrics...');
    await db.exec(`
      CREATE TABLE IF NOT EXISTS health_metrics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        animal_id INTEGER NOT NULL,
        metric_date DATE NOT NULL,
        temperature REAL,
        heart_rate INTEGER,
        respiratory_rate INTEGER,
        weight REAL,
        body_condition_score REAL,
        notes TEXT,
        veterinarian_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
        FOREIGN KEY (veterinarian_id) REFERENCES veterinarians(id)
      )
    `);

    // Insertar datos de ejemplo
    console.log('Insertando datos de ejemplo...');
    
    // Veterinarios de ejemplo
    await db.run(`
      INSERT OR IGNORE INTO veterinarians (name, license_number, phone, email, specialization) VALUES
      ('Dr. Carlos Mendoza', 'VET-001-2023', '300-123-4567', 'carlos.mendoza@vet.com', 'Medicina Interna'),
      ('Dra. Ana García', 'VET-002-2023', '300-234-5678', 'ana.garcia@vet.com', 'Cirugía'),
      ('Dr. Luis Rodríguez', 'VET-003-2023', '300-345-6789', 'luis.rodriguez@vet.com', 'Reproducción')
    `);

    // Medicamentos de ejemplo
    await db.run(`
      INSERT OR IGNORE INTO medications (name, active_ingredient, dosage_form, concentration, manufacturer, expiration_date) VALUES
      ('Penicilina G', 'Penicilina', 'inyeccion', '1,000,000 UI', 'Farmacéutica ABC', '2025-12-31'),
      ('Oxitetraciclina', 'Oxitetraciclina', 'inyeccion', '200mg/ml', 'VetCorp', '2025-06-30'),
      ('Ivermectina', 'Ivermectina', 'inyeccion', '1%', 'AgroVet', '2025-09-15'),
      ('Vitamina B12', 'Cianocobalamina', 'inyeccion', '1000mcg/ml', 'NutriVet', '2025-03-20'),
      ('Antiinflamatorio', 'Meloxicam', 'oral', '15mg', 'MediVet', '2025-08-10')
    `);

    console.log('Migración completada exitosamente!');
    
  } catch (error) {
    console.error('Error durante la migración:', error);
    throw error;
  } finally {
    await db.close();
  }
}

// Ejecutar migración si se llama directamente
if (import.meta.url === `file://${process.argv[1]}`) {
  migrate().catch(console.error);
}

export default migrate;
