<?php
/**
 * ShawirIOT Platform - Auth Functions
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

// ============================================================
// AUTH HELPERS
// ============================================================

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin']);
}

function isSuperAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'superadmin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect(PLATFORM_URL . '/login.php');
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        flash('error', 'Akses ditolak.');
        redirect(PLATFORM_URL . '/dashboard.php');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $user = DB::row(
            "SELECT u.*, p.name as plan_name, p.max_devices, p.max_widgets_per_device, p.history_days
             FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ? AND u.is_active = 1",
            [$_SESSION['user_id']]
        );
    }
    return $user;
}

function setUserSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_avatar']= $user['avatar'] ?? null;
}

// ============================================================
// REGISTER
// ============================================================

function registerUser(string $name, string $email, string $password): array {
    $name  = sanitize($name);
    $email = strtolower(trim($email));

    if (empty($name) || strlen($name) < 2) return ['success' => false, 'message' => 'Nama minimal 2 karakter.'];
    if (!validateEmail($email)) return ['success' => false, 'message' => 'Format email tidak valid.'];
    $pwErrors = validatePassword($password);
    if (!empty($pwErrors)) return ['success' => false, 'message' => implode(', ', $pwErrors)];

    if (DB::value("SELECT id FROM users WHERE email = ?", [$email])) {
        return ['success' => false, 'message' => 'Email sudah terdaftar. Silakan login atau reset password.'];
    }

    if (!getSetting('allow_registration', '1')) {
        return ['success' => false, 'message' => 'Registrasi sementara ditutup oleh administrator.'];
    }

    $requireVerify = getSetting('require_email_verification', '1') === '1';
    $verifiedAt = $requireVerify ? null : date('Y-m-d H:i:s');

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_ROUNDS]);
    $userId = DB::insert(
        "INSERT INTO users (name, email, password, role, plan_id, credits, email_verified_at) VALUES (?, ?, ?, 'user', 1, 0, ?)",
        [$name, $email, $hash, $verifiedAt]
    );

    if ($requireVerify) {
        $mailRes = sendVerificationEmail((int)$userId, $name, $email);
        return [
            'success'        => true,
            'require_verify' => true,
            'message'        => 'Pendaftaran berhasil! Kode verifikasi telah dikirim ke email Anda.',
            'user_id'        => $userId,
            'email'          => $email,
            'otp'            => $mailRes['otp'] ?? '',
            'token'          => $mailRes['token'] ?? ''
        ];
    }

    return [
        'success'        => true,
        'require_verify' => false,
        'message'        => 'Registrasi berhasil! Silakan masuk.',
        'user_id'        => $userId
    ];
}

// ============================================================
// LOGIN
// ============================================================

function loginUser(string $email, string $password, bool $remember = false): array {
    $email = strtolower(trim($email));
    if (!validateEmail($email)) return ['success' => false, 'message' => 'Format email tidak valid.'];

    $user = DB::row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Email atau password salah.'];
    }
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Akun Anda dinonaktifkan. Hubungi admin.'];
    }

    // Check email verification status
    $requireVerify = getSetting('require_email_verification', '1') === '1';
    if ($requireVerify && empty($user['email_verified_at']) && $user['role'] === 'user') {
        return [
            'success'    => false,
            'unverified' => true,
            'email'      => $user['email'],
            'message'    => 'Email Anda belum diverifikasi. Silakan periksa kotak masuk atau spam email Anda untuk mengaktifkan akun.'
        ];
    }

    // Update last login
    DB::query("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
        [$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]);

    setUserSession($user);

    if ($remember) {
        $token = generateToken(32);
        DB::query("UPDATE users SET remember_token = ? WHERE id = ?", [hash('sha256', $token), $user['id']]);
        setcookie('remember_token', $token, time() + 30 * 86400, '/', '', false, true);
    }

    return ['success' => true, 'message' => 'Login berhasil!'];
}

// ============================================================
// EMAIL VERIFICATION HELPERS
// ============================================================

/**
 * Verify email using token from URL link
 */
