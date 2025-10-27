import {Router} from 'express';
import {body,validationResult} from 'express-validator';
import {openDb} from '../db/db.js';
import {authRequired} from '../middleware/auth.js';

const router = Router();

// RF5: Veterinarios
router.get('/veterinarians', authRequired, async (req, res) => {
  const db = await openDb();
  const veterinarians = await db.all('SELECT * FROM veterinarians ORDER BY name');
  res.json({veterinarians});
});

router.post('/veterinarians', authRequired, [
  body('name').isLength({min: 2}),
  body('license_number').optional().isLength({min: 5}),
  body('email').optional().isEmail(),
  body('phone').optional().isLength({min: 7})
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {name, license_number, phone, email, specialization} = req.body;
  const db = await openDb();

  try {
    const {lastID} = await db.run(
      'INSERT INTO veterinarians (name, license_number, phone, email, specialization) VALUES (?,?,?,?,?)',
      name, license_number || null, phone || null, email || null, specialization || null
    );
    const veterinarian = await db.get('SELECT * FROM veterinarians WHERE id = ?', lastID);
    res.status(201).json(veterinarian);
  } catch (e) {
    if (String(e).includes('UNIQUE constraint failed: veterinarians.license_number')) {
      return res.status(409).json({error: 'Número de licencia ya existe'});
    }
    throw e;
  }
});

// RF5: Medicamentos
router.get('/medications', authRequired, async (req, res) => {
  const db = await openDb();
  const medications = await db.all('SELECT * FROM medications ORDER BY name');
  res.json({medications});
});

router.post('/medications', authRequired, [
  body('name').isLength({min: 2}),
  body('dosage_form').isIn(['inyeccion','oral','topico','inhalacion']),
  body('concentration').optional().isLength({min: 1}),
  body('manufacturer').optional().isLength({min: 2})
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {name, active_ingredient, dosage_form, concentration, manufacturer, batch_number, expiration_date} = req.body;
  const db = await openDb();

  const {lastID} = await db.run(
    'INSERT INTO medications (name, active_ingredient, dosage_form, concentration, manufacturer, batch_number, expiration_date) VALUES (?,?,?,?,?,?,?)',
    name, active_ingredient || null, dosage_form, concentration || null, manufacturer || null, batch_number || null, expiration_date || null
  );
  const medication = await db.get('SELECT * FROM medications WHERE id = ?', lastID);
  res.status(201).json(medication);
});

// RF5: Tratamientos
router.get('/treatments', authRequired, async (req, res) => {
  const {animal_id, page = 1, pageSize = 20} = req.query;
  const db = await openDb();
  const offset = (Number(page) - 1) * Number(pageSize);

  let whereClause = '';
  let params = [];
  
  if (animal_id) {
    whereClause = 'WHERE t.animal_id = ?';
    params.push(animal_id);
  }

  const treatments = await db.all(`
    SELECT t.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM treatments t
    LEFT JOIN animals a ON a.id = t.animal_id
    LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
    ${whereClause}
    ORDER BY t.treatment_date DESC
    LIMIT ? OFFSET ?
  `, ...params, Number(pageSize), offset);

  const totalObj = await db.get(`SELECT COUNT(*) as c FROM treatments t ${whereClause}`, ...params);
  
  res.json({
    data: treatments,
    total: totalObj.c,
    page: Number(page),
    pageSize: Number(pageSize)
  });
});

router.post('/treatments', authRequired, [
  body('animal_id').isInt({min: 1}),
  body('treatment_date').isISO8601(),
  body('treatment_type').isIn(['preventivo','curativo','quirurgico','reproductivo','nutricional']),
  body('treatment_description').isLength({min: 10})
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {
    animal_id, veterinarian_id, treatment_date, treatment_type, diagnosis, symptoms,
    treatment_description, dosage, duration_days, cost, follow_up_date, notes
  } = req.body;

  const db = await openDb();

  try {
    const {lastID} = await db.run(`
      INSERT INTO treatments (
        animal_id, veterinarian_id, treatment_date, treatment_type, diagnosis, symptoms,
        treatment_description, dosage, duration_days, cost, follow_up_date, notes
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    `, animal_id, veterinarian_id || null, treatment_date, treatment_type, 
       diagnosis || null, symptoms || null, treatment_description, dosage || null,
       duration_days || null, cost || null, follow_up_date || null, notes || null);

    const treatment = await db.get(`
      SELECT t.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
      FROM treatments t
      LEFT JOIN animals a ON a.id = t.animal_id
      LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
      WHERE t.id = ?
    `, lastID);

    res.status(201).json(treatment);
  } catch (e) {
    if (String(e).includes('FOREIGN KEY constraint failed')) {
      return res.status(400).json({error: 'Animal o veterinario no encontrado'});
    }
    throw e;
  }
});

