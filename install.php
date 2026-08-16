<?php
/**
 * ShawirIOT - Web Installer Wizard
 * One-click installation for Database, Config, and Superadmin setup
 */

// If already installed, prevent re-installation
$lockFile = __DIR__ . '/installed.lock';
$isInstalled = file_exists($lockFile);

$step = $_GET['step'] ?? ($isInstalled ? 'done' : 'welcome');
$error = '';
$success = '';

// Check Server Requirements
$requirements = [
    'PHP Version (>= 8.0)'   => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO Extension'          => extension_loaded('pdo'),
    'PDO MySQL Driver'       => extension_loaded('pdo_mysql'),
    'JSON Extension'         => extension_loaded('json'),
    'OpenSSL Extension'      => extension_loaded('openssl'),
    'cURL Extension'         => extension_loaded('curl'),
    'Includes Dir Writable'  => is_writable(__DIR__ . '/includes') || is_writable(__DIR__),
];

$allRequirementsPassed = !in_array(false, $requirements, true);

// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$autoUrl = $protocol . $host . $path;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled) {
    $action = $_POST['action'] ?? '';

    if ($action === 'install') {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'shawiriot');
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';

        $platformName = trim($_POST['platform_name'] ?? 'ShawirIOT');
        $platformUrl  = rtrim(trim($_POST['platform_url'] ?? $autoUrl), '/');
        $wsUrl        = trim($_POST['ws_url'] ?? 'ws://' . parse_url($platformUrl, PHP_URL_HOST) . ':8080');

        $adminName  = trim($_POST['admin_name'] ?? 'Super Admin');
        $adminEmail = trim(strtolower($_POST['admin_email'] ?? 'admin@shawiriot.com'));
        $adminPass  = $_POST['admin_pass'] ?? '';

        if (empty($adminPass) || strlen($adminPass) < 8) {
            $error = 'Password Admin minimal 8 karakter!';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email Admin tidak valid!';
        } else {
            try {
                // 1. Test PDO connection to MySQL Server
                $dsnNoDb = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
                $pdo = new PDO($dsnNoDb, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // 2. Create Database if not exists
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbName}`");

                // 3. Import Tables
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `plans` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `name` VARCHAR(50) NOT NULL,
                      `slug` VARCHAR(50) NOT NULL UNIQUE,
                      `credits_required` INT UNSIGNED DEFAULT 0,
                      `max_devices` INT UNSIGNED DEFAULT 1,
                      `max_widgets_per_device` INT UNSIGNED DEFAULT 5,
                      `history_days` INT UNSIGNED DEFAULT 1,
                      `description` TEXT,
                      `is_active` TINYINT(1) DEFAULT 1,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `users` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `name` VARCHAR(100) NOT NULL,
                      `email` VARCHAR(150) NOT NULL UNIQUE,
                      `password` VARCHAR(255) NOT NULL,
                      `avatar` VARCHAR(255) DEFAULT NULL,
                      `role` ENUM('user','admin','superadmin') DEFAULT 'user',
                      `plan_id` INT UNSIGNED DEFAULT 1,
                      `credits` INT UNSIGNED DEFAULT 0,
                      `is_active` TINYINT(1) DEFAULT 1,
                      `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
                      `remember_token` VARCHAR(100) DEFAULT NULL,
                      `last_login_at` TIMESTAMP NULL DEFAULT NULL,
                      `last_login_ip` VARCHAR(45) DEFAULT NULL,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `devices` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `user_id` INT UNSIGNED NOT NULL,
                      `name` VARCHAR(100) NOT NULL,
                      `description` TEXT DEFAULT NULL,
                      `token` VARCHAR(64) NOT NULL UNIQUE,
                      `hardware` VARCHAR(50) DEFAULT 'ESP8266',
                      `connection` ENUM('wifi','ethernet','gsm','other') DEFAULT 'wifi',
                      `is_online` TINYINT(1) DEFAULT 0,
                      `last_seen` TIMESTAMP NULL DEFAULT NULL,
                      `last_ip` VARCHAR(45) DEFAULT NULL,
                      `firmware_version` VARCHAR(20) DEFAULT NULL,
                      `is_active` TINYINT(1) DEFAULT 1,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `virtual_pins` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `device_id` INT UNSIGNED NOT NULL,
                      `pin` VARCHAR(10) NOT NULL,
                      `value` TEXT DEFAULT NULL,
                      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      UNIQUE KEY `device_pin` (`device_id`, `pin`),
                      FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `pin_history` (
                      `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `device_id` INT UNSIGNED NOT NULL,
                      `pin` VARCHAR(10) NOT NULL,
                      `value` TEXT DEFAULT NULL,
                      `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      INDEX `idx_device_pin_time` (`device_id`, `pin`, `recorded_at`),
                      FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `dashboards` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `device_id` INT UNSIGNED NOT NULL UNIQUE,
                      `user_id` INT UNSIGNED NOT NULL,
                      `title` VARCHAR(100) DEFAULT 'Dashboard',
                      `bg_color` VARCHAR(20) DEFAULT '#0f172a',
                      `grid_columns` TINYINT UNSIGNED DEFAULT 12,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
                      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `widgets` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `dashboard_id` INT UNSIGNED NOT NULL,
                      `type` ENUM('value_display','line_chart','bar_chart','gauge','button','slider','led','terminal','label','map','switch','radial_gauge') NOT NULL,
                      `label` VARCHAR(100) DEFAULT 'Widget',
                      `pin` VARCHAR(10) DEFAULT NULL,
                      `color` VARCHAR(20) DEFAULT '#6366f1',
                      `text_color` VARCHAR(20) DEFAULT '#ffffff',
                      `min_value` DECIMAL(12,4) DEFAULT 0,
                      `max_value` DECIMAL(12,4) DEFAULT 100,
                      `unit` VARCHAR(20) DEFAULT '',
                      `on_value` VARCHAR(50) DEFAULT '1',
                      `off_value` VARCHAR(50) DEFAULT '0',
                      `pos_x` TINYINT UNSIGNED DEFAULT 0,
                      `pos_y` TINYINT UNSIGNED DEFAULT 0,
                      `width` TINYINT UNSIGNED DEFAULT 4,
                      `height` TINYINT UNSIGNED DEFAULT 2,
                      `extra_config` JSON DEFAULT NULL,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                      FOREIGN KEY (`dashboard_id`) REFERENCES `dashboards`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `credit_transactions` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `user_id` INT UNSIGNED NOT NULL,
                      `amount` INT NOT NULL,
                      `type` ENUM('topup','spend','refund','admin_grant','admin_deduct','plan_upgrade') NOT NULL,
                      `note` VARCHAR(255) DEFAULT NULL,
                      `admin_id` INT UNSIGNED DEFAULT NULL,
                      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                      FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `websocket_connections` (
                      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                      `connection_id` VARCHAR(64) NOT NULL UNIQUE,
                      `type` ENUM('device','client') NOT NULL,
                      `device_id` INT UNSIGNED DEFAULT NULL,
                      `user_id` INT UNSIGNED DEFAULT NULL,
                      `connected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      `last_ping` TIMESTAMP NULL DEFAULT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS `platform_settings` (
                      `key` VARCHAR(100) PRIMARY KEY,
                      `value` TEXT,
                      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // 4. Populate Default Plans
                $pdo->exec("
                    INSERT INTO `plans` (`id`, `name`, `slug`, `credits_required`, `max_devices`, `max_widgets_per_device`, `history_days`, `description`) VALUES
                    (1, 'Free', 'free', 0, 1, 5, 1, 'Paket gratis untuk memulai'),
                    (2, 'Basic', 'basic', 100, 5, 20, 7, 'Cocok untuk proyek kecil'),
                    (3, 'Pro', 'pro', 300, 20, 100, 30, 'Untuk developer serius'),
                    (4, 'Enterprise', 'enterprise', 1000, 9999, 9999, 365, 'Unlimited untuk bisnis')
                    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
                ");

                // 5. Populate Platform Settings
                $stmtSettings = $pdo->prepare("INSERT INTO `platform_settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $settingsData = [
                    ['platform_name', $platformName],
                    ['platform_tagline', 'Platform IoT Modern ' . $platformName],
                    ['platform_email', $adminEmail],
                    ['allow_registration', '1'],
                    ['max_free_devices', '1'],
                    ['websocket_port', '8080'],
                    ['data_retention_days', '365'],
                ];
                foreach ($settingsData as $s) {
                    $stmtSettings->execute([$s[0], $s[1], $s[1]]);
                }

                // 6. Create / Update Superadmin Account
                $adminPassHash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmtAdmin = $pdo->prepare("
                    INSERT INTO `users` (`name`, `email`, `password`, `role`, `plan_id`, `credits`, `is_active`, `email_verified_at`)
                    VALUES (?, ?, ?, 'superadmin', 4, 99999, 1, NOW())
                    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `password` = VALUES(`password`), `role` = 'superadmin', `plan_id` = 4, `credits` = 99999
                ");
                $stmtAdmin->execute([$adminName, $adminEmail, $adminPassHash]);

                // 7. Write includes/config.php
                $appSecret = bin2hex(random_bytes(24));
                $configContent = "<?php
/**
 * {$platformName} Platform - Konfigurasi Utama
 * Dibuat otomatis oleh installer
 */

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', '" . addslashes($dbHost) . "');
define('DB_PORT', '" . addslashes($dbPort) . "');
define('DB_NAME', '" . addslashes($dbName) . "');
define('DB_USER', '" . addslashes($dbUser) . "');
define('DB_PASS', '" . addslashes($dbPass) . "');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// PLATFORM SETTINGS
// ============================================================
define('PLATFORM_NAME', '" . addslashes($platformName) . "');
define('PLATFORM_VERSION', '1.0.0');
define('PLATFORM_URL', '" . addslashes($platformUrl) . "');
define('PLATFORM_TIMEZONE', 'Asia/Makassar');

// ============================================================
// WEBSOCKET SETTINGS
// ============================================================
define('WS_HOST', '0.0.0.0');
define('WS_PORT', 8080);
define('WS_URL', '" . addslashes($wsUrl) . "');

// ============================================================
// SECURITY
// ============================================================
define('APP_SECRET', '{$appSecret}');
define('SESSION_NAME', 'shawiriot_session');
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
define('API_RATE_LIMIT', 1000);
define('API_RATE_WINDOW', 3600);

// ============================================================
// DATA SETTINGS
// ============================================================
define('MAX_HISTORY_RECORDS', 100000);
define('DEVICE_OFFLINE_TIMEOUT', 60);

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
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
";

                file_put_contents(__DIR__ . '/includes/config.php', $configContent);

                // 8. Create lock file
                file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s') . " by " . $adminEmail);

                // Done
                $step = 'done';
            } catch (PDOException $e) {
                $error = 'Koneksi Database Gagal: ' . $e->getMessage();
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installer — ShawirIOT Platform</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .installer-container {
      max-width: 680px;
      margin: 3rem auto;
      padding: 0 1rem;
    }
    .req-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.6rem 0.85rem;
      border-bottom: 1px solid var(--border-light);
      font-size: 0.85rem;
    }
    .req-item:last-child { border-bottom: none; }
  </style>
</head>
<body>
<div class="installer-container">
  <div class="auth-card" style="max-width: 100%;">
    <div class="auth-logo" style="margin-bottom:1.5rem">
      <div class="logo-mark"><i class="fas fa-magic"></i></div>
      <h1>ShawirIOT Installer</h1>
      <p>Wizard Instalasi 1-Klik Platform IoT</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger mb-2">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($step === 'done' || $isInstalled): ?>
      <!-- SUCCESS SCREEN -->
      <div style="text-align:center;padding:1.5rem 0">
        <div style="width:70px;height:70px;border-radius:50%;background:rgba(16,185,129,0.15);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1rem">
          <i class="fas fa-check-circle"></i>
        </div>
        <h2 style="font-size:1.5rem;margin-bottom:0.5rem">Instalasi Selesai!</h2>
        <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:1.5rem">
          Platform ShawirIOT telah siap digunakan. Database, tabel, dan akun Super Admin berhasil dibuat.
        </p>

        <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-light);border-radius:var(--radius-md);padding:1rem;margin-bottom:1.5rem;text-align:left;font-size:0.85rem">
          <div><strong>Catatan Keamanan:</strong> File <code>installed.lock</code> telah dibuat untuk mencegah instalasi ulang. Untuk menginstal ulang, hapus file <code>installed.lock</code> secara manual.</div>
        </div>

        <div style="display:flex;gap:1rem;justify-content:center">
          <a href="login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Masuk Sekarang</a>
          <a href="index.php" class="btn btn-secondary btn-lg"><i class="fas fa-home"></i> Halaman Utama</a>
        </div>
      </div>

    <?php else: ?>
      <!-- REQUIREMENT CHECK & FORM -->
      <div class="card mb-2" style="background:rgba(255,255,255,0.02)">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem;color:var(--primary-light)">
          <i class="fas fa-check-double"></i> Pemeriksaan Server
        </h3>
        <?php foreach ($requirements as $label => $passed): ?>
          <div class="req-item">
            <span><?= $label ?></span>
            <span class="badge <?= $passed ? 'badge-online' : 'badge-offline' ?>">
              <?= $passed ? '✓ OK' : '✗ Tidak Tersedia' ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$allRequirementsPassed): ?>
        <div class="alert alert-warning mb-2">
          <i class="fas fa-exclamation-triangle"></i> Beberapa persyaratan server belum terpenuhi. Silakan aktifkan ekstensi yang dibutuhkan di PHP / aaPanel Anda.
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="install-form">
        <input type="hidden" name="action" value="install">

        <!-- 1. Database Configuration -->
        <h4 style="font-size:0.95rem;color:var(--primary-light);margin:1.25rem 0 0.75rem">
          <i class="fas fa-database"></i> 1. Pengaturan Database MySQL
        </h4>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:0.75rem">
          <div class="form-group">
            <label class="form-label">Host Database</label>
            <input type="text" name="db_host" class="form-control" value="localhost" required>
          </div>
          <div class="form-group">
            <label class="form-label">Port</label>
            <input type="number" name="db_port" class="form-control" value="3306" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nama Database</label>
          <input type="text" name="db_name" class="form-control" value="shawiriot" required>
          <div class="form-hint">Database akan dibuat otomatis jika belum ada</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
          <div class="form-group">
            <label class="form-label">Username MySQL</label>
            <input type="text" name="db_user" class="form-control" value="root" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password MySQL</label>
            <input type="password" name="db_pass" class="form-control" placeholder="Kosongkan jika tanpa password">
          </div>
        </div>

        <!-- 2. Platform Configuration -->
        <h4 style="font-size:0.95rem;color:var(--primary-light);margin:1.25rem 0 0.75rem">
          <i class="fas fa-globe"></i> 2. Pengaturan Platform & Domain
        </h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
          <div class="form-group">
            <label class="form-label">Nama Platform</label>
            <input type="text" name="platform_name" class="form-control" value="ShawirIOT" required>
          </div>
          <div class="form-group">
            <label class="form-label">URL Web Platform</label>
            <input type="url" name="platform_url" class="form-control" value="<?= htmlspecialchars($autoUrl) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">URL WebSocket (Daemon)</label>
          <input type="text" name="ws_url" class="form-control" value="ws://<?= parse_url($autoUrl, PHP_URL_HOST) ?: 'localhost' ?>:8080">
          <div class="form-hint">Port WebSocket default: 8080 (fallback ke HTTP polling jika belum aktif)</div>
        </div>

        <!-- 3. Super Admin Account -->
        <h4 style="font-size:0.95rem;color:var(--primary-light);margin:1.25rem 0 0.75rem">
          <i class="fas fa-user-shield"></i> 3. Akun Super Admin
        </h4>
        <div class="form-group">
          <label class="form-label">Nama Lengkap Admin</label>
          <input type="text" name="admin_name" class="form-control" value="Super Admin" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
          <div class="form-group">
            <label class="form-label">Email Super Admin</label>
            <input type="email" name="admin_email" class="form-control" value="admin@shawiriot.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password Super Admin</label>
            <input type="password" name="admin_pass" class="form-control" placeholder="Minimal 8 karakter" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg mt-2" id="btn-submit-install"
          <?= !$allRequirementsPassed ? 'disabled' : '' ?>>
          <span class="btn-text"><i class="fas fa-play"></i> Mulai Instalasi Sekarang</span>
          <span class="btn-loading d-none"><span class="spinner"></span> Menginstal Database & Mengkonfigurasi...</span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
const form = document.getElementById('install-form');
if (form) {
  form.addEventListener('submit', () => {
    const btn = document.getElementById('btn-submit-install');
    btn.querySelector('.btn-text').classList.add('d-none');
    btn.querySelector('.btn-loading').classList.remove('d-none');
    btn.disabled = true;
  });
}
</script>
</body>
</html>
