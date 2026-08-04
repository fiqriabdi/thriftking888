<?php
/**
 * File: Models/cart.php
 */

class CartModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Menambahkan atau memperbarui item di keranjang database
     */
    public function saveItem($userId, $variantId, $qty) {
        // [PERBAIKAN] Untuk toko thrifting, item bersifat unik. Jangan tambahkan kuantitas.
        // Gunakan INSERT IGNORE untuk mengabaikan penambahan jika item sudah ada di keranjang.
        // Jumlah (quantity) selalu 1.
        $stmt = mysqli_prepare($this->db, "INSERT IGNORE INTO carts (user_id, product_variant_id, jumlah) VALUES (?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $variantId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * Mengambil semua item keranjang untuk user tertentu dengan detail produk
     */
    public function getItems($userId) {
        $sql = "SELECT pv.id as product_variant_id, pv.product_id, p.nama_produk, pv.harga_jual, pv.stok, p.weight, 
                       c.jumlah, pv.varian_warna, pv.varian_ukuran, pi.nama_foto as gambar_utama
                FROM carts c
                JOIN product_variants pv ON c.product_variant_id = pv.id
                JOIN products p ON pv.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                WHERE c.user_id = ?";
        
        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[$row['product_variant_id']] = $row;
        }
        mysqli_stmt_close($stmt);
        return $items;
    }

    /**
     * Menghapus satu item dari keranjang database
     */
    public function removeItem($userId, $variantId) {
        $stmt = mysqli_prepare($this->db, "DELETE FROM carts WHERE user_id = ? AND product_variant_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $variantId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * Mengosongkan keranjang database
     */
    public function clear($userId) {
        $stmt = mysqli_prepare($this->db, "DELETE FROM carts WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * Menggabungkan keranjang sesi ke database (Digunakan saat login)
     */
    public function mergeCart($userId, $sessionCart) {
        if (empty($sessionCart)) return;
        
        foreach ($sessionCart as $variantId => $item) {
            $this->saveItem($userId, $variantId, $item['jumlah']);
        }
    }
}