<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

// 1. Proteksi Halaman Admin
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/helpers/Security.php'; // [DITAMBAHKAN] Untuk CSRF

// 2. Controller User
require_once APP_ROOT . '/Controllers/Admin/usercontroller.php';
$controller = new usercontroller(Database::getConnection()); // Gunakan koneksi terpusat
$error_message = '';
$pageTitle = 'Manajemen Pengguna';
$activePage = 'pengguna';

// [DITAMBAHKAN] Generate CSRF token untuk semua form di halaman ini
$csrf_token = generateCSRFToken();

// --- LOGIKA PAGINATION & FILTER ---
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = 15; // Tentukan jumlah item per halaman
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// --- LOGIKA EKSPOR CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Panggil controller dengan limit 0 untuk mendapatkan semua data
    $export_data = $controller->index(1, 0, $status_filter, $search_query);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pengguna_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nama', 'Email', 'No HP', 'Alamat', 'Role', 'Status', 'Login Terakhir', 'Tanggal Terdaftar']);
    
    foreach ($export_data['users'] as $row) {
        fputcsv($output, [
            $row['id'],
            $row['nama'],
            $row['email'],
            $row['no_hp'],
            $row['alamat'],
            $row['role'],
            $row['status'],
            $row['last_login'],
            $row['created_at']
        ]);
    }
    fclose($output);
    exit();
}

// 3. Hapus Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Permintaan tidak valid (CSRF token).';
    } else {
        $userId = intval($_POST['hapus_id']);
        if ($userId === intval($_SESSION['user']['id'])) {
            $error_message = "Anda tidak dapat menghapus akun admin yang sedang login.";
        } else {
            $deleted = $controller->destroy($userId);
            if ($deleted) {
                header('Location: ' . BASE_URL . 'admin/pengguna?pesan=hapus_berhasil');
                exit();
            }
            $error_message = "Gagal menghapus pengguna. Hanya akun pelanggan yang dapat dihapus.";
        }
    }
}

// --- AMBIL DATA UNTUK TAMPILAN ---
$paginated_data = $controller->index($current_page, $items_per_page, $status_filter, $search_query);
$users = $paginated_data['users'];
$total_pages = $paginated_data['total_pages'];

