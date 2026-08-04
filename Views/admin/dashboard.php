<?php
// 1. Inisialisasi Session & Keamanan
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin'); 

// 2. Hubungkan Koneksi Database & Model
require_once APP_ROOT . '/Config/koneksi.php'; 
require_once APP_ROOT . '/Controllers/Admin/ulasancontroller.php';
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';

$pageTitle = 'Dashboard Admin';
$activePage = 'dashboard';

// --- PROSES AMBIL DATA UNTUK CARD STATISTIK (Logika Asli Dipertahankan) ---

// A. Koleksi Produk
$produk = new ProdukController(Database::getConnection());
$list_produk = $produk->index();
$jumlah_produk = ($list_produk) ? count($list_produk) : 0;

// B. Pesanan Aktif (Menggunakan tabel 'orders' dan kolom 'status_order')
$conn = Database::getConnection();
$q_pesanan = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status_order NOT IN ('completed', 'cancelled')");
$res_pesanan = mysqli_fetch_assoc($q_pesanan);
$jumlah_pesanan = $res_pesanan['total'] ?? 0;

// C. Total Pelanggan
$q_pelanggan = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'pelanggan'");
$res_pelanggan = mysqli_fetch_assoc($q_pelanggan);
$jumlah_pelanggan = $res_pelanggan['total'] ?? 0;

// D. Total Omzet
$q_omzet = mysqli_query($conn, "SELECT SUM(total_pembayaran) as total FROM orders WHERE status_order = 'completed'");
$res_omzet = mysqli_fetch_assoc($q_omzet);
$total_pendapatan = $res_omzet['total'] ?? 0;

// E. Ulasan Pending (Butuh Moderasi)
$ulasan_ctrl = new ulasancontroller($conn);
$list_pending = $ulasan_ctrl->getAll('pending');
$jumlah_ulasan_pending = count($list_pending);

// F. Log Stok Terbaru (24 Jam Terakhir)
$jumlah_stok_baru = $produk->countRecentStockLogs();

// G. Konfirmasi Pembayaran Pending (FIX: Tambahkan query ini)
$q_pending_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status_order = 'pending_confirmation'");
$res_pending_count = mysqli_fetch_assoc($q_pending_count);
$pending_confirmation_count = $res_pending_count['total'] ?? 0;

// --- LOGIKA UNTUK SIDEBAR AKTIF ---
$katalog_pages = ['produk', 'kategori', 'stock_logs', 'produk_create', 'produk_edit'];
$penjualan_pages = ['pesanan', 'ulasan', 'laporan', 'konfirmasi-pembayaran'];
$data_master_pages = ['bank-rekening', 'ongkir'];
$sistem_pages = ['pengguna', 'pengguna_create', 'pengguna_edit', 'settings', 'activity_log', 'reset-password'];

$isKatalogOpen = in_array($activePage, $katalog_pages);
$isPenjualanOpen = in_array($activePage, $penjualan_pages);
$isDataMasterOpen = in_array($activePage, $data_master_pages);
$isSistemOpen = in_array($activePage, $sistem_pages);

?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>

