import {Router} from 'express';
import {body,validationResult} from 'express-validator';
import {openDb} from '../db/db.js';
import {authRequired} from '../middleware/auth.js';

const router = Router();

// RF7: Reportes sanitarios
router.get('/reports', authRequired, async (req, res) => {
  const {report_type, animal_id, farm_id, status, page = 1, pageSize = 20} = req.query;
  const db = await openDb();
  const offset = (Number(page) - 1) * Number(pageSize);

  let whereClause = '';
  let params = [];

  if (report_type) {
    whereClause += 'WHERE hr.report_type = ?';
    params.push(report_type);
  }

  if (animal_id) {
    whereClause += whereClause ? ' AND hr.animal_id = ?' : 'WHERE hr.animal_id = ?';
    params.push(animal_id);
  }

  if (farm_id) {
    whereClause += whereClause ? ' AND hr.farm_id = ?' : 'WHERE hr.farm_id = ?';
    params.push(farm_id);
  }

  if (status) {
    whereClause += whereClause ? ' AND hr.status = ?' : 'WHERE hr.status = ?';
    params.push(status);
  }

  const reports = await db.all(`
    SELECT hr.*, a.tag_code, a.name as animal_name, f.name as farm_name, u.name as generated_by_name
    FROM health_reports hr
    LEFT JOIN animals a ON a.id = hr.animal_id
    LEFT JOIN farms f ON f.id = hr.farm_id
    LEFT JOIN users u ON u.id = hr.generated_by
    ${whereClause}
    ORDER BY hr.report_date DESC
    LIMIT ? OFFSET ?
  `, ...params, Number(pageSize), offset);

  const totalObj = await db.get(`SELECT COUNT(*) as c FROM health_reports hr ${whereClause}`, ...params);

  res.json({
    data: reports,
    total: totalObj.c,
    page: Number(page),
    pageSize: Number(pageSize)
  });
});

router.post('/reports', authRequired, [
  body('report_type').isIn(['individual','lote','general','sanitario','reproductivo','nutricional']),
  body('report_date').isISO8601(),
  body('title').isLength({min: 5}),
  body('generated_by').isInt({min: 1})
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {
    report_type, animal_id, farm_id, report_date, period_start, period_end,
    generated_by, title, summary, findings, recommendations
  } = req.body;

  const db = await openDb();

  const {lastID} = await db.run(`
    INSERT INTO health_reports (
      report_type, animal_id, farm_id, report_date, period_start, period_end,
      generated_by, title, summary, findings, recommendations
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?)
  `, report_type, animal_id || null, farm_id || null, report_date, 
     period_start || null, period_end || null, generated_by, title,
     summary || null, findings || null, recommendations || null);

  const report = await db.get(`
    SELECT hr.*, a.tag_code, a.name as animal_name, f.name as farm_name, u.name as generated_by_name
    FROM health_reports hr
    LEFT JOIN animals a ON a.id = hr.animal_id
    LEFT JOIN farms f ON f.id = hr.farm_id
    LEFT JOIN users u ON u.id = hr.generated_by
    WHERE hr.id = ?
  `, lastID);

  res.status(201).json(report);
});

router.get('/reports/:id', authRequired, async (req, res) => {
  const db = await openDb();

  const report = await db.get(`
    SELECT hr.*, a.tag_code, a.name as animal_name, f.name as farm_name, u.name as generated_by_name
    FROM health_reports hr
    LEFT JOIN animals a ON a.id = hr.animal_id
    LEFT JOIN farms f ON f.id = hr.farm_id
    LEFT JOIN users u ON u.id = hr.generated_by
    WHERE hr.id = ?
  `, req.params.id);

  if (!report) return res.status(404).json({error: 'Reporte no encontrado'});

  res.json(report);
});

