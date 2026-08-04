<?php
/**
 * File: app/models/ulasan.php
 * Model Ulasan - Menangani logika data tabel ulasan/review
 */

class ulasan {

    // Properti untuk menyimpan koneksi database
    private $db;

    // Konstruktor menerima koneksi database (Dependency Injection)
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    /**
     * Mengambil semua ulasan dengan filter
     * @param string|null $produk_id
     * @param string|null $status
     * @return array
     */
    public function getAll($produk_id = null, $status = null, $limit = null, $offset = null) {
        $sql = "SELECT u.*, p.nama_produk, usr.nama as nama_pembeli, usr.email as email_pembeli 
                FROM reviews u 
                JOIN products p ON u.produk_id = p.id
                JOIN users usr ON u.user_id = usr.id
                WHERE p.deleted_at IS NULL"; // Klausa WHERE utama
        
        $clauses = [];
        $params = [];
        $types = '';

        if ($produk_id) {
            $clauses[] = "u.produk_id = ?";
            $params[] = $produk_id;
            $types .= 'i';
        }
        if ($status) {
            $clauses[] = "u.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        if (!empty($clauses)) {
            $sql .= ' AND ' . implode(' AND ', $clauses); // Tambahkan AND jika ada klausa tambahan
        }
        $sql .= ' ORDER BY u.created_at DESC';

        if ($limit !== null && $offset !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }




        if ($stmt = mysqli_prepare($this->db, $sql)) {
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
            mysqli_stmt_close($stmt);
            return $data;
        }
        return [];
    }

