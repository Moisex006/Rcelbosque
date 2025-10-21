import {Router} from 'express';import {openDb} from '../db/db.js';import {authRequired,requireRole} from '../middleware/auth.js';
const router=Router();
router.get('/users',authRequired,requireRole('admin'),async(req,res)=>{const db=await openDb();const rows=await db.all('SELECT id, email, name, role, created_at FROM users ORDER BY created_at DESC');res.json({users:rows});});
router.patch('/users/:id/role',authRequired,requireRole('admin'),async(req,res)=>{const {role}=req.body;if(!['admin','user'].includes(role))return res.status(400).json({error:'Rol inválido'});
const db=await openDb();await db.run('UPDATE users SET role = ? WHERE id = ?',role,req.params.id);const row=await db.get('SELECT id, email, name, role FROM users WHERE id = ?',req.params.id);res.json(row);});
router.post('/catalog/:animal_id',authRequired,requireRole('admin'),async(req,res)=>{const db=await openDb();await db.run('INSERT OR IGNORE INTO catalog_items (animal_id, visible) VALUES (?,1)',req.params.animal_id);
const item=await db.get('SELECT * FROM catalog_items WHERE animal_id = ?',req.params.animal_id);res.status(201).json(item);});
router.delete('/catalog/:animal_id',authRequired,requireRole('admin'),async(req,res)=>{const db=await openDb();await db.run('DELETE FROM catalog_items WHERE animal_id = ?',req.params.animal_id);res.json({ok:true});});
export default router;