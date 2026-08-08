<?php
/**
 * File: pelanggan/ulasan.php
 * Halaman pelanggan untuk membuat review produk
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Format.php'; // INTEGRASI: Fungsi formatRupiah()
require_once APP_ROOT . '/helpers/Security.php';
require_once APP_ROOT . '/Middleware/auth.php'; // Sudah benar
require_once APP_ROOT . '/Controllers/Admin/UlasanController.php';
require_once APP_ROOT . '/Controllers/Admin/ProdukController.php';

auth::requireRole('pelanggan');

// Sinkronisasi koneksi database (Menggunakan fallback Database::getConnection jika $conn tidak di-set global)
$db_connection = isset($conn) ? $conn : Database::getConnection();

$ulasan_controller = new UlasanController($db_connection);
$produk_controller = new ProdukController($db_connection);

// Generate CSRF token untuk form
$csrf_token = generateCSRFToken();

$errors = [];
$success = false;
$user_id = auth::getUser()['id'] ?? 0;
// Ambil ID dari variabel $id (hasil extract Router) atau fallback ke query string
$produk_id = intval($id ?? $_GET['produk_id'] ?? 0); 

// Validasi produk
$produk = null;
if ($produk_id) {
    $produk = $produk_controller->show($produk_id);
}

if (!$produk) {
    echo "<div class='container mt-5'><div class='alert alert-danger rounded-0'>Produk tidak ditemukan!</div></div>";
    exit;
}

// Cek apakah user bisa review
$canReviewErrors = $ulasan_controller->canReview($produk_id, $user_id);
if (!empty($canReviewErrors)) {
    $errors = $canReviewErrors;
}

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Validasi CSRF token terlebih dahulu
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Permintaan tidak valid (CSRF token).';
    } else {
        $data = [
            'produk_id' => $produk_id,
            'user_id' => $user_id,
            'rating' => isset($_POST['rating']) ? (int)$_POST['rating'] : 0,
            'judul' => isset($_POST['judul']) ? trim($_POST['judul']) : '',
            'isi' => isset($_POST['isi']) ? trim($_POST['isi']) : ''
        ];

        $result = $ulasan_controller->store($data, $_FILES);
        if ($result['success']) {
            $_SESSION['success_msg'] = "Review berhasil dikirim! Data Anda akan tampil setelah diverifikasi Admin.";
            header("Location: " . BASE_URL . "pelanggan/menunggu-ulasan");
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}

// Ambil data review yang sudah ada
$all_reviews = $ulasan_controller->getByProduk($produk_id);
$rating_stats = $ulasan_controller->getRatingStats($produk_id);

$base_url = defined('BASE_URL') ? BASE_URL : '';
$pageTitle = 'Ulasan - ThriftKing888';

require_once APP_ROOT . '/Views/layouts/header.php';
require_once APP_ROOT . '/Views/layouts/navbar.php';
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .review-title { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    
    /* Card Setup Minimalis Premium */
    .card { border: 1px solid #e5e5e5; border-radius: 0px !important; box-shadow: none; }
    .card-header { border-radius: 0px !important; letter-spacing: 1px; font-size: 13px; }
    .btn { border-radius: 0px !important; letter-spacing: 1px; font-size: 12px; transition: 0.2s ease; }
    .form-control { border-radius: 0px !important; border: 1px solid #ccc; font-size: 14px; }
    .form-control:focus { border-color: #000; box-shadow: none; }
    
    /* Refaktorisasi Mekanisme Input Bintang Kanan ke Kiri CSS Murni */
    .text-vintage { color: #5D4037 !important; }
    .bg-vintage { background-color: #5D4037 !important; }
    .progress-bar { background-color: #5D4037 !important; }

    .rating-input {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
    }
    .rating-input input { display: none; }
    .rating-input label {
        font-size: 26px;
        color: #ddd;
        cursor: pointer;
        transition: color 0.2s ease;
        margin-right: 6px;
    }
    .rating-input label:hover,
    .rating-input label:hover ~ label,
    .rating-input input:checked ~ label {
        color: #5D4037; /* Coklat Vintage agar selaras */
    }
    
    /* Review List Display Grid Styling */
    .review-card { border-bottom: 1px solid #eee; padding: 20px 0; }
    .review-card:last-child { border-bottom: none; }
    .progress { border-radius: 0px !important; background-color: #f5f5f5; }
    .progress-bar { background-color: #111 !important; }
</style>

<div class="container mt-5 mb-5">
    <div class="d-flex align-items-center mb-4">
        <!-- <a href="<?= BASE_URL ?>pelanggan/menunggu-ulasan" class="text-dark me-3" title="Kembali"><i class="bi bi-arrow-left fs-4"></i></a> -->
        <h4 class="fw-bold text-capitalize review-title mb-0" style="font-size: 18px;">Tulis Ulasan</h4>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card mb-4 border-0 bg-light p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-3 col-sm-2">
                        <img src="<?= !empty($produk['gambar_utama']) 
                                ? $base_url . 'assets/img/products/' . htmlspecialchars($produk['gambar_utama'], ENT_QUOTES, 'UTF-8') 
                                : $base_url . 'assets/img/placeholder.png'; ?>" 
                             class="img-fluid border" style="width: 100%; height: 80px; object-fit: cover;">
                    </div>
                    <div class="col-9 col-sm-10">
                        <span class="text-muted text-capitalize small style-label" style="font-size: 10px; letter-spacing: 1px;"><?= htmlspecialchars($produk['nama_kategori'] ?? 'Katalog', ENT_QUOTES, 'UTF-8') ?></span>
                        <h5 class="fw-bold mb-1 text-capitalize m-0" style="font-size: 15px; letter-spacing: 0.5px;"><?= htmlspecialchars($produk['nama_produk'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <p class="text-danger fw-bold mb-2 small"><?= formatRupiah($produk['harga_jual']) ?></p> 
                        <a href="<?= $base_url . 'detail/' . intval($produk['id']) ?>" class="btn btn-sm btn-dark px-3 py-1 text-capitalize" style="font-size: 10px;">Lihat Produk</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($rating_stats['total_review']) && $rating_stats['total_review'] > 0): ?>
            <div class="card p-4 mb-4">
                <h6 class="fw-bold text-capitalize mb-3 review-title" style="font-size: 13px;">Ringkasan Rating</h6>
                <div class="row align-items-center g-3">
                    <div class="col-sm-5 text-center border-end border-sm-none py-2">
                        <div class="display-4 fw-bold mb-0" style="line-height: 1;"><?= number_format($rating_stats['avg_rating'], 1, ',', '.'); ?></div>
                        <div class="my-2">
                            <?php 
                            $avg = round($rating_stats['avg_rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg ? '<i class="bi bi-star-fill text-vintage me-1"></i>' : '<i class="bi bi-star text-muted opacity-50 me-1"></i>';
                            }
                            ?>
                        </div>
                        <small class="text-muted d-block text-capitalize" style="font-size: 10px; letter-spacing: 1px;">Berdasarkan <?= $rating_stats['total_review']; ?> Ulasan</small>
                    </div>
                    <div class="col-sm-7 ps-sm-4">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="d-flex align-items-center mb-1">
                            <small class="text-muted fw-bold" style="width: 35px; font-size: 11px;"><?= $i; ?> <i class="bi bi-star-fill text-vintage small"></i></small>
                            <div class="progress flex-grow-1 mx-2" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?= $rating_stats['total_review'] > 0 ? ($rating_stats['star_' . $i] / $rating_stats['total_review'] * 100) : 0; ?>%"></div>
                            </div>
                            <small class="text-muted text-end" style="width: 25px; font-size: 11px;"><?= $rating_stats['star_' . $i]; ?></small>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-0 mb-4" role="alert">
                <div class="fw-bold text-capitalize small mb-1"><i class="bi bi-exclamation-triangle-fill"></i> Validasi Gagal:</div>
                <ul class="mb-0 small ps-3">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (empty($canReviewErrors)): ?>
            <div class="card mb-4">
                <div class="card-header bg-dark text-white text-capitalize fw-bold py-3">
                    <i class=""></i> Tulis Ulasan Anda
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="mb-4">
                            <label class="form-label text-capitalize fw-bold small" style="letter-spacing: 1px;">Kualitas Produk <span class="text-danger">*</span></label>
                            <div class="rating-input mt-1">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i; ?>" name="rating" value="<?= $i; ?>" required>
                                <label for="star<?= $i; ?>"><i class="bi bi-star-fill"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="judul" class="form-label text-capitalize fw-bold small" style="letter-spacing: 1px;">Judul Singkat <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="judul" name="judul" 
                                   placeholder="Contoh: Kualitas bahan jaket vintage sangat premium" 
                                   maxlength="255" required>
                            <div class="form-text text-muted" style="font-size: 11px;">Maksimal 255 karakter.</div>
                        </div>

                        <div class="mb-3">
                            <label for="isi" class="form-label text-capitalize fw-bold small" style="letter-spacing: 1px;">Detail Ulasan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="isi" name="isi" rows="5" 
                                      placeholder="Ceritakan kepuasan Anda mengenai kondisi pakaian, kesesuaian ukuran, dan kecepatan pengiriman..." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="foto" class="form-label text-capitalize fw-bold small" style="letter-spacing: 1px;">Foto Produk (Opsional)</label>
                            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                            <div class="form-text text-muted" style="font-size: 11px;">Format dokumen: JPG, JPEG, PNG, GIF | Maksimal ukuran file: 2MB.</div>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2 text-capitalize fw-bold">
                            <i class=""></i> Submit Review
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-5">
                <h5 class="fw-bold text-capitalize review-title mb-4" style="font-size: 15px;">Ulasan Pembeli</h5>
                <?php if (!empty($all_reviews)): ?>
                    <div class="border-top">
                        <?php foreach ($all_reviews as $rev): ?>
                        <div class="review-card">
                            <div class="d-flex align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <span class="fw-bold small text-capitalize" style="letter-spacing: 0.5px;"><?= htmlspecialchars($rev['nama_pembeli'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="text-muted" style="font-size: 11px;"><?= date('d M Y', strtotime($rev['created_at'])); ?></div>
                                </div>
                                <div class="text-dark small flex-shrink-0">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star-fill <?= $i <= $rev['rating'] ? 'text-vintage' : 'text-muted opacity-40' ?> me-1"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <h6 class="fw-bold text-capitalize my-2" style="font-size: 13px; letter-spacing: 0.5px;"><?= htmlspecialchars($rev['judul'], ENT_QUOTES, 'UTF-8'); ?></h6>
                            <p class="text-muted small mb-2" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($rev['isi'], ENT_QUOTES, 'UTF-8')); ?></p>
                            
                            <?php if (!empty($rev['foto'])): ?>
                               <a href="<?= $base_url . 'assets/img/reviews/' . htmlspecialchars($rev['foto'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="d-inline-block mt-2">
                                   <img src="<?= $base_url . 'assets/img/reviews/' . htmlspecialchars($rev['foto'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="img-thumbnail rounded-0 p-0 border" style="max-width: 120px; height: 120px; object-fit: cover; cursor: pointer;">
                               </a>
                            <?php endif; ?>

                            <?php if (!empty($rev['admin_reply_text'])): ?>
                                <div class="mt-3 p-3 bg-light" style="border-left: 3px solid #5D4037;">
                                    <div class="d-flex align-items-center mb-2">
                                        <!-- <img src="https://ui-avatars.com/api/?name=Admin&background=5D4037&color=fff&size=30" class="rounded-circle me-2" alt="Admin"> -->
                                        <div>
                                            <span class="fw-bold small text-capitalize" style="letter-spacing: 0.5px;">Balasan Penjual</span>
                                            <div class="text-muted" style="font-size: 10px;"><?= date('d M Y', strtotime($rev['admin_replied_at'])); ?></div>
                                        </div>
                                    </div>
                                    <p class="small text-dark mb-0" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($rev['admin_reply_text'], ENT_QUOTES, 'UTF-8')); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border text-muted rounded-0 py-4 text-center small sentence case" style="letter-spacing: 1px;">
                        <i class=""></i> Belum ada ulasan terverifikasi untuk produk ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light border-0 p-4">
                <h6 class="fw-bold text-capitalize mb-3 review-title" style="font-size: 13px; letter-spacing: 1px;">Panduan Ulasan</h6>
                
                <div class="mb-4">
                    <span class="d-block fw-bold text-capitalize mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Ketentuan Syarat:</span>
                    <ul class="ps-3 mb-0 text-muted small" style="line-height: 1.7;">
                        <li>Hanya akun pembeli item bersangkutan yang diizinkan menulis testimoni ulasan.</li>
                        <li>Sistem membatasi kuota maksimal 1 ulasan unik per produk.</li>
                        <li>Guna menghindari spamming, ulasan diproses masuk antrean moderasi Admin sebelum terbit publik.</li>
                    </ul>
                </div>

                <div>
                    <span class="d-block fw-bold text-capitalize mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Kriteria Konten Terbaik:</span>
                    <ul class="ps-3 mb-0 text-muted small" style="line-height: 1.7;">
                        <li>Berikan deskripsi jujur, objektif, dan relevan sesuai kondisi fisik pakaian thrift.</li>
                        <li>Gunakan unggah foto pencahayaan natural guna membantu calon pembeli lain melihat detail serat pakaian.</li>
                        <li>Dilarang mencantumkan kata-kata provokatif, unsur SARA, tautan promosi eksternal, atau teks spamming iklan ilegal.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>

<script>
    // Penanganan urutan rendering star input CSS RTL Layout Assurance
    document.addEventListener("DOMContentLoaded", function() {
        const ratingLabels = document.querySelectorAll('.rating-input label');
        ratingLabels.forEach((label, index) => {
            label.style.order = ratingLabels.length - index;
        });
    });
</script>