// AMBIL JUMLAH UNTUK BADGE NOTIFIKASI
$conn = Database::getConnection();
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
    body { background-color: #f3f4f6 !important; font-family: 'Inter', sans-serif; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    .admin-sidebar { width: 260px; background-color: #111827; color: #9ca3af; padding: 24px 16px; display: flex; flex-direction: column; flex-shrink: 0; }
    .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 0 12px 24px 12px; border-bottom: 1px solid #1f2937; margin-bottom: 24px; }
    .sidebar-brand h5 { color: #ffffff; font-weight: 700; margin: 0; letter-spacing: 1px; font-size: 1.15rem; }
    .sidebar-brand i { color: #fbbf24; }
    .sidebar-menu { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
    .menu-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #9ca3af; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 8px; transition: all 0.2s ease; }
    .menu-item:hover { background-color: #1f2937; color: #ffffff; }
    .menu-item.active { background-color: #d97706; color: #ffffff !important; font-weight: 600; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
    .main-content-area { flex-grow: 1; padding: 30px; overflow-x: hidden; }
    .custom-breadcrumb { background-color: #1f2937; padding: 6px 16px; border-radius: 30px; display: inline-flex; align-items: center; }
    .custom-breadcrumb a, .custom-breadcrumb li { font-size: 0.8rem; color: #9ca3af !important; }
    .custom-breadcrumb .active { color: #fbbf24 !important; font-weight: 600; }

    /* --- Gaya Sidebar Dropdown Baru --- */
    .submenu { padding-left: 1rem; background-color: #1f2937; overflow: hidden; }
    .submenu-item { display: block; padding: 0.6rem 1rem; color: #9ca3af; text-decoration: none; font-size: 0.85rem; position: relative; padding-left: 2.5rem; border-radius: 8px; margin: 2px 0; }
    .submenu-item::before { content: '›'; position: absolute; left: 1.5rem; font-weight: bold; color: #6b7280; }
    .submenu-item:hover, .submenu-item.active { color: #ffffff; background-color: #374151; }
    .dropdown-toggle::after { display: block; margin-left: auto; transition: transform .2s ease-in-out; }

    /* [DITAMBAHKAN] Gaya untuk baris yang bisa diklik dan box detail tersembunyi */
    .clickable-row { cursor: pointer; }
    .detail-reveal-box { display: none; }
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
                <li><a href="<?= BASE_URL; ?>admin/produk" class="submenu-item <?= in_array($activePage, ['produk', 'produk_index', 'produk_create', 'produk_edit']) ? 'active' : '' ?>">Produk</a></li>
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
                <li><a href="<?= BASE_URL; ?>admin/pengguna" class="submenu-item <?= in_array($activePage, ['pengguna', 'pengguna_create', 'pengguna_edit']) ? 'active' : '' ?>">Manajemen Pengguna</a></li>
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
                        <h3 class="fw-bold m-0 text-dark" style="letter-spacing: 0.5px;">MANAJEMEN PENGGUNA</h3>
                        <p class="text-muted small mb-0">Kelola akses akun administrator dan data pelanggan ThriftKing888</p>
                    </div>
                    <div>
                        <ol class="breadcrumb custom-breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>admin/dashboard" class="text-decoration-none">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
                        </ol>
                    </div>
                </div>

                <div class="col-12">
                <?php if (!empty($error_message)) : ?>
                <div class="alert alert-danger mb-3 py-2 small"><i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'tambah_berhasil') : ?>
                <div class="alert alert-success mb-3 py-2 small"><i class="bi bi-check-circle-fill me-2"></i> Pengguna baru berhasil ditambahkan!</div>
            <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] === 'edit_berhasil') : ?>
                <div class="alert alert-success mb-3 py-2 small"><i class="bi bi-check-circle-fill me-2"></i> Data pengguna berhasil diperbarui!</div>
            <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] === 'hapus_berhasil') : ?>
                <div class="alert alert-success mb-3 py-2 small"><i class="bi bi-check-circle-fill me-2"></i> Pengguna berhasil dihapus!</div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class=""></i> Data Pengguna</h5>

                    <div class="d-flex gap-2">
                        <!-- <a href="<?= BASE_URL ?>admin/pengguna?export=csv&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV</a>
                        <a href="<?= BASE_URL ?>admin/pengguna/create" class="btn btn-sm btn-light fw-bold"><i class="bi bi-person-plus me-1"></i> Tambah Pengguna</a>-->
                    </div>
                    
                </div>
                <div class="card-body p-0">
                    <!-- Filter Form -->
                    <div class="p-3 bg-light border-bottom">
                        <form action="" method="GET" class="row g-2 align-items-center">
                            <div class="col-auto">
                                <label class="small fw-bold text-muted text-capitalize" style="font-size: 10px;">Cari:</label>
                            </div>
                            <div class="col-auto">
                                <input type="text" name="search" class="form-control form-control-sm rounded-0" placeholder="Nama atau email..." value="<?= htmlspecialchars($search_query) ?>">
                            </div>
                            <div class="col-auto">
                                <label class="small fw-bold text-muted text-capitalize" style="font-size: 10px;">Status:</label>
                            </div>
                            <div class="col-auto">
                                <select name="status" class="form-select form-select-sm rounded-0" onchange="this.form.submit()">
                                    <option value="">Semua Pengguna</option>
                                    <option value="online" <?= $status_filter === 'online' ? 'selected' : '' ?>>Sedang Online</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-dark rounded-0"><i class="bi bi-search"></i></button>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="text-center" style="width: 5%;">No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Email</th>
                                    <th>No. HP</th>
                                    <th>Alamat</th>
                                    <th class="text-center">Role</th>
                                    <th class="text-center">Login Terakhir</th>
                                    <th class="text-center">Dibuat Pada</th>
                                    <th class="text-center" style="width: 12%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users) && is_array($users)) : ?>
                                    <?php 
                                    $no = 1; 
                                    $last_role = null; // Inisialisasi pelacak role
                                    foreach ($users as $u) : 
                                        // Cek jika role berubah dari admin ke pelanggan
                                        if ($last_role === 'admin' && $u['role'] === 'pelanggan') {
                                            echo '<tr><td colspan="9" class="text-center small fw-bold text-muted bg-light py-1" style="letter-spacing: 1px;">--- Daftar Pelanggan ---</td></tr>';
                                        }
                                    ?>                                        <tr class="clickable-row" onclick="toggleDetail(<?= $u['id'] ?>)">
                                            <td class="text-center text-muted"><?= $no++ ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= !empty($u['no_hp']) ? htmlspecialchars($u['no_hp'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted small">-</span>' ?></td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($u['alamat'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= !empty($u['alamat']) ? htmlspecialchars($u['alamat'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted small">-</span>' ?>
                                                </div>
                                                <div id="detail-<?= $u['id'] ?>" class="detail-reveal-box mt-2 p-2 bg-light rounded shadow-sm">
                                                    <div class="fw-bold text-capitalize text-secondary mb-1" style="font-size: 9px;">Alamat Lengkap:</div>
                                                    <div class="text-dark small lh-sm"><?= !empty($u['alamat']) ? nl2br(htmlspecialchars($u['alamat'])) : 'Tidak ada alamat.' ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge rounded-pill px-3 py-1 <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                                    <?= ucfirst($u['role']) ?>
                                                </span>
                                            </td>
                                        <td class="text-center">
                                            <?php if (!empty($u['last_login'])) : 
                                                $last_login_ts = strtotime($u['last_login']);
                                                $is_online = (time() - $last_login_ts) < 300; // Aktif dalam 5 menit terakhir (300 detik)
                                            ?>
                                                <div class="small fw-bold text-dark">
                                                    <?= date('d/m/Y', $last_login_ts) ?>
                                                    <?php if ($is_online) : ?>
                                                        <span class="badge bg-success p-1 ms-1" style="font-size: 0.55rem; vertical-align: middle; letter-spacing: 0.5px;">ONLINE</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.7rem;"><?= date('H:i', $last_login_ts) ?> WIB</div>
                                            <?php else : ?>
                                                <span class="text-muted small italic">Belum Pernah</span>
                                            <?php endif; ?>
                                        </td>
                                            <td class="text-center text-muted small"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="<?= BASE_URL ?>admin/pengguna/edit/<?= intval($u['id']) ?>" class="btn btn-sm btn-primary" title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <?php if (intval($u['id']) !== intval($_SESSION['user']['id']) && $u['role'] !== 'admin') : ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="hapus_id" value="<?= intval($u['id']) ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna <?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?>?')" title="Hapus">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else : ?>
                                                        <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                            <i class="bi bi-lock-fill"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                        $last_role = $u['role']; // Update pelacak role di akhir loop
                                    endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">Belum ada data pengguna.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white py-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm justify-content-end mb-0">
                                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search_query) ?>&status=<?= $status_filter ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search_query) ?>&status=<?= $status_filter ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3">
                <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
    // Auto Refresh jika filter "Online" aktif (30 Detik)
    <?php if ($status_filter === 'online') : ?>
        setTimeout(function() {
            window.location.reload();
        }, 30000); 
    <?php endif; ?>
</script>

<script>
    // [DITAMBAHKAN] Fungsi untuk menampilkan/menyembunyikan detail saat baris diklik
    function toggleDetail(userId) {
        // Mencegah event trigger jika yang diklik adalah tombol/link di dalam baris
        if (event.target.closest('a, button, form')) return;
        const detailBox = document.getElementById('detail-' + userId);
        if (detailBox) {
            detailBox.style.display = detailBox.style.display === 'block' ? 'none' : 'block';
        }
    }
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>