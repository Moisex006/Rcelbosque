import {Router} from 'express';import {openDb} from '../db/db.js';
const router=Router();
router.get('/catalog',async(req,res)=>{const db=await openDb();const rows=await db.all(`
SELECT a.id, a.tag_code, a.name, a.sex, a.color, a.status, s.name as species, b.name as breed, f.name as farm
FROM catalog_items c
JOIN animals a ON a.id = c.animal_id
LEFT JOIN species s ON s.id = a.species_id
LEFT JOIN breeds b ON b.id = a.breed_id
LEFT JOIN farms f  ON f.id = a.farm_id
WHERE c.visible = 1
ORDER BY a.created_at DESC`);res.json({data:rows});});
router.get('/catalog/:id',async(req,res)=>{const db=await openDb();const row=await db.get(`
SELECT a.id, a.tag_code, a.name, a.sex, a.color, a.status, s.name as species, b.name as breed, f.name as farm
FROM catalog_items c
JOIN animals a ON a.id = c.animal_id
LEFT JOIN species s ON s.id = a.species_id
LEFT JOIN breeds b ON b.id = a.breed_id
LEFT JOIN farms f  ON f.id = a.farm_id
WHERE c.visible = 1 AND a.id = ?`,req.params.id);if(!row)return res.status(404).json({error:'No encontrado'});res.json(row);});
export default router;