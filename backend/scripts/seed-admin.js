import { openDb } from '../db/db.js';import bcrypt from 'bcryptjs';
const ADMIN_EMAIL=process.env.ADMIN_EMAIL||'admin@agrogan.local';const ADMIN_PASSWORD=process.env.ADMIN_PASSWORD||'admin123';const ADMIN_NAME=process.env.ADMIN_NAME||'Administrador';
const db=await openDb();const exists=await db.get('SELECT id FROM users WHERE email = ?',ADMIN_EMAIL);
if(!exists){const hash=await bcrypt.hash(ADMIN_PASSWORD,10);await db.run('INSERT INTO users (email, password_hash, name, role) VALUES (?,?,?,?)',ADMIN_EMAIL,hash,ADMIN_NAME,'admin');console.log('Admin creado:',ADMIN_EMAIL);}
else{await db.run('UPDATE users SET role = ? WHERE email = ?','admin',ADMIN_EMAIL);console.log('Admin asegurado');}
process.exit(0);