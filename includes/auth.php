<?php
/**
 * ShawirIOT Platform - Auth Functions
 */

require_once __DIR__ . '/functions.php';

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
        return ['success' => false, 'message' => 'Email sudah terdaftar.'];
    }

    if (!getSetting('allow_registration', '1')) {
        return ['success' => false, 'message' => 'Registrasi sementara ditutup.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_ROUNDS]);
    $userId = DB::insert(
        "INSERT INTO users (name, email, password, role, plan_id, credits, email_verified_at) VALUES (?, ?, ?, 'user', 1, 0, NOW())",
        [$name, $email, $hash]
    );

    return ['success' => true, 'message' => 'Registrasi berhasil!', 'user_id' => $userId];
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
