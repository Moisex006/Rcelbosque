<?php 
require __DIR__ . '/../app/config.php'; 
require __DIR__ . '/assets/stats.php';
$stats = getGanadoStats($pdo);

// Obtener imágenes del carrusel desde la base de datos
$carousel_images = [];
try {
  $carousel_images = $pdo->query("
    SELECT * FROM carousel_images 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, created_at DESC
  ")->fetchAll();
} catch (Exception $e) {
  // Si la tabla no existe, usar imágenes por defecto
  $carousel_images = [];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <!-- Corrección de errores de validación CSS de Font Awesome -->
  <style>
    /* Corrección para errores de validación CSS del W3C */
    /* Estos estilos corrigen los valores problemáticos sin cambiar la apariencia visual */
    .fa-beat,
    .fa-bounce,
    .fa-beat-fade,
    .fa-fade,
    .fa-flip,
    .fa-shake,
    .fa-spin {
      animation-delay: 0s;
    }
    
    .fa-rotate-by {
      transform: rotate(0deg);
    }
  </style>
  <title>RC El Bosque - Venta de Ganado Brahman</title>
<style>
/* Estilos específicos para la landing page */
:root {
  --primary-green: #2d5a27;
  --accent-green: #3e7b2e;
  --light-green: #4a9a3d;
  --bg-green: #f0f8ec;
  --text-dark: #1a3315;
}

/* Carrusel de imágenes */
.carousel-container {
  position: relative;
  width: 100%;
  height: 500px;
  overflow: hidden;
  border-radius: 20px;
  box-shadow: 0 15px 40px rgba(45, 90, 39, 0.3);
}

.carousel-slide {
  position: absolute;
  width: 100%;
  height: 100%;
  opacity: 0;
  transition: opacity 1.5s ease-in-out;
  background-size: cover;
  background-position: center;
}

.carousel-slide.active {
  opacity: 1;
}

/* Estilos del carrusel se aplican dinámicamente */

.carousel-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(transparent, rgba(45, 90, 39, 0.8));
  padding: 2rem;
  color: white;
}

/* Animaciones */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.animate-fade-in {
  animation: fadeInUp 1s ease-out forwards;
}

.animate-float {
  animation: float 3s ease-in-out infinite;
}

/* Métricas */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin: 3rem 0;
}

.metric-card {
  background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
  color: white;
  padding: 2rem;
  border-radius: 16px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(45, 90, 39, 0.3);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.metric-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 35px rgba(45, 90, 39, 0.4);
}

.metric-number {
  font-size: 3rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
  display: block;
}

.metric-label {
  font-size: 0.9rem;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* Hero section mejorado */
.hero-enhanced {
  background: linear-gradient(135deg, var(--bg-green) 0%, #e8f5e0 100%);
  padding: 4rem 0;
  position: relative;
  overflow: hidden;
}

.hero-enhanced::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(74, 154, 61, 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

/* Secciones */
.section {
  padding: 4rem 0;
}

.section-title {
  text-align: center;
  font-size: 2.5rem;
  color: var(--primary-green);
  margin-bottom: 1rem;
  font-weight: bold;
}

.section-subtitle {
  text-align: center;
  color: var(--text-dark);
  margin-bottom: 3rem;
  font-size: 1.1rem;
}

/* Cards mejoradas */
.info-card {
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  box-shadow: 0 10px 30px rgba(45, 90, 39, 0.1);
  border: 2px solid var(--bg-green);
  transition: all 0.3s ease;
  height: 100%;
}

.info-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(45, 90, 39, 0.2);
  border-color: var(--light-green);
}

.info-card i {
  font-size: 3rem;
  color: var(--accent-green);
  margin-bottom: 1.5rem;
}

.price-card {
  background: linear-gradient(135deg, var(--light-green), var(--accent-green));
  color: white;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.price-card::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%);
  animation: float 4s ease-in-out infinite;
}

/* Testimonios */
.testimonial {
  background: var(--bg-green);
  padding: 2rem;
  border-radius: 15px;
  border-left: 5px solid var(--accent-green);
  margin: 1rem 0;
}

.testimonial-text {
  font-style: italic;
  margin-bottom: 1rem;
  color: var(--text-dark);
}

.testimonial-author {
  font-weight: bold;
  color: var(--primary-green);
}

/* ============================================
   ESTILOS PARA "POR QUÉ ELEGIRNOS" - MEJORADOS
   ============================================ */
.why-choose-section {
  position: relative;
  overflow: hidden;
}

.why-choose-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 30%, rgba(45, 90, 39, 0.05) 0%, transparent 50%),
    radial-gradient(circle at 80% 70%, rgba(62, 123, 46, 0.05) 0%, transparent 50%);
  pointer-events: none;
  z-index: 0;
}

