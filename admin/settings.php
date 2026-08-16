<?php
/**
 * ShawirIOT - Admin Platform Settings & Plan Configuration
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$admin = currentUser();
$flash = getFlash();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // PLATFORM GENERAL SETTINGS
    if ($action === 'save_general') {
        setSetting('platform_name', sanitize($_POST['platform_name'] ?? 'ShawirIOT'));
        setSetting('platform_tagline', sanitize($_POST['platform_tagline'] ?? 'Platform IoT Modern'));
        setSetting('platform_email', sanitize($_POST['platform_email'] ?? 'admin@shawiriot.com'));
        setSetting('allow_registration', isset($_POST['allow_registration']) ? '1' : '0');
        setSetting('websocket_port', sanitize($_POST['websocket_port'] ?? '8080'));
        setSetting('data_retention_days', sanitize($_POST['data_retention_days'] ?? '365'));

        flash('success', 'Pengaturan platform berhasil disimpan.');
        redirect('settings.php');
    }

    // UPDATE PLANS
    if ($action === 'update_plan') {
        $planId      = (int)($_POST['plan_id'] ?? 0);
        $creditsReq  = (int)($_POST['credits_required'] ?? 0);
        $maxDevices  = (int)($_POST['max_devices'] ?? 1);
        $maxWidgets  = (int)($_POST['max_widgets_per_device'] ?? 5);
        $histDays    = (int)($_POST['history_days'] ?? 1);
        $desc        = sanitize($_POST['description'] ?? '');

        DB::query(
            "UPDATE plans SET credits_required = ?, max_devices = ?, max_widgets_per_device = ?, history_days = ?, description = ? WHERE id = ?",
            [$creditsReq, $maxDevices, $maxWidgets, $histDays, $desc, $planId]
        );
        flash('success', 'Pengaturan paket berhasil disimpan.');
        redirect('settings.php');
    }
}

$platformName = getSetting('platform_name', 'ShawirIOT');
$tagline      = getSetting('platform_tagline', 'Platform IoT Modern');
$email        = getSetting('platform_email', 'admin@shawiriot.com');
$allowReg     = getSetting('allow_registration', '1') === '1';
$wsPort       = getSetting('websocket_port', '8080');
$retention    = getSetting('data_retention_days', '365');

$plans = DB::rows("SELECT * FROM plans ORDER BY credits_required ASC");
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan — <?= $platformName ?> Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-layout">
<div class="app-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main-content">
    <header class="topbar admin-topbar">
      <div class="topbar-left">
        <button type="button" class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title"><i class="fas fa-cog" style="color:#fb923c;margin-right:0.4rem"></i>Pengaturan Platform</h1>
      </div>
    </header>

    <main class="page-content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
          <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
          <?= sanitize($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- GENERAL SETTINGS -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-sliders-h"></i> Pengaturan Umum</h3>
        </div>
        <form method="POST" action="">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_general">

          <div class="grid-2col">
            <div class="form-group">
              <label class="form-label">Nama Platform</label>
              <input type="text" name="platform_name" class="form-control" value="<?= sanitize($platformName) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Tagline Platform</label>
              <input type="text" name="platform_tagline" class="form-control" value="<?= sanitize($tagline) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email Kontak Admin</label>
              <input type="email" name="platform_email" class="form-control" value="<?= sanitize($email) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Port WebSocket Server</label>
              <input type="number" name="websocket_port" class="form-control" value="<?= sanitize($wsPort) ?>">
              <div class="form-hint">Port untuk daemon WebSocket (default: 8080)</div>
            </div>
          </div>

          <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem">
            <input type="checkbox" name="allow_registration" id="allow_reg" value="1" <?= $allowReg ? 'checked' : '' ?>
              style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer">
            <label for="allow_reg" style="font-size:0.875rem;cursor:pointer;margin:0">Izinkan Registrasi Publik Terbuka</label>
          </div>

          <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #f97316 0%, #ea580c 100%)">
            <i class="fas fa-save"></i> Simpan Pengaturan
          </button>
        </form>
      </div>

      <!-- SUBSCRIPTION PLANS CONFIGURATION -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-layer-group"></i> Konfigurasi Paket & Harga Kredit</h3>
          <span style="font-size:0.8rem;color:var(--text-muted)">Ubah batas device, widget, dan kebutuhan kredit untuk setiap paket</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.5rem">
          <?php foreach ($plans as $p): ?>
            <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-light);border-radius:var(--radius-md);padding:1.25rem">
              <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_plan">
                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
                  <h4 style="color:var(--primary-light);font-size:1.1rem">
                    Paket: <?= sanitize($p['name']) ?>
                  </h4>
                  <span class="plan-badge plan-<?= strtolower($p['name']) ?>"><?= sanitize($p['name']) ?></span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:0.75rem;margin-bottom:1rem">
                  <div class="form-group" style="margin:0">
                    <label class="form-label">Kredit Diperlukan</label>
                    <input type="number" name="credits_required" class="form-control" value="<?= $p['credits_required'] ?>" min="0" required>
                  </div>
                  <div class="form-group" style="margin:0">
                    <label class="form-label">Maks. Device</label>
                    <input type="number" name="max_devices" class="form-control" value="<?= $p['max_devices'] ?>" min="1" required>
                  </div>
                  <div class="form-group" style="margin:0">
                    <label class="form-label">Maks. Widget/Device</label>
                    <input type="number" name="max_widgets_per_device" class="form-control" value="<?= $p['max_widgets_per_device'] ?>" min="1" required>
                  </div>
                  <div class="form-group" style="margin:0">
                    <label class="form-label">Histori (Hari)</label>
                    <input type="number" name="history_days" class="form-control" value="<?= $p['history_days'] ?>" min="1" required>
                  </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem">
                  <label class="form-label">Deskripsi Paket</label>
                  <input type="text" name="description" class="form-control" value="<?= sanitize($p['description']) ?>">
                </div>

                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-save"></i> Simpan Paket <?= sanitize($p['name']) ?></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </main>
  </div>
</div>
</body>
</html>
