<?php
/**
 * ShawirIOT - Halaman Lupa Sandi & Reset Password
 * Mendukung verifikasi dual-mode: Tautan 1-klik di email atau input 6-digit kode OTP
 */

require_once __DIR__ . '/includes/auth.php';

// Jika pengguna sudah login, langsung alihkan ke dashboard
if (isLoggedIn()) {
    redirect(PLATFORM_URL . '/dashboard.php');
}

$platformName = getSetting('platform_name', 'ShawirIOT');
$error = '';
$warning = '';
$success = '';

// Tentukan state/tahapan halaman: 'request', 'verify_otp', 'set_password'
$stage = 'request';
$resetEmail = strtolower(trim($_GET['email'] ?? ($_SESSION['last_reset_email'] ?? '')));
$resetToken = trim($_GET['token'] ?? ($_SESSION['dev_reset_token'] ?? ''));

// 1. CEK TOKEN DI URL (Jika user klik tautan reset dari email)
if (!empty($resetToken)) {
    $tokenData = verifyPasswordResetToken($resetToken);
    if ($tokenData) {
        $stage = 'set_password';
        $resetEmail = $tokenData['email'];
    } else {
        // Token di URL tidak valid / sudah kedaluwarsa
        if (isset($_GET['token'])) {
            $error = 'Tautan reset sandi tidak valid atau sudah kedaluwarsa (masa berlaku 1 jam). Silakan minta tautan baru.';
            $resetToken = '';
            $stage = 'request';
        }
    }
}

// 2. PROSES FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'request_reset') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $res = requestPasswordReset($email);
        if ($res['success']) {
            $resetEmail = $email;
            $stage = 'verify_otp';
            if (!empty($res['warning'])) {
                $warning = $res['warning'];
            } else {
                $success = $res['message'];
            }
        } else {
            $error = $res['message'];
            $stage = 'request';
        }
    } elseif ($action === 'verify_otp') {
        $email   = strtolower(trim($_POST['email'] ?? $resetEmail));
        $otpCode = trim($_POST['otp_code'] ?? '');

        // Jika input menggunakan 6 kotak digit terpisah
        if (empty($otpCode) && isset($_POST['digit_1'])) {
            $otpCode = '';
            for ($i = 1; $i <= 6; $i++) {
                $otpCode .= trim($_POST["digit_{$i}"] ?? '');
            }
        }

        $tokenData = verifyPasswordResetOtp($email, $otpCode);
        if ($tokenData) {
            $resetToken = $tokenData['token'];
            $resetEmail = $tokenData['email'];
            $stage = 'set_password';
            $success = 'Kode OTP valid! Silakan buat kata sandi baru Anda.';
        } else {
            $error = 'Kode OTP salah atau sudah kedaluwarsa (masa berlaku 1 jam). Periksa kembali email Anda.';
            $stage = 'verify_otp';
        }
    } elseif ($action === 'set_password') {
        $token           = trim($_POST['token'] ?? $resetToken);
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $res = completePasswordReset($token, $password, $passwordConfirm);
        if ($res['success']) {
            flash('success', $res['message']);
            redirect(PLATFORM_URL . '/login.php?reset=success');
        } else {
            $error = $res['message'];
            $stage = 'set_password';
        }
    }
}