.why-choose-header {
  text-align: center;
  margin-bottom: 4rem;
  position: relative;
  z-index: 1;
}

.why-choose-header h2 {
  font-size: 3rem;
  font-weight: 700;
  color: var(--primary-green);
  margin-bottom: 1rem;
  background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: fadeInUp 0.8s ease-out;
  line-height: 1.2;
}

.why-choose-header p {
  font-size: 1.25rem;
  color: var(--text-dark);
  opacity: 0.8;
  animation: fadeInUp 0.8s ease-out 0.2s both;
  max-width: 700px;
  margin: 0 auto;
}

.why-choose-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 2.5rem;
  position: relative;
  z-index: 1;
}

.why-choose-card {
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  box-shadow: 0 10px 30px rgba(45, 90, 39, 0.1);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  position: relative;
  overflow: hidden;
  animation: fadeInUp 0.8s ease-out both;
  border: 2px solid transparent;
}

.why-choose-card:nth-child(1) { 
  animation-delay: 0.1s; 
}

.why-choose-card:nth-child(2) { 
  animation-delay: 0.2s; 
}

.why-choose-card:nth-child(3) { 
  animation-delay: 0.3s; 
}

.why-choose-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(90deg, var(--primary-green), var(--accent-green));
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.4s ease;
}

.why-choose-card:hover {
  transform: translateY(-10px) scale(1.02);
  box-shadow: 0 20px 50px rgba(45, 90, 39, 0.2);
  border-color: var(--light-green);
}

.why-choose-card:hover::before {
  transform: scaleX(1);
}

.why-choose-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  box-shadow: 0 10px 25px rgba(45, 90, 39, 0.2);
  transition: all 0.4s ease;
  position: relative;
}

.why-choose-icon::after {
  content: '';
  position: absolute;
  inset: -5px;
  border-radius: 25px;
  background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
  opacity: 0;
  transition: opacity 0.4s ease;
  z-index: -1;
  filter: blur(10px);
}

.why-choose-card:hover .why-choose-icon {
  transform: rotateY(360deg) scale(1.1);
  box-shadow: 0 15px 35px rgba(45, 90, 39, 0.3);
}

.why-choose-card:hover .why-choose-icon::after {
  opacity: 0.5;
}

.why-choose-icon i {
  font-size: 2.5rem;
  color: white;
  transition: transform 0.3s ease;
}

.why-choose-card:hover .why-choose-icon i {
  transform: scale(1.1);
}

.why-choose-card h3 {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--primary-green);
  text-align: center;
  margin-bottom: 1rem;
  transition: color 0.3s ease;
}

.why-choose-card:hover h3 {
  color: var(--accent-green);
}

.why-choose-card p {
  color: var(--text-dark);
  line-height: 1.8;
  margin-bottom: 1.5rem;
  text-align: center;
  opacity: 0.9;
  font-size: 1rem;
}

.why-choose-features {
  list-style: none;
  padding: 0;
  margin: 0;
}

.why-choose-features li {
  padding: 0.75rem 0;
  color: var(--text-dark);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  transition: all 0.3s ease;
  border-left: 3px solid transparent;
  padding-left: 1rem;
  font-size: 0.95rem;
}

