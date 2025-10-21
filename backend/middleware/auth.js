import jwt from 'jsonwebtoken';
export function authRequired(req,res,next){const h=req.headers.authorization||'';const t=h.startsWith('Bearer ')?h.slice(7):null;
if(!t)return res.status(401).json({error:'Token requerido'});try{req.user=jwt.verify(t,process.env.JWT_SECRET||'devsecret');next();}
catch(e){return res.status(401).json({error:'Token inválido'});}}
export function requireRole(role){return (req,res,next)=>{const r=req.user?.role;if(!r||(r!==role&&r!=='admin'))return res.status(403).json({error:'Permisos insuficientes'});next();}}