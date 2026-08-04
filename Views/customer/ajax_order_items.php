<?php
/**
 * File: Views/customer/ajax_order_items.php
 * Endpoint API untuk mengambil item dalam sebuah pesanan.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../Config/konstanta.php';
require_once __DIR__ . '/../../Middleware/auth.php';
auth::requireRole('pelanggan');

try {
    require_once APP_ROOT . '/Config/koneksi.php';
    require_once APP_ROOT . '/Models/transaksi.php';

    $db = Database::getConnection();
    $user_id = intval($_SESSION['user']['id']);
    
    // Ambil ID dari Router.php, yang akan mengekstrak :id menjadi variabel $id
    $order_id = intval($id ?? 0);

    if ($order_id <= 0) {
        throw new Exception("ID Pesanan tidak valid.");
    }

    $transaksiModel = new transaksi($db);
    
    // Validasi kepemilikan pesanan
    $order_header = $transaksiModel->getOrderById($order_id);
    if (!$order_header || $order_header['user_id'] !== $user_id) {
        http_response_code(403); // Forbidden
        throw new Exception("Akses ditolak.");
    }

    $items = $transaksiModel->getOrderItems($order_id);

    echo json_encode(['success' => true, 'items' => $items]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;