.why-choose-features li:hover {
  border-left-color: var(--primary-green);
  padding-left: 1.5rem;
  color: var(--primary-green);
  transform: translateX(5px);
}

.why-choose-features li i {
  color: var(--primary-green);
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  transition: transform 0.3s ease;
}

.why-choose-features li:hover i {
  transform: scale(1.2) rotate(5deg);
}

.why-choose-cta-card {
  background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
  color: white;
  position: relative;
  overflow: hidden;
}

.why-choose-cta-card::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { 
    opacity: 0.3; 
    transform: scale(1); 
  }
  50% { 
    opacity: 0.6; 
    transform: scale(1.1); 
  }
}

.why-choose-cta-card .why-choose-icon {
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
}

.why-choose-cta-card .why-choose-icon i {
  color: white;
}

.why-choose-cta-card h3 {
  color: white;
}

.why-choose-cta-card p {
  color: rgba(255, 255, 255, 0.95);
}

.why-choose-cta-card .why-choose-features li {
  color: rgba(255, 255, 255, 0.95);
}

.why-choose-cta-card .why-choose-features li:hover {
  color: white;
  border-left-color: rgba(255, 255, 255, 0.5);
}

.why-choose-cta-card .why-choose-features li i {
  color: white;
}

.why-choose-btn {
  background: white;
  color: var(--primary-green);
  padding: 1.2rem 2.5rem;
  font-size: 1.1rem;
  font-weight: 700;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
  margin-top: 1.5rem;
  width: 100%;
  justify-content: center;
  position: relative;
  z-index: 1;
}

.why-choose-btn:hover {
  transform: translateY(-5px) scale(1.05);
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
  background: var(--light-green);
  color: white;
}

.why-choose-btn i {
  transition: transform 0.3s ease;
}

.why-choose-btn:hover i {
  transform: translateX(5px);
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ============================================
   NAVBAR RESPONSIVE CON MENÚ HAMBURGUESA
   ============================================ */
.nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
  box-shadow: 0 4px 20px rgba(45, 90, 39, 0.2);
  position: sticky;
  top: 0;
  z-index: 1000;
  flex-wrap: wrap;
}

.nav-brand {
  display: flex;
  align-items: center;
  z-index: 1001;
}

