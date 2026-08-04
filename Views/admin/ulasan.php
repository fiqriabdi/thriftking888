<?php
/**
 * File: admin/ulasan.php
 * Halaman admin untuk mengelola ulasan/review produk
 */

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Middleware/auth.php';
require_once APP_ROOT . '/Controllers/Admin/ulasancontroller.php';
require_once APP_ROOT . '/helpers/Security.php'; // Untuk CSRF

auth::requireRole('admin');

$ulasan_controller = new ulasancontroller($conn); // Kirim koneksi ke controller ulasan

$csrf_token = generateCSRFToken(); // Generate CSRF token

// Proses aksi (Logika Asli Tetap Dipertahankan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Permintaan tidak valid (CSRF token).';
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'approve') {
                $id = (int)$_POST['id'];
                if ($ulasan_controller->updateStatus($id, 'approved')) {
                    $_SESSION['success'] = 'Review disetujui!';
                }
            } elseif ($_POST['action'] === 'reject') {
                $id = (int)$_POST['id'];
                if ($ulasan_controller->updateStatus($id, 'rejected')) {
                    $_SESSION['success'] = 'Review ditolak!';
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = (int)$_POST['id'];
                if ($ulasan_controller->delete($id)) {
                    $_SESSION['success'] = 'Review dihapus!';
                }
            } elseif ($_POST['action'] === 'reply') {
                $id = (int)$_POST['id'];
                if ($ulasan_controller->reply($id, trim($_POST['reply_text'] ?? ''))) {
                    $_SESSION['success'] = 'Balasan berhasil dikirim!';
                }
            }
        }
    }
}

// Filter status
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = 10; // Jumlah ulasan per halaman
$filter_status = isset($_GET['status']) ? $_GET['status'] : null;

// Menggunakan metode index baru dari controller
$paginated_data = $ulasan_controller->index($current_page, $items_per_page, $filter_status);
$all_ulasan = $paginated_data['reviews'];
$total_pages = $paginated_data['total_pages'];

// [OPTIMASI] Hitung jumlah untuk filter menggunakan metode yang efisien
$count_all = $ulasan_controller->countReviewsByStatus();
$count_pending = $ulasan_controller->countReviewsByStatus('pending');
$count_approved = $ulasan_controller->countReviewsByStatus('approved');
$count_rejected = $ulasan_controller->countReviewsByStatus('rejected');

$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);

$pageTitle = 'Kelola Ulasan Produk'; 
$activePage = 'ulasan'; 

// AMBIL JUMLAH UNTUK BADGE NOTIFIKASI
$q_pending_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status_order = 'pending_confirmation'");
$res_pending_count = mysqli_fetch_assoc($q_pending_count);
$pending_confirmation_count = $res_pending_count['total'] ?? 0;