router.get('/treatments/:id', authRequired, async (req, res) => {
  const db = await openDb();
  
  const treatment = await db.get(`
    SELECT t.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM treatments t
    LEFT JOIN animals a ON a.id = t.animal_id
    LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
    WHERE t.id = ?
  `, req.params.id);

  if (!treatment) return res.status(404).json({error: 'Tratamiento no encontrado'});

  // Obtener medicamentos asociados
  const medications = await db.all(`
    SELECT tm.*, m.name as medication_name, m.active_ingredient, m.concentration
    FROM treatment_medications tm
    JOIN medications m ON m.id = tm.medication_id
    WHERE tm.treatment_id = ?
  `, req.params.id);

  res.json({...treatment, medications});
});

router.put('/treatments/:id', authRequired, async (req, res) => {
  const db = await openDb();
  const fields = [
    'veterinarian_id', 'treatment_date', 'treatment_type', 'diagnosis', 'symptoms',
    'treatment_description', 'dosage', 'duration_days', 'cost', 'status', 'follow_up_date', 'notes'
  ];

  const updates = [];
  const params = [];

  for (const field of fields) {
    if (field in req.body) {
      updates.push(`${field} = ?`);
      params.push(req.body[field]);
    }
  }

  if (!updates.length) return res.status(400).json({error: 'Nada para actualizar'});

  params.push(req.params.id);
  await db.run(`UPDATE treatments SET ${updates.join(', ')}, updated_at = CURRENT_TIMESTAMP WHERE id = ?`, ...params);

  const treatment = await db.get(`
    SELECT t.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM treatments t
    LEFT JOIN animals a ON a.id = t.animal_id
    LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
    WHERE t.id = ?
  `, req.params.id);

  res.json(treatment);
});

// RF5: Medicamentos de tratamientos
router.post('/treatments/:id/medications', authRequired, [
  body('medication_id').isInt({min: 1}),
  body('dosage_amount').isFloat({min: 0}),
  body('dosage_unit').isLength({min: 1}),
  body('frequency_per_day').isInt({min: 1})
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {medication_id, dosage_amount, dosage_unit, frequency_per_day, administration_route} = req.body;
  const db = await openDb();

  const {lastID} = await db.run(`
    INSERT INTO treatment_medications (treatment_id, medication_id, dosage_amount, dosage_unit, frequency_per_day, administration_route)
    VALUES (?,?,?,?,?,?)
  `, req.params.id, medication_id, dosage_amount, dosage_unit, frequency_per_day, administration_route || null);

  const medication = await db.get(`
    SELECT tm.*, m.name as medication_name, m.active_ingredient, m.concentration
    FROM treatment_medications tm
    JOIN medications m ON m.id = tm.medication_id
    WHERE tm.id = ?
  `, lastID);

  res.status(201).json(medication);
});

// RF6: Alertas sanitarias
router.get('/alerts', authRequired, async (req, res) => {
  const {status = 'activa', severity, animal_id} = req.query;
  const db = await openDb();

  let whereClause = 'WHERE ha.status = ?';
  let params = [status];

  if (severity) {
    whereClause += ' AND ha.severity = ?';
    params.push(severity);
  }

  if (animal_id) {
    whereClause += ' AND ha.animal_id = ?';
    params.push(animal_id);
  }

  const alerts = await db.all(`
    SELECT ha.*, a.tag_code, a.name as animal_name, u.name as resolved_by_name
    FROM health_alerts ha
    LEFT JOIN animals a ON a.id = ha.animal_id
    LEFT JOIN users u ON u.id = ha.resolved_by
    ${whereClause}
    ORDER BY ha.severity DESC, ha.alert_date DESC
  `, ...params);

  res.json({alerts});
});

router.post('/alerts', authRequired, [
  body('alert_type').isIn(['vacuna_vencida','tratamiento_pendiente','cuarentena','enfermedad','peso_bajo','peso_alto','revision_periodica']),
  body('severity').isIn(['baja','media','alta','critica']),
  body('title').isLength({min: 5}),
  body('description').isLength({min: 10}),
  body('alert_date').isISO8601()
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {animal_id, alert_type, severity, title, description, alert_date, due_date} = req.body;
  const db = await openDb();

  const {lastID} = await db.run(`
    INSERT INTO health_alerts (animal_id, alert_type, severity, title, description, alert_date, due_date)
    VALUES (?,?,?,?,?,?,?)
  `, animal_id || null, alert_type, severity, title, description, alert_date, due_date || null);

  const alert = await db.get(`
    SELECT ha.*, a.tag_code, a.name as animal_name
    FROM health_alerts ha
    LEFT JOIN animals a ON a.id = ha.animal_id
    WHERE ha.id = ?
  `, lastID);

  res.status(201).json(alert);
});

