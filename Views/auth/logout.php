<?php
require_once APP_ROOT . '/Middleware/auth.php';

// Menggunakan Middleware/auth sebagai Single Source of Truth untuk logout.
// Kita mengarahkan kembali ke halaman login setelah logout.
auth::logout('auth/login');