// Dev fallback OTP / Token (jika SMTP belum aktif)
$devOtp = '';
$devToken = '';
$smtpEnabled = getSetting('smtp_enabled', '0') === '1';
if (!$smtpEnabled && isset($_SESSION['dev_reset_otp'])) {
    $devOtp   = $_SESSION['dev_reset_otp'];
    $devToken = $_SESSION['dev_reset_token'] ?? '';
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Lupa Kata Sandi — <?= $platformName ?></title>
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
    .dev-hint-box {
      background: rgba(245, 158, 11, 0.1);
      border: 1px dashed #f59e0b;
      border-radius: var(--radius-md);
      padding: 0.85rem 1rem;
      margin-bottom: 1.25rem;
      font-size: 0.82rem;
      color: #fbbf24;
      text-align: left;
      line-height: 1.5;
    }
    .step-indicator {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }
    .step-dot {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--bg-surface);
      border: 2px solid var(--border-light);
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 700;
      transition: all 0.3s;
    }
    .step-dot.active {
      border-color: var(--primary);
      background: var(--primary);
      color: #ffffff;
      box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
    }
    .step-dot.completed {
      border-color: #10b981;
      background: rgba(16, 185, 129, 0.2);
      color: #10b981;
    }
    .step-line {
      flex: 1;
      max-width: 40px;
      height: 2px;
      background: var(--border-light);
    }
    .step-line.active {
      background: var(--primary);
    }
  </style>
