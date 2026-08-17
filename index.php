<?php
/**
 * ShawirIOT Platform - Landing Page
 */
if (!file_exists(__DIR__ . '/installed.lock') && file_exists(__DIR__ . '/install.php')) {
    header('Location: install.php');
    exit;
}
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) redirect(PLATFORM_URL . '/dashboard.php');
$platformName = getSetting('platform_name', 'ShawirIOT');
$tagline      = getSetting('platform_tagline', 'Platform IoT Modern');
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title><?= $platformName ?> — Platform IoT Modern</title>
  <meta name="description" content="<?= $platformName ?> adalah platform IoT berbasis web untuk monitoring dan kontrol perangkat mikrokontroler Anda secara real-time.">
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
  <style>
    /* LANDING PAGE SPECIFIC */
    .landing { min-height: 100vh; overflow-x: hidden; }

    /* NAVBAR */
    .navbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      padding: 0.85rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--bg-surface);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border-light);
      transition: all 0.3s ease;
    }

    .navbar.scrolled {
      background: var(--bg-surface);
      box-shadow: var(--shadow-md);
      border-bottom-color: var(--border);
    }

    .navbar-brand {
      display: flex;
      align-items: center;
      text-decoration: none;
    }

    .navbar-brand img {
      height: 42px;
      max-width: 200px;
      object-fit: contain;
    }

    .navbar-links { display: flex; align-items: center; gap: 2rem; }
    .navbar-links a { color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; transition: color var(--transition); }
    .navbar-links a:hover { color: var(--text-primary); }

    .navbar-cta { display: flex; align-items: center; gap: 0.75rem; }

    /* HERO */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 8rem 2rem 4rem;
      position: relative;
    }

    .hero-bg {
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 50% 0%, rgba(99,102,241,0.2) 0%, transparent 60%),
        radial-gradient(ellipse 60% 40% at 80% 80%, rgba(6,182,212,0.1) 0%, transparent 60%);
      pointer-events: none;
    }

    .hero-content { position: relative; max-width: 800px; }

    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(99,102,241,0.12);
      border: 1px solid rgba(99,102,241,0.25);
      border-radius: 99px;
      padding: 0.35rem 1rem;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--primary-light);
      margin-bottom: 1.5rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .hero-title {
      font-size: clamp(2.5rem, 6vw, 4.5rem);
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 1.5rem;
      color: var(--text-primary);
    }

    .hero-title .highlight {
      background: var(--grad-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero-desc {
      font-size: 1.15rem;
      color: var(--text-secondary);
      max-width: 580px;
      margin: 0 auto 2.5rem;
      line-height: 1.7;
    }

    .hero-btns { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 3rem; }

    .hero-stats {
      display: flex;
      gap: 3rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .hero-stat { text-align: center; }
    .hero-stat .hs-num { font-size: 2rem; font-weight: 900; color: var(--text-primary); }
    .hero-stat .hs-lbl { font-size: 0.8rem; color: var(--text-muted); }

    /* FEATURES */
    .section {
      padding: 5rem 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }

    .section-header { text-align: center; margin-bottom: 3.5rem; }
    .section-label {
      display: inline-block;
      background: rgba(99,102,241,0.12);
      color: var(--primary-light);
      border: 1px solid rgba(99,102,241,0.25);
      border-radius: 99px;
      padding: 4px 14px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 1rem;
    }

    .section-title { font-size: 2.25rem; font-weight: 900; margin-bottom: 1rem; }
    .section-desc  { color: var(--text-secondary); font-size: 1rem; max-width: 560px; margin: 0 auto; }

    .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }

    .feature-card {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-xl);
      padding: 2rem;
      transition: all var(--transition);
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--primary), transparent);
      opacity: 0;
      transition: opacity var(--transition);
    }

    .feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--border); }
    .feature-card:hover::before { opacity: 1; }

    .feature-icon {
      width: 52px; height: 52px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      margin-bottom: 1.25rem;
    }

    .feature-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
    .feature-desc  { font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6; }

    /* HOW IT WORKS */
    .how-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; position: relative; }
    .how-grid::before {
      content: '';
      position: absolute;
      top: 30px; left: 15%; right: 15%;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--border), transparent);
    }

    .how-step { text-align: center; position: relative; }
    .how-step-num {
      width: 60px; height: 60px;
      border-radius: 50%;
      background: var(--grad-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      font-weight: 900;
      margin: 0 auto 1rem;
      box-shadow: var(--shadow-glow);
    }
    .how-step h3 { font-size: 1rem; margin-bottom: 0.5rem; }
    .how-step p  { font-size: 0.84rem; color: var(--text-secondary); }

    /* PLANS SECTION */
    .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; }

    .plan-card-landing {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-xl);
      padding: 2rem;
      text-align: center;
      transition: all var(--transition);
      position: relative;
    }

    .plan-card-landing.featured {
      border-color: var(--primary);
      background: linear-gradient(135deg, rgba(99,102,241,0.08) 0%, var(--bg-card) 100%);
    }

    .plan-card-landing.featured::before {
      content: '⭐ Populer';
      position: absolute;
      top: -14px; left: 50%;
      transform: translateX(-50%);
      background: var(--grad-primary);
      color: #fff;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 4px 14px;
      border-radius: 99px;
      white-space: nowrap;
    }

    .plan-card-landing:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
    .plan-name-lg { font-size: 1.1rem; font-weight: 800; margin-bottom: 0.5rem; }
    .plan-price { font-size: 2.5rem; font-weight: 900; color: var(--accent); margin: 0.5rem 0; }
    .plan-price sub { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); }
    .plan-credit { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1.25rem; }
    .plan-feat-list { list-style: none; text-align: left; font-size: 0.82rem; margin-bottom: 1.5rem; }
    .plan-feat-list li { padding: 0.3rem 0; color: var(--text-secondary); }
    .plan-feat-list li::before { content: '✓ '; color: var(--success); font-weight: 700; }

    /* WIDGETS PREVIEW */
    .widgets-preview { display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center; }
    .widget-preview-chip {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: 99px;
      padding: 0.4rem 1rem;
      font-size: 0.82rem;
      color: var(--text-secondary);
      transition: all var(--transition);
    }
    .widget-preview-chip:hover { border-color: var(--border-focus); color: var(--primary-light); }

    /* FOOTER */
    .footer {
      background: var(--bg-surface);
      border-top: 1px solid var(--border-light);
      padding: 3rem 2rem 1.5rem;
    }

    .footer-inner {
      max-width: 1200px;
      margin: 0 auto;
    }

    .footer-top {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 3rem;
      margin-bottom: 2.5rem;
    }

    .footer-brand p { font-size: 0.875rem; color: var(--text-muted); margin-top: 0.75rem; line-height: 1.6; }
    .footer-col h4 { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 0.75rem; }
    .footer-col a { display: block; font-size: 0.85rem; color: var(--text-secondary); padding: 0.2rem 0; transition: color var(--transition); }
    .footer-col a:hover { color: var(--primary-light); }

    .footer-bottom {
      border-top: 1px solid var(--border-light);
      padding-top: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.8rem;
      color: var(--text-muted);
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    /* Animated grid bg */
    .animated-grid {
      position: fixed;
      inset: 0;
      background-image: linear-gradient(rgba(99,102,241,0.03) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(99,102,241,0.03) 1px, transparent 1px);
      background-size: 60px 60px;
      pointer-events: none;
      z-index: 0;
    }

    @media (max-width: 992px) {
      .features-grid { grid-template-columns: repeat(2, 1fr); }
      .plans-grid { grid-template-columns: repeat(2, 1fr); }
      .hero-stats { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
      .navbar { padding: 0.75rem 1rem; }
      .navbar-links { display: none; }
      .hero { padding: 6rem 1rem 3rem; }
      .hero-title { font-size: 2.1rem; }
      .hero-desc { font-size: 0.95rem; }
      .hero-btns { flex-direction: column; width: 100%; gap: 0.75rem; }
      .hero-btns .btn { width: 100%; justify-content: center; }
      .features-grid { grid-template-columns: 1fr; gap: 1rem; }
      .plans-grid { grid-template-columns: 1fr; gap: 1.25rem; }
      .how-grid { grid-template-columns: 1fr; gap: 2rem; }
      .how-grid::before { display: none; }
      .footer-top { grid-template-columns: 1fr; gap: 2rem; }
    }

    @media (max-width: 480px) {
      .hero-stats { grid-template-columns: 1fr; }
      .navbar-cta .btn { padding: 0.35rem 0.65rem; font-size: 0.78rem; }
      .brand-name { font-size: 1.15rem; }
    }
  </style>
</head>
<body class="landing">
<div class="animated-grid"></div>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <a href="index.php" class="navbar-brand" style="display:flex;align-items:center;text-decoration:none">
    <img src="assets/img/logo.png" alt="<?= $platformName ?>" style="height:48px;max-width:210px;object-fit:contain">
  </a>
  <div class="navbar-links">
    <a href="#features">Fitur</a>
    <a href="#how">Cara Kerja</a>
    <a href="#widgets">Widget</a>
    <a href="#plans">Paket</a>
  </div>
  <div class="navbar-cta">
    <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema (Terang / Gelap)">
      <i class="fas fa-moon theme-toggle-icon"></i>
    </button>
    <a href="login.php" class="btn btn-secondary">Masuk</a>
    <a href="register.php" class="btn btn-primary"><i class="fas fa-rocket"></i> Daftar</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-content">
    <div style="margin-bottom:1.5rem">
      <img src="assets/img/logo.png" alt="<?= $platformName ?>" style="max-height:85px;max-width:320px;width:100%;object-fit:contain;filter:drop-shadow(0 6px 20px rgba(0,0,0,0.3))">
    </div>
    <div class="hero-eyebrow"><i class="fas fa-bolt"></i> Platform IoT Next-Gen</div>
    <h1 class="hero-title">
      Kontrol Perangkat IoT<br>
      Anda dengan <span class="highlight"><?= $platformName ?></span>
    </h1>
    <p class="hero-desc">
      Platform monitoring IoT real-time berbasis web. Hubungkan ESP8266, ESP32, atau Arduino,
      buat dashboard kustom, dan pantau data sensor dari mana saja.
    </p>
    <div class="hero-btns">
      <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-play"></i> Mulai Gratis Sekarang</a>
      <a href="#how" class="btn btn-secondary btn-lg"><i class="fas fa-book"></i> Cara Kerja</a>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><div class="hs-num">ESP8266</div><div class="hs-lbl">& ESP32 Ready</div></div>
      <div class="hero-stat"><div class="hs-num">9</div><div class="hs-lbl">Tipe Widget</div></div>
      <div class="hero-stat"><div class="hs-num">Real-time</div><div class="hs-lbl">WebSocket</div></div>
      <div class="hero-stat"><div class="hs-num">100%</div><div class="hs-lbl">Gratis Mulai</div></div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section" id="features">
  <div class="section-header">
    <div class="section-label">Fitur Unggulan</div>
    <h2 class="section-title">Semua yang Anda Butuhkan</h2>
    <p class="section-desc">Dari monitoring sensor hingga kontrol aktuator, <?= $platformName ?> punya semua fitur untuk proyek IoT Anda.</p>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(99,102,241,0.15);color:var(--primary-light)">
        <i class="fas fa-th-large"></i>
      </div>
      <h3 class="feature-title">Dashboard Drag-and-Drop</h3>
      <p class="feature-desc">Buat dashboard kustom dengan widget yang bisa digeser dan diubah ukurannya sesuka hati — persis seperti Blynk.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(6,182,212,0.15);color:var(--secondary)">
        <i class="fas fa-bolt"></i>
      </div>
      <h3 class="feature-title">Real-time WebSocket</h3>
      <p class="feature-desc">Data sensor diperbarui secara instan menggunakan WebSocket — latensi rendah, tampilan selalu terkini.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(16,185,129,0.15);color:var(--success)">
        <i class="fas fa-microchip"></i>
      </div>
      <h3 class="feature-title">Library Arduino Resmi</h3>
      <p class="feature-desc">Library C++ siap pakai untuk ESP8266, ESP32, dan Arduino. Install di Arduino IDE, langsung konek.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(245,158,11,0.15);color:var(--accent)">
        <i class="fas fa-coins"></i>
      </div>
      <h3 class="feature-title">Sistem Kredit Fleksibel</h3>
      <p class="feature-desc">Mulai gratis, upgrade paket dengan kredit untuk lebih banyak device, widget, dan histori data lebih lama.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(239,68,68,0.15);color:var(--danger)">
        <i class="fas fa-shield-alt"></i>
      </div>
      <h3 class="feature-title">Keamanan Terjamin</h3>
      <p class="feature-desc">Setiap device punya token unik terenkripsi. Data Anda aman dengan autentikasi berlapis.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(139,92,246,0.15);color:#a78bfa">
        <i class="fas fa-chart-line"></i>
      </div>
      <h3 class="feature-title">Grafik & Histori Data</h3>
      <p class="feature-desc">Visualisasikan data historis dengan grafik line dan bar interaktif. Export data ke CSV kapan saja.</p>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section style="background:rgba(255,255,255,0.015); padding: 5rem 2rem; border-top:1px solid var(--border-light); border-bottom:1px solid var(--border-light);" id="how">
  <div style="max-width:1000px;margin:0 auto">
    <div class="section-header">
      <div class="section-label">Cara Kerja</div>
      <h2 class="section-title">Mulai dalam 4 Langkah</h2>
    </div>
    <div class="how-grid">
      <div class="how-step">
        <div class="how-step-num">1</div>
        <h3>Daftar Akun</h3>
        <p>Buat akun gratis. Tidak perlu kartu kredit, langsung bisa pakai.</p>
      </div>
      <div class="how-step">
        <div class="how-step-num">2</div>
        <h3>Buat Device</h3>
        <p>Tambah device baru dan salin token unik yang dihasilkan.</p>
      </div>
      <div class="how-step">
        <div class="how-step-num">3</div>
        <h3>Upload Sketch</h3>
        <p>Install library <?= $platformName ?>, masukkan token, upload ke Arduino/ESP.</p>
      </div>
      <div class="how-step">
        <div class="how-step-num">4</div>
        <h3>Monitor & Kontrol</h3>
        <p>Tambah widget di dashboard dan pantau data real-time!</p>
      </div>
    </div>
  </div>
</section>

<!-- WIDGETS -->
<section class="section" id="widgets">
  <div class="section-header">
    <div class="section-label">Widget Library</div>
    <h2 class="section-title">Widget untuk Semua Kebutuhan</h2>
    <p class="section-desc">Pilih dari berbagai widget yang tersedia untuk membangun dashboard IoT impian Anda.</p>
  </div>
  <div class="widgets-preview" style="margin-bottom:2rem">
    <div class="widget-preview-chip"><i class="fas fa-tachometer-alt"></i> Value Display</div>
    <div class="widget-preview-chip"><i class="fas fa-chart-line"></i> Line Chart</div>
    <div class="widget-preview-chip"><i class="fas fa-chart-bar"></i> Bar Chart</div>
    <div class="widget-preview-chip"><i class="fas fa-gauge-high"></i> Gauge</div>
    <div class="widget-preview-chip"><i class="fas fa-toggle-on"></i> Toggle Switch</div>
    <div class="widget-preview-chip"><i class="fas fa-hand-pointer"></i> Button</div>
    <div class="widget-preview-chip"><i class="fas fa-sliders-h"></i> Slider</div>
    <div class="widget-preview-chip"><i class="fas fa-circle-dot"></i> LED Indicator</div>
    <div class="widget-preview-chip"><i class="fas fa-terminal"></i> Terminal</div>
    <div class="widget-preview-chip"><i class="fas fa-map-marker-alt"></i> GPS Map</div>
  </div>
</section>

<!-- PLANS -->
<section style="background:rgba(255,255,255,0.015); padding: 5rem 2rem; border-top:1px solid var(--border-light);" id="plans">
  <div style="max-width:1100px;margin:0 auto">
    <div class="section-header">
      <div class="section-label">Paket & Harga</div>
      <h2 class="section-title">Mulai Gratis, Upgrade Sesuai Kebutuhan</h2>
      <p class="section-desc">Semua paket menggunakan sistem kredit yang dikelola admin.</p>
    </div>
    <div class="plans-grid">
      <div class="plan-card-landing">
        <div class="plan-name-lg">Free</div>
        <div class="plan-price">0 <sub>kredit</sub></div>
        <div class="plan-credit">Gratis selamanya</div>
        <ul class="plan-feat-list">
          <li>1 Device</li>
          <li>5 Widget per Device</li>
          <li>Histori 1 Hari</li>
          <li>API Akses</li>
        </ul>
        <a href="register.php" class="btn btn-secondary btn-block">Mulai Gratis</a>
      </div>
      <div class="plan-card-landing">
        <div class="plan-name-lg">Basic</div>
        <div class="plan-price">100 <sub>kredit</sub></div>
        <div class="plan-credit">Sekali bayar</div>
        <ul class="plan-feat-list">
          <li>5 Device</li>
          <li>20 Widget per Device</li>
          <li>Histori 7 Hari</li>
          <li>Export CSV</li>
        </ul>
        <a href="register.php" class="btn btn-secondary btn-block">Pilih Basic</a>
      </div>
      <div class="plan-card-landing featured">
        <div class="plan-name-lg">Pro</div>
        <div class="plan-price">300 <sub>kredit</sub></div>
        <div class="plan-credit">Sekali bayar</div>
        <ul class="plan-feat-list">
          <li>20 Device</li>
          <li>100 Widget per Device</li>
          <li>Histori 30 Hari</li>
          <li>Priority Support</li>
        </ul>
        <a href="register.php" class="btn btn-primary btn-block">Pilih Pro</a>
      </div>
      <div class="plan-card-landing">
        <div class="plan-name-lg">Enterprise</div>
        <div class="plan-price">1000 <sub>kredit</sub></div>
        <div class="plan-credit">Sekali bayar</div>
        <ul class="plan-feat-list">
          <li>Unlimited Device</li>
          <li>Unlimited Widget</li>
          <li>Histori 1 Tahun</li>
          <li>Dedicated Support</li>
        </ul>
        <a href="register.php" class="btn btn-secondary btn-block">Pilih Enterprise</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-top">
      <div class="footer-brand">
        <div class="navbar-brand" style="margin-bottom:0.75rem">
          <div class="brand-icon" style="width:32px;height:32px;font-size:0.85rem"><i class="fas fa-wifi"></i></div>
          <span class="brand-name"><?= $platformName ?></span>
        </div>
        <p>Platform IoT modern untuk monitoring dan kontrol perangkat mikrokontroler berbasis web.</p>
      </div>
      <div class="footer-col">
        <h4>Platform</h4>
        <a href="#features">Fitur</a>
        <a href="#how">Cara Kerja</a>
        <a href="#plans">Paket</a>
        <a href="register.php">Daftar</a>
      </div>
      <div class="footer-col">
        <h4>Dokumentasi</h4>
        <a href="#">Arduino Library</a>
        <a href="#">REST API</a>
        <a href="#">WebSocket API</a>
        <a href="#">Widget Guide</a>
      </div>
      <div class="footer-col">
        <h4>Akun</h4>
        <a href="login.php">Masuk</a>
        <a href="register.php">Daftar</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="admin/">Admin Panel</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= $platformName ?>. Dibuat dengan ❤️ untuk Indonesia.</span>
      <span>v<?= PLATFORM_VERSION ?></span>
    </div>
  </div>
</footer>

<script>
// Navbar scroll effect
window.addEventListener('scroll', () => {
  const nav = document.getElementById('navbar');
  if (nav) {
    if (window.scrollY > 30) {
      nav.classList.add('scrolled');
    } else {
      nav.classList.remove('scrolled');
    }
  }
});
</script>
</body>
</html>
