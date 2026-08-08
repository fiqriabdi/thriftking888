<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Config/konstanta.php';
require_once __DIR__ . '/../Config/koneksi.php';

// Muat semua library Composer secara global
if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Router/Router.php';

// Muat semua controller yang dibutuhkan di sini agar dikenali secara global
require_once __DIR__ . '/../Controllers/Auth/AuthController.php';

// Do not change the current working directory here.
// Views and included files should resolve using APP_ROOT or __DIR__ instead.

// 1. Ambil path URL secara bersih
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 2. POTONG BASE URL SUBFOLDER SECARA DINAMIS & AMAN
// Cek apakah aplikasi berjalan di subfolder (development) atau root (production)
$baseFolder = str_replace('/public', '', dirname($_SERVER['SCRIPT_NAME']));
if ($baseFolder !== '/' && $baseFolder !== '\\') {
    // Aplikasi berjalan di subfolder, bersihkan dari URI
    $uri = preg_replace('#^' . preg_quote($baseFolder) . '#', '', $uri);
}

// 3. Bersihkan slash di awal dan akhir string
$uri = trim($uri, '/');

// --- REGISTRASI RUTE DINAMIS ---
Router::get('', 'shop/beranda.php'); // Rute default untuk root
Router::get('index.php', 'shop/beranda.php'); // Rute untuk index.php (jika diakses langsung)

// Rute Toko (Shop)
Router::get('produk', 'shop/produk.php');
Router::get('produk.php', 'shop/produk.php'); // Menyesuaikan dengan nama baru
Router::get('produk/:kategori', 'shop/produk.php'); // Menyesuaikan dengan nama baru

// Contoh rute dinamis dengan parameter :id
Router::get('detail/:id', 'shop/detail_produk.php'); // Rute baru untuk detail produk
Router::get('detail.php', 'shop/detail_produk.php'); // Tetap support rute lama untuk sementara

// Rute Otentikasi
Router::get('auth/login', 'auth/login.php'); // Tampilkan form login
Router::post('auth/login', 'auth/login.php'); // Proses login
Router::get('auth/register', 'auth/register.php'); // Tampilkan form register
Router::post('auth/register', 'auth/register.php'); // Proses register
Router::get('auth/forgot-password', 'auth/forgot_password.php'); // Tampilkan form lupa password
Router::post('auth/forgot-password', 'auth/forgot_password.php'); // Proses permintaan reset
Router::post('auth/request-manual-reset', 'auth/forgot_password.php'); // [DITAMBAHKAN] Endpoint AJAX untuk notifikasi manual
Router::get('auth/reset-password/:token', 'auth/reset_password.php'); // Tampilkan form reset password dengan token
Router::post('auth/reset-password/:token', 'auth/reset_password.php'); // Proses reset password
Router::get('auth/logout', 'auth/logout.php'); // Proses logout

// Rute Pelanggan
Router::get('pelanggan/keranjang', 'cart/keranjang.php');
Router::post('pelanggan/keranjang', 'cart/keranjang.php');
Router::get('pelanggan/checkout', 'cart/checkout.php');
Router::post('pelanggan/checkout', 'cart/checkout.php');
Router::post('pelanggan/cek-voucher', 'cart/checkout.php');
Router::get('pelanggan/pembayaran/:id', 'customer/pembayaran.php'); // [DIUBAH] Rute dan file untuk konfirmasi pembayaran
Router::post('pelanggan/pembayaran/:id', 'customer/pembayaran.php'); // [DIUBAH] Rute dan file untuk konfirmasi pembayaran
Router::get('pelanggan/pesanan', 'customer/pesanan.php');
Router::post('pelanggan/pesanan', 'customer/pesanan.php');
Router::get('pelanggan/ulasan/:id', 'customer/ulasan.php'); // Gunakan :id agar konsisten dan aman
Router::get('pelanggan/menunggu-ulasan', 'customer/menunggu_ulasan.php');
Router::post('pelanggan/pesanan/:id/batal', 'customer/pesanan.php'); // <-- RUTE BARU UNTUK PEMBATALAN
Router::get('pelanggan/pesanan/items/:id', 'customer/ajax_order_items.php'); // [DITAMBAHKAN] Endpoint AJAX untuk item pesanan
Router::post('pelanggan/ulasan/:id', 'customer/ulasan.php'); // Tambahkan POST untuk proses simpan
Router::get('pelanggan/ulasan-saya', 'customer/ulasan_saya.php');
Router::get('pelanggan/pesanan/:id', 'customer/detail_pesanan.php'); // Pindah ke folder customer
Router::get('admin/pesanan/detail/:id', 'customer/detail_pesanan.php'); // Rute alias untuk admin, arahkan ke file yang sama
Router::get('pelanggan/ulasan', 'customer/ulasan.php');
Router::post('pelanggan/ulasan', 'customer/ulasan.php'); // Tambahkan POST untuk proses simpan
Router::get('pelanggan/profil', 'customer/profil.php'); // [DIUBAH] Arahkan ke file profil pelanggan
Router::get('pelanggan/profil', 'customer/profil.php'); // [DIUBAH] Alias
Router::post('pelanggan/profil', 'customer/profil.php'); // [DIUBAH] Arahkan POST ke file profil pelanggan