.nav-menu {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.nav-menu a {
  color: white;
  text-decoration: none;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}

.nav-menu a:hover {
  background: rgba(255, 255, 255, 0.1);
  transform: translateY(-2px);
}

.nav-menu a i {
  font-size: 1rem;
}

/* Botón hamburguesa */
.nav-toggle {
  display: none;
  flex-direction: column;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0.5rem;
  gap: 4px;
  z-index: 1002;
}

.nav-toggle span {
  width: 25px;
  height: 3px;
  background: white;
  border-radius: 3px;
  transition: all 0.3s ease;
  display: block;
}

.nav-toggle.active span:nth-child(1) {
  transform: rotate(45deg) translate(5px, 5px);
}

.nav-toggle.active span:nth-child(2) {
  opacity: 0;
}

.nav-toggle.active span:nth-child(3) {
  transform: rotate(-45deg) translate(7px, -6px);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 768px) {
  /* Navbar móvil */
  .nav {
    padding: 1rem;
    position: relative;
  }
  
  .nav-toggle {
    display: flex;
  }
  
  .nav-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
    flex-direction: column;
    padding: 1rem;
    box-shadow: 0 8px 25px rgba(45, 90, 39, 0.3);
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
    gap: 0.5rem;
  }
  
  .nav-menu.active {
    max-height: 500px;
    padding: 1.5rem 1rem;
  }
  
  .nav-menu a {
    width: 100%;
    padding: 1rem;
    justify-content: flex-start;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .nav-menu a:last-child {
    border-bottom: none;
  }
  
  /* Hero section */
  .hero-enhanced {
    padding: 2rem 0;
  }
  
  .grid.grid-2 {
    grid-template-columns: 1fr;
    gap: 2rem;
  }
  
  .hero-enhanced h1 {
    font-size: 2rem !important;
    line-height: 1.2;
  }
  
  .hero-enhanced h1 img {
    height: 40px !important;
  }
  
  /* Secciones */
  .section {
    padding: 2rem 0;
  }
  
  .section-title {
    font-size: 1.75rem;
    padding: 0 1rem;
  }
  
  .section-subtitle {
    font-size: 1rem;
    padding: 0 1rem;
    margin-bottom: 2rem;
  }
  
  /* Container */
  .container {
    padding: 0 1rem;
  }
  
  /* Carrusel */
  .carousel-container {
    height: 300px;
    border-radius: 15px;
  }
  
  .carousel-overlay {
    padding: 1rem;
  }
  
  .carousel-overlay h2 {
    font-size: 1.5rem !important;
  }
  
  .carousel-overlay p {
    font-size: 0.9rem !important;
  }
  
  /* Métricas */
  .metrics-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
    margin: 2rem 0;
  }
  
  .metric-card {
    padding: 1.5rem;
  }
  
  .metric-number {
    font-size: 2rem;
  }
  
  /* Cards */
  .info-card {
    padding: 1.5rem;
  }
  
  .info-card i {
    font-size: 2rem;
    margin-bottom: 1rem;
  }
  
  .info-card h3 {
    font-size: 1.25rem;
  }
  
  /* Grid responsive */
  .grid-3,
  .grid-4 {
    grid-template-columns: 1fr;
  }
  
  /* Why Choose Section */
  .why-choose-header h2 {
    font-size: 1.75rem !important;
  }
  
  .why-choose-header p {
    font-size: 1rem !important;
  }
  
  .why-choose-grid {
    grid-template-columns: 1fr !important;
    gap: 1.5rem !important;
  }
  
  .why-choose-card {
    padding: 1.5rem !important;
  }
  
  .why-choose-icon {
    width: 60px !important;
    height: 60px !important;
  }
  
  .why-choose-icon i {
    font-size: 1.75rem !important;
  }
  
  /* Botones */
  .btn {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    width: 100%;
    justify-content: center;
  }
  
  /* CTA Final */
  .section a.btn {
    margin-bottom: 1rem;
  }
  
  /* Testimonios */
  .testimonial {
    padding: 1.5rem;
  }
  
  /* Footer */
  .footer {
    padding: 1.5rem 0;
    font-size: 0.9rem;
  }
}

@media (max-width: 480px) {
  /* Extra pequeño */
  .nav {
    padding: 0.75rem;
  }
  
  #site_name {
    font-size: 1.1rem;
  }
  
  .nav-brand img {
    height: 35px !important;
  }
  
  .hero-enhanced h1 {
    font-size: 1.75rem !important;
  }
  
  .section-title {
    font-size: 1.5rem;
  }
  
  .metric-number {
    font-size: 1.75rem;
  }
  
  .carousel-container {
    height: 250px;
  }
  
  .info-card {
    padding: 1.25rem;
  }
}

/* Animación para el menú móvil */
@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.nav-menu.active a {
  animation: slideDown 0.3s ease forwards;
}

