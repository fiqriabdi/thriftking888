<?php 
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Format.php'; // INTEGRASI: Pustaka pemformatan Rupiah
require_once APP_ROOT . '/Middleware/auth.php'; // INTEGRASI: Pengalihan middleware berbasis kelas
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
require_once APP_ROOT . '/Controllers/Admin/ulasancontroller.php';

$id = intval($id ?? $_GET['id'] ?? 0);
$controller = new produkcontroller($conn); // Kirim koneksi ke controller produk
$ulasan_controller = new ulasancontroller($conn); // Kirim koneksi ke controller ulasan 
$data = $controller->show($id);

// Proteksi jika data produk tidak ditemukan di database
if (!$data) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . 'produk');
    exit();
}

// AMAN: Pastikan variabel koneksi $conn tersedia sebelum menyiapkan statement SQL
$galeri = [];
if (isset($conn) && $conn) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM product_images WHERE product_id = ? AND sort_order > 0 ORDER BY sort_order ASC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $galeri = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
    }
}

$description = isset($data['deskripsi']) ? str_replace(["\\r\\n", "\\n", "\r", "\n"], "\n", $data['deskripsi']) : '';

// Mengambil data ulasan & statistik rating toko
$all_reviews = $ulasan_controller->getByProduk($id);
$rating_stats = $ulasan_controller->getRatingStats($id);

// OPTIMALISASI: Menggunakan kelas Auth terpusat untuk mendeteksi hak akses pengguna
$isLoginPelanggan = Auth::isPelanggan();
$isLoginAdmin     = Auth::isAdmin();

// Sinkronisasi URL dasar global
$base_url = defined('BASE_URL') ? BASE_URL : '';

