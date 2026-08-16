<?php
/**
 * ShawirIOT Platform - Helper Functions
 */

require_once __DIR__ . '/db.php';

// ============================================================
// STRING & TOKEN HELPERS
// ============================================================

/**
 * Generate token random
 */
function generateToken(int $length = TOKEN_LENGTH): string {
    return bin2hex(random_bytes($length));
}

/**
 * Generate device token (format: XXXX-XXXX-XXXX-XXXX)
 */
function generateDeviceToken(): string {
    $parts = [];
    for ($i = 0; $i < 4; $i++) {
        $parts[] = strtoupper(bin2hex(random_bytes(2)));
    }
    return implode('-', $parts);
}

/**
 * Sanitize string
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format tanggal Indonesia
 */
function formatDate(string $datetime, string $format = 'd M Y H:i'): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '-';
    return date($format, strtotime($datetime));
}

/**
 * Time ago (waktu relatif)
 */
function timeAgo(string $datetime): string {
    if (empty($datetime)) return '-';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    if ($diff < 2592000) return floor($diff/86400) . ' hari lalu';
    return formatDate($datetime, 'd M Y');
}

// ============================================================
// RESPONSE HELPERS
// ============================================================

/**
 * JSON response
 */
function jsonResponse(bool $success, string $message = '', mixed $data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect
 */
function redirect(string $url): void {
    header("Location: {$url}");
    exit;
}

/**
 * Flash message (simpan ke session)
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil dan hapus flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================
// CSRF PROTECTION
// ============================================================

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        jsonResponse(false, 'Token tidak valid (CSRF).', null, 403);
    }
}

// ============================================================
// DEVICE & PIN HELPERS
// ============================================================

/**
 * Get device oleh token (caching sederhana)
 */
function getDeviceByToken(string $token): ?array {
    return DB::row("SELECT d.*, u.plan_id, u.credits FROM devices d JOIN users u ON d.user_id = u.id WHERE d.token = ? AND d.is_active = 1", [$token]);
}

/**
 * Update status device online
 */
function deviceHeartbeat(int $deviceId): void {
    DB::query(
        "UPDATE devices SET is_online = 1, last_seen = NOW(), last_ip = ? WHERE id = ?",
        [$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $deviceId]
    );
}

/**
 * Cek device offline (dipanggil via cron atau lazy check)
 */
function checkOfflineDevices(): void {
    DB::query(
        "UPDATE devices SET is_online = 0 WHERE is_online = 1 AND last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)",
        [DEVICE_OFFLINE_TIMEOUT]
    );
}

/**
 * Simpan nilai pin
 */
function savePinValue(int $deviceId, string $pin, string $value): void {
    DB::query(
        "INSERT INTO virtual_pins (device_id, pin, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()",
        [$deviceId, $pin, $value, $value]
    );
}

/**
 * Simpan ke histori
 */
function savePinHistory(int $deviceId, string $pin, string $value): void {
    DB::query(
        "INSERT INTO pin_history (device_id, pin, value) VALUES (?, ?, ?)",
        [$deviceId, $pin, $value]
    );
}

// ============================================================
// CREDIT HELPERS
// ============================================================

/**
 * Tambah atau kurangi kredit user
 */
function adjustCredits(int $userId, int $amount, string $type, string $note = '', ?int $adminId = null): bool {
    $user = DB::row("SELECT credits FROM users WHERE id = ?", [$userId]);
    if (!$user) return false;

    $newCredits = max(0, $user['credits'] + $amount);
    DB::query("UPDATE users SET credits = ? WHERE id = ?", [$newCredits, $userId]);
    DB::query(
        "INSERT INTO credit_transactions (user_id, amount, type, note, admin_id) VALUES (?, ?, ?, ?, ?)",
        [$userId, $amount, $type, $note, $adminId]
    );
    return true;
}

/**
 * Get plan user saat ini
 */
function getUserPlan(int $userId): ?array {
    return DB::row("SELECT p.* FROM plans p JOIN users u ON u.plan_id = p.id WHERE u.id = ?", [$userId]);
}

/**
 * Upgrade plan user
 */
function upgradePlan(int $userId, int $planId): array {
    $plan = DB::row("SELECT * FROM plans WHERE id = ? AND is_active = 1", [$planId]);
    $user = DB::row("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$plan || !$user) return ['success' => false, 'message' => 'Data tidak ditemukan'];
    if ($user['credits'] < $plan['credits_required']) {
        return ['success' => false, 'message' => 'Kredit tidak cukup. Butuh ' . $plan['credits_required'] . ' kredit.'];
    }

    adjustCredits($userId, -$plan['credits_required'], 'plan_upgrade', 'Upgrade ke paket ' . $plan['name']);
    DB::query("UPDATE users SET plan_id = ? WHERE id = ?", [$planId, $userId]);
    return ['success' => true, 'message' => 'Berhasil upgrade ke paket ' . $plan['name']];
}

// ============================================================
// PLATFORM SETTINGS
// ============================================================

function getSetting(string $key, string $default = ''): string {
    $val = DB::value("SELECT value FROM platform_settings WHERE `key` = ?", [$key]);
    return $val !== false ? $val : $default;
}

function setSetting(string $key, string $value): void {
    DB::query(
        "INSERT INTO platform_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
        [$key, $value, $value]
    );
}

// ============================================================
// VALIDATION HELPERS
// ============================================================

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePin(string $pin): bool {
    return preg_match('/^[VvDdAa]\d{1,3}$/', $pin) === 1;
}

/**
 * Password strength check
 */
function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < 8) $errors[] = 'Minimal 8 karakter';
    return $errors;
}
