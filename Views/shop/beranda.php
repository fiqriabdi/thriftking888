<?php 
if (session_status() === PHP_SESSION_NONE) session_start();
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Format.php'; // INTEGRASI: Pustaka pemformatan Rupiah
require_once APP_ROOT . '/Middleware/auth.php'; // INTEGRASI: Membaca status login secara modular
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';

// Sinkronisasi navigasi global aplikasi menggunakan kelas Auth terpusat
$isLoggedIn = auth::isLoggedIn();
$userRole   = auth::getRole();
$userData   = auth::getUser();
$userName   = $userData ? $userData['nama'] : null;

// Mengambil base_url yang telah didefinisikan secara global di konstanta/header
$base_url = defined('BASE_URL') ? BASE_URL : '';

require_once APP_ROOT . '/Views/layouts/header.php'; 
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #ffffff; }
    
    /* Hero Slider Adjustments */
    .hero-slide {
        height: 550px;
        background-color: #111;
        position: relative;
        overflow: hidden;
    }
    .hero-slide-content {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        color: white;
    }

    .hero-title {
        font-family: 'Tenor Sans', sans-serif;
        letter-spacing: 3px;
        text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
        font-size: 3.5rem;
    }

    /* Styling Navigasi Carousel Ala Marketplace Premium */
    .carousel-control-prev, .carousel-control-next {
        width: 50px;
        height: 50px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0; /* Hilang otomatis secara default */
        visibility: hidden; /* Memastikan tidak bisa diklik saat tersembunyi */
        transition: opacity 0.4s ease, transform 0.3s ease, visibility 0.4s;
    }

    .carousel-control-prev { left: 20px; }
    .carousel-control-next { right: 20px; }

    /* Munculkan keduanya otomatis saat kursor berada di area slider */
    #heroCarousel:hover .carousel-control-prev, 
    #heroCarousel:hover .carousel-control-next {
        opacity: 1;
        visibility: visible;
    }

    .carousel-control-prev:hover,
    .carousel-control-next:hover {
        transform: translateY(-50%) scale(1.1); /* Animasi scale saat hover pada tombol */
    }

    .carousel-control-prev-icon, .carousel-control-next-icon {
        background-color: #000000;
        background-size: 50%;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        filter: invert(1); /* Membuat panah Bootstrap (putih) menjadi hitam */
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* Indicators Modern Style */
    .carousel-indicators [data-bs-target] {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        border: none;
        margin: 0 5px;
        transition: all 0.3s ease;
    }
    .carousel-indicators .active {
        width: 25px;
        border-radius: 5px;
    }

    /* Product Card Styling Grid */
    .product-card { 
        border: none; 
        transition: transform 0.3s ease; 
        background: none; 
    }
    .product-card:hover { 
        transform: translateY(-5px); 
    }
    
    .image-container {
        position: relative;
        overflow: hidden;
        background-color: #f9f9f9;
        padding-top: 120%; /* Membuat rasio box gambar seragam (Potret) */
    }

    .image-container img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .btn-overlay {
        position: absolute;
        bottom: -50px;
        left: 0;
        width: 100%;
        background: rgba(0,0,0,0.9);
        color: #fff;
        padding: 15px;
        text-align: center;
        text-decoration: none;
        font-size: 11px;
        font-weight: bold;
        letter-spacing: 2px;
        transition: 0.3s;
        z-index: 2;
    }

    .product-card:hover .btn-overlay { bottom: 0; }

    .section-title {
        font-family: 'Tenor Sans', sans-serif;
        letter-spacing: 6px;
    }

    /* Efek visual untuk produk habis agar pengunjung tahu barang tidak tersedia */
    .out-of-stock-img {
        filter: grayscale(100%);
        opacity: 0.6;
    }

    /* Badge Sold Out ala Marketplace Premium */
    .sold-out-overlay {
        background-color: rgba(0, 0, 0, 0.6);
        color: white;
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 4px 8px;
        z-index: 3;
        pointer-events: none;
    }
</style>

<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
    </div>
    <div class="carousel-inner">
        <!-- Slide 1 -->
        <div class="carousel-item active hero-slide">
            <div style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?= $base_url ?>assets/img/main-banner.jpg') center/cover; height: 100%; width: 100%;"></div>
            <div class="hero-slide-content">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-7">
                            <h1 class="display-2 fw-bold mb-3 hero-title text-capitalize">Curated <br> Thrifting Gems</h1>
                            <p class="lead mb-4 sentence case" style="letter-spacing: 3px; font-size: 1rem;">Temukan gaya unikmu melalui koleksi pilihan ThriftKing888.</p>
                            <a href="#latest" class="btn btn-light btn-lg rounded-0 px-5 fw-bold shadow-sm" style="font-size: 0.8rem; letter-spacing: 2px;">Mulai Belanja</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Slide 2 -->
        <div class="carousel-item hero-slide">
            <div style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?= $base_url ?>assets/img/hero-bg.jpg') center/cover; height: 100%; width: 100%;"></div>
            <div class="hero-slide-content">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-7">
                            <h1 class="display-2 fw-bold mb-3 hero-title text-capitalize">Timeless <br> Style</h1>
                            <p class="lead mb-4 sentence case" style="letter-spacing: 3px; font-size: 1rem;">Koleksi pakaian bekas dengan kualitas premium dan terjamin.</p>
                            <a href="<?= $base_url ?>produk" class="btn btn-light btn-lg rounded-0 px-5 fw-bold shadow-sm" style="font-size: 0.8rem; letter-spacing: 2px;">Lihat Produk</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<section class="py-5 bg-white border-bottom">
    <div class="container pt-4">
        <div class="row g-4">
            <div class="col-12">
                <div class="position-relative overflow-hidden text-white d-flex align-items-center justify-content-center" style="height: 280px; background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.4)), url('<?= $base_url ?>assets/img/thrifting-cat.jpg') no-repeat center center/cover; background-color: #222;">
                    <div class="text-center">
                        <h3 class="fw-bold text-capitalize m-0 text-white" style="font-family: 'Tenor Sans', sans-serif; letter-spacing: 3px;">THRIFTING</h3>
                        <!--<a href="<?= $base_url ?>produk" class="btn btn-sm btn-light rounded-0 px-4 py-2 text-capitalize fw-bold mt-3" style="font-size: 0.65rem; letter-spacing: 1px;">Lihat Semua Koleksi</a>-->
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="latest" class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3">
            <div>
                <h2 class="section-title fw-bold text-capitalize mb-1" style="font-size: 1.25rem;">Hot Sales</h2>
                <p class="text-muted small text-capitalize mb-0" style="letter-spacing: 2px; font-size: 0.7rem;">Produk terbaru yang baru saja mendarat</p>
            </div>
            <a href="<?= $base_url ?>produk" class="text-decoration-none fw-bold text-dark small text-capitalize" style="letter-spacing: 1.5px; font-size: 0.75rem;">
                Lihat Semua <i class="bi bi-chevron-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php
            $controller = new produkcontroller($conn);
            $data = $controller->index(null, null, 8, null, 'latest', true); // PERBAIKAN: Hanya tampilkan produk 'active'
            
            if (!empty($data)) :
                foreach ($data as $p) :
                    $produk_nama = htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8');
                    $produk_kategori = htmlspecialchars($p['nama_kategori'] ?? 'Produk', ENT_QUOTES, 'UTF-8');
                    $produk_gambar_raw = $p['gambar_utama'] ?? '';
                    $produk_gambar = htmlspecialchars($produk_gambar_raw, ENT_QUOTES, 'UTF-8');
                    $produk_id = intval($p['id']);
            ?>
            <div class="col-6 col-md-3">
                <div class="card product-card h-100">
                    <div class="image-container">
                        <?php if ($p['stok'] <= 0) : ?>
                            <div class="position-absolute top-0 start-0 sold-out-overlay m-2">Sold Out</div>
                        <?php elseif ($p['stok'] < 3) : ?>
                            <div class="position-absolute top-0 end-0 bg-warning text-dark small fw-bold px-2 py-1 m-2" style="font-size: 9px; letter-spacing: 1px; z-index: 3;">LIMITED</div>
                        <?php endif; ?>
                        <a href="<?= $base_url ?>detail/<?= $produk_id ?>">
                            <img src="<?= !empty($produk_gambar_raw) ? $base_url . 'assets/img/products/' . $produk_gambar : $base_url . 'assets/img/no-image.png' ?>" 
                                 alt="<?= $produk_nama ?>"
                                 class="<?= $p['stok'] <= 0 ? 'out-of-stock-img' : '' ?>"
                                 onerror="this.onerror=null;this.src='<?= $base_url ?>assets/img/no-image.png';">
                        </a> 
                        <a href="<?= $base_url ?>detail/<?= $produk_id ?>" class="btn-overlay text-capitalize">Lihat Detail</a>
                    </div>
                    <div class="card-body text-center px-0 pb-0">
                        <p class="text-muted text-capitalize mb-1" style="font-size: 10px; letter-spacing: 1px;"><?= $produk_kategori ?></p>
                        <h6 class="text-capitalize mb-1 fw-bold text-truncate" style="font-size: 13px; letter-spacing: 1px;" title="<?= $produk_nama ?>"><?= $produk_nama ?></h6>
                        <p class="<?= $p['stok'] <= 0 ? 'text-muted' : 'text-danger' ?> fw-bold mb-0" style="font-size: 14px; <?= $p['stok'] <= 0 ? 'text-decoration: line-through; opacity: 0.6;' : '' ?>">
                            <?= formatRupiah($p['harga_jual']) ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php 
                endforeach; 
            else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted small text-capitalize" style="letter-spacing: 1px;">Belum ada koleksi pakaian yang diunggah.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5 pt-4">
            <a href="<?= $base_url ?>produk" class="btn btn-outline-dark rounded-0 px-5 py-3 fw-bold" style="font-size: 0.7rem; letter-spacing: 2px;">Lihat Semua Koleksi</a>
        </div>
    </div>
</section>

<section id="recommendations" class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3">
            <div>
                <h2 class="section-title fw-bold text-capitalize mb-1" style="font-size: 1.25rem;">Rekomendasi Untukmu</h2>
                <p class="text-muted small text-capitalize mb-0" style="letter-spacing: 2px; font-size: 0.7rem;">Pilihan terbaik dari koleksi kami</p>
            </div>
            <a href="<?= $base_url ?>produk" class="text-decoration-none fw-bold text-dark small text-capitalize" style="letter-spacing: 1.5px; font-size: 0.75rem;">
                Lihat Semua <i class="bi bi-chevron-right ms-1"></i>
            </a>
        </div>

        
        <div class="row flex-nowrap overflow-auto pb-3" style="-webkit-overflow-scrolling: touch;">
            
            <?php
            // Kontainer Geser Horizontal Scroll
            // Mengambil 8 produk lainnya untuk rekomendasi, dimulai dari offset 8
            // PERBAIKAN: Hanya tampilkan produk 'active'
            $recommended_products = $controller->index(null, null, 8, 8, 'latest', true); 
            
            if (!empty($recommended_products)) :
                foreach ($recommended_products as $p) :
                    $produk_nama = htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8');
                    $produk_kategori = htmlspecialchars($p['nama_kategori'] ?? 'Produk', ENT_QUOTES, 'UTF-8');
                    $produk_gambar_raw = $p['gambar_utama'] ?? '';
                    $produk_gambar = htmlspecialchars($produk_gambar_raw, ENT_QUOTES, 'UTF-8');
                    $produk_id = intval($p['id']);
            ?>
            <div class="col-6 col-md-3 col-lg-3 d-flex">
                <div class="card product-card h-100 w-100">
                    <div class="image-container">
                        <?php if ($p['stok'] <= 0) : ?>
                            <div class="position-absolute top-0 start-0 sold-out-overlay m-2">Sold Out</div>
                        <?php elseif ($p['stok'] < 3) : ?>
                            <div class="position-absolute top-0 end-0 bg-warning text-dark small fw-bold px-2 py-1 m-2" style="font-size: 9px; letter-spacing: 1px; z-index: 3;">LIMITED</div>
                        <?php endif; ?>
                        <a href="<?= $base_url ?>detail/<?= $produk_id ?>">
                            <img src="<?= !empty($produk_gambar_raw) ? $base_url . 'assets/img/products/' . $produk_gambar : $base_url . 'assets/img/no-image.png' ?>" 
                                 alt="<?= $produk_nama ?>"
                                 class="<?= $p['stok'] <= 0 ? 'out-of-stock-img' : '' ?>"
                                 onerror="this.onerror=null;this.src='<?= $base_url ?>assets/img/no-image.png';">
                        </a> 
                        <a href="<?= $base_url ?>detail/<?= $produk_id ?>" class="btn-overlay text-capitalize">View Details</a>
                    </div>
                    <div class="card-body text-center px-0 pb-0">
                        <p class="text-muted text-capitalize mb-1" style="font-size: 10px; letter-spacing: 1px;"><?= $produk_kategori ?></p>
                        <h6 class="text-capitalize mb-1 fw-bold text-truncate" style="font-size: 13px; letter-spacing: 1px;" title="<?= $produk_nama ?>"><?= $produk_nama ?></h6>
                        <p class="<?= $p['stok'] <= 0 ? 'text-muted' : 'text-danger' ?> fw-bold mb-0" style="font-size: 14px; <?= $p['stok'] <= 0 ? 'text-decoration: line-through; opacity: 0.6;' : '' ?>">
                            <?= formatRupiah($p['harga_jual']) ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php 
                endforeach; 
            else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted small text-capitalize" style="letter-spacing: 1px;">Belum ada rekomendasi produk.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>