require_once APP_ROOT . '/Views/layouts/header.php'; 
require_once APP_ROOT . '/Views/layouts/navbar.php'; 
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #ffffff; }

    /* Galeri Foto Kotak Konsisten & Tajam (Premium Vintage Look) */
    .product-main-img-container {
        border-radius: 0px; /* Diubah ke sudut tajam sesuai tema */
        position: sticky;
        top: 100px; /* Jarak aman dari navbar */
        overflow: hidden;
        aspect-ratio: 1 / 1; 
        background-color: #ffffff;
        border: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .product-main-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover; 
    }
    .thumb-img-container {
        width: 70px;
        height: 70px;
        overflow: hidden;
        border-radius: 0px; /* Sudut tajam */
        cursor: pointer;
        border: 1px solid #f0f0f0;
        transition: 0.2s;
    }
    .thumb-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .thumb-img-container.active {
        border: 2px solid #000000;
    }
    
    /* Kotak Checkout Minimalis Premium */
    .checkout-box {
        border: 1px solid #e5e7eb;
        border-radius: 12px; /*  menggunakan sudut yang sedikit membulat untuk card action */
        padding: 16px;
        position: sticky;
        top: 100px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        border-bottom: 2px solid transparent;
        font-size: 0.9rem;
        letter-spacing: 1px;
    }
    .nav-tabs .nav-link.active {
        color: #000 !important;
        border-bottom: 2px solid #000;
        background: none;
    }

    /* Efek visual untuk produk habis */
    .out-of-stock-img {
        filter: grayscale(100%);
        opacity: 0.6;
    }
    .sold-out-badge {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        z-index: 10;
        border: 4px solid #d9534f;
        color: #d9534f;
        font-weight: 900;
        padding: 10px 20px;
        font-size: 1.5rem;
        background: rgba(255, 255, 255, 0.8);
        text-transform: uppercase;
        pointer-events: none;
    }
    .price-tag {
        font-size: 2rem;
        color: #000;
        letter-spacing: -1px;
    }
    .text-vintage { color: #5D4037 !important; }
    .progress-bar-vintage { background-color: #5D4037 !important; }
</style>

<div class="container mt-5 mb-5">
    <div class="row g-5">
        <div class="col-md-4">
            <div class="product-main-img-container shadow-sm">
                <?php if ($data['stok'] <= 0): ?>
                    <div class="sold-out-badge">Sold Out</div>
                <?php elseif ($data['stok'] < 3): ?>
                    <div class="position-absolute top-0 start-0 bg-warning text-dark small fw-bold px-3 py-2" style="font-size: 11px; letter-spacing: 1px; z-index: 10;">LIMITED EDITION</div>
                <?php endif; ?>
                <?php 
                    $mainImg = !empty($data['gambar_utama']) 
                        ? $base_url . 'assets/img/products/' . htmlspecialchars(basename($data['gambar_utama']), ENT_QUOTES, 'UTF-8')
                        : $base_url . 'assets/img/no-image.png';
                ?>
                <img src="<?= $mainImg ?>" 
                     id="mainImg" 
                     class="<?= $data['stok'] <= 0 ? 'out-of-stock-img' : '' ?>"
                     alt="<?= htmlspecialchars($data['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" 
                     onerror="this.onerror=null;this.src='<?= $base_url ?>assets/img/no-image.png';">
            </div>
            <div class="d-flex gap-2 mt-3 overflow-auto pb-2">
                <div class="thumb-img-container active shadow-sm" onclick="changeImage(this)">
                    <?php 
                        $thumbImgSrc = !empty($data['gambar_utama']) 
                            ? $base_url . 'assets/img/products/' . htmlspecialchars(basename($data['gambar_utama']), ENT_QUOTES, 'UTF-8')
                            : $base_url . 'assets/img/no-image.png';
                    ?>
                    <img src="<?= $thumbImgSrc ?>" 
                         class="<?= $data['stok'] <= 0 ? 'out-of-stock-img' : '' ?>"
                         alt="<?= htmlspecialchars($data['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" 
                         onerror="this.onerror=null;this.src='<?= $base_url ?>assets/img/no-image.png';">
                </div>
                <?php foreach($galeri as $g): ?>
                    <div class="thumb-img-container shadow-sm" onclick="changeImage(this)">
                        <img src="<?= $base_url ?>assets/img/products/<?= htmlspecialchars(!empty($g['nama_foto']) ? basename($g['nama_foto']) : 'no-image.png', ENT_QUOTES, 'UTF-8') ?>"
                             class="<?= $data['stok'] <= 0 ? 'out-of-stock-img' : '' ?>"
                             alt="Galeri <?= htmlspecialchars($data['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" 
                             onerror="this.onerror=null;this.src='<?= $base_url ?>assets/img/no-image.png';">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-5">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2" style="font-size: 12px; letter-spacing: 0.5px;">
                    <li class="breadcrumb-item"><a href="<?= $base_url ?>" class="text-decoration-none text-muted text-capitalize">Home</a></li>
                    <li class="breadcrumb-item active text-dark fw-bold text-capitalize"><?= htmlspecialchars($data['nama_kategori'] ?? 'Produk', ENT_QUOTES, 'UTF-8') ?></li>
                </ol>
            </nav>
            
            <h1 class="fw-bold fs-4 mb-1 text-capitalize" style="letter-spacing: 0.5px; font-family: 'Inter', sans-serif;"><?= htmlspecialchars($data['nama_produk'], ENT_QUOTES, 'UTF-8') ?></h1>
            
            <div class="mb-3">
                <h2 class="fw-bold mb-0 price-tag <?= $data['stok'] <= 0 ? 'text-muted' : '' ?>">
                    <?= formatRupiah($data['harga_jual']) ?>
                </h2>
            </div>

            <div class="py-4 border-top border-bottom mb-4">
                <div class="row g-4">
                    <?php if (!empty($data['brand'])): ?>
                    <div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Merek</span>
                        <span class="fw-bold text-capitalize"><?= htmlspecialchars($data['brand'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Kondisi</span>
                        <span class="fw-bold text-dark text-capitalize"><?= htmlspecialchars($data['kondisi'] ?? 'Bekas', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Kategori</span>
                        <span class="fw-bold text-dark text-capitalize"><?= htmlspecialchars($data['nama_kategori'] ?? 'Pakaian', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Ukuran</span>
                        <span class="fw-bold text-uppercase"><?= htmlspecialchars($data['varian_ukuran'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if (!empty($data['varian_warna'])): ?>
                    <div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Warna</span>
                        <span class="fw-bold text-capitalize"><?= htmlspecialchars($data['varian_warna'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Berat Satuan</span>
                        <span class="fw-bold"><?= intval($data['weight'] ?? 0) ?>g</span>
                    </div>
                    <!--<div class="col-6 col-sm-4 small">
                        <span class="text-muted d-block mb-1">Min. Beli</span>
                        <span class="fw-bold">1 Buah</span>
                    </div>-->
                </div>
            </div>

            <ul class="nav nav-tabs mb-3 border-bottom" id="productTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active text-capitalize fw-bold p-2" data-bs-toggle="tab" data-bs-target="#desc">Deskripsi</button>
                </li>
            </ul>
            <div class="tab-content pt-2">
                <div class="tab-pane fade show active" id="desc">
                    <p class="text-muted small" style="line-height: 1.8; text-align: justify;">
                        <?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="checkout-box shadow-sm">
                <h6 class="fw-bold text-capitalize small mb-3" style="letter-spacing: 1px;">Jumlah</h6>
                <div class="mb-3">
                    <span class="small text-muted">Stok Total: <strong class="<?= $data['stok'] < 3 ? 'text-black' : 'text-dark' ?>"><?= $data['stok'] ?></strong></span>
                </div>

                <div class="d-grid gap-2">
                    <?php if ($isLoginAdmin): ?>
                        <div class="alert alert-info py-2 small border-0 text-center rounded-2 text-capitalize fw-semibold" style="font-size: 11px; letter-spacing: 1px;">Mode Admin</div>
                        <a href="<?= $base_url ?>admin/produk/edit/<?= $data['id'] ?>" class="btn btn-dark text-white rounded-2 fw-bold text-capitalize small py-2" style="font-size: 0.75rem; letter-spacing: 1px;">Edit Data Produk</a>
                    
                    <?php elseif ($data['stok'] > 0): ?>
                        
                        <?php if ($isLoginPelanggan): ?>
                            <button onclick="window.location.href='<?= $base_url ?>pelanggan/keranjang?tambah=<?= intval($data['id']) ?>'" 
                                    class="btn btn-dark fw-bold py-2 rounded-2 text-capitalize" style="font-size: 0.75rem; letter-spacing: 1px;">
                                + Keranjang
                            </button>
                            
                            <button onclick="window.location.href='<?= $base_url ?>pelanggan/checkout?beli_langsung=<?= intval($data['id']) ?>'" 
                                    class="btn btn-outline-dark fw-bold py-2 rounded-2 text-capitalize" style="font-size: 0.75rem; letter-spacing: 1px;">
                                Beli
                            </button>
                            <!--<button class="btn btn-light border fw-bold py-2 rounded-2 text-capitalize mt-1" style="font-size: 0.75rem; letter-spacing: 1px;">
                                <i class="bi bi-chat-dots me-1"></i> Chat
                            </button>-->
                        <?php else: ?>
                            <a href="<?= $base_url ?>auth/login" class="btn btn-dark fw-bold text-center rounded-2 text-capitalize py-2" style="font-size: 0.75rem; letter-spacing: 1px;">Masuk untuk Beli</a>
                        <?php endif; ?>

                    <?php else: ?>
                        <button class="btn btn-secondary py-2 rounded-2 text-capitalize fw-bold" style="font-size: 0.75rem; letter-spacing: 1px;" disabled>Stok Habis</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-md-12">
            <h3 class="fw-bold text-capitalize mb-4" style="font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; font-size: 1.3rem;">
                <i class=""></i> Ulasan Produk
                <?php if (!empty($rating_stats['total_review']) && $rating_stats['total_review'] > 0): ?>
                    <span class="badge bg-dark text-white ms-1 rounded-0" style="font-size: 0.8rem;"><?php echo $rating_stats['total_review']; ?></span>
                <?php endif; ?>
            </h3>

            <?php if (!empty($rating_stats['total_review']) && $rating_stats['total_review'] > 0): ?>
            <div class="row mb-5 g-4 border p-4 bg-white">
                <div class="col-md-4 text-center border-end">
                    <div class="p-2">
                        <div class="display-4 fw-bold text-dark"><?php echo number_format($rating_stats['avg_rating'] ?? 0, 1, ',', '.'); ?></div>
                        <div class="mb-2">
                            <?php 
                            $avg = round($rating_stats['avg_rating'] ?? 0);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg ? '<i class="bi bi-star-fill text-vintage mx-0.5"></i>' : '<i class="bi bi-star text-muted opacity-50 mx-0.5"></i>';
                            }
                            ?>
                        </div>
                        <small class="text-muted text-capitalize tracking-wider" style="font-size: 11px;">Dari <?php echo $rating_stats['total_review'] ?? 0; ?> ulasan terverifikasi</small>
                    </div>
                </div>
                <div class="col-md-8 ps-md-5">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <div class="d-flex align-items-center mb-2">
                        <small class="fw-bold text-muted" style="width: 50px; font-size: 11px;"><?php echo $i; ?> <i class="bi bi-star-fill text-vintage ms-1"></i></small>
                        <div class="progress flex-grow-1 mx-3 rounded-0" style="height: 6px;">
                            <div class="progress-bar progress-bar-vintage" role="progressbar" 
                                 style="width: <?= ($rating_stats['total_review'] ?? 0) > 0 ? ($rating_stats['star_' . $i] / $rating_stats['total_review'] * 100) : 0; ?>%"></div>
                        </div>
                        <small class="fw-bold text-muted" style="width: 30px; font-size: 11px;"><?php echo $rating_stats['star_' . $i]; ?></small>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-4">
                <?php if ($isLoginPelanggan): ?>
                    <a href="<?= $base_url ?>pelanggan/ulasan/<?php echo $id; ?>" class="btn btn-outline-dark rounded-0 text-capitalize fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">
                        <i class="bi bi-pencil-square me-1"></i> Tulis Review
                    </a>
                <?php elseif (!Auth::isLoggedIn()): ?>
                    <a href="<?= $base_url ?>auth/login" class="btn btn-outline-secondary rounded-0 text-capitalize fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">
                        <i class="bi bi-lock me-1"></i> Login untuk memberikan Review
                    </a>
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <?php if (!empty($all_reviews)): ?>
                    <?php foreach ($all_reviews as $review): ?>
                    <div class="col-md-6">
                        <div class="card h-100 border rounded-0 bg-white shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="min-w-0">
                                        <h6 class="card-title mb-1 text-capitalize fw-bold text-truncate" style="font-size: 12px; letter-spacing: 0.5px;"><?php echo htmlspecialchars($review['nama_pembeli'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <small class="text-muted" style="font-size: 11px;">
                                            <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star-fill <?= $i <= $review['rating'] ? 'text-vintage' : 'text-muted opacity-40' ?>" style="font-size: 12px;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <h6 class="card-subtitle mb-2 text-dark fw-semibold" style="font-size: 13px;"><?php echo htmlspecialchars($review['judul'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                <p class="card-text text-muted small" style="line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars(substr($review['isi'], 0, 150), ENT_QUOTES, 'UTF-8')); ?>
                                    <?php if (strlen($review['isi']) > 150): ?>

                                    <br><a href="<?= $base_url ?>pelanggan/ulasan?produk_id=<?php echo $id; ?>#review<?php echo $review['id']; ?>" class="text-dark fw-bold text-decoration-none">Lihat selengkapnya</a> 

                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($review['foto'])): ?>
                                <img src="<?= $base_url ?>assets/img/reviews/<?php echo htmlspecialchars($review['foto'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="img-thumbnail rounded-0 mt-2" style="max-width: 80px; height: 80px; object-fit: cover;" onerror="this.style.display='none';">
                                <?php endif; ?>

                                <?php if (!empty($review['admin_reply_text'])): ?>
                                    <div class="mt-3 p-3 bg-light" style="border-left: 3px solid #5D4037;">
                                        <div class="d-flex align-items-center mb-2">
                                            <div>
                                                <span class="fw-bold small text-capitalize" style="letter-spacing: 0.5px;">Balasan Penjual</span>
                                                <div class="text-muted" style="font-size: 10px;"><?= date('d M Y', strtotime($review['admin_replied_at'])); ?></div>
                                            </div>
                                        </div>
                                        <p class="small text-dark mb-0" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($review['admin_reply_text'], ENT_QUOTES, 'UTF-8')); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-md-12">
                        <div class="alert alert-light text-center border rounded-0 text-muted py-4 small">
                            <i class="bi bi-info-circle me-1"></i> Belum ada ulasan untuk produk ini. Jadilah pembeli pertama yang memberikan ulasan!
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!--<?php if (!empty($all_reviews)): ?>
            <div class="text-center mt-5">
                <a href="<?= $base_url ?>pelanggan/ulasan/<?php echo $id; ?>" class="btn btn-outline-dark rounded-0 text-capitalize fw-bold px-4" style="font-size: 0.7rem; letter-spacing: 1px;">
                    Lihat Semua Ulasan (<?php echo count($all_reviews); ?>)
                </a>
            </div>-->
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Fitur Pop-up Urgency: Low Stock Alert -->
<!--<?php if ($data['stok'] > 0 && $data['stok'] < 3): ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
    <div id="lowStockToast" class="toast align-items-center text-white bg-dark border-0 rounded-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body p-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-lightning-fill text-warning"></i>
                    <span class="fw-bold text-capitalize" style="font-size: 11px; letter-spacing: 1px;">Stock Alert</span>
                </div>
                <p class="mb-0 small" style="letter-spacing: 0.5px;">Hanya tersisa <strong><?= $data['stok'] ?></strong> item! Segera amankan sebelum kehabisan.</p>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>-->

<?php endif; ?>

<script>
    function changeImage(container) {
        const newSrc = container.querySelector('img').src;
        document.getElementById('mainImg').src = newSrc;
        document.querySelectorAll('.thumb-img-container').forEach(img => img.classList.remove('active'));
        container.classList.add('active');
    }

    // Inisialisasi Toast untuk stok menipis secara otomatis
    document.addEventListener('DOMContentLoaded', function () {
        var toastEl = document.getElementById('lowStockToast');
        if (toastEl) {
            var toast = new bootstrap.Toast(toastEl, { autohide: true, delay: 8000 });
            toast.show();
        }
    });
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>