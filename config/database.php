
<?php
// =====================================================
// config/database.php
// InfinityFree Compatible
// =====================================================

if (!defined('DB_HOST')) {
    define('DB_HOST', 'sql110.infinityfree.com');
    define('DB_USER', 'if0_40706792');
    define('DB_PASS', 'NewTree1');
    define('DB_NAME', 'if0_40706792_shopbd');
    define('DB_CHARSET', 'utf8mb4');
}

// App Settings
define('APP_NAME', 'সমিতি ম্যানেজার');
define('APP_URL', 'https://shopbd.free.nf');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Dhaka');

date_default_timezone_set(TIMEZONE);

// Session Config
define('SESSION_NAME', 'somity_session');
define('SESSION_LIFETIME', 7200);

// Upload Config
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 2097152);
define('ALLOWED_IMG', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOC', ['pdf', 'jpg', 'jpeg', 'png']);

// Pagination
define('RECORDS_PER_PAGE', 15);

// Backup Configuration
define('BACKUP_DIR', __DIR__ . '/../temp_backups/');
define('BACKUP_EMAIL', 'youremail@gmail.com');
define('BACKUP_RETENTION_DAYS', 30);

// Do not create or expose backup files automatically here.
// Create the directory and protect it separately.

// =====================================================
// DATABASE CLASS
// =====================================================

class Database
{
    private static $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        $this->connect();
    }

    private function connect(): void
    {
        $connection = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($connection->connect_errno) {
            error_log(
                'Database connection failed: ' .
                $connection->connect_error
            );

            throw new RuntimeException(
                'ডেটাবেস সংযোগ ব্যর্থ হয়েছে।'
            );
        }

        if (!$connection->set_charset(DB_CHARSET)) {
            error_log(
                'Database charset error: ' .
                $connection->error
            );
        }

        // Saudi Arabia/Bangladesh time handling:
        // Keep database session timezone at UTC+06:00.
        $connection->query("SET time_zone = '+06:00'");

        $this->connection = $connection;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        // mysqli::ping() is deprecated.
        // Return the current connection without ping().
        return $this->connection;
    }

    public function query(string $sql): mysqli_result|bool
    {
        $result = $this->getConnection()->query($sql);

        if ($result === false) {
            error_log(
                'Database query failed: ' .
                $this->connection->error
            );
        }

        return $result;
    }

    public function prepare(string $sql): mysqli_stmt|false
    {
        $statement = $this->getConnection()->prepare($sql);

        if ($statement === false) {
            error_log(
                'Database prepare failed: ' .
                $this->connection->error
            );
        }

        return $statement;
    }

    public function escape(string $value): string
    {
        return $this->getConnection()->real_escape_string($value);
    }

    public function lastInsertId(): int
    {
        return $this->connection->insert_id;
    }

    public function affectedRows(): int
    {
        return $this->connection->affected_rows;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->begin_transaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollback();
    }
}

// =====================================================
// GLOBAL DATABASE HELPERS
// =====================================================

function db(): Database
{
    return Database::getInstance();
}

function conn(): mysqli
{
    return Database::getInstance()->getConnection();
}