    /**
     * Menghitung total ulasan berdasarkan filter untuk pagination.
     * @param string|null $status
     * @return int
     */
    public function countAll($status = null) {
        $sql = "SELECT COUNT(u.id) as total 
                FROM reviews u
                JOIN products p ON u.produk_id = p.id
                WHERE p.deleted_at IS NULL";
        
        $params = [];
        $types = '';

        if ($status) {
            $sql .= " AND u.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $stmt = mysqli_prepare($this->db, $sql);
        if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return $row['total'] ?? 0;
    }

    /**
     * Mengambil ulasan berdasarkan ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $stmt = mysqli_prepare($this->db, "SELECT u.*, p.nama_produk, usr.nama as nama_pembeli, usr.email as email_pembeli
                                        FROM reviews u 
                                        JOIN products p ON u.produk_id = p.id 
                                        JOIN users usr ON u.user_id = usr.id WHERE p.deleted_at IS NULL AND u.id = ?");
        if (!$stmt) return null;
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Mengambil ulasan untuk produk tertentu (yang approved)
     * @param int $produk_id
     * @return array
     */
    public function getByProduk($produk_id) {
        $stmt = mysqli_prepare($this->db, "SELECT u.*, usr.nama as nama_pembeli
                                        FROM reviews u 
                                        JOIN users usr ON u.user_id = usr.id 
                                        JOIN products p ON u.produk_id = p.id
                                        WHERE u.produk_id = ? AND u.status = 'approved' AND p.deleted_at IS NULL
                                        ORDER BY u.created_at DESC");
        if (!$stmt) return [];
        
        mysqli_stmt_bind_param($stmt, 'i', $produk_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Mengambil ulasan yang dibuat oleh user tertentu
     * @param int $user_id
     * @return array
     */
    public function getByUser($user_id) {
        $stmt = mysqli_prepare($this->db, "SELECT u.*, p.nama_produk, pi.nama_foto as gambar_utama
                                        FROM reviews u 
                                        JOIN products p ON u.produk_id = p.id 
                                        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                                        WHERE u.user_id = ? AND p.deleted_at IS NULL
                                        ORDER BY u.created_at DESC");
        if (!$stmt) return [];
        
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $data;
    }

    /**
     * Cek apakah user sudah pernah review produk
     * @param int $produk_id
     * @param int $user_id
     * @return bool
     */
    public function hasReview($produk_id, $user_id) {
        $stmt = mysqli_prepare($this->db, "SELECT id FROM reviews WHERE produk_id = ? AND user_id = ?");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'ii', $produk_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_fetch_assoc($result) !== null;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    /**
     * Cek apakah user membeli produk
     * @param int $produk_id
     * @param int $user_id
     * @return bool
     */
    public function userBoughtProduct($produk_id, $user_id) {
        $stmt = mysqli_prepare($this->db, "SELECT oi.id
                                        FROM order_items oi
                                        JOIN orders o ON oi.order_id = o.id 
                                        JOIN product_variants pv ON oi.product_variant_id = pv.id
                                        JOIN products p ON p.id = pv.product_id
                                        WHERE pv.product_id = ? AND o.user_id = ? AND o.status_order = 'completed'
                                        AND p.deleted_at IS NULL
                                        LIMIT 1");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'ii', $produk_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_fetch_assoc($result) !== null;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    /**
     * Menambah ulasan baru
     * @param array $data
     * @return bool
     */
    public function create($data) {
        $produk_id = (int)$data['produk_id'];
        $user_id = (int)$data['user_id'];
        $rating = (int)$data['rating'];
        $judul = $data['judul']; // Tidak perlu escape di sini karena menggunakan prepare statement
        $isi = $data['isi'];
        $foto = isset($data['foto']) ? $data['foto'] : null;

        $stmt = mysqli_prepare($this->db, "INSERT INTO reviews (produk_id, user_id, rating, judul, isi, foto) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'iiisss', $produk_id, $user_id, $rating, $judul, $isi, $foto);
        $success = mysqli_stmt_execute($stmt);
        $result = $success ? mysqli_insert_id($this->db) : false;
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Update status ulasan
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status) {
        $id = (int)$id;

        $stmt = mysqli_prepare($this->db, "UPDATE reviews SET status = ? WHERE id = ?");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Menambahkan balasan admin ke ulasan.
     * @param int $id ID ulasan.
     * @param string $replyText Teks balasan.
     * @return bool
     */
    public function addAdminReply($id, $replyText) {
        $id = (int)$id;
        $stmt = mysqli_prepare($this->db, "UPDATE reviews SET admin_reply_text = ?, admin_replied_at = NOW() WHERE id = ?");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'si', $replyText, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Hapus ulasan
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $id = (int)$id;
        
        // Ambil foto terlebih dahulu
        $ulasan = $this->getById($id);
        if ($ulasan && !empty($ulasan['foto'])) {
            require_once __DIR__ . '/../helpers/Security.php';
            $target_dir = APP_ROOT . '/public/assets/img/reviews/';
            $file_name = $ulasan['foto'];
            if (function_exists('safeUnlink')) {
                safeUnlink($target_dir, $file_name);
            } else {
                $path = $target_dir . $file_name;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        $stmt = mysqli_prepare($this->db, "DELETE FROM reviews WHERE id = ?");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'i', $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Hitung rata-rata rating produk
     * @param int $produk_id
     * @return array
     */
    public function getRatingStats($produk_id) {
        $stmt = mysqli_prepare($this->db, "SELECT 
                                        COUNT(*) as total_review,
                                        AVG(rating) as avg_rating,
                                        COUNT(CASE WHEN rating = 5 THEN 1 END) as star_5,
                                        COUNT(CASE WHEN rating = 4 THEN 1 END) as star_4,
                                        COUNT(CASE WHEN rating = 3 THEN 1 END) as star_3,
                                        COUNT(CASE WHEN rating = 2 THEN 1 END) as star_2,
                                        COUNT(CASE WHEN rating = 1 THEN 1 END) as star_1 
                                        FROM reviews 
                                        JOIN products p ON reviews.produk_id = p.id
                                        WHERE reviews.produk_id = ? AND reviews.status = 'approved' AND p.deleted_at IS NULL");
        if (!$stmt) return [];
        
        mysqli_stmt_bind_param($stmt, 'i', $produk_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $data;
    }
}