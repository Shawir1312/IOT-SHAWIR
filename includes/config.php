<?php
/**
 * ShawirIOT Platform - Konfigurasi Utama
 * Ubah sesuai dengan pengaturan server Anda
 */

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'nusaiot');
define('DB_USER', 'root');          // Ganti dengan user MySQL Anda
define('DB_PASS', '');              // Ganti dengan password MySQL Anda
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// PLATFORM SETTINGS
// ============================================================
define('PLATFORM_NAME', 'ShawirIOT');
define('PLATFORM_VERSION', '1.0.0');
define('PLATFORM_URL', 'http://localhost');      // Ganti dengan URL server Anda
define('PLATFORM_TIMEZONE', 'Asia/Makassar');

// ============================================================
// WEBSOCKET SETTINGS
// ============================================================
define('WS_HOST', '0.0.0.0');
define('WS_PORT', 8080);
define('WS_URL', 'ws://localhost:8080');         // Ganti dengan URL WS server Anda

// ============================================================
// SECURITY
// ============================================================
define('APP_SECRET', 'nusaiot_secret_key_ganti_ini_2024!'); // WAJIB DIGANTI
define('SESSION_NAME', 'nusaiot_session');
define('TOKEN_LENGTH', 32);
define('BCRYPT_ROUNDS', 12);

// ============================================================
// FILE PATHS
// ============================================================
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', PLATFORM_URL . '/uploads');

// ============================================================
// API RATE LIMITING
// ============================================================
define('API_RATE_LIMIT', 1000);         // requests per jam per device
define('API_RATE_WINDOW', 3600);        // window dalam detik

// ============================================================
// DATA SETTINGS
// ============================================================
define('MAX_HISTORY_RECORDS', 100000);  // Maks record histori per pin
define('DEVICE_OFFLINE_TIMEOUT', 60);   // Detik sebelum device dianggap offline

// ============================================================
// INIT
// ============================================================
date_default_timezone_set(PLATFORM_TIMEZONE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,      // Set true jika pakai HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
