<?php
require_once __DIR__ . '/../../Models/transaksi.php';
require_once __DIR__ . '/../../helpers/Security.php';

class CheckoutController {
    private $transaksiModel;
    private $db;

    public function __construct($conn) {
        $this->db = $conn;
        $this->transaksiModel = new transaksi($conn); // Kirim koneksi ke model transaksi
    }

    /**
     * Mengambil data ongkir dari database
     */
    public function getShippingData() {
        $res = mysqli_query($this->db, "SELECT * FROM ongkir WHERE is_active = 1 ORDER BY wilayah, kota ASC");
        $data = [];
        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $grup = $row['wilayah'] ?: 'Lainnya';
                if (!isset($data[$grup])) {
                    $data[$grup] = [];
                }
                $data[$grup][] = $row;
            }
        }
        return $data;
    }

    /**
     * Menangani proses penempatan pesanan (Place Order)
     */
    public function processCheckout($userId, $postData, $keranjang, $dataOngkir) {
        // Validasi CSRF token terlebih dahulu
        if (!isset($postData['csrf_token']) || !verifyCSRFToken($postData['csrf_token'])) {
            return ['success' => false, 'message' => 'Permintaan tidak valid (CSRF token).'];
        }

        $nama = sanitizeInput($postData['nama'] ?? '');
        $no_hp = sanitizeInput($postData['no_hp'] ?? '');
        $alamat = trim(sanitizeInput($postData['alamat'] ?? ''));
        $kota = sanitizeInput($postData['kota'] ?? '');
        // Voucher functionality removed — no voucher accepted from client

        if (!is_array($keranjang) || empty($keranjang)) {
            return ['success' => false, 'message' => 'Keranjang belanja tidak valid.'];
        }

        // 1. Validasi Input
        if (empty($nama) || empty($no_hp) || empty($alamat) || empty($kota)) {
            return ['success' => false, 'message' => 'Semua kolom pengiriman wajib diisi.'];
        }

        // Validasi ongkir dari data yang sudah dikelompokkan
        $ongkirIsValid = false;
        foreach ($dataOngkir as $grup => $cities) {
            foreach ($cities as $city) {
                if ($city['kota'] === $kota) $ongkirIsValid = true;
            }
        }
        if (!$ongkirIsValid) {
            return ['success' => false, 'message' => 'Kota pengiriman tidak valid.'];
        }

        // 2. Kalkulasi Ulang (Keamanan: Jangan percaya total dari client-side)
        $totalProduk = 0;
        foreach($keranjang as $item) {
            if (!isset($item['harga_jual'], $item['jumlah'])) {
                return ['success' => false, 'message' => 'Data keranjang tidak lengkap.'];
            }

            $harga = floatval($item['harga_jual']);
            $jumlah = intval($item['jumlah']);
            if ($harga < 0 || $jumlah <= 0) {
                return ['success' => false, 'message' => 'Jumlah atau harga item tidak valid.'];
            }

            $totalProduk += ($harga * $jumlah);
        }

        $totalDiskon = 0; // vouchers disabled

        $ongkir = 0;
        foreach ($dataOngkir as $grup => $cities) {
            foreach ($cities as $city) {
                if ($city['kota'] === $kota) {
                    $ongkir = $city['biaya'];
                }
            }
        }
        $totalBayar = ($totalProduk) + $ongkir;

        $dataInput = [
            'user_id' => $userId,
            'nama_penerima' => $nama,
            'no_hp_penerima' => $no_hp,
            'total_harga' => $totalBayar,
            'ongkir' => $ongkir,
            'total_diskon' => 0,
            'total_harga_produk' => $totalProduk,
            'alamat' => $alamat . " (" . $kota . ")"
        ];

        // 3. Panggil metode createOrder dari model.
        // Metode ini sekarang menangani transaksi database, pengurangan stok, dan pembuatan item pesanan.
        $orderId = $this->transaksiModel->createOrder($dataInput, $keranjang);

        if ($orderId) {
            // Jika berhasil, kembalikan ID pesanan.
            // [DITAMBAHKAN] Kosongkan keranjang setelah checkout berhasil
            require_once __DIR__ . '/../../Models/Cart.php';
            $cartModel = new CartModel($this->db);
            $cartModel->clear($userId);
            $_SESSION['keranjang'] = [];
            return ['success' => true, 'order_id' => $orderId];
        } else {
            // Jika gagal, kembalikan pesan error. Pesan error spesifik (seperti stok habis)
            // sudah di-log di dalam model.
            return ['success' => false, 'message' => 'Gagal memproses pesanan. Stok mungkin tidak mencukupi atau terjadi kesalahan sistem.'];
        }
    }
}