// Rute Admin
Router::get('admin/dashboard', 'admin/dashboard.php');
Router::get('admin/laporan', 'admin/laporan.php');
Router::get('admin/produk', 'admin/produk_index.php');
Router::get('admin/produk/stock-logs', 'admin/stock_logs.php');
Router::get('admin/kategori', 'admin/kategori.php');
Router::post('admin/kategori', 'admin/kategori.php');
Router::get('admin/kategori/edit/:id', 'admin/kategori.php');
Router::post('admin/kategori/edit/:id', 'admin/kategori.php');
Router::post('admin/kategori/delete/:id', 'admin/kategori.php');
Router::post('admin/produk', 'admin/produk_index.php'); // Untuk filter atau aksi POST lainnya
Router::get('admin/produk/create', 'admin/produk_create.php');
Router::post('admin/produk/create', 'admin/produk_create.php');
Router::get('admin/produk/edit/:id', 'admin/produk_edit.php');
Router::post('admin/produk/delete-image/:id', 'admin/produk_edit.php'); // Rute untuk menghapus gambar via AJAX
Router::post('admin/produk/reorder-images', 'admin/produk_edit.php');
Router::post('admin/produk/edit/:id', 'admin/produk_edit.php');
Router::get('admin/produk/cleanup', 'admin/produk_cleanup.php');
Router::post('admin/produk/delete/:id', 'admin/produk_index.php');
Router::get('admin/produk/recycle-bin', 'admin/produk_recycle_bin.php'); // Rute untuk recycle bin
Router::post('admin/produk/restore/:id', 'admin/produk_recycle_bin.php');
Router::post('admin/produk/force-delete/:id', 'admin/produk_recycle_bin.php');
Router::get('admin/pengguna', 'admin/pengguna.php');
Router::post('admin/pengguna', 'admin/pengguna.php'); // [DITAMBAHKAN] Menangani aksi POST (hapus)
Router::get('admin/pengguna.php', 'admin/pengguna.php');
Router::get('admin/pengguna/create', 'admin/pengguna_create.php');
Router::get('admin/pengguna_create.php', 'admin/pengguna_create.php');
Router::post('admin/pengguna/create', 'admin/pengguna_create.php');
Router::post('admin/pengguna_create.php', 'admin/pengguna_create.php');
Router::get('admin/pengguna/edit/:id', 'admin/pengguna_edit.php');
Router::get('admin/pengguna_edit.php', 'admin/pengguna_edit.php');
Router::post('admin/pengguna/edit/:id', 'admin/pengguna_edit.php');
Router::post('admin/pengguna_edit.php', 'admin/pengguna_edit.php');
Router::get('admin/pesanan', 'admin/pesanan.php');
Router::get('admin/pesanan/cetak/:id', 'admin/pesanan_cetak.php');
Router::get('admin/pesanan/dibatalkan', 'admin/pesanan_dibatalkan.php'); // Rute baru
Router::post('admin/pesanan', 'admin/pesanan.php');
Router::get('admin/reset-requests', 'admin/reset_requests.php');
Router::get('admin/konfirmasi-pembayaran', 'admin/konfirmasi_pembayaran.php');
Router::post('admin/konfirmasi-pembayaran', 'admin/konfirmasi_pembayaran.php');
Router::get('admin/settings', 'admin/settings.php');
Router::get('admin/reset-password', 'admin/reset_password.php'); // Rute baru
Router::post('admin/reset-password', 'admin/reset_password.php'); // Rute baru
Router::post('admin/settings', 'admin/settings.php');
Router::get('admin/bank-rekening', 'admin/bank_rekening.php'); // Sudah ada
Router::post('admin/bank-rekening', 'admin/bank_rekening.php'); // Sudah ada
Router::get('admin/ongkir', 'admin/ongkir.php'); // Rute baru
Router::post('admin/ongkir', 'admin/ongkir.php'); // Rute baru
Router::get('admin/ulasan', 'admin/ulasan.php');
Router::post('admin/ulasan', 'admin/ulasan.php');
Router::get('admin/activity-log', 'admin/activity_log.php');
Router::post('admin/activity-log', 'admin/activity_log.php'); // [DITAMBAHKAN] Menangani aksi POST untuk hapus log
Router::get('admin/profil', 'admin/profil.php'); // [DIUBAH] Arahkan ke file profil admin
Router::post('admin/profil', 'admin/profil.php'); // [DIUBAH] Arahkan POST ke file profil admin