router.put('/reports/:id', authRequired, async (req, res) => {
  const db = await openDb();
  const fields = [
    'report_type', 'animal_id', 'farm_id', 'report_date', 'period_start', 'period_end',
    'title', 'summary', 'findings', 'recommendations', 'status'
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
  await db.run(`UPDATE health_reports SET ${updates.join(', ')} WHERE id = ?`, ...params);

  const report = await db.get(`
    SELECT hr.*, a.tag_code, a.name as animal_name, f.name as farm_name, u.name as generated_by_name
    FROM health_reports hr
    LEFT JOIN animals a ON a.id = hr.animal_id
    LEFT JOIN farms f ON f.id = hr.farm_id
    LEFT JOIN users u ON u.id = hr.generated_by
    WHERE hr.id = ?
  `, req.params.id);

  res.json(report);
});

// RF7: Generar reporte sanitario automático
router.post('/reports/generate', authRequired, [
  body('report_type').isIn(['individual','lote','general','sanitario','reproductivo','nutricional']),
  body('animal_id').optional().isInt({min: 1}),
  body('farm_id').optional().isInt({min: 1}),
  body('period_start').isISO8601(),
  body('period_end').isISO8601()
], async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) return res.status(400).json({errors: errors.array()});

  const {report_type, animal_id, farm_id, period_start, period_end, generated_by} = req.body;
  const db = await openDb();

  try {
    let reportData = {};
    let title = '';
    let summary = '';
    let findings = '';
    let recommendations = '';

    // Generar datos según el tipo de reporte
    switch (report_type) {
      case 'individual':
        if (!animal_id) return res.status(400).json({error: 'animal_id requerido para reporte individual'});
        
        const animal = await db.get('SELECT * FROM animals WHERE id = ?', animal_id);
        if (!animal) return res.status(404).json({error: 'Animal no encontrado'});

        title = `Reporte Sanitario Individual - ${animal.tag_code}`;
        
        // Obtener tratamientos del período
        const treatments = await db.all(`
          SELECT t.*, v.name as veterinarian_name
          FROM treatments t
          LEFT JOIN veterinarians v ON v.id = t.veterinarian_id
          WHERE t.animal_id = ? AND t.treatment_date BETWEEN ? AND ?
          ORDER BY t.treatment_date DESC
        `, animal_id, period_start, period_end);

        // Obtener métricas de salud del período
        const metrics = await db.all(`
          SELECT hm.*, v.name as veterinarian_name
          FROM health_metrics hm
          LEFT JOIN veterinarians v ON v.id = hm.veterinarian_id
          WHERE hm.animal_id = ? AND hm.metric_date BETWEEN ? AND ?
          ORDER BY hm.metric_date DESC
        `, animal_id, period_start, period_end);

        // Obtener alertas del período
        const alerts = await db.all(`
          SELECT ha.*, u.name as resolved_by_name
          FROM health_alerts ha
          LEFT JOIN users u ON u.id = ha.resolved_by
          WHERE ha.animal_id = ? AND ha.alert_date BETWEEN ? AND ?
          ORDER BY ha.alert_date DESC
        `, animal_id, period_start, period_end);

        reportData = {animal, treatments, metrics, alerts};
        
        summary = `Reporte sanitario del animal ${animal.tag_code} (${animal.name || 'Sin nombre'}) para el período ${period_start} a ${period_end}.`;
        summary += ` Se registraron ${treatments.length} tratamientos, ${metrics.length} mediciones de salud y ${alerts.length} alertas.`;

        findings = `Tratamientos: ${treatments.length} registrados. `;
        findings += `Métricas de salud: ${metrics.length} mediciones. `;
        findings += `Alertas: ${alerts.length} alertas (${alerts.filter(a => a.status === 'activa').length} activas).`;

        recommendations = 'Revisar tratamientos pendientes y seguir protocolos de seguimiento. ';
        if (alerts.filter(a => a.status === 'activa').length > 0) {
          recommendations += 'Atender alertas activas prioritariamente.';
        }

        break;

      case 'sanitario':
        title = `Reporte Sanitario General - ${period_start} a ${period_end}`;
        
        // Estadísticas generales
        const totalAnimals = await db.get('SELECT COUNT(*) as c FROM animals');
        const activeAlerts = await db.get('SELECT COUNT(*) as c FROM health_alerts WHERE status = "activa"');
        const treatmentsPeriod = await db.get(`
          SELECT COUNT(*) as c FROM treatments 
          WHERE treatment_date BETWEEN ? AND ?
        `, period_start, period_end);

        const quarantineCount = await db.get(`
          SELECT COUNT(*) as c FROM quarantines 
          WHERE status = "activa" AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)
        `, period_end, period_start);

        reportData = {
          totalAnimals: totalAnimals.c,
          activeAlerts: activeAlerts.c,
          treatmentsPeriod: treatmentsPeriod.c,
          quarantineCount: quarantineCount.c
        };

        summary = `Reporte sanitario general del período ${period_start} a ${period_end}. `;
        summary += `Total de animales: ${totalAnimals.c}. `;
        summary += `Alertas activas: ${activeAlerts.c}. `;
        summary += `Tratamientos realizados: ${treatmentsPeriod.c}. `;
        summary += `Animales en cuarentena: ${quarantineCount.c}.`;

        findings = `Estado sanitario general: ${activeAlerts.c > 0 ? 'Requiere atención' : 'Estable'}. `;
        findings += `Actividad veterinaria: ${treatmentsPeriod.c} tratamientos en el período.`;

        recommendations = activeAlerts.c > 0 ? 
          'Revisar y resolver alertas activas. Implementar medidas preventivas.' :
          'Mantener protocolos de prevención. Continuar monitoreo regular.';

        break;

      default:
        return res.status(400).json({error: 'Tipo de reporte no implementado aún'});
    }

    // Crear el reporte
    const {lastID} = await db.run(`
      INSERT INTO health_reports (
        report_type, animal_id, farm_id, report_date, period_start, period_end,
        generated_by, title, summary, findings, recommendations, status
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    `, report_type, animal_id || null, farm_id || null, new Date().toISOString().split('T')[0],
       period_start, period_end, generated_by || req.user.id, title, summary, findings, recommendations, 'finalizado');

    const report = await db.get(`
      SELECT hr.*, a.tag_code, a.name as animal_name, f.name as farm_name, u.name as generated_by_name
      FROM health_reports hr
      LEFT JOIN animals a ON a.id = hr.animal_id
      LEFT JOIN farms f ON f.id = hr.farm_id
      LEFT JOIN users u ON u.id = hr.generated_by
      WHERE hr.id = ?
    `, lastID);

    res.status(201).json({report, reportData});

  } catch (error) {
    console.error('Error generando reporte:', error);
    res.status(500).json({error: 'Error interno generando reporte'});
  }
});

