import {Router} from 'express';import multer from 'multer';import {body,validationResult} from 'express-validator';import {openDb} from '../db/db.js';import {authRequired} from '../middleware/auth.js';
const router=Router();const upload=multer({dest:'uploads/'});
router.get('/',authRequired,async(req,res)=>{const {q,species_id,breed_id,farm_id,page=1,pageSize=20}=req.query;const db=await openDb();
const offset=(Number(page)-1)*Number(pageSize);const params=[];const where=[];if(q){where.push('(a.tag_code LIKE ? OR a.name LIKE ?)');params.push(`%${q}%`,`%${q}%`);}
if(species_id){where.push('a.species_id = ?');params.push(species_id);}if(breed_id){where.push('a.breed_id = ?');params.push(breed_id);}if(farm_id){where.push('a.farm_id = ?');params.push(farm_id);}
const whereSql=where.length?('WHERE '+where.join(' AND ')):'';const rows=await db.all(`
SELECT a.*, s.name AS species, b.name AS breed, f.name AS farm
FROM animals a
LEFT JOIN species s ON s.id = a.species_id
LEFT JOIN breeds b ON b.id = a.breed_id
LEFT JOIN farms f  ON f.id = a.farm_id
${whereSql}
ORDER BY a.created_at DESC LIMIT ? OFFSET ?`,...params,Number(pageSize),offset);
const totalObj=await db.get(`SELECT COUNT(*) as c FROM animals a ${whereSql}`,...params);
res.json({data:rows,total:totalObj.c,page:Number(page),pageSize:Number(pageSize)});});
router.post('/',authRequired,body('tag_code').isLength({min:1}),body('sex').isIn(['M','F','U']),async(req,res)=>{
const errors=validationResult(req);if(!errors.isEmpty())return res.status(400).json({errors:errors.array()});
const {tag_code,name,sex,birth_date,species_id,breed_id,farm_id,color,status,sire_id,dam_id}=req.body;const db=await openDb();
try{const {lastID}=await db.run(`INSERT INTO animals (tag_code, name, sex, birth_date, species_id, breed_id, farm_id, color, status, sire_id, dam_id)
VALUES (?,?,?,?,?,?,?,?,?,?,?)`,tag_code,name||null,sex,birth_date||null,species_id||null,breed_id||null,farm_id||null,color||null,status||'activo',sire_id||null,dam_id||null);
const animal=await db.get('SELECT * FROM animals WHERE id = ?',lastID);res.status(201).json(animal);}catch(e){if(String(e).includes('UNIQUE constraint failed: animals.tag_code'))return res.status(409).json({error:'tag_code debe ser único'});throw e;}});
router.get('/:id',authRequired,async(req,res)=>{const db=await openDb();const animal=await db.get('SELECT * FROM animals WHERE id = ?',req.params.id);if(!animal)return res.status(404).json({error:'No encontrado'});
const weights=await db.all('SELECT * FROM animal_weights WHERE animal_id = ? ORDER BY date DESC',req.params.id);
const vaccs=await db.all('SELECT * FROM animal_vaccinations WHERE animal_id = ? ORDER BY date DESC',req.params.id);
const photos=await db.all('SELECT * FROM photos WHERE animal_id = ? ORDER BY uploaded_at DESC',req.params.id);
res.json({...animal,weights,vaccinations:vaccs,photos});});
router.put('/:id',authRequired,async(req,res)=>{const db=await openDb();const fields=['tag_code','name','sex','birth_date','species_id','breed_id','farm_id','color','status','sire_id','dam_id'];
const updates=[];const params=[];for(const f of fields){if(f in req.body){updates.push(f+' = ?');params.push(req.body[f]);}}if(!updates.length)return res.status(400).json({error:'Nada para actualizar'});
params.push(req.params.id);await db.run(`UPDATE animals SET ${updates.join(', ')}, updated_at = CURRENT_TIMESTAMP WHERE id = ?`,...params);
const animal=await db.get('SELECT * FROM animals WHERE id = ?',req.params.id);res.json(animal);});
router.delete('/:id',authRequired,async(req,res)=>{const db=await openDb();await db.run('DELETE FROM animals WHERE id = ?',req.params.id);res.json({ok:true});});
router.post('/:id/weights',authRequired,async(req,res)=>{const {date,weight_kg}=req.body;const db=await openDb();const {lastID}=await db.run('INSERT INTO animal_weights (animal_id, date, weight_kg) VALUES (?,?,?)',req.params.id,date||null,weight_kg);
const row=await db.get('SELECT * FROM animal_weights WHERE id = ?',lastID);res.status(201).json(row);});
router.post('/:id/vaccinations',authRequired,async(req,res)=>{const {date,vaccine,notes}=req.body;const db=await openDb();const {lastID}=await db.run('INSERT INTO animal_vaccinations (animal_id, date, vaccine, notes) VALUES (?,?,?,?)',req.params.id,date||null,vaccine,notes||null);
const row=await db.get('SELECT * FROM animal_vaccinations WHERE id = ?',lastID);res.status(201).json(row);});
router.post('/:id/photos',authRequired,upload.single('photo'),async(req,res)=>{const db=await openDb();const {lastID}=await db.run('INSERT INTO photos (animal_id, filename) VALUES (?,?)',req.params.id,req.file.filename);
const row=await db.get('SELECT * FROM photos WHERE id = ?',lastID);res.status(201).json(row);});export default router;