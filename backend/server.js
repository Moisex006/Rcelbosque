import express from 'express';import cors from 'cors';import dotenv from 'dotenv';
import authRoutes from './routes/auth.js';import animalRoutes from './routes/animals.js';import adminRoutes from './routes/admin.js';import publicRoutes from './routes/public.js';
dotenv.config();const app=express();app.use(cors({origin:process.env.CORS_ORIGIN?.split(',')||['*']}));app.use(express.json({limit:'10mb'}));app.use('/uploads',express.static('uploads'));
app.get('/',(req,res)=>res.json({ok:true,name:'AgroGan API',version:'2.0.0'}));
app.use('/api/auth',authRoutes);app.use('/api/animals',animalRoutes);app.use('/api/admin',adminRoutes);app.use('/api/public',publicRoutes);
const port=process.env.PORT||3000;app.listen(port,()=>console.log('AgroGan API on :'+port));