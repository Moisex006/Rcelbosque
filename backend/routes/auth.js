import {Router} from 'express';import {body,validationResult} from 'express-validator';import bcrypt from 'bcryptjs';import jwt from 'jsonwebtoken';import {openDb} from '../db/db.js';import {authRequired} from '../middleware/auth.js';
const router=Router();
router.post('/register',body('email').isEmail(),body('password').isLength({min:6}),body('name').isLength({min:2}),async(req,res)=>{
const errors=validationResult(req);if(!errors.isEmpty())return res.status(400).json({errors:errors.array()});const {email,password,name}=req.body;const db=await openDb();
const exists=await db.get('SELECT id FROM users WHERE email = ?',email);if(exists)return res.status(409).json({error:'Email ya registrado'});
const hash=await bcrypt.hash(password,10);const role='user';const {lastID}=await db.run('INSERT INTO users (email, password_hash, name, role) VALUES (?,?,?,?)',email,hash,name,role);
const token=jwt.sign({id:lastID,email,name,role},process.env.JWT_SECRET||'devsecret',{expiresIn:'7d'});res.status(201).json({token});});
router.post('/login',body('email').isEmail(),body('password').notEmpty(),async(req,res)=>{const errors=validationResult(req);if(!errors.isEmpty())return res.status(400).json({errors:errors.array()});
const {email,password}=req.body;const db=await openDb();const user=await db.get('SELECT * FROM users WHERE email = ?',email);if(!user)return res.status(401).json({error:'Credenciales inválidas'});
const ok=await bcrypt.compare(password,user.password_hash);if(!ok)return res.status(401).json({error:'Credenciales inválidas'});
const token=jwt.sign({id:user.id,email:user.email,name:user.name,role:user.role},process.env.JWT_SECRET||'devsecret',{expiresIn:'7d'});res.json({token});});
router.get('/me',authRequired,async(req,res)=>res.json({user:req.user}));
export default router;