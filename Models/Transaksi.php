<?php
require_once __DIR__ . '/../Config/koneksi.php';
require_once __DIR__ . '/Notification.php';

class Transaksi {
    private $db; // Gunakan $db sebagai standar
    private $notif;

    // Inject koneksi melalui konstruktor
    public function __construct($db_connection) {
        $this->db = $db_connection;
        $this->notif = new Notification($db_connection);
    }

    /**
     * Fungsi Baru: Menghitung notifikasi pesanan aktif untuk pelanggan
     */
    public function hitungNotifPelanggan($user_id) {
        // Menghitung transaksi yang statusnya membutuhkan perhatian pelanggan
        // 'menunggu_pembayaran', 'diproses', dan 'dikirim'
        $user_id = intval($user_id);
        $stmt = mysqli_prepare($this->db, "SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status_order IN ('unpaid', 'processing', 'shipped')");
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $total);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $total ?: 0;
    }

    /**
     * Menghitung jumlah produk unik yang sudah selesai dibeli namun belum diulas
     */
    public function hitungPendingReview($user_id) {
        $user_id = intval($user_id);
        // Mencari item di pesanan 'completed' yang produknya belum ada di tabel 'reviews' untuk user ini
        $sql = "SELECT COUNT(DISTINCT pv.product_id) as total 
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN product_variants pv ON oi.product_variant_id = pv.id
                WHERE o.user_id = ? 
                AND o.status_order = 'completed'
                AND pv.product_id NOT IN (
                    SELECT produk_id FROM reviews WHERE user_id = ?
                )";
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return 0;
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $total);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $total ?: 0;
    }

    /**
     * Mengambil daftar item produk yang sudah selesai dibeli namun belum diulas
     */
    public function getPendingReviews($user_id) {
        $user_id = intval($user_id);
        // Mengambil detail produk dari pesanan 'completed' yang belum ada di tabel 'reviews'
        $sql = "SELECT oi.*, p.nama_produk, pi.nama_foto, pv.product_id, o.invoice_code, o.created_at as order_date
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN product_variants pv ON oi.product_variant_id = pv.id
                JOIN products p ON pv.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                WHERE o.user_id = ? 
                AND o.status_order = 'completed'
                AND pv.product_id NOT IN (
                    SELECT produk_id FROM reviews WHERE user_id = ?
                )
                ORDER BY o.created_at DESC";
                
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Mengambil riwayat pesanan berdasarkan user ID
     */
    public function getOrdersByUser($user_id) {
        $user_id = intval($user_id);
        // [OPTIMASI] Mengganti beberapa subquery terpisah dengan JOIN yang lebih efisien.
        $sql = "SELECT o.*,
                       (SELECT pi.nama_foto 
                        FROM order_items oi 
                        JOIN product_variants pv ON oi.product_variant_id = pv.id 
                        JOIN product_images pi ON pv.product_id = pi.product_id 
                        WHERE oi.order_id = o.id AND pi.sort_order = 0 
                        LIMIT 1) as gambar_produk,
                       (SELECT pv.product_id FROM order_items oi JOIN product_variants pv ON oi.product_variant_id = pv.id WHERE oi.order_id = o.id LIMIT 1) as product_id,
                       (SELECT oi.nama_produk_snapshot FROM order_items oi WHERE oi.order_id = o.id LIMIT 1) as produk_pertama,
                       (SELECT COUNT(id) FROM order_items WHERE order_id = o.id) as total_item
                FROM orders o WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return [];
        
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Mengambil satu data pesanan berdasarkan ID
     */
    public function getOrderById($id) {
        $id = intval($id);
        $sql = "SELECT o.*, u.nama as nama_pelanggan, u.no_hp, pay.metode_pembayaran 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                LEFT JOIN payments pay ON o.id = pay.order_id
                WHERE o.id = ? LIMIT 1";
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return null;

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Mengambil item produk dalam sebuah pesanan
     */
    public function getOrderItems($order_id) {
        $order_id = intval($order_id);
        $sql = "SELECT oi.*, pi.nama_foto, pv.product_id, pv.sku, p.weight
                FROM order_items oi 
                LEFT JOIN product_variants pv ON oi.product_variant_id = pv.id
                LEFT JOIN products p ON pv.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                WHERE oi.order_id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return [];

        mysqli_stmt_bind_param($stmt, 'i', $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $data;
    }

    public function createOrder($data, $keranjang_items) {
        $user_id = intval($data['user_id']);
        
        // [DIUBAH] Memulai transaksi database untuk memastikan konsistensi data
        mysqli_begin_transaction($this->db);

        try {
            // 1. Buat Order Header
            $prefix = "INV/" . date('Ymd') . "/";
            $random = strtoupper(bin2hex(random_bytes(3)));
            $kode = $prefix . $random;

            $nama = $data['nama_penerima'];
            $no_hp = $data['no_hp_penerima'];
            $total_bayar = (int)$data['total_harga'];
            $ongkir = (int)$data['ongkir'];
            $total_produk = (int)$data['total_harga_produk'];
            $alamat = $data['alamat'];
            $expired = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt_order = mysqli_prepare($this->db, "INSERT INTO orders (user_id, invoice_code, nama_penerima, no_hp_penerima, total_harga_produk, total_ongkir, total_pembayaran, alamat_pengiriman, expired_at, status_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')");
            if (!$stmt_order) throw new Exception("Gagal menyiapkan statement order.");
            
            mysqli_stmt_bind_param($stmt_order, 'isssiiiss', $user_id, $kode, $nama, $no_hp, $total_produk, $ongkir, $total_bayar, $alamat, $expired);
            if (!mysqli_stmt_execute($stmt_order)) throw new Exception("Gagal membuat order.");

            $order_id = mysqli_insert_id($this->db);
            mysqli_stmt_close($stmt_order);

            // 2. Buat Order Items dan Kurangi Stok
            foreach ($keranjang_items as $variant_id => $item) {
                // [LOGIKA BARU] Kurangi stok terlebih dahulu
                $stmt_stock = mysqli_prepare($this->db, "UPDATE product_variants SET stok = stok - ? WHERE id = ? AND stok >= ?");
                if (!$stmt_stock) throw new Exception("Gagal menyiapkan statement stok.");
                mysqli_stmt_bind_param($stmt_stock, 'iii', $item['jumlah'], $variant_id, $item['jumlah']);
                if (!mysqli_stmt_execute($stmt_stock) || mysqli_stmt_affected_rows($stmt_stock) === 0) {
                    throw new Exception("Stok untuk produk '{$item['nama_produk']}' tidak mencukupi.");
                }
                mysqli_stmt_close($stmt_stock);

                // Buat order item setelah stok berhasil dikurangi
                $this->createOrderItem($order_id, $variant_id, $item['harga_jual'], $item['jumlah']);
            }

            // 3. Buat Notifikasi untuk Admin
            $link_notif = BASE_URL . 'admin/pesanan/detail/' . $order_id;
            $this->notif->create('admin', null, 'new_order', $order_id, "Pesanan baru masuk dengan invoice #{$kode}", $link_notif);

            // Jika semua berhasil, commit transaksi
            mysqli_commit($this->db);
            return $order_id;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            error_log("Create Order Error: " . $e->getMessage());
            return false;
        }
    }

    public function createOrderItem($order_id, $product_variant_id, $harga, $jumlah = 1) {
        $order_id = intval($order_id);
        $product_variant_id = intval($product_variant_id);
        $harga = (int)$harga;
        $jumlah = intval($jumlah);
        $subtotal = (int)($harga * $jumlah);

        // Ambil snapshot nama produk dari varian
        $stmt_s = mysqli_prepare($this->db, "SELECT p.nama_produk FROM products p JOIN product_variants pv ON p.id = pv.product_id WHERE pv.id = ?");
        mysqli_stmt_bind_param($stmt_s, 'i', $product_variant_id);
        mysqli_stmt_execute($stmt_s);
        $res_s = mysqli_stmt_get_result($stmt_s);
        $row = mysqli_fetch_assoc($res_s);
        $nama_snapshot = $row['nama_produk'] ?? 'Produk Terhapus';
        mysqli_stmt_close($stmt_s);

        $stmt = mysqli_prepare($this->db, "INSERT INTO order_items (order_id, product_variant_id, nama_produk_snapshot, harga_satuan, jumlah, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iisiii', $order_id, $product_variant_id, $nama_snapshot, $harga, $jumlah, $subtotal);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**kode revisi
     * [CONTOH IMPLEMENTASI] Menyimpan bukti pembayaran dan mengubah status pesanan.
     * Fungsi ini akan dipanggil dari controller setelah form pembayaran di-submit.
     */
    public function createPaymentAndConfirmOrder($order_id, $user_id, $payment_data, $file_name) {
        $order_id = intval($order_id);
        $user_id = intval($user_id);

        mysqli_begin_transaction($this->db);
        try {
            // 1. Simpan detail pembayaran ke tabel 'payments' (INSERT INTO)
            // [REVISI] Menggunakan ON DUPLICATE KEY UPDATE untuk mengizinkan pelanggan mengunggah ulang bukti bayar.
            $sql_payment = "INSERT INTO payments (order_id, metode_pembayaran, bukti_transfer) 
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                metode_pembayaran = VALUES(metode_pembayaran),
                                bukti_transfer = VALUES(bukti_transfer),
                                status_pembayaran = 'pending',
                                created_at = CURRENT_TIMESTAMP";
            $stmt_payment = mysqli_prepare($this->db, $sql_payment);
            if (!$stmt_payment) throw new Exception("Gagal menyiapkan statement pembayaran.");
            
            mysqli_stmt_bind_param($stmt_payment, 'iss', $order_id, $payment_data['metode_pembayaran'], $file_name);
            if (!mysqli_stmt_execute($stmt_payment)) throw new Exception("Gagal menyimpan data pembayaran.");
            mysqli_stmt_close($stmt_payment);

            // 2. Ubah status pesanan menjadi 'menunggu konfirmasi' (UPDATE)
            $stmt_order = mysqli_prepare($this->db, "UPDATE orders SET status_order = 'pending_confirmation' WHERE id = ? AND user_id = ? AND status_order = 'unpaid'");
            if (!$stmt_order) throw new Exception("Gagal menyiapkan statement update order.");
            mysqli_stmt_bind_param($stmt_order, 'ii', $order_id, $user_id);
            mysqli_stmt_execute($stmt_order);
            // [PERBAIKAN] Cek apakah ada baris yang benar-benar di-update.
            if (mysqli_stmt_affected_rows($stmt_order) === 0) {
                throw new Exception("Pesanan tidak dapat diproses. Status pesanan mungkin telah berubah atau sudah kedaluwarsa.");
            }
            mysqli_stmt_close($stmt_order);

            // 3. Buat notifikasi untuk admin
            $link_notif = BASE_URL . 'admin/konfirmasi-pembayaran';
            $this->notif->create('admin', null, 'payment_confirmation', $order_id, "Pembayaran untuk pesanan #{$order_id} perlu dikonfirmasi.", $link_notif);

            mysqli_commit($this->db);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            error_log("Create Payment Error: " . $e->getMessage());
            // [PERBAIKAN] Kembalikan pesan error spesifik ke Controller
            return $e->getMessage();
        }
    }

    /**
     * Mengambil semua data pesanan untuk kebutuhan manajemen admin
     * @param int $page Halaman saat ini.
     * @param int $limit Jumlah item per halaman.
     * @return array Data pesanan dan detail pagination.
     */
    public function getAllOrdersAdmin($page = 1, $limit = 15, $status = null) {
        $offset = ($page - 1) * $limit;

        // Query untuk menghitung total data
        $count_sql = "SELECT COUNT(*) as total FROM orders" . ($status ? " WHERE status_order = ?" : "");
        $stmt_count = mysqli_prepare($this->db, $count_sql);
        if ($status) mysqli_stmt_bind_param($stmt_count, 's', $status);
        mysqli_stmt_execute($stmt_count);
        $total_orders = mysqli_stmt_get_result($stmt_count)->fetch_assoc()['total'] ?? 0;
        $total_pages = ceil($total_orders / $limit);

        // [PERBAIKAN] Query dioptimalkan menggunakan subquery untuk menghindari GROUP BY yang lambat.
        $sql = "SELECT
                    o.*,
                    u.nama AS nama_pelanggan,
                    pay.bukti_transfer,
                    pay.metode_pembayaran,
                    (SELECT oi.nama_produk_snapshot FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) AS produk_pertama,
                    (SELECT pi.nama_foto FROM product_images pi JOIN product_variants pv ON pi.product_id = pv.product_id JOIN order_items oi ON pv.id = oi.product_variant_id WHERE oi.order_id = o.id AND pi.sort_order = 0 ORDER BY oi.id ASC LIMIT 1) AS gambar_produk,
                    (SELECT COUNT(oi.id) FROM order_items oi WHERE oi.order_id = o.id) AS total_item
                FROM
                    orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN payments pay ON o.id = pay.order_id
                WHERE 1=1";

        $params = [];
        $types = "";
        if ($status) {
            $sql .= " AND o.status_order = ?";
            $params[] = $status;
            $types .= 's';
        }
        $sql .= " ORDER BY o.created_at DESC LIMIT ?, ?";
        $params[] = $offset; $params[] = $limit;
        $types .= 'ii';

        $stmt = mysqli_prepare($this->db, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $orders = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);

        return ['orders' => $orders, 'total_pages' => (int)$total_pages, 'current_page' => (int)$page];
    }

    /**
     * Memperbarui status pesanan.
     */
    public function updateOrderStatus($order_id, $new_status) {
        $order_id = intval($order_id);
        
        // Gunakan transaksi database untuk memastikan notifikasi dan update status berjalan bersamaan
        try {
            mysqli_begin_transaction($this->db);
            
            // Ambil status lama untuk pengecekan (opsional tapi disarankan agar tidak double restore)
            $stmt_old = mysqli_prepare($this->db, "SELECT status_order FROM orders WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_old, 'i', $order_id);
            mysqli_stmt_execute($stmt_old);
            $res_old = mysqli_stmt_get_result($stmt_old);
            $old_order = mysqli_fetch_assoc($res_old);

            // [LOGIKA DIUBAH] Pengurangan stok telah dipindahkan ke proses checkout.
            // Di sini, kita hanya menangani PENGEMBALIAN stok jika pesanan dibatalkan.
            // Stok harus dikembalikan jika pesanan dibatalkan dari status 'unpaid', 'pending_confirmation', atau 'processing'.
            // Pada dasarnya, setiap pembatalan sebelum 'completed' harus mengembalikan stok.
            if ($new_status === 'cancelled' && in_array($old_order['status_order'], ['unpaid', 'pending_confirmation', 'processing', 'shipped'])) {
                $stmt_items = mysqli_prepare($this->db, "SELECT product_variant_id, jumlah FROM order_items WHERE order_id = ?");
                mysqli_stmt_bind_param($stmt_items, 'i', $order_id);
                mysqli_stmt_execute($stmt_items);
                $res_items = mysqli_stmt_get_result($stmt_items);

                while ($item = mysqli_fetch_assoc($res_items)) {
                    // Kembalikan Stok ke product_variants.
                    // Ini sekarang akan berjalan saat pelanggan membatalkan pesanan 'unpaid'.
                    $stmt_upd_stok = mysqli_prepare($this->db, "UPDATE product_variants SET stok = stok + ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_upd_stok, 'ii', $item['jumlah'], $item['product_variant_id']);
                    mysqli_stmt_execute($stmt_upd_stok);
                }
            }

            // [LOGIKA DIPINDAHKAN] Pencatatan log penjualan terjadi saat pesanan benar-benar selesai ('completed').
            if ($new_status === 'completed' && $old_order['status_order'] === 'shipped') {
                $order = $this->getOrderById($order_id); // Ambil info pesanan untuk log
                $items = $this->getOrderItems($order_id);
                foreach ($items as $item) {
                    $keterangan = "Penjualan dari Pesanan #" . ($order['invoice_code'] ?? $order_id);
                    $stmt_log = mysqli_prepare($this->db, "INSERT INTO stock_logs (product_variant_id, user_id, type, jumlah, keterangan) VALUES (?, ?, 'sale', ?, ?)");
                    mysqli_stmt_bind_param($stmt_log, 'iiis', $item['product_variant_id'], $order['user_id'], $item['jumlah'], $keterangan);
                    mysqli_stmt_execute($stmt_log);
                }
            }

            // 4. Update status pesanan
            $stmt = mysqli_prepare($this->db, "UPDATE orders SET status_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $order_id);
            $result = mysqli_stmt_execute($stmt);
            
            if (!$result) throw new Exception("Gagal memperbarui status pesanan.");

            if ($new_status === 'cancelled') {
                $link_notif = BASE_URL . 'admin/pesanan/detail/' . $order_id;
                $this->notif->create('admin', null, 'cancelled_order', $order_id, "Pesanan #{$order_id} telah dibatalkan oleh pelanggan.", $link_notif);
            }
            
            mysqli_commit($this->db);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            error_log("Error updateOrderStatus: " . $e->getMessage());
            return $e->getMessage(); // [PERBAIKAN] Kembalikan pesan error spesifik
        }
    }
}