.nav-menu.active a:nth-child(1) { animation-delay: 0.05s; }
.nav-menu.active a:nth-child(2) { animation-delay: 0.1s; }
.nav-menu.active a:nth-child(3) { animation-delay: 0.15s; }
.nav-menu.active a:nth-child(4) { animation-delay: 0.2s; }
.nav-menu.active a:nth-child(5) { animation-delay: 0.25s; }
</style>
</head>
<body>
<nav class="nav">
  <div class="nav-brand">
    <a href="index.php" id="logo_link" style="display:flex;align-items:center;gap:.7rem;font-size:1.3rem;font-weight:bold;text-decoration:none;color:inherit;">
      <img src="assets/images/logo-rc-el-bosque.png" alt="Logo RC El Bosque" id="site_logo" style="height:40px;width:auto;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);">
      <span id="site_name">RC El Bosque</span>
    </a>
  </div>
  
  <!-- Botón hamburguesa para móviles -->
  <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
    <span></span>
    <span></span>
    <span></span>
  </button>
  
  <!-- Menú de navegación -->
  <div class="nav-menu" id="navMenu">
    <a href="catalogo.php"><i class="fas fa-list"></i> <span>Catálogo</span></a>
    <?php if(!is_logged_in()): ?>
      <a href="login.php"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
      <a href="register.php"><i class="fas fa-user-plus"></i> <span>Registro</span></a>
    <?php else: 
      $current_user = current_user();
      $user_role = $current_user['role'] ?? 'user';
      ?>
      <?php if($user_role === 'user'): ?>
        <a href="catalogo.php" style="display: flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-user-circle" style="font-size: 1.2rem;"></i>
          <span><?= e($current_user['name'] ?? 'Usuario') ?></span>
        </a>
      <?php else: ?>
        <a href="admin.php"><i class="fas fa-cogs"></i> <span>Admin</span></a>
      <?php endif; ?>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Salir</span></a>
    <?php endif; ?>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero-enhanced">
  <div class="container">
    <div class="grid grid-2" style="align-items: center; gap: 3rem;">
      <div class="animate-fade-in">
        <h1 style="font-size: 3.5rem; color: var(--primary-green); margin-bottom: 1rem; line-height: 1.2;display:flex;align-items:center;gap:1rem;">
          <img src="assets/images/logo-rc-el-bosque.png" alt="Logo RC El Bosque" style="height:60px;width:auto;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);">
          RC El Bosque
        </h1>
        <p style="font-size: 1.3rem; color: var(--text-dark); margin-bottom: 2rem; line-height: 1.6;">
          Encuentra los mejores ejemplares bovinos de Colombia. 
          Catálogo especializado en ganado de alta calidad genética para tu operación ganadera.
        </p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a class="btn" href="catalogo.php" style="padding: 1rem 2rem; font-size: 1.1rem;">
            <i class="fas fa-cow"></i> Ver Catálogo
          </a>
          <a class="btn secondary" href="register.php" style="padding: 1rem 2rem; font-size: 1.1rem;">
            <i class="fas fa-user-plus"></i> Registrarse
          </a>
        </div>
      </div>
      <div class="carousel-container animate-fade-in">
        <?php if (empty($carousel_images)): ?>
        <!-- Imágenes por defecto si no hay imágenes en la BD -->
        <div class="carousel-slide active" style="background-image: url('https://images.unsplash.com/photo-1500595046743-cd271d694d30?ixlib=rb-4.0.3&w=1200&h=600&fit=crop');">
          <div class="carousel-overlay">
            <h3><i class="fas fa-cow"></i> Holstein Premium en Venta</h3>
            <p>Exemplares certificados disponibles en nuestro catálogo</p>
          </div>
        </div>
        <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1516467508483-a7212febe31a?ixlib=rb-4.0.3&w=1200&h=600&fit=crop');">
          <div class="carousel-overlay">
            <h3><i class="fas fa-medal"></i> Brahman Selecto</h3>
            <p>Los mejores reproductores del mercado colombiano</p>
          </div>
        </div>
        <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1544966503-7cc5ac882d5f?ixlib=rb-4.0.3&w=1200&h=600&fit=crop');">
          <div class="carousel-overlay">
            <h3><i class="fas fa-star"></i> Cebuinos de Exportación</h3>
            <p>Ganado con registro genealógico y certificaciones</p>
          </div>
        </div>
        <?php else: ?>
        <?php foreach ($carousel_images as $index => $img): ?>
        <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= htmlspecialchars($img['file_path']) ?>');">
          <div class="carousel-overlay">
            <?php if (!empty($img['title'])): ?>
            <h3><?= htmlspecialchars($img['title']) ?></h3>
            <?php endif; ?>
            <?php if (!empty($img['description'])): ?>
            <p><?= htmlspecialchars($img['description']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Métricas de Ventas -->
<section class="section" style="background: white;">
  <div class="container">
    <h2 class="section-title">
      <i class="fas fa-chart-bar"></i> Nuestro Inventario
    </h2>
    <p class="section-subtitle">Ganado disponible en nuestro catálogo especializado</p>
    
    <div class="metrics-grid">
      <div class="metric-card animate-fade-in">
        <span class="metric-number" data-target="<?= $stats['total_animals'] ?>">0</span>
        <span class="metric-label">
          <i class="fas fa-cow"></i><br>
          Cabezas Disponibles
        </span>
      </div>
    </div>
  </div>
</section>

<!-- Por Qué Elegirnos -->
<section class="section why-choose-section" style="background: linear-gradient(135deg, #f0f8ec 0%, #e8f5e9 50%, #f0f8ec 100%); padding: 5rem 0; position: relative; overflow: hidden;">
  <div class="container why-choose-section">
    <div class="why-choose-header">
      <h2>¿Por Qué Elegir Rc El Bosque?</h2>
      <p>La plataforma líder en venta de ganado premium en Colombia</p>
    </div>
    
    <div class="why-choose-grid">
      <div class="why-choose-card">
        <div class="why-choose-icon">
          <i class="fas fa-certificate"></i>
        </div>
        <h3>Ganado Certificado</h3>
        <p>Todos nuestros animales cuentan con documentación completa, registros genealógicos y certificaciones sanitarias al día.</p>
        <ul class="why-choose-features">
          <li><i class="fas fa-check-circle"></i> Registro ICA actualizado</li>
          <li><i class="fas fa-check-circle"></i> Genealogía verificada</li>
          <li><i class="fas fa-check-circle"></i> Vacunación completa</li>
          <li><i class="fas fa-check-circle"></i> Exámenes veterinarios</li>
        </ul>
      </div>
      
      <div class="why-choose-card">
        <div class="why-choose-icon">
          <i class="fas fa-eye"></i>
        </div>
        <h3>Transparencia Total</h3>
        <p>Información detallada de cada animal con fotografías, videos, historial médico y características genéticas.</p>
        <ul class="why-choose-features">
          <li><i class="fas fa-check-circle"></i> Galería fotográfica HD</li>
          <li><i class="fas fa-check-circle"></i> Videos del animal</li>
          <li><i class="fas fa-check-circle"></i> Historial completo</li>
          <li><i class="fas fa-check-circle"></i> Datos reproductivos</li>
        </ul>
      </div>
      
      <div class="why-choose-card why-choose-cta-card">
        <div class="why-choose-icon">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h3>Compra Segura</h3>
        <p>Garantizamos la calidad y seguridad en cada transacción, con soporte completo durante todo el proceso.</p>
        <ul class="why-choose-features">
          <li><i class="fas fa-check-circle"></i> Garantía de calidad</li>
          <li><i class="fas fa-check-circle"></i> Transporte coordinado</li>
          <li><i class="fas fa-check-circle"></i> Documentación legal</li>
          <li><i class="fas fa-check-circle"></i> Soporte post-venta</li>
        </ul>
        <a href="catalogo.php" class="why-choose-btn">
          <i class="fas fa-shopping-cart"></i>
          <span>Explorar Catálogo</span>
        </a>
      </div>
    </div>
  </div>
</section>


<!-- CTA Final -->
<section class="section" style="background: linear-gradient(135deg, var(--primary-green), var(--accent-green)); color: white;">
  <div class="container" style="text-align: center;">
    <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">
      <i class="fas fa-cow"></i> Encuentra el Ganado Perfecto para Tu Operación
    </h2>
    <p style="font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9;">
      Más de 1,200 compradores confían en nuestro catálogo para encontrar los mejores ejemplares
    </p>
    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
      <a class="btn" href="catalogo.php" style="background: white; color: var(--primary-green); padding: 1.2rem 2.5rem; font-size: 1.2rem; font-weight: 600; border-radius: 8px; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: inline-block;">
        <i class="fas fa-search"></i> Explorar Catálogo
      </a>
      <a class="btn secondary" href="register.php" style="border: 2px solid white; padding: 1rem 2rem; font-size: 1.1rem;">
        <i class="fas fa-user-plus"></i> Crear Cuenta
      </a>
    </div>
  </div>
</section>

<div class="footer" style="background: var(--text-dark); color: white; padding: 3rem 0;">
  <div class="container">
    <div class="grid grid-3">
      <div>
        <h4 style="display:flex;align-items:center;gap:.7rem;"><img src="assets/images/logo-rc-el-bosque.png" alt="Logo RC El Bosque" style="height:32px;width:auto;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);"> RC El Bosque</h4>
        <p>Catálogo especializado en venta de ganado Brahman. Los mejores ejemplares bovinos de Colombia al alcance de un clic.</p>
      </div>
      <div>
        <h4>Contacto</h4>
        <p><i class="fas fa-phone"></i> 3132280538</p>
        <p><i class="fas fa-envelope"></i> rc.elbosque.app@gmail.com</p>
        <p><i class="fas fa-map-marker-alt"></i> Aguachica, César</p>
      </div>
      <div>
        <h4>Enlaces Útiles</h4>
        <p><a href="#" style="color: white; text-decoration: none;"><i class="fas fa-question-circle"></i> Centro de Ayuda</a></p>
        <p><a href="#" style="color: white; text-decoration: none;"><i class="fas fa-file-contract"></i> Términos y Condiciones</a></p>
        <p><a href="#" style="color: white; text-decoration: none;"><i class="fas fa-shield-alt"></i> Política de Privacidad</a></p>
      </div>
    </div>
    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #444;">
      © 2025 Rc El Bosque. Todos los derechos reservados.
    </div>
  </div>
</div>

<script>
// Carrusel de imágenes
let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');
const totalSlides = slides.length;

function showSlide(index) {
  slides.forEach((slide, i) => {
    slide.classList.toggle('active', i === index);
  });
}

function nextSlide() {
  currentSlide = (currentSlide + 1) % totalSlides;
  showSlide(currentSlide);
}

// Cambiar slide cada 4 segundos
setInterval(nextSlide, 4000);

// Animación de números
function animateNumbers() {
  const counters = document.querySelectorAll('[data-target]');
  
  counters.forEach(counter => {
    const target = parseInt(counter.getAttribute('data-target'));
    const increment = target / 100;
    let current = 0;
    
    const updateCounter = () => {
      if (current < target) {
        current += increment;
        if (counter.textContent.includes('%')) {
          counter.textContent = Math.ceil(current) + '%';
        } else if (counter.textContent.includes('$')) {
          counter.textContent = '$' + Math.ceil(current) + 'M';
        } else {
          counter.textContent = Math.ceil(current).toLocaleString();
        }
        setTimeout(updateCounter, 20);
      } else {
        if (counter.textContent.includes('%')) {
          counter.textContent = target + '%';
        } else if (counter.textContent.includes('$')) {
          counter.textContent = '$' + target + 'M';
        } else {
          counter.textContent = target.toLocaleString();
        }
      }
    };
    
    updateCounter();
  });
}

// Intersection Observer para animaciones
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
      
      // Animar números cuando entran en vista
      if (entry.target.querySelector('[data-target]')) {
        setTimeout(animateNumbers, 300);
      }
    }
  });
}, observerOptions);

