<?php
/**
 * File: scripts/generate_hash.php
 * Deskripsi: Skrip command-line untuk membuat hash password BCRYPT.
 * Keamanan: JANGAN letakkan file ini di dalam folder 'public'.
 * Penggunaan dari CMD: php C:\xampp\htdocs\thriftking888\scripts\generate_hash.php "password_baru_anda"
 */

if (php_sapi_name() !== 'cli') {
    die("Skrip ini hanya dapat dijalankan melalui Command Line Interface (CLI).");
}

if (empty($argv[1])) {
    echo "Gagal: Password baru tidak diberikan.\n";
    echo "Penggunaan: php " . basename(__FILE__) . " \"password_baru_anda\"\n";
    exit(1);
}

$newPassword = $argv[1];

$options = [
    'cost' => 12, // Sesuaikan 'cost' dengan yang digunakan di aplikasi Anda
];

$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, $options);

if ($hashedPassword === false) {
    echo "Terjadi kesalahan saat membuat hash.\n";
    exit(1);
}

echo "=========================================================\n";
echo "Password Baru : " . $newPassword . "\n";
echo "Hash BCRYPT   : " . $hashedPassword . "\n";
echo "=========================================================\n";
echo "Salin (copy) seluruh baris Hash BCRYPT di atas untuk digunakan di database.\n";