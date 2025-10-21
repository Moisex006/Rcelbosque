<?php 
require __DIR__ . '/../app/config.php'; 
require __DIR__ . '/assets/stats.php';
require __DIR__ . '/assets/market.php';
$stats = getGanadoStats($pdo);
$prices = getMarketPrices();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

.carousel-slide:nth-child(1) {
  background-image: url('https://images.unsplash.com/photo-1500595046743-cd271d694d30?ixlib=rb-4.0.3&w=1200&h=600&fit=crop');
}

.carousel-slide:nth-child(2) {
  background-image: url('https://images.unsplash.com/photo-1516467508483-a7212febe31a?ixlib=rb-4.0.3&w=1200&h=600&fit=crop');
}

.carousel-slide:nth-child(3) {
  background-image: url('https://images.unsplash.com/photo-1544966503-7cc5ac882d5f?ixlib=rb-4.0.3&w=1200&h=600&fit=crop');
}

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
</style>
</head><body>
<nav class="nav">
  <a href="index.php" style="display:flex;align-items:center;gap:.7rem;font-size:1.3rem;font-weight:bold;">
    <img src="assets/images/logo-rc-el-bosque.png" alt="Logo RC El Bosque" style="height:40px;width:auto;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    RC El Bosque
  </a>
  <a href="catalogo.php"><i class="fas fa-list"></i> Catálogo</a>
  <?php if(!is_logged_in()): ?>
    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
    <a href="register.php"><i class="fas fa-user-plus"></i> Registro</a>
  <?php else: ?>
    <a href="admin.php"><i class="fas fa-cogs"></i> Admin</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
  <?php endif; ?>
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
        <div class="carousel-slide active">
          <div class="carousel-overlay">
            <h3><i class="fas fa-cow"></i> Holstein Premium en Venta</h3>
            <p>Exemplares certificados disponibles en nuestro catálogo</p>
          </div>
        </div>
        <div class="carousel-slide">
          <div class="carousel-overlay">
            <h3><i class="fas fa-medal"></i> Brahman Selecto</h3>
            <p>Los mejores reproductores del mercado colombiano</p>
          </div>
        </div>
        <div class="carousel-slide">
          <div class="carousel-overlay">
            <h3><i class="fas fa-star"></i> Cebuinos de Exportación</h3>
            <p>Ganado con registro genealógico y certificaciones</p>
          </div>
        </div>
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
<section class="section" style="background: var(--bg-green);">
  <div class="container">
    <h2 class="section-title">¿Por Qué Elegir AgroGan?</h2>
    <p class="section-subtitle">La plataforma líder en venta de ganado premium en Colombia</p>
    
    <div class="grid grid-3">
      <div class="info-card">
        <i class="fas fa-certificate"></i>
        <h3 style="color: var(--primary-green);">Ganado Certificado</h3>
        <p>Todos nuestros animales cuentan con documentación completa, registros genealógicos y certificaciones sanitarias al día.</p>
        <ul style="color: var(--text-dark);">
          <li>Registro ICA actualizado</li>
          <li>Genealogía verificada</li>
          <li>Vacunación completa</li>
          <li>Exámenes veterinarios</li>
        </ul>
      </div>
      
      <div class="info-card">
        <i class="fas fa-eye"></i>
        <h3 style="color: var(--primary-green);">Transparencia Total</h3>
        <p>Información detallada de cada animal con fotografías, videos, historial médico y características genéticas.</p>
        <ul style="color: var(--text-dark);">
          <li>Galería fotográfica HD</li>
          <li>Videos del animal</li>
          <li>Historial completo</li>
          <li>Datos reproductivos</li>
        </ul>
      </div>
      
      <div class="info-card price-card">
        <i class="fas fa-handshake"></i>
        <h3>Compra Segura</h3>
        <div style="margin: 1rem 0;">
          <i class="fas fa-shield-alt" style="font-size: 3rem;"></i>
        </div>
        <ul style="list-style: none; padding: 0;">
          <li><i class="fas fa-check"></i> Garantía de calidad</li>
          <li><i class="fas fa-check"></i> Transporte coordinado</li>
          <li><i class="fas fa-check"></i> Documentación legal</li>
          <li><i class="fas fa-check"></i> Soporte post-venta</li>
        </ul>
        <a href="catalogo.php" class="btn" style="background: white; color: var(--primary-green); margin-top: 1rem;">
          <i class="fas fa-shopping-cart"></i> Explorar Catálogo
        </a>
      </div>
    </div>
  </div>