</head>
<body>
<div class="auth-page">
  <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" title="Ubah Tema" style="position:absolute;top:1.25rem;right:1.25rem;z-index:10">
    <i class="fas fa-moon theme-toggle-icon"></i>
  </button>

  <div class="auth-card" style="max-width:440px">
    <div class="auth-logo" style="text-align:center;margin-bottom:1.25rem">
      <img src="assets/img/logo.png" alt="<?= $platformName ?>" style="max-height:90px;max-width:280px;width:100%;margin:0 auto 0.75rem;display:block;object-fit:contain;filter:drop-shadow(0 4px 16px rgba(0,0,0,0.25))">
      
      <!-- Indikator 3 Langkah -->
      <div class="step-indicator">
        <div class="step-dot <?= $stage === 'request' ? 'active' : 'completed' ?>">1</div>
        <div class="step-line <?= in_array($stage, ['verify_otp', 'set_password']) ? 'active' : '' ?>"></div>
        <div class="step-dot <?= $stage === 'verify_otp' ? 'active' : ($stage === 'set_password' ? 'completed' : '') ?>">2</div>
        <div class="step-line <?= $stage === 'set_password' ? 'active' : '' ?>"></div>
        <div class="step-dot <?= $stage === 'set_password' ? 'active' : '' ?>">3</div>
      </div>

      <?php if ($stage === 'request'): ?>
        <h2 style="font-size:1.35rem;font-weight:800;color:var(--text-primary);margin:0 0 0.4rem">Lupa Kata Sandi</h2>
        <p style="font-size:0.875rem;color:var(--text-secondary);margin:0">Masukkan email akun Anda untuk mendapatkan kode pemulihan sandi</p>
      <?php elseif ($stage === 'verify_otp'): ?>
        <h2 style="font-size:1.35rem;font-weight:800;color:var(--text-primary);margin:0 0 0.4rem">Verifikasi Kode OTP</h2>
        <p style="font-size:0.875rem;color:var(--text-secondary);margin:0">
          Masukkan 6-digit kode yang dikirim ke <strong style="color:var(--text-primary)"><?= sanitize($resetEmail) ?></strong>
        </p>
      <?php else: ?>
        <h2 style="font-size:1.35rem;font-weight:800;color:var(--text-primary);margin:0 0 0.4rem">Buat Kata Sandi Baru</h2>
        <p style="font-size:0.875rem;color:var(--text-secondary);margin:0">Amankan akun Anda dengan kata sandi baru yang kuat</p>
      <?php endif; ?>
    </div>

    <!-- Alert Notifikasi -->
    <?php if ($error): ?>
      <div class="alert alert-danger" style="font-size:0.875rem">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($warning): ?>
      <div class="alert alert-warning" style="font-size:0.875rem">
        <i class="fas fa-exclamation-triangle"></i> <?= sanitize($warning) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success" style="font-size:0.875rem">
        <i class="fas fa-check-circle"></i> <?= sanitize($success) ?>
      </div>
    <?php endif; ?>

    <!-- Mode Pengembang / Uji Coba Lokal (Jika SMTP belum aktif) -->
    <?php if (!empty($devOtp) && $stage === 'verify_otp'): ?>
      <div class="dev-hint-box">
        <div style="font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:6px">
          <i class="fas fa-flask"></i> Mode Uji Coba (SMTP Server Belum Aktif)
        </div>
        Kode OTP Anda: <strong style="font-size:1.1rem;letter-spacing:2px;color:#fff;background:#374151;padding:2px 8px;border-radius:4px;"><?= $devOtp ?></strong>
        <?php if (!empty($devToken)): ?>
          <div style="margin-top:6px">
            Atau klik tautan langsung: <a href="forgot_password.php?token=<?= urlencode($devToken) ?>" style="color:#60a5fa;font-weight:600;text-decoration:underline">Atur Sandi Tanpa OTP &rarr;</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- TAHAP 1: INPUT ALAMAT EMAIL -->
    <!-- ============================================================ -->
    <?php if ($stage === 'request'): ?>
      <form method="POST" action="" id="request-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="request_reset">

        <div class="form-group">
          <label class="form-label" for="email">Alamat Email Terdaftar</label>
          <div class="input-group">
            <span class="input-prefix"><i class="fas fa-envelope"></i></span>
            <input type="email" id="email" name="email"
              class="form-control"
              placeholder="nama@email.com"
              value="<?= sanitize($resetEmail) ?>"
              required autofocus autocomplete="email">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="submit-request-btn">
          <span class="btn-text"><i class="fas fa-paper-plane"></i> Kirim Kode Pemulihan</span>
          <span class="btn-loading d-none"><span class="spinner"></span> Mengirim...</span>
        </button>
      </form>

    <!-- ============================================================ -->
    <!-- TAHAP 2: INPUT KODE OTP 6-DIGIT -->
    <!-- ============================================================ -->
    <?php elseif ($stage === 'verify_otp'): ?>
      <form method="POST" action="" id="otp-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="verify_otp">
        <input type="hidden" name="email" value="<?= sanitize($resetEmail) ?>">
        <input type="hidden" name="otp_code" id="otp-hidden-input" value="">

        <label class="form-label text-center" style="display:block;margin-bottom:0.25rem">
          Ketik 6 Digit Kode OTP
        </label>

        <div class="otp-inputs" id="otp-container">
          <input type="text" maxlength="1" class="otp-digit" name="digit_1" data-index="1" inputmode="numeric" pattern="[0-9]*" autofocus autocomplete="off">
          <input type="text" maxlength="1" class="otp-digit" name="digit_2" data-index="2" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
          <input type="text" maxlength="1" class="otp-digit" name="digit_3" data-index="3" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
          <input type="text" maxlength="1" class="otp-digit" name="digit_4" data-index="4" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
          <input type="text" maxlength="1" class="otp-digit" name="digit_5" data-index="5" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
          <input type="text" maxlength="1" class="otp-digit" name="digit_6" data-index="6" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="submit-otp-btn">
          <span class="btn-text"><i class="fas fa-shield-alt"></i> Verifikasi OTP & Lanjutkan</span>
          <span class="btn-loading d-none"><span class="spinner"></span> Memeriksa...</span>
        </button>
      </form>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.25rem;font-size:0.82rem">
        <form method="POST" action="" style="display:inline" id="resend-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="request_reset">
          <input type="hidden" name="email" value="<?= sanitize($resetEmail) ?>">
          <button type="submit" id="resend-btn" style="background:none;border:none;color:var(--primary-light);font-weight:600;cursor:pointer;padding:0;font-size:0.82rem;display:flex;align-items:center;gap:4px">
            <i class="fas fa-redo-alt"></i> Kirim Ulang Kode
          </button>
        </form>
        <a href="forgot_password.php" style="color:var(--text-muted);text-decoration:none">
          <i class="fas fa-envelope-open"></i> Ganti Email
        </a>
      </div>

    <!-- ============================================================ -->
    <!-- TAHAP 3: BUAT KATA SANDI BARU -->
    <!-- ============================================================ -->
    <?php elseif ($stage === 'set_password'): ?>
      <form method="POST" action="" id="password-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="set_password">
        <input type="hidden" name="token" value="<?= sanitize($resetToken) ?>">

        <div class="form-group">
          <label class="form-label" for="password">Kata Sandi Baru</label>
          <div class="input-group">
            <span class="input-prefix"><i class="fas fa-lock"></i></span>
            <input type="password" id="password" name="password"
              class="form-control"
              placeholder="Minimal 8 karakter"
              required minlength="8" autofocus autocomplete="new-password">
            <button type="button" class="btn btn-secondary" onclick="togglePasswordVisibility('password', this)" title="Tampilkan/Sembunyikan">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <small class="form-text text-muted" style="font-size:0.75rem;margin-top:4px;display:block">
            Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.
          </small>
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirm">Konfirmasi Kata Sandi Baru</label>
          <div class="input-group">
            <span class="input-prefix"><i class="fas fa-lock"></i></span>
            <input type="password" id="password_confirm" name="password_confirm"
              class="form-control"
              placeholder="Ketik ulang kata sandi baru"
              required minlength="8" autocomplete="new-password">
            <button type="button" class="btn btn-secondary" onclick="togglePasswordVisibility('password_confirm', this)" title="Tampilkan/Sembunyikan">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="submit-pwd-btn">
          <span class="btn-text"><i class="fas fa-check-circle"></i> Simpan Kata Sandi Baru</span>
          <span class="btn-loading d-none"><span class="spinner"></span> Menyimpan...</span>
        </button>
      </form>
    <?php endif; ?>

    <hr class="divider" style="margin:1.5rem 0 1rem">

    <p class="text-center" style="font-size:0.875rem;color:var(--text-muted);margin:0">
      Sudah ingat kata sandi Anda?
      <a href="login.php" style="font-weight:600;color:var(--primary-light)">Kembali ke Masuk</a>
    </p>
  </div>
