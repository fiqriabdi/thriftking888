<?php
/**
 * File: Views/customer/api_counts.php
 * Endpoint API internal untuk sinkronisasi badge navbar secara real-time.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Keamanan: Hanya untuk role pelanggan
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelanggan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../../Config/koneksi.php';
    require_once __DIR__ . '/../../Models/transaksi.php';
    require_once __DIR__ . '/../../Models/cart.php';

    $conn = Database::getConnection();
    $user_id = intval($_SESSION['user']['id']);

    $m_transaksi = new transaksi($conn);
    $m_cart = new CartModel($conn);

    // Hitung data real-time menggunakan model yang sudah ada
    $notif_count = $m_transaksi->hitungNotifPelanggan($user_id);
    $review_count = $m_transaksi->hitungPendingReview($user_id);
    $cart_items = $m_cart->getItems($user_id);

    // [PERBAIKAN] Untuk toko thrifting, hitung jumlah item unik, bukan total kuantitas.
    $cart_count = is_array($cart_items) ? count($cart_items) : 0;

    echo json_encode([
        'success'      => true,
        'notif_count'  => intval($notif_count),
        'review_count' => intval($review_count),
        'cart_count'   => $cart_count
    ]);

} catch (Throwable $e) {
    error_log("API Counts Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}