// --- LOGIKA UNTUK SIDEBAR AKTIF ---
$katalog_pages = ['produk', 'produk_index', 'produk_create', 'produk_edit', 'kategori', 'stock_logs'];
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

    /* SIDEBAR STYLE (Sesuai Panah Gambar Mockup) */
    .admin-sidebar {
        width: 260px;
        background-color: #111827; /* Hitam Pekat Premium */
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
        margin-bottom: 24px;
    }
    
    .sidebar-brand h5 {
        color: #ffffff;
        font-weight: 700;
        margin: 0;
        letter-spacing: 1px;
        font-size: 1.15rem;
    }
    
    .sidebar-brand i {
        color: #fbbf24; /* Mahkota Emas */
    }

    .sidebar-menu {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex-grow: 1;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
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

    /* State Active Khusus Menu ULASAN */
    .menu-item.active {
        background-color: #d97706; /* Emas/Amber seperti gambar */
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

    /* --- Gaya Sidebar Dropdown Baru --- */
    .submenu { padding-left: 1rem; background-color: #1f2937; overflow: hidden; }
    .submenu-item { display: block; padding: 0.6rem 1rem; color: #9ca3af; text-decoration: none; font-size: 0.85rem; position: relative; padding-left: 2.5rem; border-radius: 8px; margin: 2px 0; }
    .submenu-item::before { content: '›'; position: absolute; left: 1.5rem; font-weight: bold; color: #6b7280; }
    .submenu-item:hover, .submenu-item.active { color: #ffffff; background-color: #374151; }
    .dropdown-toggle::after { display: block; margin-left: auto; transition: transform .2s ease-in-out; }

    /* Statistik Cards Grid */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .panel-stat-card {
        border: none;
        border-radius: 12px;
        padding: 20px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .stat-info h3 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
    }
    .stat-info p {
        font-size: 0.75rem;
        text-uppercase;
        margin: 5px 0 0 0;
        opacity: 0.85;
    }

    /* Capsule Filters */
    .capsule-filters {
        display: flex;
        gap: 8px;
        background: white;
        padding: 10px 16px;
        border-radius: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        width: fit-content;
    }
    .btn-capsule {
        border-radius: 20px !important;
        padding: 6px 16px !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        border: none !important;
    }

    /* Modern Table Output */
    .table-responsive-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        padding: 20px;
        border: 1px solid #e5e7eb;
    }
    .modern-table {
        width: 100%;
        vertical-align: middle;
    }
    .modern-table th {
        background: #ffffff;
        color: #4b5563;
        font-size: 0.75rem;
        text-uppercase;
        padding: 12px 16px;
        border-bottom: 2px solid #f3f4f6;
    }
    .modern-table td {
        padding: 16px;
        font-size: 0.88rem;
    }

    .badge-capsule {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-capsule-pending { background-color: #fef3c7; color: #d97706; }
    .badge-capsule-approved { background-color: #d1fae5; color: #059669; }
    .badge-capsule-rejected { background-color: #fee2e2; color: #dc2626; }
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
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
                <li><a href="<?= BASE_URL; ?>admin/ulasan" class="submenu-item <?= $activePage === 'ulasan' ? 'active' : '' ?>"> Ulasan</a></li>
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
            <div class="row">
                
                <div class="col-12 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                    <div>
                        <h3 class="fw-bold m-0 text-dark" style="letter-spacing: 0.5px;">KELOLA ULASAN PRODUK</h3>
                        <p class="text-muted small mb-0">Moderasi dan analisis feedback pelanggan Thrift King 888 secara real-time</p>
                    </div>
                    <div>
                        <ol class="breadcrumb custom-breadcrumb m-0">
                            <li class="breadcrumb-item">
                                <a href="<?= BASE_URL; ?>admin/dashboard" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Ulasan Produk</li>
                        </ol>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                <div class="col-12 mb-3">
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <div class="stats-container">
                        <div class="panel-stat-card" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                            <div class="stat-info">
                                <h3><?php echo count($ulasan_controller->getAll()); ?></h3>
                                <p>Total Ulasan</p>
                            </div>
                            <i class="bi bi-chat-right-text-fill fs-3 opacity-25"></i>
                        </div>
                        <div class="panel-stat-card" style="background: linear-gradient(135deg, #d97706, #b45309);">
                            <div class="stat-info">
                                <h3><?php echo count($ulasan_controller->getAll('pending')); ?></h3>
                                <p>Ulasan Baru (Pending)</p>
                            </div>
                            <i class="bi bi-hourglass-split fs-3 opacity-25"></i>
                        </div>
                        <div class="panel-stat-card" style="background: linear-gradient(135deg, #059669, #047857);">
                            <div class="stat-info">
                                <h3><?php echo count($ulasan_controller->getAll('approved')); ?></h3>
                                <p>Disetujui</p>
                            </div>
                            <i class="bi bi-check-circle-fill fs-3 opacity-25"></i>
                        </div>
                        <div class="panel-stat-card" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                            <div class="stat-info">
                                <h3><?php echo count($ulasan_controller->getAll('rejected')); ?></h3>
                                <p>Ditolak</p>
                            </div>
                            <i class="bi bi-x-circle-fill fs-3 opacity-25"></i>
                        </div>
                    </div>
                </div>

                <div class="col-12 mb-4">
                    <div class="capsule-filters">
                        <a href="?status=" class="btn btn-capsule <?= $filter_status === null ? 'btn-dark' : 'btn-light text-secondary'; ?>">
                            Semua (<?= $count_all ?>)
                        </a>
                        <a href="?status=pending" class="btn btn-capsule <?= $filter_status === 'pending' ? 'btn-warning text-white' : 'btn-light text-secondary'; ?>">
                            Pending (<?= $count_pending ?>)
                        </a>
                        <a href="?status=approved" class="btn btn-capsule <?= $filter_status === 'approved' ? 'btn-success' : 'btn-light text-secondary'; ?>">
                            Disetujui (<?= $count_approved ?>)
                        </a>
                        <a href="?status=rejected" class="btn btn-capsule <?= $filter_status === 'rejected' ? 'btn-danger' : 'btn-light text-secondary'; ?>">
                            Ditolak (<?= $count_rejected ?>)
                        </a>
                    </div>
                </div>

                <div class="col-12">
                    <div class="table-responsive-card">
                        <div class="table-responsive">
                            <table class="table modern-table align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Produk</th>
                                        <th style="width: 25%;">Pelanggan</th>
                                        <th style="width: 35%;">Ulasan</th>
                                        <th style="width: 10%;">Status</th>
                                        <th style="width: 10%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($all_ulasan)): ?>
                                        <?php foreach ($all_ulasan as $ulasan): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if (!empty($ulasan['foto'])): ?>
                                                        <img src="<?= BASE_URL ?>assets/img/reviews/<?= htmlspecialchars($ulasan['foto'], ENT_QUOTES, 'UTF-8') ?>" style="width:40px; height:40px; object-fit:cover; border-radius:6px;">
                                                    <?php else: ?>
                                                        <div style="width:40px; height:40px; background:#f3f4f6; border-radius:6px;" class="d-flex align-items-center justify-content-center text-secondary">
                                                            <i class="bi bi-box-seam"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($ulasan['nama_produk']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($ulasan['nama_pembeli']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($ulasan['email_pembeli']); ?></div>
                                            </td>
                                            <td>
                                                <div class="text-warning mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++) {
                                                        echo $i <= $ulasan['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                                    } ?>
                                                </div>
                                                <div class="small fw-bold text-dark"><?php echo htmlspecialchars($ulasan['judul']); ?></div>
                                                <div class="text-muted small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($ulasan['isi']); ?></div>

                                                <?php if (!empty($ulasan['admin_reply_text'])): ?>
                                                    <div class="mt-2 p-2 bg-light border-start border-4 border-success">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-bold small text-success"><i class="bi bi-reply-fill me-1"></i> Balasan Anda</span>
                                                            <small class="text-muted" style="font-size: 10px;"><?= date('d/m/y H:i', strtotime($ulasan['admin_replied_at'])) ?></small>
                                                        </div>
                                                        <p class="small text-dark mb-0 mt-1" style="line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($ulasan['admin_reply_text']) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-capsule badge-capsule-<?php echo $ulasan['status']; ?>">
                                                    <?php echo ucfirst($ulasan['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <?php if ($ulasan['status'] === 'approved' && empty($ulasan['admin_reply_text'])): ?>
                                                        <button type="button" class="btn btn-sm btn-info text-white py-1 px-2" style="font-size:0.75rem;" 
                                                                data-bs-toggle="modal" data-bs-target="#replyReviewModal"
                                                                data-review-id="<?= $ulasan['id'] ?>"
                                                                data-review-content="<?= htmlspecialchars($ulasan['isi']) ?>">
                                                            Balas
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($ulasan['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="id" value="<?php echo $ulasan['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-success py-1 px-2" style="font-size:0.75rem;" onclick="return confirm('Setujui?')">Approve</button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="id" value="<?php echo $ulasan['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn btn-sm btn-warning text-white py-1 px-2" style="font-size:0.75rem;" onclick="return confirm('Tolak?')">Reject</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="id" value="<?php echo $ulasan['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-danger py-1 px-2" style="font-size:0.75rem;" onclick="return confirm('Hapus?')"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="bi bi-chat-left-dots fs-3 d-block mb-2"></i> Belum ada data ulasan.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <?php
                            $path = strtok($_SERVER['REQUEST_URI'], '?');
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);
                        ?>
                        <div class="card-footer bg-white py-3">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm justify-content-end mb-0">
                                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $path ?>?page=<?= $current_page - 1 ?><?= !empty($queryString) ? '&' . $queryString : '' ?>"><<</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= $path ?>?page=<?= $i ?><?= !empty($queryString) ? '&' . $queryString : '' ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $path ?>?page=<?= $current_page + 1 ?><?= !empty($queryString) ? '&' . $queryString : '' ?>">>></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>

            </div>
        </div>
    </div>
    
</div>

<!-- Modal Balas Ulasan -->
<div class="modal fade" id="replyReviewModal" tabindex="-1" aria-labelledby="replyReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="replyReviewModalLabel"><i class="bi bi-reply-fill me-2"></i>Balas Ulasan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="id" id="reviewIdInput">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Ulasan Pelanggan:</label>
                        <blockquote class="blockquote bg-light p-3 rounded small" id="customerReviewContent" style="font-size: 0.9rem; white-space: pre-wrap;"></blockquote>
                    </div>
                    <div class="mb-3">
                        <label for="replyText" class="form-label small fw-bold">Tulis Balasan Anda:</label>
                        <textarea class="form-control" id="replyText" name="reply_text" rows="5" required placeholder="Tulis balasan yang sopan dan informatif..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill me-1"></i> Kirim Balasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var replyModal = document.getElementById('replyReviewModal');
    replyModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('reviewIdInput').value = button.getAttribute('data-review-id');
        document.getElementById('customerReviewContent').textContent = button.getAttribute('data-review-content');
        document.getElementById('replyText').value = '';
    });
});
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>