// Observar elementos para animación
document.addEventListener('DOMContentLoaded', () => {
  const animatedElements = document.querySelectorAll('.animate-fade-in, .metric-card, .info-card');
  animatedElements.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
  });
});

// Smooth scroll para navegación
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

// Efecto parallax suave
window.addEventListener('scroll', () => {
  const scrolled = window.pageYOffset;
  const rate = scrolled * -0.5;
  
  const heroElements = document.querySelectorAll('.animate-float');
  heroElements.forEach(el => {
    el.style.transform = `translateY(${rate}px)`;
  });
});

// Animación de aparición progresiva
const observerOptions2 = {
  threshold: 0.1,
  rootMargin: '0px 0px -100px 0px'
};

const observer2 = new IntersectionObserver((entries) => {
  entries.forEach((entry, index) => {
    if (entry.isIntersecting) {
      setTimeout(() => {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0) scale(1)';
      }, index * 100);
    }
  });
}, observerOptions2);

// Efectos hover mejorados para las cards
document.querySelectorAll('.info-card, .metric-card').forEach(card => {
  card.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-8px) scale(1.02)';
    this.style.boxShadow = '0 20px 40px rgba(45, 90, 39, 0.25)';
  });
  
  card.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0) scale(1)';
    this.style.boxShadow = '0 8px 25px rgba(45, 90, 39, 0.1)';
  });
});