// Rute API Notifikasi untuk Polling
Router::get('admin/notifications/check', 'admin/notifications_check.php');
Router::post('admin/notifications/mark-read', 'admin/notifications_mark_read.php');
Router::get('api/counts', 'customer/api_counts.php');

// --- EKSEKUSI ROUTER ---
$route = Router::dispatch($uri);
$viewFile = null;

if ($route) {
    // Jika handler mengandung '@', ini adalah Controller (Enterprise style)
    // Namun karena saat ini Anda menggunakan file View sebagai handler:
    $viewFile = __DIR__ . '/../Views/' . $route['handler'];
    
    // Parameter dari URL (seperti :id) otomatis tersedia di Router::getParam('id')
    // Ini membuat variabel seperti $id, $token, $produk_id tersedia di View
    extract(Router::getParams()); 

    // --- PENANGANAN LOGIKA POST TERPUSAT (CONTOH UNTUK FORGOT PASSWORD) ---
    // Ini adalah cara untuk memisahkan logika dari view.
    if ($route['handler'] === 'auth/forgot_password.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../helpers/Security.php';

        // [DITAMBAHKAN] Pembeda antara form email dan AJAX chat
        if (isset($_POST['email'])) {
            // Logika untuk form "Minta Link Otomatis"
            if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $auth = new authcontroller(Database::getConnection());
                $result = $auth->requestWhatsAppReset($_POST['email']);
                $msg = $result['message'] ?? 'Terjadi kesalahan.';
                $msg_type = $result['success'] ? 'info' : 'danger';
            }
        } elseif (isset($_POST['keterangan'])) {
            // [DITAMBAHKAN] Logika untuk AJAX "Hubungi Admin"
            header('Content-Type: application/json');
            if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $auth = new authcontroller(Database::getConnection());
                $result = $auth->requestAnonymousManualReset($_POST['keterangan']);
                echo json_encode($result);
            }
            exit(); // Hentikan eksekusi setelah menangani AJAX
        }

        // Kirim variabel $msg ke view
        $viewData = ['msg' => $msg, 'msg_type' => $msg_type];
        if (isset($viewData)) {
            extract($viewData);
        }
    }

} elseif (isset($_GET['halaman'])) { // Fallback untuk sistem lama berbasis ?halaman=xxx
    // Fallback untuk sistem lama berbasis ?halaman=xxx
    $halaman = preg_replace('/[^a-z0-9_\/]/i', '', $_GET['halaman']);
    $viewFile = __DIR__ . '/../Views/' . $halaman . '.php';
}

if (!$viewFile || !file_exists($viewFile)) {
    http_response_code(404);
    echo '<h1>404 - Halaman tidak ditemukan</h1>';
    exit;
}

// KIRIM KONEKSI KE VIEW / CONTROLLER
// Anda bisa menggunakan variabel global $conn yang sudah di-load di atas
// atau langsung gunakan variabel $conn di sini.


include $viewFile;
