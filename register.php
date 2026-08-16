<?php
/**
 * ShawirIOT - Register Page
 */
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) redirect(PLATFORM_URL . '/dashboard.php');

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['password'] !== ($_POST['password_confirm'] ?? '')) {
        $error = 'Password dan konfirmasi tidak cocok.';
    } else {
        $result = registerUser($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            flash('success', 'Registrasi berhasil! Silakan login.');
            redirect(PLATFORM_URL . '/login.php');
        } else {
            $error = $result['message'];
        }
    }
}

$platformName = getSetting('platform_name', 'ShawirIOT');
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Daftar — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
</head>
<body>
<div class="auth-page">
  <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema (Terang / Gelap)" style="position:absolute;top:1.25rem;right:1.25rem;z-index:10">
    <i class="fas fa-moon theme-toggle-icon"></i>
  </button>
  <div class="auth-card">
    <div class="auth-logo" style="text-align:center;margin-bottom:1.5rem">
      <img src="assets/img/logo.png" alt="<?= $platformName ?>" style="max-height:110px;max-width:320px;width:100%;margin:0 auto 0.75rem;display:block;object-fit:contain;filter:drop-shadow(0 4px 16px rgba(0,0,0,0.25))">
      <p style="font-size:0.92rem;color:var(--text-secondary)">Buat Akun Gratis IoT Anda</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="register-form" novalidate>
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="name">Nama Lengkap</label>
        <div class="input-group">
          <span class="input-prefix"><i class="fas fa-user"></i></span>
          <input type="text" id="name" name="name"
            class="form-control"
            placeholder="Nama Lengkap Anda"
            value="<?= sanitize($_POST['name'] ?? '') ?>"
            required autocomplete="name">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Alamat Email</label>
        <div class="input-group">
          <span class="input-prefix"><i class="fas fa-envelope"></i></span>
          <input type="email" id="email" name="email"
            class="form-control"
            placeholder="nama@email.com"
            value="<?= sanitize($_POST['email'] ?? '') ?>"
            required autocomplete="email">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
          <span class="input-prefix"><i class="fas fa-lock"></i></span>
          <input type="password" id="password" name="password"
            class="form-control"
            placeholder="Minimal 8 karakter"
            required autocomplete="new-password">
          <button type="button" class="btn btn-secondary" id="toggle-pw">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <!-- Password strength bar -->
        <div style="margin-top:0.4rem">
          <div id="pw-strength-bar" style="height:3px;border-radius:99px;background:var(--bg-input);overflow:hidden">
            <div id="pw-strength-fill" style="height:100%;width:0%;transition:all 0.3s ease;border-radius:99px"></div>
          </div>
          <small id="pw-strength-label" style="color:var(--text-muted)">Masukkan password</small>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Konfirmasi Password</label>
        <div class="input-group">
          <span class="input-prefix"><i class="fas fa-lock"></i></span>
          <input type="password" id="password_confirm" name="password_confirm"
            class="form-control"
            placeholder="Ulangi password"
            required autocomplete="new-password">
        </div>
        <small id="pw-match-label" style="color:var(--text-muted);margin-top:0.25rem;display:block"></small>
      </div>

      <div class="form-group" style="display:flex;align-items:flex-start;gap:0.5rem">
        <input type="checkbox" id="agree" name="agree" required
          style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;flex-shrink:0;margin-top:3px">
        <label for="agree" style="font-size:0.82rem;color:var(--text-secondary);cursor:pointer;margin:0">
          Saya setuju dengan <a href="#" style="color:var(--primary-light)">Syarat & Ketentuan</a>
          dan <a href="#" style="color:var(--primary-light)">Kebijakan Privasi</a>
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" id="register-btn">
        <span class="btn-text"><i class="fas fa-user-plus"></i> Buat Akun Gratis</span>
        <span class="btn-loading d-none"><span class="spinner"></span> Memproses...</span>
      </button>
    </form>

    <hr class="divider">
    <p class="text-center" style="font-size:0.875rem;color:var(--text-muted)">
      Sudah punya akun?
      <a href="login.php" style="font-weight:600;color:var(--primary-light)">Masuk Sekarang</a>
    </p>
    <p class="text-center mt-1">
      <a href="index.php" style="font-size:0.8rem;color:var(--text-muted)">
        <i class="fas fa-arrow-left"></i> Kembali ke Beranda
      </a>
    </p>
  </div>
</div>

<script>
// Toggle password
document.getElementById('toggle-pw').addEventListener('click', function() {
  const pw = document.getElementById('password');
  const icon = this.querySelector('i');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  icon.className = pw.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
});

// Password strength
document.getElementById('password').addEventListener('input', function() {
  const val = this.value;
  const bar = document.getElementById('pw-strength-fill');
  const lbl = document.getElementById('pw-strength-label');
  let strength = 0;
  if (val.length >= 8) strength++;
  if (/[A-Z]/.test(val)) strength++;
  if (/[0-9]/.test(val)) strength++;
  if (/[^A-Za-z0-9]/.test(val)) strength++;
  const map = [
    { w: '0%',   c: '', t: 'Masukkan password' },
    { w: '25%',  c: '#ef4444', t: 'Terlalu lemah' },
    { w: '50%',  c: '#f59e0b', t: 'Lemah' },
    { w: '75%',  c: '#06b6d4', t: 'Cukup kuat' },
    { w: '100%', c: '#10b981', t: 'Sangat kuat' }
  ];
  const s = val.length === 0 ? 0 : strength + 1;
  bar.style.width = map[s].w;
  bar.style.background = map[s].c;
  lbl.textContent = map[s].t;
  lbl.style.color = map[s].c || 'var(--text-muted)';
});

// Password match
document.getElementById('password_confirm').addEventListener('input', function() {
  const lbl = document.getElementById('pw-match-label');
  if (this.value === document.getElementById('password').value) {
    lbl.textContent = '✓ Password cocok';
    lbl.style.color = 'var(--success)';
  } else {
    lbl.textContent = '✗ Password tidak cocok';
    lbl.style.color = 'var(--danger)';
  }
});

// Loading state
document.getElementById('register-form').addEventListener('submit', function(e) {
  const btn = document.getElementById('register-btn');
  btn.querySelector('.btn-text').classList.add('d-none');
  btn.querySelector('.btn-loading').classList.remove('d-none');
  btn.disabled = true;
});
</script>
</body>
</html>