</div>

<script>
// Toggle Password Visibility
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) {
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    }
  } else {
    input.type = 'password';
    if (icon) {
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
}

// Form Button Loading Spinners
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    if (btn && !btn.disabled) {
      const text = btn.querySelector('.btn-text');
      const load = btn.querySelector('.btn-loading');
      if (text && load) {
        text.classList.add('d-none');
        load.classList.remove('d-none');
      }
    }
  });
});

// Interaktif 6-digit OTP Box
const digitInputs = document.querySelectorAll('.otp-digit');
if (digitInputs.length === 6) {
  digitInputs.forEach((input, idx) => {
    input.addEventListener('input', (e) => {
      const val = e.target.value;
      if (val) {
        input.classList.add('filled');
        if (idx < 5) digitInputs[idx + 1].focus();
      } else {
        input.classList.remove('filled');
      }
      updateFullOtp();
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !input.value && idx > 0) {
        digitInputs[idx - 1].focus();
        digitInputs[idx - 1].value = '';
        digitInputs[idx - 1].classList.remove('filled');
        updateFullOtp();
      }
    });

    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData('text').trim();
      if (/^\d{6}$/.test(paste)) {
        paste.split('').forEach((char, i) => {
          if (digitInputs[i]) {
            digitInputs[i].value = char;
            digitInputs[i].classList.add('filled');
          }
        });
        digitInputs[5].focus();
        updateFullOtp();
      }
    });
  });

  function updateFullOtp() {
    let full = '';
    digitInputs.forEach(i => full += i.value);
    const hidden = document.getElementById('otp-hidden-input');
    if (hidden) hidden.value = full;
    
    // Auto-submit saat semua 6 digit terisi
    if (full.length === 6) {
      const form = document.getElementById('otp-form');
      if (form) form.submit();
    }
  }
}
</script>
</body>
</html>
