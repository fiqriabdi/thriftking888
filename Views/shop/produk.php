<?php 
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Format.php'; // INTEGRASI: Pustaka pemformatan Rupiah
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';

$controller = new produkcontroller(Database::getConnection());

// Menangkap kategori dan pencarian dari URL
// REVISI: Ambil kategori yang diizinkan secara dinamis dari database
$all_categories_for_validation = $controller->getCategories();
$allowed_categories = array_column($all_categories_for_validation, 'slug');

// Dukungan untuk Query String (?kategori=) dan Router Param (:kategori)
$kategori_raw = $_GET['kategori'] ?? ($kategori ?? null);
$kategori_aktif = ($kategori_raw && in_array(strtolower($kategori_raw), $allowed_categories, true))
    ? $kategori_raw
    : null;

$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$search_query = htmlspecialchars($search ?: '', ENT_QUOTES, 'UTF-8');
$search_param = $search ? '&search=' . urlencode($search) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

$display_title = 'Semua Produk';
if ($search) {
    $display_title = 'Hasil pencarian untuk "' . $search_query . '"';
    if ($kategori_aktif) {
        $display_title .= ' di ' . htmlspecialchars($kategori_aktif, ENT_QUOTES, 'UTF-8');
    }
} elseif ($kategori_aktif) {
    $display_title = htmlspecialchars($kategori_aktif, ENT_QUOTES, 'UTF-8');
}

// --- LOGIKA PAGINATION VIA CONTROLLER ---
$current_page = max(1, (int)($_GET['page'] ?? 1));
$katalogData = $controller->getKatalogPaginated($kategori_aktif, $search, $current_page, 6, $sort);

$data = $katalogData['products'];
// Ambil semua kategori untuk ditampilkan di filter
$all_categories = $controller->getCategories();

$total_pages = $katalogData['total_pages'];

// --- HANDLER AJAX UNTUK MUAT LEBIH BANYAK ---
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    echo $controller->renderProductCards($data);
    exit;
}

// Sinkronisasi URL dasar global
$base_url = defined('BASE_URL') ? BASE_URL : '';
?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; }
    
    /* Header Katalog Premium - Jalur Diperbaiki ke Public Base URL */
    .katalog-header {
        background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?= $base_url ?>assets/img/hero-bg.jpg');
        background-size: cover;
        background-position: center;
        padding: 80px 0;
        color: white;
        background-color: #111;
    }
    
    .katalog-title {
        font-family: 'Tenor Sans', sans-serif;
        letter-spacing: 5px;
    }

    .filter-title { 
        font-family: 'Tenor Sans', sans-serif;
        font-size: 12px; 
        letter-spacing: 3px; 
        font-weight: 700; 
        border-bottom: 2px solid #000; 
        padding-bottom: 10px; 
        margin-bottom: 20px; 
    }
    
    /* Navigasi Filter Kategori Bersudut Tajam (Minimalist Look) */
    .list-group-item { 
        font-size: 13px; 
        border: none; 
        border-radius: 0px !important;
        padding: 14px 0; 
        transition: 0.2s ease; 
        color: #7c7c7c; 
        text-decoration: none; 
        background: none;
        letter-spacing: 1px;
    }
    .list-group-item:hover { 
        color: #000; 
        padding-left: 5px;
    }
    .list-group-item.active { 
        background: none !important; 
        color: #000 !important; 
        font-weight: 700; 
        border-left: 2px solid #000 !important;
        padding-left: 10px;
    }
    
    /* Product Card Grid Standardisasi Layout */
    .product-card { 
        border: none; 
        transition: transform 0.3s ease; 
        background: none;
        border-radius: 0px;
    }
    .product-card:hover { 
        transform: translateY(-5px); 
    }
    
    .image-container { 
        position: relative; 
        overflow: hidden; 
        background-color: #f9f9f9; 
        padding-top: 120%; /* Rasio Gambar Seragam (Portrait) */
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
        padding: 12px; 
        text-align: center; 
        text-decoration: none; 
        font-size: 11px; 
        font-weight: bold; 
        letter-spacing: 2px;
        transition: 0.3s; 
        z-index: 2;
    }
    .product-card:hover .btn-overlay { bottom: 0; }

    /* Pagination Minimalis */
    .pagination .page-link {
        border-radius: 0;
        color: #111;
        border-color: #eee;
        font-size: 13px;
        padding: 8px 16px;
    }
    .pagination .page-item.active .page-link {
        background-color: #111;
        border-color: #111;
        color: #fff;
    }
