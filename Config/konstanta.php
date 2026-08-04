<?php
/**
 * File: Config/konstanta.php
 */

if (!defined('APP_ROOT')) {
    $appRoot = realpath(__DIR__ . '/..');
    if ($appRoot === false) {
        $appRoot = __DIR__ . '/..';
    }
    define('APP_ROOT', $appRoot);
}

// Mengambil fungsi loadEnv agar getenv() di bawah ini tidak bernilai kosong/false
if (file_exists(APP_ROOT . '/Config/koneksi.php')) {
    // Memanggil koneksi secara otomatis memuat fungsi loadEnv()
    require_once APP_ROOT . '/Config/koneksi.php'; 
}

// Deteksi otomatis URL dasar (BASE_URL)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// Memastikan BASE_URL dinamis atau mengambil fallback dari .env jika diatur manual
if (!defined('BASE_URL')) {
    $projectDir = rtrim(dirname($scriptName), '/\\');
    if ($projectDir === '/' || $projectDir === '.') {
        $projectDir = '';
    }
    
    // Jika di .env didefinisikan APP_URL, gunakan itu. Jika tidak, gunakan deteksi otomatis script.
    $envUrl = getenv('APP_URL');
    if ($envUrl) {
        define('BASE_URL', rtrim($envUrl, '/') . '/');
    } else {
        define('BASE_URL', rtrim($scheme . '://' . $host . $projectDir, '/') . '/');
    }
}

// Mengambil nama aplikasi langsung dari file .env Anda
if (!defined('APP_NAME')) {
    define('APP_NAME', getenv('APP_NAME') ?: 'ThriftKing888');
}