// Contador animado mejorado
function animateValue(element, start, end, duration, suffix = '') {
  const range = end - start;
  const increment = range / (duration / 16);
  let current = start;
  
  const timer = setInterval(() => {
    current += increment;
    if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
      clearInterval(timer);
      current = end;
    }
    
    if (suffix === '%') {
      element.textContent = Math.ceil(current) + '%';
    } else if (suffix === 'M') {
      element.textContent = '$' + Math.ceil(current) + 'M';
    } else {
      element.textContent = Math.ceil(current).toLocaleString('es-CO');
    }
  }, 16);
}

// Activar partículas de fondo (simuladas con CSS)
function createParticle() {
  const particle = document.createElement('div');
  particle.style.cssText = `
    position: fixed;
    width: 4px;
    height: 4px;
    background: rgba(45, 90, 39, 0.3);
    border-radius: 50%;
    pointer-events: none;
    z-index: -1;
    left: ${Math.random() * 100}vw;
    top: 100vh;
    animation: floatUp ${5 + Math.random() * 5}s linear infinite;
  `;
  
  document.body.appendChild(particle);
  
  setTimeout(() => {
    particle.remove();
  }, 10000);
}

// Crear partículas periódicamente
if (window.innerWidth > 768) { // Solo en desktop
  setInterval(createParticle, 2000);
}