<style>
    /* Reset & Base Dashboard Wrapper */
    body {
        background-color: #f3f4f6 !important;
        font-family: 'Inter', sans-serif;
    }
    
    .dashboard-wrapper {
        display: flex;
        min-height: 100vh;
    }

    /* SIDEBAR STYLE (Sesuai Desain Panel Toko) */
    .admin-sidebar {
        width: 260px;
        background-color: #111827;
        color: #9ca3af;
        padding: 24px 16px;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }
    
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 12px 24px 12px;
        border-bottom: 1px solid #1f2937;
        margin-bottom: 12px;
    }
    
    .sidebar-brand h5 {
        color: #ffffff;
        font-weight: 700;
        margin: 0;
        letter-spacing: 1px;
        font-size: 1.15rem;
    }
    
    .sidebar-brand i {
        color: #fbbf24;
    }

    .sidebar-menu {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-grow: 1;
    }

    /* Tambahan style untuk divider menu sidebar */
    .menu-divider {
        color: #6b7280;
        font-size: 0.75rem;
        letter-spacing: 1px;
        padding: 12px 16px 4px 16px;
        margin-top: 8px;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        color: #9ca3af;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .menu-item:hover {
        background-color: #1f2937;
        color: #ffffff;
    }

    /* State Active Khusus Menu DASHBOARD */
    .menu-item.active {
        background-color: #d97706; 
        color: #ffffff !important;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    
    .menu-item i {
        font-size: 1.1rem;
    }

    /* KONTEN KANAN UTAMA */
    .main-content-area {
        flex-grow: 1;
        padding: 30px;
        overflow-x: hidden;
    }

    /* Top Breadcrumb Badges */
    .custom-breadcrumb {
        background-color: #1f2937;
        padding: 6px 16px;
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
    }
    .custom-breadcrumb a, .custom-breadcrumb li {
        font-size: 0.8rem;
        color: #9ca3af !important;
    }
    .custom-breadcrumb .active {
        color: #fbbf24 !important;
        font-weight: 600;
    }

    /* Panel Grid Statistik */
    .panel-stat-card {
        border: none;
        border-radius: 14px;
        padding: 24px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease;
    }
    .panel-stat-card:hover {
        transform: translateY(-5px);
    }
    .stat-info h2 {
        font-size: 2rem;
        font-weight: 700;
        margin: 5px 0 0 0;
    }
    .stat-info p {
        font-size: 0.75rem;
        text-transform: uppercase;
        margin: 0;
        opacity: 0.85;
        letter-spacing: 0.5px;
    }

    /* Navigation Modul Manajemen Cards */
    .hover-card { 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        border: 1px solid #e5e7eb !important;
    }

    /* --- Gaya Sidebar Dropdown Baru --- */
    .submenu {
        padding-left: 1rem;
        background-color: #1f2937;
        overflow: hidden;
    }
    .submenu-item {
        display: block;
        padding: 0.6rem 1rem;
        color: #9ca3af;
        text-decoration: none;
        font-size: 0.85rem;
        position: relative;
        padding-left: 2.5rem;
        border-radius: 8px;
        margin: 2px 0;
    }
    .submenu-item::before {
        content: '›';
        position: absolute;
        left: 1.5rem;
        font-weight: bold;
        color: #6b7280;
    }
    .submenu-item:hover, .submenu-item.active {
        color: #ffffff;
        background-color: #374151;
    }
    .dropdown-toggle::after {
        display: block;
        margin-left: auto;
        transition: transform .2s ease-in-out;
    }
</style>

<div class="dashboard-wrapper">
    
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-crown-fill fs-4"></i>
            <h5>THRIFTKING888</h5>
        </div>
        
        <div class="sidebar-menu">
            <a href="<?= BASE_URL; ?>admin/dashboard" class="menu-item <?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span></a>

            <!-- Grup Katalog -->
            <a href="#katalogSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isKatalogOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isKatalogOpen ? 'active' : '' ?>">
                <i class="bi bi-box-seam-fill"></i> <span>Data Produk</span>
            </a>
            <ul class="collapse list-unstyled submenu <?= $isKatalogOpen ? 'show' : '' ?>" id="katalogSubmenu">
                <li><a href="<?= BASE_URL; ?>admin/produk" class="submenu-item <?= in_array($activePage, ['produk', 'produk_index', 'produk_create', 'produk_edit']) ? 'active' : '' ?>"> Produk</a></li>
                <li><a href="<?= BASE_URL; ?>admin/kategori" class="submenu-item <?= $activePage === 'kategori' ? 'active' : '' ?>">Kategori</a></li>
                <li><a href="<?= BASE_URL; ?>admin/produk/stock-logs" class="submenu-item <?= $activePage === 'stock_logs' ? 'active' : '' ?>">Laporan Stok</a></li>
            </ul>

            <!-- Grup Penjualan -->
            <a href="#penjualanSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isPenjualanOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isPenjualanOpen ? 'active' : '' ?>">
                <i class="bi bi-cart-fill"></i> <span>Data Pesanan</span>
            </a>
            <ul class="collapse list-unstyled submenu <?= $isPenjualanOpen ? 'show' : '' ?>" id="penjualanSubmenu">
                <li><a href="<?= BASE_URL; ?>admin/pesanan" class="submenu-item <?= $activePage === 'pesanan' ? 'active' : '' ?>">Data Pesanan</a></li>
                <li><a href="<?= BASE_URL; ?>admin/konfirmasi-pembayaran" class="submenu-item <?= $activePage === 'konfirmasi-pembayaran' ? 'active' : '' ?> d-flex justify-content-between align-items-center">
                    <span>Data Pembayaran</span>
                    <?php if ($pending_confirmation_count > 0): ?>
                        <span class="badge bg-warning rounded-pill"><?= $pending_confirmation_count ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="<?= BASE_URL; ?>admin/ulasan" class="submenu-item <?= $activePage === 'ulasan' ? 'active' : '' ?>">Ulasan</a></li>
                <li><a href="<?= BASE_URL; ?>admin/laporan" class="submenu-item <?= $activePage === 'laporan' ? 'active' : '' ?>">Laporan Penjualan</a></li>
            </ul>

              <!-- Grup Data Bank/Ongkir -->
            <a href="#dataMasterSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isDataMasterOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isDataMasterOpen ? 'active' : '' ?>">
                <i class="bi bi-server"></i> <span>Data Bank & Ongkir</span>
            </a>
            <ul class="collapse list-unstyled submenu <?= $isDataMasterOpen ? 'show' : '' ?>" id="dataMasterSubmenu">
                <li><a href="<?= BASE_URL; ?>admin/bank-rekening" class="submenu-item <?= $activePage === 'bank-rekening' ? 'active' : '' ?>">Manajemen Bank</a></li>
                <li><a href="<?= BASE_URL; ?>admin/ongkir" class="submenu-item <?= $activePage === 'ongkir' ? 'active' : '' ?>">Manajemen Ongkir</a></li>
            </ul>

            <!-- Grup Sistem -->
            <a href="#sistemSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isSistemOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isSistemOpen ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i> <span>Sistem</span>
            </a>
            <ul class="collapse list-unstyled submenu <?= $isSistemOpen ? 'show' : '' ?>" id="sistemSubmenu">
                <li><a href="<?= BASE_URL; ?>admin/pengguna" class="submenu-item <?= in_array($activePage, ['pengguna', 'pengguna_create', 'pengguna_edit']) ? 'active' : '' ?>">Data Pengguna</a></li>
                <li><a href="<?= BASE_URL; ?>admin/settings" class="submenu-item <?= $activePage === 'settings' ? 'active' : '' ?>">Pengaturan Toko</a></li>
                <li><a href="<?= BASE_URL; ?>admin/reset-password" class="submenu-item <?= $activePage === 'reset-password' ? 'active' : '' ?>">Reset Password</a></li>
                <li><a href="<?= BASE_URL; ?>admin/activity-log" class="submenu-item <?= $activePage === 'activity_log' ? 'active' : '' ?>">Log Aktivitas</a></li>
            </ul>
            
            <div class="mt-auto mb-2">
                <hr style="border-color: #374151;">
                <a href="<?= BASE_URL; ?>auth/logout" class="menu-item text-danger"><i class="bi bi-box-arrow-left"></i> <span>Logout</span></a>
            </div>
        </div>
    </div>

    <div class="main-content-area">
        <div class="container-fluid p-0">
            
            <div class="row mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                <div>
                    <h2 class="fw-bold m-0 text-dark" style="letter-spacing: 0.5px; font-family: 'Tenor Sans', sans-serif;">ADMINISTRATOR DASHBOARD</h2>
                    <p class="text-muted small mb-0">Sesi Aktif Internal: <span class="fw-semibold text-dark"><?= htmlspecialchars($_SESSION['user']['nama']); ?></span></p>
                </div>
                <div>
                    <ol class="breadcrumb custom-breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </div>
            </div>

            <div class="row g-3 mb-5">
                <div class="col-md-3">
                    <div class="panel-stat-card" style="background: linear-gradient(135deg, #1f2937, #111827);">
                        <div class="stat-info">
                            <p>Total Produk</p>
                            <h2><?= $jumlah_produk; ?> <span class="fs-6 fw-normal opacity-75">Item</span></h2>
                        </div>
                        <i class="bi bi-tags-fill fs-2 opacity-25"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="panel-stat-card" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        <div class="stat-info">
                            <p>Total Pesanan </p>
                            <h2><?= $jumlah_pesanan; ?> <span class="fs-6 fw-normal opacity-75">Trx</span></h2>
                        </div>
                        <i class="bi bi-receipt-cutoff fs-2 opacity-25"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="panel-stat-card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                        <div class="stat-info">
                            <p>Total Pelanggan</p>
                            <h2><?= $jumlah_pelanggan; ?> <span class="fs-6 fw-normal opacity-75">User</span></h2>
                        </div>
                        <i class="bi bi-people-fill fs-2 opacity-25"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="panel-stat-card" style="background: linear-gradient(135deg, #059669, #047857);">
                        <div class="stat-info">
                            <p>Total Pendapatan</p>
                            <h2 class="fs-4 mt-2">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h2>
                        </div>
                        <i class="bi bi-wallet2 fs-2 opacity-25"></i>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-4">
                    <h5 class="fw-bold border-start border-4 border-dark ps-3 text-uppercase small" style="letter-spacing: 1px;">Modul Manajemen</h5>
                </div>
                
                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/produk" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4">
                        <i class="bi bi-box-seam-fill fs-2 text-warning mb-2"></i>
                        <h6 class="fw-bold mb-1">Data Produk</h6>
                        <p class="small text-muted mb-0">Atur Stok, Tambah Produk & Kategori</p>
                    </a>
                </div>

                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/pengguna" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4">
                        <i class="bi bi-people-fill fs-2 text-info mb-2"></i>
                        <h6 class="fw-bold mb-1">Data Pengguna</h6>
                        <p class="small text-muted mb-0">Manajemen Akses & Informasi Akun Users</p>
                    </a>
                </div>

                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/pesanan" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4">
                        <i class="bi bi-cart-check-fill fs-2 text-danger mb-2"></i>
                        <h6 class="fw-bold mb-1">Data Pesanan</h6>
                        <p class="small text-muted mb-0">Kelola Status Pengiriman & Pesanan Masuk</p>
                    </a>
                </div>

                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/laporan" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4">
                        <i class="bi bi-file-earmark-bar-graph-fill fs-2 text-success mb-2"></i>
                        <h6 class="fw-bold mb-1">Laporan Penjualan</h6>
                        <p class="small text-muted mb-0">Analisis Grafik & Cetak Pembukuan Laporan</p>
                    </a>
                </div>

                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/settings" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4">
                        <i class="bi bi-gear-fill fs-2 text-secondary mb-2"></i>
                        <h6 class="fw-bold mb-1">Pengaturan Toko</h6>
                        <p class="small text-muted mb-0">Konfigurasi Pengaturan Sistem & Informasi Kontak</p>
                    </a>
                </div>
                
                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/activity-log" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4">
                        <i class="bi bi-shield-lock-fill fs-2 text-dark mb-2"></i>
                        <h6 class="fw-bold mb-1"> Log Aktivitas </h6>
                        <p class="small text-muted mb-0">Pantau Jejak Aktivitas Administrator</p>
                    </a>
                </div>
                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>admin/produk/stock-logs" class="card text-center p-4 border-0 shadow-sm text-decoration-none text-dark h-100 hover-card rounded-4 position-relative">

                       <!-- <?php if ($jumlah_stok_baru > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger animate-pulse" style="z-index: 10; margin-top: 20px; margin-left: -35px;">
                                <?= $jumlah_stok_baru; ?>
                                <span class="visually-hidden">log baru</span>
                            </span>
                        <?php endif; ?>-->
                        
                        <i class="bi bi-clock-history fs-2 text-primary mb-2"></i>
                        <h6 class="fw-bold mb-1">Histori Stok</h6>
                        <p class="small text-muted mb-0">Lacak Perubahan Stok Produk</p>
                    </a>
                </div>
                <div class="col-md-4 mb-4">
                    <a href="<?= BASE_URL; ?>" class="card text-center p-4 bg-light border-0 shadow-sm text-decoration-none text-secondary h-100 hover-card rounded-4 d-flex flex-column align-items-center justify-content-center">
                        <i class="bi bi-arrow-left-circle-fill fs-2 mb-2 text-muted"></i>
                        <h6 class="fw-bold mb-0">Back Home Page</h6>
                    </a>
                </div>
            </div>

        </div>
    </div>
    
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>