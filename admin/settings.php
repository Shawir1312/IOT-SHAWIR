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

    // SAVE SMTP & VERIFICATION SETTINGS
    if ($action === 'save_smtp') {
        setSetting('require_email_verification', isset($_POST['require_email_verification']) ? '1' : '0');
        setSetting('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
        setSetting('smtp_host', sanitize($_POST['smtp_host'] ?? 'smtp.gmail.com'));
        setSetting('smtp_port', sanitize($_POST['smtp_port'] ?? '465'));
        setSetting('smtp_user', sanitize($_POST['smtp_user'] ?? ''));
        if (!empty($_POST['smtp_pass'])) {
            setSetting('smtp_pass', $_POST['smtp_pass']);
        }
        setSetting('smtp_crypto', sanitize($_POST['smtp_crypto'] ?? 'ssl'));
        setSetting('smtp_from_email', sanitize($_POST['smtp_from_email'] ?? ''));
        setSetting('smtp_from_name', sanitize($_POST['smtp_from_name'] ?? 'ShawirIOT Platform'));

        flash('success', 'Pengaturan SMTP & Verifikasi Email berhasil disimpan.');
        redirect('settings.php');
    }

    // TEST SEND EMAIL VIA SMTP
    if ($action === 'test_smtp') {
        $testEmail = strtolower(trim($_POST['test_email'] ?? ''));
        if (!validateEmail($testEmail)) {
            flash('error', 'Alamat email pengujian tidak valid.');
            redirect('settings.php');
        }

        require_once __DIR__ . '/../includes/mailer.php';
        $testSubj = "Uji Coba Pengiriman Email SMTP — " . getSetting('platform_name', 'ShawirIOT');
        $testHtml = "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;padding:24px;background:#0f172a;color:#f8fafc;border-radius:12px;max-width:520px;margin:0 auto;'>"
            . "<h2 style='color:#38bdf8;margin-top:0'>Koneksi SMTP Sukses! 🎉</h2>"
            . "<p style='color:#cbd5e1;line-height:1.6'>Halo! Email ini mengonfirmasi bahwa konfigurasi server SMTP di <strong>" . htmlspecialchars(getSetting('platform_name', 'ShawirIOT')) . "</strong> telah berhasil terhubung dan siap mengirimkan kode verifikasi serta notifikasi sistem ke pengguna.</p>"
            . "<div style='background:#1e293b;padding:12px 16px;border-radius:8px;font-size:13px;color:#94a3b8;margin:16px 0;'>"
            . "<div><strong>Host:</strong> " . htmlspecialchars(getSetting('smtp_host', '')) . ":" . htmlspecialchars(getSetting('smtp_port', '')) . "</div>"
            . "<div><strong>Pengirim:</strong> " . htmlspecialchars(getSetting('smtp_from_email', '')) . "</div>"
            . "<div><strong>Waktu:</strong> " . date('d M Y H:i:s T') . "</div>"
            . "</div>"
            . "<p style='font-size:12px;color:#64748b;margin-bottom:0'>Email pengujian otomatis dari ShawirIOT Platform.</p>"
            . "</div>";

        $res = sendEmail($testEmail, $testSubj, $testHtml);
        if ($res['success']) {
            flash('success', '✓ Sukses! Email uji coba berhasil dikirim ke ' . htmlspecialchars($testEmail) . '. Periksa kotak masuk atau spam.');
        } else {
            flash('error', 'Gagal mengirim email uji coba: ' . $res['message']);
        }
        redirect('settings.php');
    }
}

$platformName = getSetting('platform_name', 'ShawirIOT');
$tagline      = getSetting('platform_tagline', 'Platform IoT Modern');
$email        = getSetting('platform_email', 'admin@shawiriot.com');
$allowReg     = getSetting('allow_registration', '1') === '1';
$wsPort       = getSetting('websocket_port', '8080');
$retention    = getSetting('data_retention_days', '365');

// SMTP Settings
$requireVerify = getSetting('require_email_verification', '1') === '1';
$smtpEnabled   = getSetting('smtp_enabled', '0') === '1';
$smtpHost      = getSetting('smtp_host', 'smtp.gmail.com');
$smtpPort      = getSetting('smtp_port', '465');
$smtpUser      = getSetting('smtp_user', '');
$smtpPassSet   = !empty(getSetting('smtp_pass', ''));
$smtpCrypto    = getSetting('smtp_crypto', 'ssl');
$smtpFromEmail = getSetting('smtp_from_email', $email);
$smtpFromName  = getSetting('smtp_from_name', $platformName);