</section>

<!-- Precios de Mercado -->
<section class="section" style="background: white;">
  <div class="container">
    <h2 class="section-title">
      <i class="fas fa-tags"></i> Precios de Referencia
    </h2>
    <p class="section-subtitle">
      Valores actualizados del mercado ganadero - Última actualización: <?= date('d/m/Y', strtotime($prices['last_update'])) ?>
    </p>
    
    <div class="grid grid-4">
      <div class="info-card" style="text-align: center; position: relative;">
        <i class="fas fa-cow" style="color: #8B4513;"></i>
        <h4 style="color: var(--primary-green);">Novillo Gordo</h4>
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-green);">
          <?= formatPrice($prices['novillo_gordo']['min']) ?> - <?= formatPrice($prices['novillo_gordo']['max']) ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 0.5rem;">
          <i class="<?= getTrendIcon($prices['novillo_gordo']['trend']) ?>" 
             style="color: <?= getTrendColor($prices['novillo_gordo']['trend']) ?>; font-size: 0.8rem;"></i>
          <small style="color: <?= getTrendColor($prices['novillo_gordo']['trend']) ?>;">
            <?= formatPrice($prices['novillo_gordo']['change']) ?>
          </small>
        </div>
        <small style="color: #666;">Por kg en pie</small>
      </div>
      
      <div class="info-card" style="text-align: center;">
        <i class="fas fa-female" style="color: #FF69B4;"></i>
        <h4 style="color: var(--primary-green);">Vaca Gorda</h4>
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-green);">
          <?= formatPrice($prices['vaca_gorda']['min']) ?> - <?= formatPrice($prices['vaca_gorda']['max']) ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 0.5rem;">
          <i class="<?= getTrendIcon($prices['vaca_gorda']['trend']) ?>" 
             style="color: <?= getTrendColor($prices['vaca_gorda']['trend']) ?>; font-size: 0.8rem;"></i>
          <small style="color: <?= getTrendColor($prices['vaca_gorda']['trend']) ?>;">
            <?= formatPrice($prices['vaca_gorda']['change']) ?>
          </small>
        </div>
        <small style="color: #666;">Por kg en pie</small>
      </div>
      
      <div class="info-card" style="text-align: center;">
        <i class="fas fa-baby" style="color: #DEB887;"></i>
        <h4 style="color: var(--primary-green);">Ternero Destete</h4>
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-green);">
          <?= formatPrice($prices['ternero_destete']['min']) ?> - <?= formatPrice($prices['ternero_destete']['max']) ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 0.5rem;">
          <i class="<?= getTrendIcon($prices['ternero_destete']['trend']) ?>" 
             style="color: <?= getTrendColor($prices['ternero_destete']['trend']) ?>; font-size: 0.8rem;"></i>
          <small style="color: <?= getTrendColor($prices['ternero_destete']['trend']) ?>;">
            <?= formatPrice($prices['ternero_destete']['change']) ?>
          </small>
        </div>
        <small style="color: #666;">Por kg en pie</small>
      </div>
      
      <div class="info-card" style="text-align: center;">
        <i class="fas fa-medal" style="color: #FFD700;"></i>
        <h4 style="color: var(--primary-green);">Reproductor</h4>
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-green);">
          <?= formatPrice($prices['reproductor']['min'], true) ?> - <?= formatPrice($prices['reproductor']['max'], true) ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 0.5rem;">
          <i class="<?= getTrendIcon($prices['reproductor']['trend']) ?>" 
             style="color: <?= getTrendColor($prices['reproductor']['trend']) ?>; font-size: 0.8rem;"></i>
          <small style="color: <?= getTrendColor($prices['reproductor']['trend']) ?>;">
            +<?= formatPrice($prices['reproductor']['change'], true) ?>
          </small>
        </div>
        <small style="color: #666;">Por ejemplar</small>
      </div>
    </div>
    
    <!-- Widget de mercado adicional -->
    <div class="card" style="margin-top: 2rem; background: linear-gradient(135deg, var(--bg-light), white);">
      <div class="grid grid-2">
        <div>
          <h4 style="color: var(--primary-green); margin-bottom: 1rem;">
            <i class="fas fa-chart-line"></i> Resumen del Mercado
          </h4>
          <div class="grid grid-2" style="gap: 1rem;">
            <div style="text-align: center; padding: 1rem; background: white; border-radius: 10px;">
              <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-green);">+12.5%</div>
              <small>Crecimiento mensual</small>
            </div>
            <div style="text-align: center; padding: 1rem; background: white; border-radius: 10px;">
              <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-green);">847</div>
              <small>Transacciones hoy</small>
            </div>
          </div>
        </div>
        <div>
          <h4 style="color: var(--primary-green); margin-bottom: 1rem;">
            <i class="fas fa-thermometer-half"></i> Condiciones del Sector
          </h4>
          <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <span style="color: var(--accent-green); font-size: 1.2rem;"><i class="fas fa-circle"></i></span>
            <span>Demanda: <strong>Alta</strong></span>
          </div>
          <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <span style="color: #ffc107; font-size: 1.2rem;"><i class="fas fa-circle"></i></span>
            <span>Oferta: <strong>Moderada</strong></span>
          </div>
          <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="color: var(--accent-green); font-size: 1.2rem;"><i class="fas fa-circle"></i></span>
            <span>Perspectiva: <strong>Positiva</strong></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Noticias del Sector -->