// Agregar estilos para las partículas
const style = document.createElement('style');
style.textContent = `
  @keyframes floatUp {
    0% {
      transform: translateY(0) rotate(0deg);
      opacity: 0;
    }
    10% {
      opacity: 1;
    }
    90% {
      opacity: 1;
    }
    100% {
      transform: translateY(-100vh) rotate(360deg);
      opacity: 0;
    }
  }
  
  .metric-card {
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  }
  
  .info-card {
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  }
`;
document.head.appendChild(style);

// Loading inicial con fade in
window.addEventListener('load', () => {
  document.body.style.opacity = '0';
  document.body.style.transition = 'opacity 0.5s ease';
  
  setTimeout(() => {
    document.body.style.opacity = '1';
  }, 100);
});

// ============================================
// NAVBAR MOBILE TOGGLE
// ============================================
const navToggle = document.getElementById('navToggle');
const navMenu = document.getElementById('navMenu');

if (navToggle && navMenu) {
  navToggle.addEventListener('click', () => {
    navToggle.classList.toggle('active');
    navMenu.classList.toggle('active');
  });
  
  // Cerrar menú al hacer clic en un enlace (móvil)
  const navLinks = navMenu.querySelectorAll('a');
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        navToggle.classList.remove('active');
        navMenu.classList.remove('active');
      }
    });
  });
  
  // Cerrar menú al hacer clic fuera (móvil)
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768) {
      if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
        navToggle.classList.remove('active');
        navMenu.classList.remove('active');
      }
    }
  });
  
  // Ajustar menú al cambiar tamaño de ventana
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      navToggle.classList.remove('active');
      navMenu.classList.remove('active');
    }
  });
}
</script>

</body></html>
