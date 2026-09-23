<?php
/**
 * ShawirIOT - REST API Mobile Backend (Native Android Client)
 * 
 * Melayani seluruh interaksi aplikasi Android Native:
 * - Autentikasi (Login, Register, Logout, Me)
 * - Manajemen Device & Status Online/Offline
 * - Detail Dashboard, Widget, dan Nilai Pin Real-Time
 * - Kontrol Pin (Switch, Button, Slider)
 * - Histori Data Pin untuk Grafik
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

// Pastikan tabel user_api_tokens sudah ada (Auto-migration aman)
try {
    if (class_exists('DB')) {
        DB::query("
            CREATE TABLE IF NOT EXISTS `user_api_tokens` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `user_id` INT UNSIGNED NOT NULL,
              `token` VARCHAR(64) NOT NULL UNIQUE,
              `device_name` VARCHAR(100) DEFAULT 'Android Native',
              `last_used_at` TIMESTAMP NULL DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_user_token` (`token`),
              FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
} catch (Throwable $e) {
    // Abaikan jika tabel sudah ada atau DB belum running
}

// ============================================================
// TOKEN AUTHENTICATION HELPER
// ============================================================

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!empty($header) && preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return $matches[1];
    }
    // Fallback via GET/POST param
    return $_POST['auth_token'] ?? $_GET['auth_token'] ?? null;
}

function getAuthenticatedUser(): ?array {
    $token = getBearerToken();
    if (empty($token)) return null;

    $row = DB::row("
        SELECT u.*, p.name as plan_name, p.max_devices, p.max_widgets_per_device, p.history_days
        FROM user_api_tokens t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN plans p ON u.plan_id = p.id
        WHERE t.token = ? AND u.is_active = 1
        LIMIT 1
    ", [$token]);

    if ($row) {
        // Update waktu terakhir digunakan
        DB::query("UPDATE user_api_tokens SET last_used_at = NOW() WHERE token = ?", [$token]);
    }
    return $row;
}

function requireMobileAuth(): array {
    $user = getAuthenticatedUser();
    if (!$user) {
        jsonResponse(false, 'Sesi tidak valid atau telah berakhir. Silakan login kembali.', null, 401);
    }
    return $user;
}

// Ambil input JSON atau POST form-data
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$input = array_merge($_GET, $_POST, $jsonInput);
$action = $input['action'] ?? '';

switch ($action) {

    // ========================================================
    // 0. SERVER INFO & HEALTH CHECK (Untuk tes koneksi dari app)
    // ========================================================
    case 'server_info':
    case 'ping': {
        jsonResponse(true, 'Server ShawirIOT aktif.', [
            'platform' => getSetting('platform_name', 'ShawirIOT'),
            'version'  => '1.0.0',
            'time'     => date('Y-m-d H:i:s'),
            'status'   => 'ready'
        ]);
        break;
    }

    // ========================================================
    // 1. LOGIN
    // ========================================================
    case 'login': {
        $email    = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        $devName  = sanitize($input['device_name'] ?? 'Android App');

        if (empty($email) || empty($password)) {
            jsonResponse(false, 'Email dan password harus diisi.', null, 400);
        }

        $user = DB::row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(false, 'Email atau password salah.', null, 401);
        }

        if (!$user['is_active']) {
            jsonResponse(false, 'Akun Anda dinonaktifkan oleh administrator.', null, 403);
        }

        // Cek status verifikasi email
        $requireVerify = getSetting('require_email_verification', '1') === '1';
        if ($requireVerify && empty($user['email_verified_at']) && $user['role'] === 'user') {
            jsonResponse(false, 'Email Anda belum diverifikasi. Silakan masukkan kode OTP yang dikirim ke email Anda.', [
                'unverified'     => true,
                'email'          => $user['email'],
                'require_verify' => true
            ], 403);
        }

        // Generate token autentikasi mobile
        $token = bin2hex(random_bytes(32));
        DB::query("INSERT INTO user_api_tokens (user_id, token, device_name, last_used_at) VALUES (?, ?, ?, NOW())", [
            $user['id'], $token, $devName
        ]);

        // Catat last login
        DB::query("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $user['id']
        ]);

        $plan = getUserPlan((int)$user['id']);

        jsonResponse(true, 'Login berhasil!', [
            'token' => $token,
            'user'  => [
                'id'        => (int)$user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'credits'   => (int)$user['credits'],
                'plan_name' => $plan['name'] ?? 'Free',
                'max_devices' => (int)($plan['max_devices'] ?? 1),
                'avatar'    => $user['avatar'] ?? null,
            ]
        ]);
        break;
    }

    // ========================================================
    // 2. REGISTER
    // ========================================================
    case 'register': {
        $name     = sanitize($input['name'] ?? '');
        $email    = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        $devName  = sanitize($input['device_name'] ?? 'Android App');

        $result = registerUser($name, $email, $password);
        if (!$result['success']) {
            jsonResponse(false, $result['message'], null, 400);
        }

        // Jika butuh verifikasi email OTP
        if (!empty($result['require_verify'])) {
            jsonResponse(true, $result['message'], [
                'require_verify' => true,
                'email'          => $email
            ]);
        }

        // Ambil user yang baru dibuat jika langsung aktif
        $user = DB::row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        $token = bin2hex(random_bytes(32));
        DB::query("INSERT INTO user_api_tokens (user_id, token, device_name, last_used_at) VALUES (?, ?, ?, NOW())", [
            $user['id'], $token, $devName
        ]);

        $plan = getUserPlan((int)$user['id']);

        jsonResponse(true, 'Registrasi berhasil!', [
            'require_verify' => false,
            'token' => $token,
            'user'  => [
                'id'        => (int)$user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'credits'   => (int)$user['credits'],
                'plan_name' => $plan['name'] ?? 'Free',
                'max_devices' => (int)($plan['max_devices'] ?? 1),
                'avatar'    => $user['avatar'] ?? null,
            ]
        ]);
        break;
    }

    // ========================================================
    // 2.1 VERIFY EMAIL OTP
    // ========================================================
    case 'verify_email_otp': {
        $email   = strtolower(trim($input['email'] ?? ''));
        $otp     = trim($input['otp_code'] ?? '');
        $devName = sanitize($input['device_name'] ?? 'Android App');

        $res = verifyEmailOtp($email, $otp);
        if (!$res['success']) {
            jsonResponse(false, $res['message'], null, 400);
        }

        $user = $res['user'] ?? DB::row("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        $token = bin2hex(random_bytes(32));
        DB::query("INSERT INTO user_api_tokens (user_id, token, device_name, last_used_at) VALUES (?, ?, ?, NOW())", [
            $user['id'], $token, $devName
        ]);
        $plan = getUserPlan((int)$user['id']);

        jsonResponse(true, $res['message'], [
            'token' => $token,
            'user'  => [
                'id'          => (int)$user['id'],
                'name'        => $user['name'],
                'email'       => $user['email'],
                'role'        => $user['role'],
                'credits'     => (int)$user['credits'],
                'plan_name'   => $plan['name'] ?? 'Free',
                'max_devices' => (int)($plan['max_devices'] ?? 1),
                'avatar'      => $user['avatar'] ?? null,
            ]
        ]);
        break;
    }

    // ========================================================
    // 2.2 RESEND EMAIL OTP
    // ========================================================
    case 'resend_email_otp': {
        $email = strtolower(trim($input['email'] ?? ''));
        $res = resendVerification($email);
        if (!$res['success']) {
            jsonResponse(false, $res['message'], null, 400);
        }
        jsonResponse(true, $res['message']);
        break;
    }

    // ========================================================
    // 3. LOGOUT
    // ========================================================
    case 'logout': {
        $token = getBearerToken();
        if ($token) {
            DB::query("DELETE FROM user_api_tokens WHERE token = ?", [$token]);
        }
        jsonResponse(true, 'Logout berhasil.');
        break;
    }

    // ========================================================
    // 4. USER PROFILE (ME)
    // ========================================================
    case 'me': {
        $user = requireMobileAuth();
        $plan = getUserPlan((int)$user['id']);
        jsonResponse(true, 'OK', [
            'id'        => (int)$user['id'],
            'name'      => $user['name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'credits'   => (int)$user['credits'],
            'plan_name' => $plan['name'] ?? 'Free',
            'max_devices' => (int)($plan['max_devices'] ?? 1),
            'max_widgets_per_device' => (int)($plan['max_widgets_per_device'] ?? 5),
            'history_days' => (int)($plan['history_days'] ?? 1),
            'created_at' => $user['created_at'],
        ]);
        break;
    }

    // ========================================================
    // 5. LIST DEVICES
    // ========================================================
    case 'devices': {
        $user = requireMobileAuth();
        checkOfflineDevices();

        $rows = DB::rows("
            SELECT d.id, d.name, d.description, d.token, d.hardware, d.connection,
                   d.is_online, d.last_seen, d.last_ip, d.created_at,
                   (SELECT COUNT(*) FROM widgets w JOIN dashboards db ON w.dashboard_id = db.id WHERE db.device_id = d.id) AS widget_count
            FROM devices d
            WHERE d.user_id = ? AND d.is_active = 1
            ORDER BY d.is_online DESC, d.name ASC
        ", [$user['id']]);

        // Normalisasi data boolean dan integer
        foreach ($rows as &$d) {
            $d['id'] = (int)$d['id'];
            $d['is_online'] = (bool)$d['is_online'];
            $d['widget_count'] = (int)$d['widget_count'];
            $d['last_seen_relative'] = !empty($d['last_seen']) ? timeAgo($d['last_seen']) : 'Belum pernah terhubung';
        }
        unset($d);

        jsonResponse(true, 'OK', $rows);
        break;
    }

    // ========================================================
    // 6. DEVICE DASHBOARD (Widgets + Pin Values)
    // ========================================================
    case 'device_dashboard': {
        $user = requireMobileAuth();
        checkOfflineDevices();

        $deviceId = (int)($input['device_id'] ?? 0);
        $device = DB::row("SELECT * FROM devices WHERE id = ? AND user_id = ? AND is_active = 1", [$deviceId, $user['id']]);
        if (!$device) {
            jsonResponse(false, 'Perangkat tidak ditemukan.', null, 404);
        }

        // Ambil atau buat dashboard
        $dashboard = DB::row("SELECT * FROM dashboards WHERE device_id = ?", [$deviceId]);
        if (!$dashboard) {
            $dbId = DB::insert("INSERT INTO dashboards (device_id, user_id) VALUES (?,?)", [$deviceId, (int)$user['id']]);
            $dashboard = DB::row("SELECT * FROM dashboards WHERE id = ?", [$dbId]);
        }

        // Ambil widgets
        $widgets = DB::rows("SELECT * FROM widgets WHERE dashboard_id = ? ORDER BY pos_y, pos_x", [$dashboard['id']]);
        foreach ($widgets as &$w) {
            $w['id'] = (int)$w['id'];
            $w['min_value'] = (float)$w['min_value'];
            $w['max_value'] = (float)$w['max_value'];
            $w['pos_x']     = (int)$w['pos_x'];
            $w['pos_y']     = (int)$w['pos_y'];
            $w['width']     = (int)$w['width'];
            $w['height']    = (int)$w['height'];
        }
        unset($w);

        // Ambil nilai pin virtual saat ini
        $pins = DB::rows("SELECT pin, value, updated_at FROM virtual_pins WHERE device_id = ?", [$deviceId]);
        $pinMap = [];
        foreach ($pins as $p) {
            $pinMap[$p['pin']] = $p['value'];
        }

        jsonResponse(true, 'OK', [
            'device' => [
                'id'         => (int)$device['id'],
                'name'       => $device['name'],
                'token'      => $device['token'],
                'hardware'   => $device['hardware'],
                'connection' => $device['connection'],
                'is_online'  => (bool)$device['is_online'],
                'last_seen'  => $device['last_seen'],
            ],
            'dashboard'  => [
                'id'    => (int)$dashboard['id'],
                'title' => $dashboard['title'] ?? 'Dashboard',
            ],
            'widgets'    => $widgets,
            'pin_values' => $pinMap,
        ]);
        break;
    }

    // ========================================================
    // 7. PIN VALUES POLLING (Ringan untuk pembaruan real-time)
    // ========================================================
    case 'pin_values': {
        $user = requireMobileAuth();
        $deviceId = (int)($input['device_id'] ?? 0);
        $device = DB::row("SELECT id, is_online FROM devices WHERE id = ? AND user_id = ? AND is_active = 1", [$deviceId, $user['id']]);
        if (!$device) {
            jsonResponse(false, 'Perangkat tidak ditemukan.', null, 404);
        }

        checkOfflineDevices();
        $isOnline = (bool)DB::value("SELECT is_online FROM devices WHERE id = ?", [$deviceId]);
        $pins = DB::rows("SELECT pin, value, updated_at FROM virtual_pins WHERE device_id = ?", [$deviceId]);

        $pinMap = [];
        foreach ($pins as $p) {
            $pinMap[$p['pin']] = $p['value'];
        }

        jsonResponse(true, 'OK', [
            'is_online'  => $isOnline,
            'pin_values' => $pinMap,
            'timestamp'  => time(),
        ]);
        break;
    }

    // ========================================================
    // 8. CONTROL PIN (Kirim perintah Saklar, Slider, Tombol)
    // ========================================================
    case 'control_pin': {
        $user = requireMobileAuth();
        $deviceId = (int)($input['device_id'] ?? 0);
        $pin      = strtoupper(sanitize($input['pin'] ?? ''));
        $value    = (string)($input['value'] ?? '');

        if (empty($deviceId) || empty($pin)) {
            jsonResponse(false, 'Parameter device_id dan pin diperlukan.', null, 400);
        }

        $device = DB::row("SELECT id, token FROM devices WHERE id = ? AND user_id = ? AND is_active = 1", [$deviceId, $user['id']]);
        if (!$device) {
            jsonResponse(false, 'Perangkat tidak ditemukan atau tidak aktif.', null, 404);
        }

        // Simpan nilai pin virtual
        savePinValue($deviceId, $pin, $value);

        jsonResponse(true, "Perintah berhasil dikirim ke {$pin}", [
            'pin'   => $pin,
            'value' => $value,
        ]);
        break;
    }

    // ========================================================
    // 9. PIN HISTORY (Histori data untuk chart)
    // ========================================================
    case 'pin_history': {
        $user = requireMobileAuth();
        $deviceId = (int)($input['device_id'] ?? 0);
        $pin      = strtoupper(sanitize($input['pin'] ?? ''));
        $limit    = min(100, max(5, (int)($input['n'] ?? 30)));

        $device = DB::row("SELECT id FROM devices WHERE id = ? AND user_id = ? AND is_active = 1", [$deviceId, $user['id']]);
        if (!$device) {
            jsonResponse(false, 'Perangkat tidak ditemukan.', null, 404);
        }

        $rows = DB::rows("
            SELECT value, recorded_at
            FROM pin_history
            WHERE device_id = ? AND pin = ?
            ORDER BY recorded_at DESC
            LIMIT ?
        ", [$deviceId, $pin, $limit]);

        $formatted = [];
        foreach (array_reverse($rows) as $r) {
            $formatted[] = [
                'value'       => is_numeric($r['value']) ? (float)$r['value'] : 0.0,
                'recorded_at' => $r['recorded_at'],
                'time_label'  => date('H:i:s', strtotime($r['recorded_at'])),
            ];
        }

        jsonResponse(true, 'OK', $formatted);
        break;
    }

    // ========================================================
    // 10. TAMBAH PERANGKAT (ADD DEVICE)
    // ========================================================
    case 'add_device': {
        $user = requireMobileAuth();
        $plan = getUserPlan((int)$user['id']);
        $deviceCount = DB::count('devices', 'user_id = ? AND is_active = 1', [$user['id']]);
        if ($deviceCount >= ($plan['max_devices'] ?? 5)) {
            jsonResponse(false, "Batas perangkat untuk paket {$plan['name']} adalah {$plan['max_devices']} perangkat. Upgrade paket untuk menambah lebih.", null, 403);
        }

        $name = sanitize($input['name'] ?? 'Perangkat Baru');
        $hw   = sanitize($input['hardware'] ?? 'ESP32');
        $conn = sanitize($input['connection'] ?? 'wifi');
        $desc = sanitize($input['description'] ?? '');
        $token = generateDeviceToken();

        $deviceId = DB::insert(
            "INSERT INTO devices (user_id, name, hardware, connection, description, token, is_active) VALUES (?,?,?,?,?,?,1)",
            [$user['id'], $name, $hw, $conn, $desc, $token]
        );
        // Buat dashboard otomatis
        DB::insert("INSERT INTO dashboards (device_id, user_id) VALUES (?,?)", [$deviceId, $user['id']]);

        $newDevice = DB::row("SELECT id, name, description, token, hardware, connection, is_online, last_seen, created_at FROM devices WHERE id = ?", [$deviceId]);
        $newDevice['id'] = (int)$newDevice['id'];
        $newDevice['is_online'] = false;
        $newDevice['widget_count'] = 0;

        jsonResponse(true, "Perangkat \"{$name}\" berhasil ditambahkan!", $newDevice);
        break;
    }

    // ========================================================
    // 11. HAPUS PERANGKAT (DELETE DEVICE)
    // ========================================================
    case 'delete_device': {
        $user = requireMobileAuth();
        $deviceId = (int)($input['device_id'] ?? 0);
        $device = DB::row("SELECT * FROM devices WHERE id = ? AND user_id = ?", [$deviceId, $user['id']]);
        if (!$device) {
            jsonResponse(false, 'Perangkat tidak ditemukan.', null, 404);
        }
        DB::query("UPDATE devices SET is_active = 0 WHERE id = ?", [$deviceId]);
        jsonResponse(true, "Perangkat \"{$device['name']}\" berhasil dihapus.");
        break;
    }

    // ========================================================
    // 12. TAMBAH WIDGET (ADD WIDGET)
    // ========================================================
    case 'add_widget': {
        $user = requireMobileAuth();
        $deviceId = (int)($input['device_id'] ?? 0);
        $dashboard = DB::row("SELECT d.* FROM dashboards d JOIN devices dev ON d.device_id = dev.id WHERE d.device_id = ? AND d.user_id = ? AND dev.is_active = 1", [$deviceId, $user['id']]);
        if (!$dashboard) {
            jsonResponse(false, 'Dashboard untuk perangkat ini tidak ditemukan.', null, 404);
        }

        $dashboardId = (int)$dashboard['id'];
        $plan = getUserPlan((int)$user['id']);
        $widgetCount = DB::count('widgets', 'dashboard_id = ?', [$dashboardId]);
        if ($widgetCount >= ($plan['max_widgets_per_device'] ?? 10)) {
            jsonResponse(false, "Batas widget paket {$plan['name']} adalah {$plan['max_widgets_per_device']} widget per perangkat.", null, 403);
        }

        $validTypes = ['switch','button','slider','value_display','led','gauge','line_chart'];
        $type = sanitize($input['type'] ?? 'switch');
        if (!in_array($type, $validTypes)) {
            $type = 'switch';
        }

        $label = sanitize($input['label'] ?? 'Widget Baru');
        $pin = strtoupper(sanitize($input['pin'] ?? 'V0'));
        $color = sanitize($input['color'] ?? '#6366f1');
        $minVal = (float)($input['min_value'] ?? 0);
        $maxVal = (float)($input['max_value'] ?? 100);
        $unit = sanitize($input['unit'] ?? '');
        $onVal = sanitize($input['on_value'] ?? '1');
        $offVal = sanitize($input['off_value'] ?? '0');

        $maxBottom = (int)DB::value("SELECT COALESCE(MAX(pos_y + height), 0) FROM widgets WHERE dashboard_id = ?", [$dashboardId]);

        $width = ($type === 'line_chart' || $type === 'gauge') ? 6 : 3;
        $height = ($type === 'line_chart') ? 3 : (($type === 'gauge') ? 3 : 2);

        $id = DB::insert(
            "INSERT INTO widgets (dashboard_id, type, label, pin, color, text_color, min_value, max_value, unit, on_value, off_value, pos_x, pos_y, width, height)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$dashboardId, $type, $label, $pin, $color, '#ffffff', $minVal, $maxVal, $unit, $onVal, $offVal, 0, $maxBottom, $width, $height]
        );

        $widget = DB::row("SELECT * FROM widgets WHERE id = ?", [$id]);
        if ($widget) {
            $widget['id'] = (int)$widget['id'];
            $widget['dashboard_id'] = (int)$widget['dashboard_id'];
            $widget['min_value'] = (float)$widget['min_value'];
            $widget['max_value'] = (float)$widget['max_value'];
            $widget['width'] = (int)$widget['width'];
            $widget['height'] = (int)$widget['height'];
        }

        jsonResponse(true, 'Widget berhasil ditambahkan!', $widget);
        break;
    }

    // ========================================================
    // 13. EDIT WIDGET (UPDATE WIDGET)
    // ========================================================
    case 'update_widget': {
        $user = requireMobileAuth();
        $widgetId = (int)($input['widget_id'] ?? 0);
        $widget = DB::row("SELECT w.* FROM widgets w JOIN dashboards d ON w.dashboard_id = d.id WHERE w.id = ? AND d.user_id = ?", [$widgetId, $user['id']]);
        if (!$widget) {
            jsonResponse(false, 'Widget tidak ditemukan.', null, 404);
        }

        $label = sanitize($input['label'] ?? $widget['label']);
        $pin = strtoupper(sanitize($input['pin'] ?? $widget['pin']));
        $color = sanitize($input['color'] ?? $widget['color']);
        $minVal = isset($input['min_value']) ? (float)$input['min_value'] : (float)$widget['min_value'];
        $maxVal = isset($input['max_value']) ? (float)$input['max_value'] : (float)$widget['max_value'];
        $unit = sanitize($input['unit'] ?? $widget['unit']);
        $onVal = sanitize($input['on_value'] ?? $widget['on_value']);
        $offVal = sanitize($input['off_value'] ?? $widget['off_value']);

        DB::query("UPDATE widgets SET label=?, pin=?, color=?, min_value=?, max_value=?, unit=?, on_value=?, off_value=? WHERE id=?", [
            $label, $pin, $color, $minVal, $maxVal, $unit, $onVal, $offVal, $widgetId
        ]);

        $updated = DB::row("SELECT * FROM widgets WHERE id = ?", [$widgetId]);
        if ($updated) {
            $updated['id'] = (int)$updated['id'];
            $updated['dashboard_id'] = (int)$updated['dashboard_id'];
            $updated['min_value'] = (float)$updated['min_value'];
            $updated['max_value'] = (float)$updated['max_value'];
            $updated['width'] = (int)$updated['width'];
            $updated['height'] = (int)$updated['height'];
        }

        jsonResponse(true, 'Widget berhasil diperbarui!', $updated);
        break;
    }

    // ========================================================
    // 14. HAPUS WIDGET (DELETE WIDGET)
    // ========================================================
    case 'delete_widget': {
        $user = requireMobileAuth();
        $widgetId = (int)($input['widget_id'] ?? 0);
        $widget = DB::row("SELECT w.* FROM widgets w JOIN dashboards d ON w.dashboard_id = d.id WHERE w.id = ? AND d.user_id = ?", [$widgetId, $user['id']]);
        if (!$widget) {
            jsonResponse(false, 'Widget tidak ditemukan.', null, 404);
        }

        DB::query("DELETE FROM widgets WHERE id = ?", [$widgetId]);
        jsonResponse(true, 'Widget berhasil dihapus.');
        break;
    }

    default:
        jsonResponse(false, "Action '{$action}' tidak dikenali.", null, 400);
}
