<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Thriftking888 | Curated Thrifting Collection';

require_once __DIR__ . '/../../Config/konstanta.php';
$base_url = defined('BASE_URL') ? BASE_URL : '';
require_once APP_ROOT . '/helpers/Format.php'; // Memastikan formatRupiah tersedia

$isLoggedIn = isset($_SESSION['user']);
$userRole = $isLoggedIn ? ($_SESSION['user']['role'] ?? null) : null;
$userDisplayName = 'User';
if ($isLoggedIn && !empty($_SESSION['user']['nama'])) {
    $names = explode(' ', trim($_SESSION['user']['nama']));
    $userDisplayName = $names[0] ?: 'User';
}

$notif_count = 0;
$review_pending_count = 0;
if ($isLoggedIn && $userRole === 'pelanggan' && isset($conn)) {
    // Ambil Model Transaksi untuk Notifikasi Pesanan Aktif
    require_once APP_ROOT . '/Models/transaksi.php';
    $m_transaksi = new transaksi($conn); 
    $notif_count = $m_transaksi->hitungNotifPelanggan(intval($_SESSION['user']['id']));
    $review_pending_count = $m_transaksi->hitungPendingReview(intval($_SESSION['user']['id']));

    // Sinkronkan Keranjang dari DB ke Session agar Badge di Navbar selalu Akurat di seluruh halaman
    require_once APP_ROOT . '/Models/Cart.php';
    $cartModel = new CartModel($conn);
    $_SESSION['keranjang'] = $cartModel->getItems($_SESSION['user']['id']);
}

// [PERBAIKAN] Untuk toko thrifting, hitung jumlah item unik, bukan total kuantitas.
$cart_item_count = (!empty($_SESSION['keranjang']) && is_array($_SESSION['keranjang'])) ? count($_SESSION['keranjang']) : 0;

// [DITAMBAHKAN] Ambil pengaturan global dan kategori untuk digunakan di seluruh situs
$global_categories = [];
$global_settings = [];
if (isset($conn)) {
    $q_cats = mysqli_query($conn, "SELECT nama_kategori, slug FROM categories ORDER BY nama_kategori ASC");
    if ($q_cats) {
        $global_categories = mysqli_fetch_all($q_cats, MYSQLI_ASSOC);
    }
    $q_settings = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
    if ($q_settings) {
        $global_settings = mysqli_fetch_assoc($q_settings) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <base href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-pWU8m5XY1V8kY1uUMu0ZWxa6CqU1nr0JTHXzDWV8OjPxsy2xGF6jXSEo68B0uK8ffXlKC8vR3UObUaVv+z9J0A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@700;800;900&family=Tenor+Sans&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/site.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #222222;
            --accent-color: #d9534f; /* Soft Red untuk Harga */
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--primary-color);
            background-color: #ffffff;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, .nav-link {
            font-family: 'Tenor Sans', sans-serif;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f8f9fa; /* Warna track yang sangat terang/hampir putih */
        }
        ::-webkit-scrollbar-thumb {
            background: #888; /* Warna gagang abu-abu medium */
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555; /* Lebih gelap saat hover */
        }

        /* [DIUBAH] Tombol Scroll Atas & Bawah */
        .scroll-btn {
            position: fixed;
            right: 25px;
            display: none; /* Sembunyi secara default */
            width: 40px;
            height: 40px;
            background-color: rgba(0, 0, 0, 0.7);
            color: #fff;
            border: none;
            border-radius: 8px;
            text-align: center;
            font-size: 1rem;
            line-height: 40px;
            z-index: 1050;
            transition: opacity 0.3s, visibility 0.3s;
            opacity: 0;
            visibility: hidden;
        }
        .scroll-btn:hover { background-color: #000; color: #fff; }
        .scroll-btn.show { display: block; opacity: 1; visibility: visible; }

        .scroll-to-top { bottom: 75px; }

        /* Animasi Pulse untuk Notifikasi */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .animate-pulse {
            animation: pulse 2s infinite;
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7);
        }

        /* Responsivitas Zoom & Skala */
        img { max-width: 100%; height: auto; }
        
        /* Mencegah layout overflow saat font diperbesar browser */
        .container {
            overflow-wrap: break-word;
        }
    </style>
</head>
<body>