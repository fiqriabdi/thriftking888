<?php
require_once __DIR__ . '/../../Models/Produk.php';
require_once __DIR__ . '/../../Models/Cart.php';
require_once __DIR__ . '/../../helpers/Format.php';

class KeranjangController {
    private $produkModel;
    private $cartModel;
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
        $this->produkModel = new produk($conn);
        $this->cartModel = new CartModel($conn);
    }

    /**
     * Menambahkan produk ke keranjang (Sesi & Database)
     */
    public function addToCart($userId, $productId, $variantId = null) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Ambil detail produk dan varian
        // Pastikan model produk mengembalikan 'product_variant_id' atau kita handle keduanya
        $produk_varian = $this->produkModel->getByIdWithVariantAndImage($productId, $variantId);
        
        // Cek product_variant_id (alias baru) atau variant_id (alias lama/fallback)
        $vId = $produk_varian['product_variant_id'] ?? $produk_varian['variant_id'] ?? null;

        if ($produk_varian && $vId && $produk_varian['stok'] > 0) {
            $key = $vId;
            if (!isset($_SESSION['keranjang'])) $_SESSION['keranjang'] = [];

            if (isset($_SESSION['keranjang'][$key])) {
                $_SESSION['keranjang'][$key]['jumlah'] += 1;
            } else {
                $_SESSION['keranjang'][$key] = [
                    'product_id'         => $produk_varian['id'],
                    'product_variant_id' => $key,
                    'nama_produk'        => $produk_varian['nama_produk'],
                    'varian_warna'       => $produk_varian['varian_warna'],
                    'varian_ukuran'      => $produk_varian['varian_ukuran'],
                    'harga_jual'         => $produk_varian['harga_jual'],
                    'gambar_utama'       => $produk_varian['gambar_utama'],
                    'jumlah'             => 1
                ];
            }

            if ($userId) {
                $this->cartModel->saveItem($userId, $key, 1);
            }
            return true;
        }
        return false;
    }

    /**
     * Menghapus item dan mengembalikan data JSON untuk AJAX
     */
    public function ajaxRemoveItem($userId, $variantId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Hapus dari Sesi
        if (isset($_SESSION['keranjang'][$variantId])) {
            unset($_SESSION['keranjang'][$variantId]);
        }

        // 2. Hapus dari Database jika login
        if ($userId) {
            $this->cartModel->removeItem($userId, $variantId);
        }

        return [
            'success' => true,
            'cart_summary' => $this->_getCartSummary($userId)
        ];
    }

    /**
     * Mengosongkan keranjang
     */
    public function ajaxClearCart($userId) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        unset($_SESSION['keranjang']);
        if ($userId) {
            $this->cartModel->clear($userId);
        }

        return ['success' => true, 'cart_count' => 0];
    }

    /**
     * Memperbarui kuantitas item via AJAX
     */
    public function ajaxUpdateQty($userId, $variantId, $newQty) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $newQty = max(1, intval($newQty));

        // 1. Update Sesi
        if (isset($_SESSION['keranjang'][$variantId])) {
            $_SESSION['keranjang'][$variantId]['jumlah'] = $newQty;
        }

        // 2. Update Database jika login
        if ($userId) {
            $this->cartModel->updateQuantity($userId, $variantId, $newQty);
        }

        // 3. Siapkan data untuk respons
        $newSubtotal = 0;
        $finalQty = 0;
        if (isset($_SESSION['keranjang'][$variantId])) {
            $item = $_SESSION['keranjang'][$variantId];
            $finalQty = (int)$item['jumlah'];
            $newSubtotal = (int)$item['harga_jual'] * $finalQty;
        }

        return [
            'success' => true,
            'new_qty' => $finalQty,
            'new_subtotal' => $newSubtotal,
            'cart_summary' => $this->_getCartSummary($userId)
        ];
    }

    public function handleRequest($userId, $getData) {
        if (isset($getData['tambah'])) {
            $this->addToCart($userId, intval($getData['tambah']), $getData['variant_id'] ?? null);
        } elseif (isset($getData['hapus'])) {
            $this->ajaxRemoveItem($userId, intval($getData['hapus']));
        } elseif (isset($getData['bersihkan'])) {
            $this->ajaxClearCart($userId);
        }
    }

    public function index($userId) {
        if ($userId) {
            $dbItems = $this->cartModel->getItems($userId);
            $restructuredItems = [];
            
            // Petakan ulang agar key session adalah product_variant_id
            foreach ($dbItems as $item) {
                $vId = $item['product_variant_id'];
                // Validasi One-of-One: Tandai jika stok habis
                $item['is_available'] = ($item['stok'] > 0);
                $restructuredItems[$vId] = $item;
            }
            
            $_SESSION['keranjang'] = $restructuredItems;
            return $restructuredItems;
        }
        return $_SESSION['keranjang'] ?? [];
    }


    /**
     * Helper terpusat untuk menghitung ringkasan keranjang.
     */
    private function _getCartSummary($userId) {
        // Sinkronisasi ulang dari DB jika user login, untuk data paling akurat
        if ($userId) {
            $this->index($userId);
        }

        $total_belanja = 0;
        $total_items_count = 0;

        foreach (($_SESSION['keranjang'] ?? []) as $item) {
            // Hanya hitung item yang stoknya tersedia
            if (!empty($item['is_available'])) {
                $total_belanja += (int)$item['harga_jual'] * (int)$item['jumlah'];
            }
            $total_items_count += (int)$item['jumlah'];
        }

        return [
            'total_belanja'      => $total_belanja,
            'unique_items_count' => count($_SESSION['keranjang'] ?? []),
        ];
    }
}