</style>

<header class="katalog-header text-center mb-5">
    <div class="container">
        <h1 class="display-6 fw-bold text-capitalize katalog-title">
            <?= htmlspecialchars($display_title, ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <p class="lead small text-capitalize mb-0" style="letter-spacing: 3px; font-size: 0.75rem;">Koleksi Pilihan ThriftKing888</p>
    </div>
</header>

<div class="container mb-5">
    <div class="row">
        <div class="col-lg-3 col-md-4 mb-5">
            <h6 class="filter-title text-capitalize">Kategori</h6>
            <div class="list-group">
                
                <a href="<?= $base_url ?>produk<?= $search ? '?search=' . urlencode($search) : '' ?>" 
                   class="list-group-item text-capitalize <?= !$kategori_aktif ? 'active' : '' ?>">Semua Produk</a>
                
                <?php foreach ($all_categories as $cat): ?>
                    <a href="<?= $base_url ?>produk/<?= htmlspecialchars($cat['slug']) ?><?= $search ? '?search=' . urlencode($search) : '' ?>" 
                       class="list-group-item text-capitalize <?= $kategori_aktif == $cat['slug'] ? 'active' : '' ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-9 col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="filter-title text-capitalize mb-0 border-0 pb-0">Daftar Produk</h6>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-capitalize fw-bold text-muted mb-0" style="font-size: 10px; letter-spacing: 1px;">Urutkan:</label>
                    <select id="sortKatalog" class="form-select form-select-sm rounded-0 border-0 border-bottom bg-transparent shadow-none" style="width: 160px; font-size: 12px; cursor: pointer;">
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Harga Terendah</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Harga Tertinggi</option>
                    </select>
                </div>
            </div>

            <div class="row g-4" id="productGrid">
                <?php if (!empty($data)) : ?>
                    <?= $controller->renderProductCards($data) ?>
                <?php else : ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-search display-3 text-muted opacity-50"></i>
                        <h5 class="text-muted mt-3 text-capitalize small tracking-wider" style="letter-spacing: 1px;">Maaf, produk tidak ditemukan.</h5>                        
                        <a href="<?= $base_url ?>produk" class="btn btn-dark mt-3 rounded-0 px-4 text-capitalize small" style="font-size: 0.7rem; letter-spacing: 1px;">Reset Filter</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Fitur Muat Lebih Banyak (AJAX) -->
            <?php if ($total_pages > 1) : ?>
            <div class="text-center mt-5" id="loadMoreContainer">
                <?php if ($total_pages > $current_page) : ?>
                    <button id="btnLoadMore" class="btn btn-outline-dark rounded-0 px-5 py-3 fw-bold" style="font-size: 0.7rem; letter-spacing: 2px;"
                            data-page="<?= $current_page ?>" data-total="<?= $total_pages ?>">Muat Lebih Banyak</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnLoadMore = document.getElementById('btnLoadMore');
    const sortKatalog = document.getElementById('sortKatalog');

    if (btnLoadMore) {
        btnLoadMore.addEventListener('click', function() {
            const btn = this;
            const nextPage = parseInt(btn.dataset.page) + 1;
            const totalPages = parseInt(btn.dataset.total);
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('page', nextPage);

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> MEMUAT...';
            btn.disabled = true;

            fetch(currentUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    document.getElementById('productGrid').insertAdjacentHTML('beforeend', html);
                    btn.dataset.page = nextPage;
                    btn.innerHTML = 'MUAT LEBIH BANYAK';
                    btn.disabled = false;
                    if (nextPage >= totalPages) btn.parentElement.remove();
                });
        });
    }

    sortKatalog.addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', this.value);
        url.searchParams.set('page', 1);
        window.location.href = url.href;
    });
});
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>