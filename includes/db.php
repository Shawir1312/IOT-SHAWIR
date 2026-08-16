<?php
/**
 * ShawirIOT Platform - Database Connection (PDO)
 */

require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                error_log('Database connection failed: ' . $e->getMessage());
                die(json_encode([
                    'success' => false,
                    'message' => 'Koneksi database gagal. Periksa konfigurasi di includes/config.php.',
                    'error_detail' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE));
            }
        }
        return self::$instance;
    }

    /**
     * Shortcut untuk query
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Ambil satu baris
     */
    public static function row(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Ambil semua baris
     */
    public static function rows(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Ambil nilai satu kolom
     */
    public static function value(string $sql, array $params = []): mixed {
        $result = self::query($sql, $params)->fetchColumn();
        return $result;
    }

    /**
     * Insert dan ambil last insert ID
     */
    public static function insert(string $sql, array $params = []): string {
        self::query($sql, $params);
        return self::getInstance()->lastInsertId();
    }

    /**
     * Count rows
     */
    public static function count(string $table, string $where = '1=1', array $params = []): int {
        return (int) self::value("SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $params);
    }
}
