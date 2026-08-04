<?php
require_once __DIR__ . '/../../Models/transaksi.php';
require_once __DIR__ . '/../../helpers/Loggable.php';

class PesananController {
    use Loggable;
    private $model;
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
        $this->model = new Transaksi($conn); // Kirim koneksi ke model transaksi
    }

    /**
     * Mengambil semua pesanan untuk ditampilkan di dashboard admin
     */
    public function index($page = 1, $limit = 15) {
        $page = max(1, intval($page));
        $limit = max(1, intval($limit));

        return $this->model->getAllOrdersAdmin($page, $limit);
    }

    /**
     * Melihat detail satu pesanan secara spesifik
     */
    public function show($id) {
        $order = $this->model->getOrderById($id);
        if ($order) {
            $order['items'] = $this->model->getOrderItems($id);
        }
        return $order;
    }

    /**
     * Mengambil semua pesanan yang dibatalkan untuk halaman khusus
     */
    public function getCancelledOrders($page = 1, $limit = 15) {
        $page = max(1, intval($page));
        $limit = max(1, intval($limit));

        return $this->model->getAllOrdersAdmin($page, $limit, 'cancelled');
    }

    /**
     * Menangani perubahan status pesanan (Konfirmasi, Kirim, Batal)
     */
    public function updateStatus($id, $status) {
        $id = intval($id);
        // Model 'transaksi' sekarang menangani semua logika stok (pengurangan & restorasi) secara internal
        $result = $this->model->updateOrderStatus($id, $status);
        
        if ($result) {
            $this->logActivity("UPDATE_ORDER_STATUS", "Mengubah status pesanan #$id menjadi $status");
            
            // Di sini Anda juga bisa menambahkan logika pengiriman email notifikasi otomatis
            // ke pelanggan jika status berubah menjadi 'shipped' atau 'cancelled'.
        }
        
        return $result;
    }
}