router.patch('/alerts/:id/resolve', authRequired, async (req, res) => {
  const db = await openDb();
  const userId = req.user.id;

  await db.run(`
    UPDATE health_alerts 
    SET status = 'resuelta', resolved_at = CURRENT_TIMESTAMP, resolved_by = ?
    WHERE id = ? AND status = 'activa'
  `, userId, req.params.id);

  const alert = await db.get(`
    SELECT ha.*, a.tag_code, a.name as animal_name, u.name as resolved_by_name
    FROM health_alerts ha
    LEFT JOIN animals a ON a.id = ha.animal_id
    LEFT JOIN users u ON u.id = ha.resolved_by
    WHERE ha.id = ?
  `, req.params.id);

  if (!alert) return res.status(404).json({error: 'Alerta no encontrada'});

  res.json(alert);
});

// RF6: Cuarentenas
router.get('/quarantines', authRequired, async (req, res) => {
  const {status = 'activa'} = req.query;
  const db = await openDb();

  const quarantines = await db.all(`
    SELECT q.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM quarantines q
    LEFT JOIN animals a ON a.id = q.animal_id
    LEFT JOIN veterinarians v ON v.id = q.veterinarian_id
    WHERE q.status = ?
    ORDER BY q.start_date DESC
  `, status);

  res.json({quarantines});
});

router.post('/quarantines', authRequired, [
  body('animal_id').isInt({min: 1}),
  body('start_date').isISO8601(),
  body('reason').isLength({min: 10})
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {animal_id, start_date, end_date, reason, location, restrictions, veterinarian_id, notes} = req.body;
  const db = await openDb();

  const {lastID} = await db.run(`
    INSERT INTO quarantines (animal_id, start_date, end_date, reason, location, restrictions, veterinarian_id, notes)
    VALUES (?,?,?,?,?,?,?,?)
  `, animal_id, start_date, end_date || null, reason, location || null, restrictions || null, veterinarian_id || null, notes || null);

  const quarantine = await db.get(`
    SELECT q.*, a.tag_code, a.name as animal_name, v.name as veterinarian_name
    FROM quarantines q
    LEFT JOIN animals a ON a.id = q.animal_id
    LEFT JOIN veterinarians v ON v.id = q.veterinarian_id
    WHERE q.id = ?
  `, lastID);

  res.status(201).json(quarantine);
});

// RF7: Métricas de salud
router.get('/health-metrics/:animal_id', authRequired, async (req, res) => {
  const db = await openDb();
  const {page = 1, pageSize = 20} = req.query;
  const offset = (Number(page) - 1) * Number(pageSize);

  const metrics = await db.all(`
    SELECT hm.*, v.name as veterinarian_name
    FROM health_metrics hm
    LEFT JOIN veterinarians v ON v.id = hm.veterinarian_id
    WHERE hm.animal_id = ?
    ORDER BY hm.metric_date DESC
    LIMIT ? OFFSET ?
  `, req.params.animal_id, Number(pageSize), offset);

  const totalObj = await db.get('SELECT COUNT(*) as c FROM health_metrics WHERE animal_id = ?', req.params.animal_id);

  res.json({
    data: metrics,
    total: totalObj.c,
    page: Number(page),
    pageSize: Number(pageSize)
  });
});

router.post('/health-metrics', authRequired, [
  body('animal_id').isInt({min: 1}),
  body('metric_date').isISO8601()
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {animal_id, metric_date, temperature, heart_rate, respiratory_rate, weight, body_condition_score, notes, veterinarian_id} = req.body;
  const db = await openDb();

  const {lastID} = await db.run(`
    INSERT INTO health_metrics (animal_id, metric_date, temperature, heart_rate, respiratory_rate, weight, body_condition_score, notes, veterinarian_id)
    VALUES (?,?,?,?,?,?,?,?,?)
  `, animal_id, metric_date, temperature || null, heart_rate || null, respiratory_rate || null, weight || null, body_condition_score || null, notes || null, veterinarian_id || null);

  const metric = await db.get(`
    SELECT hm.*, v.name as veterinarian_name
    FROM health_metrics hm
    LEFT JOIN veterinarians v ON v.id = hm.veterinarian_id
    WHERE hm.id = ?
  `, lastID);

  res.status(201).json(metric);
});

export default router;
