<?php
/**
 * Maruf Traders - Database Connection Configuration
 * PDO Connection Handler with UTF8MB4 and Transaction Support
 */

defined('APP_INIT') or define('APP_INIT', true);

class Database {
    private static ?PDO $instance = null;
    
    // Database Configuration Defaults
    private static string $host = 'localhost';
    private static string $port = '3306';
    private static string $dbname = 'dealership_saas';
    private static string $username = 'root';
    private static string $password = '';
    private static string $charset = 'utf8mb4';

    /**
     * Get singleton PDO Database connection
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Load overrides from environment if present
            $host = getenv('DB_HOST') ?: self::$host;
            $port = getenv('DB_PORT') ?: self::$port;
            $dbname = getenv('DB_NAME') ?: self::$dbname;
            $username = getenv('DB_USER') ?: self::$username;
            $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::$password;
            $charset = self::$charset;

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // Return clear error message in JSON if AJAX or throw
                if (defined('IS_AJAX') && IS_AJAX) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Database connection error. Please contact the administrator.'
                    ]);
                    exit;
                }
                throw new PDOException("Database connection error: " . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }

    /**
     * Set a custom PDO instance (useful for testing or migrations)
     */
    public static function setConnection(PDO $pdo): void {
        self::$instance = $pdo;
    }

    /**
     * Reset connection instance
     */
    public static function resetConnection(): void {
        self::$instance = null;
    }
}