// RF7: Estadísticas sanitarias
router.get('/stats', authRequired, async (req, res) => {
  const db = await openDb();

  try {
    // Estadísticas generales
    const totalAnimals = await db.get('SELECT COUNT(*) as c FROM animals');
    const totalTreatments = await db.get('SELECT COUNT(*) as c FROM treatments');
    const activeAlerts = await db.get('SELECT COUNT(*) as c FROM health_alerts WHERE status = "activa"');
    const activeQuarantines = await db.get('SELECT COUNT(*) as c FROM quarantines WHERE status = "activa"');
    const totalVeterinarians = await db.get('SELECT COUNT(*) as c FROM veterinarians');
    const totalMedications = await db.get('SELECT COUNT(*) as c FROM medications');

    // Alertas por severidad
    const alertsBySeverity = await db.all(`
      SELECT severity, COUNT(*) as count
      FROM health_alerts
      WHERE status = 'activa'
      GROUP BY severity
    `);

    // Tratamientos por tipo
    const treatmentsByType = await db.all(`
      SELECT treatment_type, COUNT(*) as count
      FROM treatments
      WHERE treatment_date >= date('now', '-30 days')
      GROUP BY treatment_type
    `);

    // Animales con más tratamientos (últimos 30 días)
    const animalsWithMostTreatments = await db.all(`
      SELECT a.tag_code, a.name, COUNT(t.id) as treatment_count
      FROM animals a
      JOIN treatments t ON t.animal_id = a.id
      WHERE t.treatment_date >= date('now', '-30 days')
      GROUP BY a.id, a.tag_code, a.name
      ORDER BY treatment_count DESC
      LIMIT 5
    `);

    res.json({
      general: {
        totalAnimals: totalAnimals.c,
        totalTreatments: totalTreatments.c,
        activeAlerts: activeAlerts.c,
        activeQuarantines: activeQuarantines.c,
        totalVeterinarians: totalVeterinarians.c,
        totalMedications: totalMedications.c
      },
      alertsBySeverity,
      treatmentsByType,
      animalsWithMostTreatments
    });

  } catch (error) {
    console.error('Error obteniendo estadísticas:', error);
    res.status(500).json({error: 'Error interno obteniendo estadísticas'});
  }
});

export default router;
