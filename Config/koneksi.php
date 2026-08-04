<?php
/**
 * File: Config/koneksi.php
 * Konfigurasi Koneksi Database Terpusat dengan Support untuk Dependency Injection
 */

// Lapisi keamanan atau definisikan APP_ROOT jika belum ada
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..') ?: __DIR__ . '/..');
}

// Load environment variables dari .env file
function loadEnv($envPath = null) {
    if ($envPath === null) {
        $envPath = __DIR__ . '/../.env'; 
    }
    
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

loadEnv();

// --- OTOMATISASI FOLDER LOG ---
$logPath = getenv('LOG_FILE') ?: 'storage/logs/app.log';
$logDir = APP_ROOT . '/' . dirname($logPath);

if (!is_dir($logDir)) {
    // Membuat folder secara rekursif (true) dengan permission 0755
    mkdir($logDir, 0755, true);
    // Opsional: Buat file .gitignore di dalam folder logs agar log tidak ter-commit ke Git
    file_put_contents($logDir . '/.gitignore', "*\n!.gitignore");
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

// Konfigurasi Database
$host     = env('DB_HOST', 'localhost');
$port     = env('DB_PORT', '3306');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$database = env('DB_NAME', 'thriftking888');
$appEnv   = env('APP_ENV', 'development');

// Mengaktifkan pelaporan error mysqli untuk catching exception
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Membuat koneksi ke MySQL
try {
    $conn = mysqli_connect($host, $username, $password, $database, $port);
    Database::setInternalConnection($conn);
    // Set waktu lokal & Charset
    date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));
    mysqli_set_charset($conn, env('APP_CHARSET', 'utf8mb4'));
} catch (mysqli_sql_exception $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die($appEnv === 'development' ? "DB Error: " . $e->getMessage() : "Maaf, sistem sedang gangguan.");
}

/**
 * Kelas Database untuk mendukung Dependency Injection.
 * Digunakan agar Controller/Model tidak bergantung pada variabel global.
 */
class Database {
    private static $connection = null;

    public static function setInternalConnection($conn) {
        self::$connection = $conn;
    }

    public static function getConnection() {
        if (self::$connection === null) {
            throw new Exception("Koneksi database tidak tersedia.");
        }
        return self::$connection;
    }
}
?>