function verifyEmailToken(string $token): array {
    ensureEmailVerificationTable();
    $token = trim($token);
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token verifikasi tidak valid.'];
    }

    $rec = DB::row("SELECT * FROM email_verifications WHERE token = ? AND expires_at > NOW() LIMIT 1", [$token]);
    if (!$rec) {
        return ['success' => false, 'message' => 'Tautan verifikasi tidak valid atau sudah kadaluarsa. Silakan minta tautan baru.'];
    }

    $userId = (int)$rec['user_id'];
    DB::query("UPDATE users SET email_verified_at = NOW(), is_active = 1 WHERE id = ?", [$userId]);
    DB::query("DELETE FROM email_verifications WHERE user_id = ? OR email = ?", [$userId, $rec['email']]);

    $user = DB::row("SELECT * FROM users WHERE id = ?", [$userId]);
    if ($user) {
        setUserSession($user);
    }

    return [
        'success' => true,
        'message' => 'Email Anda berhasil diverifikasi! Selamat datang di platform.',
        'user'    => $user
    ];
}

/**
 * Verify email using 6-digit OTP code
 */
function verifyEmailOtp(string $email, string $otp): array {
    ensureEmailVerificationTable();
    $email = strtolower(trim($email));
    $otp   = trim($otp);

    if (empty($email) || empty($otp)) {
        return ['success' => false, 'message' => 'Email dan kode OTP 6-digit harus diisi.'];
    }

    $rec = DB::row("SELECT * FROM email_verifications WHERE email = ? AND otp_code = ? AND expires_at > NOW() LIMIT 1", [$email, $otp]);
    if (!$rec) {
        return ['success' => false, 'message' => 'Kode OTP salah atau sudah kadaluarsa. Periksa kembali email Anda.'];
    }

    $userId = (int)$rec['user_id'];
    DB::query("UPDATE users SET email_verified_at = NOW(), is_active = 1 WHERE id = ?", [$userId]);
    DB::query("DELETE FROM email_verifications WHERE user_id = ? OR email = ?", [$userId, $email]);

    $user = DB::row("SELECT * FROM users WHERE id = ?", [$userId]);
    if ($user) {
        setUserSession($user);
    }

    return [
        'success' => true,
        'message' => 'Email Anda berhasil diverifikasi! Akun telah aktif.',
        'user'    => $user
    ];
}

/**
 * Resend verification email with rate limit cooldown
 */
function resendVerification(string $email): array {
    ensureEmailVerificationTable();
    $email = strtolower(trim($email));
    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Format email tidak valid.'];
    }

    $user = DB::row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    if (!$user) {
        return ['success' => false, 'message' => 'Akun dengan alamat email ini tidak ditemukan.'];
    }

    if (!empty($user['email_verified_at'])) {
        return ['success' => false, 'message' => 'Email ini sudah terverifikasi sebelumnya. Silakan langsung login.'];
    }

    // Rate limiting: 60 seconds cooldown
    $last = DB::row("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as diff FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1", [$email]);
    if ($last && $last['diff'] !== null && (int)$last['diff'] < 60) {
        $remaining = 60 - (int)$last['diff'];
        return ['success' => false, 'message' => "Mohon tunggu {$remaining} detik sebelum meminta pengiriman ulang."];
    }

    $res = sendVerificationEmail((int)$user['id'], $user['name'], $user['email']);
    if ($res['success']) {
        return ['success' => true, 'message' => 'Kode verifikasi baru telah dikirim ke email Anda. Periksa kotak masuk atau folder spam.'];
    }

    return ['success' => false, 'message' => 'Gagal mengirim email: ' . ($res['message'] ?? 'Periksa server SMTP')];
}

// ============================================================
// PASSWORD RESET HELPERS
// ============================================================

/**
 * Request password reset (Generates Token + OTP and sends email)
 */
function requestPasswordReset(string $email): array {
    ensurePasswordResetTable();
    $email = strtolower(trim($email));

    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Format alamat email tidak valid.'];
    }

    $user = DB::row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
    if (!$user) {
        return ['success' => false, 'message' => 'Alamat email tidak terdaftar dalam sistem.'];
    }

    if (empty($user['is_active'])) {
        return ['success' => false, 'message' => 'Akun dengan email ini dinonaktifkan. Silakan hubungi admin.'];
    }

    // Rate limiting: 60 seconds cooldown
    $last = DB::row("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as diff FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1", [$email]);
    if ($last && $last['diff'] !== null && (int)$last['diff'] < 60) {
        $remaining = 60 - (int)$last['diff'];
        return ['success' => false, 'message' => "Mohon tunggu {$remaining} detik sebelum meminta pengiriman ulang kode."];
    }

    $res = sendPasswordResetEmail((int)$user['id'], $user['name'], $user['email']);
    if ($res['success']) {
        return [
            'success' => true,
            'message' => 'Instruksi pemulihan dan kode OTP telah dikirim ke email Anda.',
            'email'   => $email,
            'otp'     => $res['otp'] ?? '',
            'token'   => $res['token'] ?? ''
        ];
    }

    return [
        'success' => true, // Still allow moving to OTP screen so user/developer can use OTP
        'warning' => 'Gagal mengirim email secara langsung (SMTP offline/belum aktif). ' . ($res['message'] ?? ''),
        'message' => 'Permintaan reset berhasil dibuat.',
        'email'   => $email,
        'otp'     => $res['otp'] ?? '',
        'token'   => $res['token'] ?? ''
    ];
}

