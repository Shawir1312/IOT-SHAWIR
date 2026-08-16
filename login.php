<?php
/**
 * ShawirIOT - Login Page
 */
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) redirect(PLATFORM_URL . '/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $result = loginUser(
        $_POST['email'] ?? '',
        $_POST['password'] ?? '',
        !empty($_POST['remember'])
    );
    if ($result['success']) {
        redirect(PLATFORM_URL . '/dashboard.php');
    } else {
        $error = $result['message'];
    }
}

$platformName = getSetting('platform_name', 'ShawirIOT');
$flash = getFlash();
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Masuk — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo" style="text-align:center;margin-bottom:1.5rem">
      <img src="assets/img/logo.png" alt="<?= $platformName ?>" style="max-height:85px;max-width:280px;width:100%;margin:0 auto 0.75rem;display:block;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,0.35))">
      <p style="font-size:0.9rem;color:var(--text-secondary)">Masuk ke Platform IoT Anda</p>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
        <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
        <?= sanitize($flash['message']) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger" id="login-error">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="login-form" novalidate>
      <?= csrfField() ?>

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
        <label class="form-label" for="password">
          Password
          <a href="#" style="float:right;font-size:0.78rem;color:var(--primary-light)">Lupa password?</a>
        </label>
        <div class="input-group">
          <span class="input-prefix"><i class="fas fa-lock"></i></span>
          <input type="password" id="password" name="password"
            class="form-control"
            placeholder="••••••••"
            required autocomplete="current-password">
          <button type="button" class="btn btn-secondary" id="toggle-pw" title="Tampilkan/Sembunyikan">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group" style="display:flex;align-items:center;gap:0.5rem">
        <input type="checkbox" id="remember" name="remember"
          style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer">
        <label for="remember" style="font-size:0.875rem;color:var(--text-secondary);cursor:pointer;margin:0">
          Ingat saya selama 30 hari
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">
        <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Masuk</span>
        <span class="btn-loading d-none"><span class="spinner"></span> Memproses...</span>
      </button>
    </form>

    <hr class="divider">
    <p class="text-center" style="font-size:0.875rem;color:var(--text-muted)">
      Belum punya akun?
      <a href="register.php" style="font-weight:600;color:var(--primary-light)">Daftar Gratis</a>
    </p>
    <p class="text-center mt-1">
      <a href="index.php" style="font-size:0.8rem;color:var(--text-muted)">
        <i class="fas fa-arrow-left"></i> Kembali ke Beranda
      </a>
    </p>
  </div>
</div>

<script>
// Toggle password visibility
document.getElementById('toggle-pw').addEventListener('click', function() {
  const pw = document.getElementById('password');
  const icon = this.querySelector('i');
  if (pw.type === 'password') {
    pw.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    pw.type = 'password';
    icon.className = 'fas fa-eye';
  }
});

// Loading state on submit
document.getElementById('login-form').addEventListener('submit', function(e) {
  const btn = document.getElementById('login-btn');
  btn.querySelector('.btn-text').classList.add('d-none');
  btn.querySelector('.btn-loading').classList.remove('d-none');
  btn.disabled = true;
});
</script>
</body>
</html>
