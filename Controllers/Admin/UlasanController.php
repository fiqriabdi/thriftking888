<?php
/**
 * File: app/controllers/ulasancontroller.php
 * Controller Ulasan - Menangani logika bisnis review/ulasan
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/helpers/Loggable.php';
require_once APP_ROOT . '/Models/ulasan.php';
require_once APP_ROOT . '/Models/notification.php';

class UlasanController {
    use Loggable;
    private $db;
    private $model;

    public function __construct($conn) {
        $this->db = $conn;
        $this->model = new ulasan($conn); // Kirim koneksi ke model ulasan
    }

    /**
     * Mengambil data ulasan dengan pagination untuk halaman admin.
     */
    public function index($page = 1, $itemsPerPage = 15, $status = null) {
        $offset = ($page - 1) * $itemsPerPage;
        
        // Ambil total item untuk menghitung total halaman
        $totalItems = $this->model->countAll($status);
        $totalPages = ceil($totalItems / $itemsPerPage);

        // Ambil data ulasan untuk halaman saat ini
        $reviews = $this->model->getAll(null, $status, $itemsPerPage, $offset);

        return [
            'reviews' => $reviews,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Menghitung jumlah ulasan berdasarkan status (efisien).
     * @param string|null $status
     * @return int
     */
    public function countReviewsByStatus($status = null) {
        return $this->model->countAll($status);
    }
    /**
     * Ambil semua ulasan (untuk admin)
     * @param string|null $status
     * @return array
     */
    public function getAll($status = null) {
        return $this->model->getAll(null, $status);
    }

    /**
     * Ambil ulasan produk tertentu
     * @param int $produk_id
     * @return array
     */
    public function getByProduk($produk_id) {
        return $this->model->getByProduk($produk_id);
    }

    /**
     * Ambil ulasan milik user tertentu
     * @param int $user_id
     * @return array
     */
    public function getByUser($user_id) {
        return $this->model->getByUser($user_id);
    }

    /**
     * Ambil ulasan berdasarkan ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        return $this->model->getById($id);
    }

    /**
     * Cek apakah user bisa review
     * @param int $produk_id
     * @param int $user_id
     * @return array
     */
    public function canReview($produk_id, $user_id) {
        $errors = [];

        // Cek apakah user sudah pernah review
        if ($this->model->hasReview($produk_id, $user_id)) {
            $errors[] = 'Anda sudah memberikan review untuk produk ini';
        }

        // Cek apakah user membeli produk
        if (!$this->model->userBoughtProduct($produk_id, $user_id)) {
            $errors[] = 'Ulasan hanya dapat diberikan untuk pesanan yang telah selesai (Completed).';
        }

        return $errors;
    }

    /**
     * Tambah ulasan baru
     * @param array $data
     * @return array
     */
    public function store($data, $files) {
        $errors = [];

        // Validasi
        if (empty($data['produk_id'])) {
            $errors[] = 'Produk tidak ditemukan';
        }
        if (empty($data['user_id'])) {
            $errors[] = 'User tidak ditemukan';
        }
        if (empty($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
            $errors[] = 'Rating harus antara 1-5';
        }
        if (empty($data['judul'])) {
            $errors[] = 'Judul review tidak boleh kosong';
        }
        if (empty($data['isi'])) {
            $errors[] = 'Isi review tidak boleh kosong';
        }

        // Cek validasi awal
        $produk_id = (int)$data['produk_id'];
        $user_id = (int)$data['user_id'];
        $canReviewErrors = $this->canReview($produk_id, $user_id);
        if (!empty($canReviewErrors)) {
            $errors = array_merge($errors, $canReviewErrors);
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Validasi panjang
        if (strlen($data['judul']) > 255) {
            return ['success' => false, 'errors' => ['Judul terlalu panjang (max 255 karakter)']];
        }

        // Handle file upload
        $foto = null;
        if (isset($files['foto']) && $files['foto']['error'] === UPLOAD_ERR_OK) {
            $file = $files['foto'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                return ['success' => false, 'errors' => ['Format file harus jpg, jpeg, png, atau gif']];
            }

            // Tambahan verifikasi konten gambar asli untuk keamanan
            if (!getimagesize($file['tmp_name'])) {
                return ['success' => false, 'errors' => ['File yang diunggah bukan gambar yang valid.']];
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                return ['success' => false, 'errors' => ['Ukuran file maksimal 2MB']];
            }

            $uploadDir = APP_ROOT . '/public/assets/img/reviews/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid('review_') . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $foto = $filename;
            } else {
                return ['success' => false, 'errors' => ['Gagal upload foto']];
            }
        }

        // Tambah ulasan
        $data['foto'] = $foto;
        $review_id = $this->model->create($data);

        if ($review_id) {
            // Notifikasi Admin: Ulasan Baru
            $notif = new Notification($this->db);
            $link_notif = BASE_URL . 'admin/ulasan?status=pending'; // Arahkan ke halaman moderasi ulasan
            $notif->create('admin', null, 'new_review', (int)$review_id, "Ada ulasan baru untuk produk ID #{$data['produk_id']}. Segera moderasi.", $link_notif);

            return ['success' => true, 'message' => 'Review berhasil ditambahkan. Menunggu persetujuan admin.'];
        } else {
            return ['success' => false, 'errors' => ['Gagal menyimpan review']];
        }
    }

    /**
     * Menyimpan balasan admin untuk sebuah ulasan.
     * @param int $id
     * @param string $replyText
     * @return bool
     */
    public function reply($id, $replyText) {
        if (empty(trim($replyText))) {
            return false;
        }
        $result = $this->model->addAdminReply($id, $replyText);
        if ($result) {
            $this->logActivity("REPLY_REVIEW", "Memberikan balasan pada ulasan ID: $id");
        }
        return $result;
    }
    /**
     * Update status ulasan (approve/reject)
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status) {
        if (!in_array($status, ['pending', 'approved', 'rejected'])) {
            return false;
        }
        return $this->model->updateStatus($id, $status);
    }

    /**
     * Hapus ulasan
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        return $this->model->delete($id);
    }

    /**
     * Ambil statistik rating produk
     * @param int $produk_id
     * @return array
     */
    public function getRatingStats($produk_id) {
        return $this->model->getRatingStats($produk_id);
    }
}
?>