/**
 * Verify reset token from URL
 */
function verifyPasswordResetToken(string $token): ?array {
    ensurePasswordResetTable();
    $token = trim($token);
    if (empty($token)) return null;

    $row = DB::row(
        "SELECT pr.*, u.name, u.email as user_email 
         FROM password_resets pr 
         JOIN users u ON pr.user_id = u.id 
         WHERE pr.token = ? AND pr.expires_at > NOW() 
         LIMIT 1",
        [$token]
    );

    return $row ?: null;
}

/**
 * Verify reset using 6-digit OTP
 */
function verifyPasswordResetOtp(string $email, string $otp): ?array {
    ensurePasswordResetTable();
    $email = strtolower(trim($email));
    $otp   = trim($otp);
    if (empty($email) || empty($otp)) return null;

    $row = DB::row(
        "SELECT pr.*, u.name, u.email as user_email 
         FROM password_resets pr 
         JOIN users u ON pr.user_id = u.id 
         WHERE pr.email = ? AND pr.otp_code = ? AND pr.expires_at > NOW() 
         LIMIT 1",
        [$email, $otp]
    );

    return $row ?: null;
}

/**
 * Complete password reset with new password
 */
function completePasswordReset(string $token, string $password, string $passwordConfirm): array {
    ensurePasswordResetTable();
    $reset = verifyPasswordResetToken($token);

    if (!$reset) {
        return [
            'success' => false,
            'message' => 'Tautan atau sesi pemulihan sandi tidak valid atau sudah kedaluwarsa. Silakan ajukan permohonan baru.'
        ];
    }

    if ($password !== $passwordConfirm) {
        return [
            'success' => false,
            'message' => 'Konfirmasi kata sandi baru tidak cocok.'
        ];
    }

    $errors = validatePassword($password);
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => implode(', ', $errors)
        ];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_ROUNDS]);

    // Update user password, clear remember tokens for security, and auto-verify email
    DB::query(
        "UPDATE users 
         SET password = ?, 
             remember_token = NULL, 
             email_verified_at = COALESCE(email_verified_at, NOW()), 
             is_active = 1, 
             updated_at = NOW() 
         WHERE id = ?",
        [$hash, (int)$reset['user_id']]
    );

    // Delete used password reset tokens
    try {
        DB::query("DELETE FROM password_resets WHERE user_id = ? OR email = ?", [(int)$reset['user_id'], $reset['email']]);
    } catch (\Throwable $e) {}

    // Clear session fallbacks
    unset($_SESSION['dev_reset_otp'], $_SESSION['dev_reset_token'], $_SESSION['last_reset_email']);

    return [
        'success' => true,
        'message' => 'Kata sandi berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.'
    ];
}

// ============================================================
// LOGOUT
// ============================================================

function logoutUser(): void {
    if (isset($_COOKIE['remember_token'])) {
        DB::query("UPDATE users SET remember_token = NULL WHERE id = ?", [$_SESSION['user_id'] ?? 0]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    session_unset();
    session_destroy();
}

// ============================================================
// REMEMBER ME AUTO-LOGIN
// ============================================================

function autoLoginFromCookie(): void {
    if (isLoggedIn() || empty($_COOKIE['remember_token'])) return;
    $hashed = hash('sha256', $_COOKIE['remember_token']);
    $user = DB::row("SELECT * FROM users WHERE remember_token = ? AND is_active = 1 LIMIT 1", [$hashed]);
    if ($user) {
        DB::query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
        setUserSession($user);
    }
}

autoLoginFromCookie();
