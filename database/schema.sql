-- ============================================================
-- ShawirIOT Platform - Database Schema
-- Version: 1.0.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS nusaiot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nusaiot;

-- ============================================================
-- Table: plans (Paket langganan)
-- ============================================================
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

-- Default plans
INSERT INTO `plans` (`name`, `slug`, `credits_required`, `max_devices`, `max_widgets_per_device`, `history_days`, `description`) VALUES
('Free', 'free', 0, 1, 5, 1, 'Paket gratis untuk memulai'),
('Basic', 'basic', 100, 5, 20, 7, 'Cocok untuk proyek kecil'),
('Pro', 'pro', 300, 20, 100, 30, 'Untuk developer serius'),
('Enterprise', 'enterprise', 1000, 9999, 9999, 365, 'Unlimited untuk bisnis');

-- ============================================================
-- Table: users
-- ============================================================
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

-- Default superadmin
INSERT INTO `users` (`name`, `email`, `password`, `role`, `plan_id`, `credits`, `is_active`, `email_verified_at`) VALUES
('Super Admin', 'admin@nusaiot.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 4, 99999, 1, NOW());
-- Password default: 'password' (ganti setelah install!)

-- ============================================================
-- Table: devices
-- ============================================================
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

-- ============================================================
-- Table: virtual_pins (Nilai pin saat ini)
-- ============================================================
CREATE TABLE IF NOT EXISTS `virtual_pins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT UNSIGNED NOT NULL,
  `pin` VARCHAR(10) NOT NULL COMMENT 'e.g. V0, V1, D0, A0',
  `value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `device_pin` (`device_id`, `pin`),
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: pin_history (Histori data sensor)
-- ============================================================
CREATE TABLE IF NOT EXISTS `pin_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT UNSIGNED NOT NULL,
  `pin` VARCHAR(10) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_device_pin_time` (`device_id`, `pin`, `recorded_at`),
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: dashboards
-- ============================================================
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

-- ============================================================
-- Table: widgets
-- ============================================================
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

-- ============================================================
-- Table: credit_transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS `credit_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` INT NOT NULL COMMENT 'Positif = tambah, negatif = kurang',
  `type` ENUM('topup','spend','refund','admin_grant','admin_deduct','plan_upgrade') NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `admin_id` INT UNSIGNED DEFAULT NULL COMMENT 'Admin yang melakukan transaksi',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: websocket_connections (Tracking koneksi aktif)
-- ============================================================
CREATE TABLE IF NOT EXISTS `websocket_connections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `connection_id` VARCHAR(64) NOT NULL UNIQUE,
  `type` ENUM('device','client') NOT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `connected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_ping` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: platform_settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `platform_settings` (`key`, `value`) VALUES
('platform_name', 'ShawirIOT'),
('platform_tagline', 'Platform IoT Modern ShawirIOT'),
('platform_email', 'admin@shawiriot.com'),
('allow_registration', '1'),
('max_free_devices', '1'),
('websocket_port', '8080'),
('data_retention_days', '365');