$plans = DB::rows("SELECT * FROM plans ORDER BY credits_required ASC");
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Pengaturan Platform — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="../assets/js/theme.js"></script>
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

      <!-- SMTP & EMAIL VERIFICATION SETTINGS -->
      <div class="card mb-3">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
          <div>
            <h3 class="card-title"><i class="fas fa-envelope-open-text" style="color:#38bdf8"></i> Pengaturan SMTP & Verifikasi Email</h3>
            <span style="font-size:0.8rem;color:var(--text-muted)">Konfigurasi server email untuk pengiriman kode verifikasi pendaftaran (OTP & link 1-klik)</span>
          </div>
          <div>
            <span class="badge" style="background:<?= $smtpEnabled ? 'rgba(16,185,129,0.15);color:#10b981;border:1px solid #10b981' : 'rgba(239,68,68,0.15);color:#ef4444;border:1px solid #ef4444' ?>;padding:0.35rem 0.75rem;border-radius:99px;font-size:0.75rem;font-weight:700">
              <i class="fas fa-<?= $smtpEnabled ? 'check-circle' : 'times-circle' ?>"></i> <?= $smtpEnabled ? 'SMTP Aktif' : 'SMTP Nonaktif' ?>
            </span>
          </div>
        </div>

        <form method="POST" action="" style="margin-bottom:1.5rem">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save_smtp">

          <div style="background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.2);border-radius:var(--radius-md);padding:0.85rem 1.25rem;margin-bottom:1.25rem">
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem">
              <input type="checkbox" name="require_email_verification" id="req_verify" value="1" <?= $requireVerify ? 'checked' : '' ?>
                style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer">
              <label for="req_verify" style="font-size:0.9rem;font-weight:700;color:var(--text-primary);cursor:pointer;margin:0">
                Wajibkan Verifikasi Email untuk Pengguna Baru
              </label>
            </div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-left:1.7rem">
              Jika dicentang, pengguna baru harus memverifikasi email melalui kode OTP 6-digit atau tautan email sebelum dapat masuk.
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.25rem">
            <input type="checkbox" name="smtp_enabled" id="smtp_on" value="1" <?= $smtpEnabled ? 'checked' : '' ?>
              style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer">
            <label for="smtp_on" style="font-size:0.9rem;font-weight:700;color:var(--text-primary);cursor:pointer;margin:0">
              Aktifkan Pengiriman Email via Server SMTP
            </label>
          </div>

          <div class="grid-2col">
            <div class="form-group">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" value="<?= sanitize($smtpHost) ?>" placeholder="smtp.gmail.com" required>
              <div class="form-hint">Contoh: <code>smtp.gmail.com</code> (Gmail) atau <code>smtp-relay.brevo.com</code></div>
            </div>

            <div class="form-group">
              <label class="form-label">SMTP Port</label>
              <input type="number" name="smtp_port" class="form-control" value="<?= sanitize($smtpPort) ?>" placeholder="465" required>
              <div class="form-hint">Port <strong>465</strong> untuk SSL, atau <strong>587</strong> untuk TLS</div>
            </div>

            <div class="form-group">
              <label class="form-label">Tipe Enkripsi</label>
              <select name="smtp_crypto" class="form-control">
                <option value="ssl" <?= $smtpCrypto === 'ssl' ? 'selected' : '' ?>>SSL (Port 465 - Disarankan untuk Gmail)</option>
                <option value="tls" <?= $smtpCrypto === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (Port 587)</option>
                <option value="none" <?= $smtpCrypto === 'none' ? 'selected' : '' ?>>Tanpa Enkripsi (Port 25)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">SMTP Username / Email</label>
              <input type="text" name="smtp_user" class="form-control" value="<?= sanitize($smtpUser) ?>" placeholder="emailanda@gmail.com">
              <div class="form-hint">Alamat email lengkap Anda</div>
            </div>

            <div class="form-group">
              <label class="form-label">
                SMTP Password / Sandi Aplikasi
                <?= $smtpPassSet ? '<span style="color:#10b981;font-size:0.75rem;font-weight:normal">(Sandi tersimpan)</span>' : '' ?>
              </label>
              <input type="password" name="smtp_pass" class="form-control" placeholder="<?= $smtpPassSet ? '•••••••••••••••• (Kosongkan jika tidak diubah)' : 'Masukkan sandi aplikasi' ?>" autocomplete="new-password">
              <div class="form-hint">Untuk Gmail: gunakan 16-huruf <strong>Sandi Aplikasi (App Password)</strong></div>
            </div>

            <div class="form-group">
              <label class="form-label">Email Pengirim (From Email)</label>
              <input type="email" name="smtp_from_email" class="form-control" value="<?= sanitize($smtpFromEmail) ?>" placeholder="admin@shawiriot.com">
              <div class="form-hint">Alamat yang tertera sebagai pengirim email</div>
            </div>

            <div class="form-group">
              <label class="form-label">Nama Pengirim (From Name)</label>
              <input type="text" name="smtp_from_name" class="form-control" value="<?= sanitize($smtpFromName) ?>" placeholder="ShawirIOT Platform">
              <div class="form-hint">Nama brand atau sistem yang muncul di inbox penerima</div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #0284c7 0%, #0369a1 100%)">
            <i class="fas fa-save"></i> Simpan Pengaturan SMTP
          </button>
        </form>

        <hr class="divider">

        <!-- TEST EMAIL SENDING -->
        <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-light);border-radius:var(--radius-md);padding:1.25rem">
          <h4 style="font-size:0.95rem;font-weight:700;color:var(--text-primary);margin-bottom:0.4rem">
            <i class="fas fa-paper-plane" style="color:var(--primary-light)"></i> Uji Coba Koneksi Pengiriman Email
          </h4>
          <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem">
            Kirimkan satu email uji coba untuk memastikan server SMTP terhubung dengan baik ke inbox Anda sebelum digunakan pengguna.
          </p>
          <form method="POST" action="" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="test_smtp">
            <input type="email" name="test_email" class="form-control" placeholder="Masukkan email penerima uji coba" style="max-width:320px;flex:1" required>
            <button type="submit" class="btn btn-secondary">
              <i class="fas fa-paper-plane"></i> Kirim Email Tes
            </button>
          </form>
        </div>
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