<section class="section" style="background: var(--bg-light);">
  <div class="container">
    <h2 class="section-title">
      <i class="fas fa-newspaper"></i> Noticias del Sector Ganadero
    </h2>
    <p class="section-subtitle">Mantente informado sobre las últimas tendencias y desarrollos</p>
    
    <div class="grid grid-3">
      <div class="info-card">
        <div style="height: 150px; background: linear-gradient(135deg, var(--accent-green), var(--light-green)); border-radius: 10px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center;">
          <i class="fas fa-leaf" style="font-size: 3rem; color: white;"></i>
        </div>
        <h4 style="color: var(--primary-green);">Ganadería Sostenible 2025</h4>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
          Nuevas técnicas de pastoreo rotacional incrementan la productividad hasta un 35% mientras mejoran la salud del suelo.
        </p>
        <small style="color: var(--muted);">
          <i class="fas fa-clock"></i> Hace 2 días
        </small>
      </div>
      
      <div class="info-card">
        <div style="height: 150px; background: linear-gradient(135deg, #ff6b6b, #ee5a24); border-radius: 10px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center;">
          <i class="fas fa-chart-line" style="font-size: 3rem; color: white;"></i>
        </div>
        <h4 style="color: var(--primary-green);">Récord en Exportaciones</h4>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
          Colombia bate récord en exportación de carne bovina con 125,000 toneladas en lo que va del año.
        </p>
        <small style="color: var(--muted);">
          <i class="fas fa-clock"></i> Hace 5 días
        </small>
      </div>
      
      <div class="info-card">
        <div style="height: 150px; background: linear-gradient(135deg, #3742fa, #2f3542); border-radius: 10px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center;">
          <i class="fas fa-dna" style="font-size: 3rem; color: white;"></i>
        </div>
        <h4 style="color: var(--primary-green);">Avances Genéticos</h4>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
          Nuevos marcadores genéticos prometen mejorar la resistencia a enfermedades en un 60%.
        </p>
        <small style="color: var(--muted);">
          <i class="fas fa-clock"></i> Hace 1 semana
        </small>
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
      <a class="btn" href="catalogo.php" style="background: white; color: var(--primary-green); padding: 1rem 2rem; font-size: 1.1rem;">
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
        <div style="margin-top: 1rem;">
          <i class="fab fa-facebook" style="margin-right: 1rem; font-size: 1.5rem;"></i>
          <i class="fab fa-instagram" style="margin-right: 1rem; font-size: 1.5rem;"></i>
          <i class="fab fa-twitter" style="margin-right: 1rem; font-size: 1.5rem;"></i>
        </div>
      </div>
      <div>
        <h4>Contacto</h4>
        <p><i class="fas fa-phone"></i> +57 300 123 4567</p>
        <p><i class="fas fa-envelope"></i> info@agrogan.com</p>
        <p><i class="fas fa-map-marker-alt"></i> Medellín, Colombia</p>
      </div>
      <div>
        <h4>Enlaces Útiles</h4>
        <p><a href="#" style="color: white; text-decoration: none;"><i class="fas fa-question-circle"></i> Centro de Ayuda</a></p>
        <p><a href="#" style="color: white; text-decoration: none;"><i class="fas fa-file-contract"></i> Términos y Condiciones</a></p>
        <p><a href="#" style="color: white; text-decoration: none;"><i class="fas fa-shield-alt"></i> Política de Privacidad</a></p>
      </div>
    </div>
    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #444;">
      © 2025 AgroGan. Todos los derechos reservados.
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
</script>

</body></html>
