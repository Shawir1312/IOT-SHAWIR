<?php
/**
 * ShawirIOT - Email Verification Page
 * Handles dual mode verification: 1-click token link or 6-digit OTP code input
 */

require_once __DIR__ . '/includes/auth.php';

$platformName = getSetting('platform_name', 'ShawirIOT');
$error = '';
$success = '';
$verifiedSuccess = false;

// 1. AUTO-VERIFY VIA TOKEN IN QUERY STRING (Link dari email)
$urlToken = trim($_GET['token'] ?? '');
if (!empty($urlToken)) {
    $res = verifyEmailToken($urlToken);
    if ($res['success']) {
        $verifiedSuccess = true;
        $success = $res['message'];
    } else {
        $error = $res['message'];
    }
}

// 2. GET TARGET EMAIL
$targetEmail = strtolower(trim($_GET['email'] ?? ($_SESSION['last_verification_email'] ?? '')));
if (empty($targetEmail) && isLoggedIn()) {
    $curr = currentUser();
    if ($curr) $targetEmail = strtolower(trim($curr['email']));
}

// 3. HANDLE POST REQUESTS (Submit OTP atau Kirim Ulang)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$verifiedSuccess) {
    verifyCsrf();
    $action = $_POST['action'] ?? 'verify_otp';

    if ($action === 'verify_otp') {
        $email   = strtolower(trim($_POST['email'] ?? $targetEmail));
        $otpCode = trim($_POST['otp_code'] ?? '');

        // Jika form memakai 6 input individual (digit-1 s/d digit-6)
        if (empty($otpCode) && isset($_POST['digit_1'])) {
            $otpCode = '';
            for ($i = 1; $i <= 6; $i++) {
                $otpCode .= trim($_POST["digit_{$i}"] ?? '');
            }
        }

        $res = verifyEmailOtp($email, $otpCode);
        if ($res['success']) {
            $verifiedSuccess = true;
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    } elseif ($action === 'resend') {
        $email = strtolower(trim($_POST['email'] ?? $targetEmail));
        $res = resendVerification($email);
        if ($res['success']) {
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    }
}

// Check if already verified
if (isLoggedIn() && empty($error)) {
    $curr = currentUser();
    if ($curr && !empty($curr['email_verified_at']) && !$verifiedSuccess) {
        flash('info', 'Email Anda sudah terverifikasi.');
        redirect(PLATFORM_URL . '/dashboard.php');
    }
}

// Dev fallback OTP (untuk pengujian lokal jika SMTP belum diisi)
$devFallbackOtp = '';
$smtpEnabled = getSetting('smtp_enabled', '0') === '1';
if (!$smtpEnabled && isset($_SESSION['dev_otp_fallback'])) {
    $devFallbackOtp = $_SESSION['dev_otp_fallback'];
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Verifikasi Email — <?= $platformName ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="assets/js/theme.js"></script>
  <style>
    .otp-inputs {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      margin: 1.5rem 0;
    }
    .otp-digit {
      width: 48px;
      height: 56px;
      text-align: center;
      font-size: 1.5rem;
      font-weight: 700;
      font-family: var(--font-mono, monospace);
      border-radius: var(--radius-md);
      background: var(--bg-surface);
      border: 2px solid var(--border-light);
      color: var(--text-primary);
      transition: all 0.2s ease;
      outline: none;
    }
    .otp-digit:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
      transform: translateY(-2px);
    }
    .otp-digit.filled {
      border-color: var(--primary-light);
      background: rgba(99, 102, 241, 0.05);
    }
    .success-badge {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(16, 185, 129, 0.15);
      border: 2px solid #10b981;
      color: #10b981;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin: 0 auto 1.25rem;
      animation: scaleIn 0.3s ease;
    }
    @keyframes scaleIn {
      from { transform: scale(0.6); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    .dev-hint-box {
      background: rgba(245, 158, 11, 0.1);
      border: 1px dashed #f59e0b;
      border-radius: var(--radius-md);
      padding: 0.75rem 1rem;
      margin-bottom: 1.25rem;
      font-size: 0.82rem;
      color: #fbbf24;
      text-align: left;
    }
  </style>
</head>
<body>
<div class="auth-page">
  <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema (Terang / Gelap)" style="position:absolute;top:1.25rem;right:1.25rem;z-index:10">
    <i class="fas fa-moon theme-toggle-icon"></i>
  </button>

  <div class="auth-card" style="max-width:440px">
    <div class="auth-logo" style="text-align:center;margin-bottom:1.5rem">
      <img src="assets/img/logo.png" alt="<?= $platformName ?>" style="max-height:80px;max-width:240px;width:100%;margin:0 auto 0.5rem;display:block;object-fit:contain">
    </div>

    <?php if ($verifiedSuccess): ?>
      <!-- TAMPILAN BERHASIL VERIFIKASI -->
      <div style="text-align:center;padding:1rem 0">
        <div class="success-badge">
          <i class="fas fa-check"></i>
        </div>
        <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:0.5rem;color:var(--text-primary)">
          Email Terverifikasi!
        </h2>
        <p style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:1.75rem;line-height:1.5">
          <?= sanitize($success) ?>
        </p>
        <a href="dashboard.php" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-arrow-right"></i> Masuk ke Dashboard
        </a>
      </div>

    <?php else: ?>
      <!-- FORM INPUT KODE OTP / KIRIM ULANG -->
      <div style="text-align:center;margin-bottom:1.25rem">
        <h2 style="font-size:1.3rem;font-weight:700;color:var(--text-primary);margin-bottom:0.35rem">
          Verifikasi Email Anda
        </h2>
        <p style="font-size:0.875rem;color:var(--text-secondary);line-height:1.5;margin:0">
          Masukkan 6 digit kode verifikasi yang telah dikirim ke:<br>
          <strong style="color:var(--primary-light)"><?= sanitize($targetEmail ?: 'alamat email Anda') ?></strong>
        </p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:1rem">
          <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success && !$verifiedSuccess): ?>
        <div class="alert alert-success" style="margin-bottom:1rem">
          <i class="fas fa-check-circle"></i> <?= sanitize($success) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($devFallbackOtp)): ?>
        <div class="dev-hint-box">
          <div style="font-weight:700;margin-bottom:2px"><i class="fas fa-info-circle"></i> Mode Pengujian (SMTP Belum Aktif):</div>
          Kode OTP Anda adalah: <strong style="font-size:1.1rem;letter-spacing:2px;color:#fff"><?= $devFallbackOtp ?></strong>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="otp-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="verify_otp">
        <input type="hidden" name="email" value="<?= sanitize($targetEmail) ?>">

        <div class="otp-inputs">
          <input type="text" class="otp-digit" name="digit_1" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" autofocus required>
          <input type="text" class="otp-digit" name="digit_2" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" class="otp-digit" name="digit_3" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" class="otp-digit" name="digit_4" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" class="otp-digit" name="digit_5" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" class="otp-digit" name="digit_6" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="btn-verify">
          <i class="fas fa-shield-alt"></i> Verifikasi Akun
        </button>
      </form>

      <!-- FORM KIRIM ULANG KODE OTP -->
      <div style="margin-top:1.5rem;text-align:center">
        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.5rem">
          Tidak menerima email atau kode kadaluarsa?
        </p>
        <form method="POST" action="" style="display:inline">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="resend">
          <input type="hidden" name="email" value="<?= sanitize($targetEmail) ?>">
          <button type="submit" class="btn btn-secondary btn-sm" id="btn-resend">
            <i class="fas fa-redo"></i> <span id="resend-text">Kirim Ulang Kode</span>
          </button>
        </form>
      </div>

      <hr class="divider">
      <div style="text-align:center;font-size:0.85rem">
        <a href="login.php" style="color:var(--text-muted);text-decoration:none">
          <i class="fas fa-arrow-left"></i> Kembali ke Halaman Masuk
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Auto-focus and paste handler for 6-digit OTP
const inputs = document.querySelectorAll('.otp-digit');
inputs.forEach((input, index) => {
  input.addEventListener('input', (e) => {
    const val = e.target.value;
    if (val.length > 0) {
      input.classList.add('filled');
      if (index < inputs.length - 1) {
        inputs[index + 1].focus();
      }
    } else {
      input.classList.remove('filled');
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !input.value && index > 0) {
      inputs[index - 1].focus();
      inputs[index - 1].value = '';
      inputs[index - 1].classList.remove('filled');
    }
  });

  // Handle Paste
  input.addEventListener('paste', (e) => {
    e.preventDefault();
    const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim();
    if (/^\d{6}$/.test(pasteData)) {
      pasteData.split('').forEach((char, i) => {
        if (inputs[i]) {
          inputs[i].value = char;
          inputs[i].classList.add('filled');
        }
      });
      inputs[5].focus();
      // Auto submit after paste
      setTimeout(() => {
        document.getElementById('otp-form').submit();
      }, 200);
    }
  });
});

// Cooldown timer for resend
const resendBtn = document.getElementById('btn-resend');
const resendText = document.getElementById('resend-text');
if (resendBtn && resendText) {
  let cooldown = 60;
  // If user just clicked or on load, check local cooldown
  const lastResendTime = localStorage.getItem('last_resend_time');
  if (lastResendTime) {
    const elapsed = Math.floor((Date.now() - parseInt(lastResendTime)) / 1000);
    if (elapsed < 60) {
      startCooldown(60 - elapsed);
    }
  }

  resendBtn.addEventListener('click', () => {
    localStorage.setItem('last_resend_time', Date.now());
  });

  function startCooldown(seconds) {
    resendBtn.disabled = true;
    let s = seconds;
    resendText.textContent = `Tunggu (${s}s)`;
    const timer = setInterval(() => {
      s--;
      if (s <= 0) {
        clearInterval(timer);
        resendBtn.disabled = false;
        resendText.textContent = 'Kirim Ulang Kode';
        localStorage.removeItem('last_resend_time');
      } else {
        resendText.textContent = `Tunggu (${s}s)`;
      }
    }, 1000);
  }
}
</script>
</body>
</html>
