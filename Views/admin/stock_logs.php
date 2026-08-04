<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
require_once APP_ROOT . '/helpers/Format.php';

$db = Database::getConnection();
$controller = new produkcontroller($db);

$filters = [
    'start_date' => $_GET['start_date'] ?? null,
    'end_date'   => $_GET['end_date'] ?? null,
    'type'       => $_GET['type'] ?? null
];
$logs = $controller->getStockHistory($filters);

$pageTitle = 'Histori Stok - Admin ThriftKing888';
$activePage = 'stock_logs';

// AMBIL JUMLAH UNTUK BADGE NOTIFIKASI
$q_pending_count = mysqli_query($db, "SELECT COUNT(*) as total FROM orders WHERE status_order = 'pending_confirmation'");
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

require_once APP_ROOT . '/Views/layouts/header.php';
?>

<style>
    body { background-color: #f3f4f6 !important; font-family: 'Inter', sans-serif; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    .admin-sidebar { width: 260px; background-color: #111827; color: #9ca3af; padding: 24px 16px; display: flex; flex-direction: column; flex-shrink: 0; }
    .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 0 12px 24px 12px; border-bottom: 1px solid #1f2937; margin-bottom: 12px; }
    .sidebar-brand h5 { color: #ffffff; font-weight: 700; margin: 0; letter-spacing: 1px; font-size: 1.15rem; }
    .sidebar-brand i { color: #fbbf24; }
    .main-content-area { flex-grow: 1; padding: 30px; }
    .card { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .sidebar-menu { display: flex; flex-direction: column; gap: 4px; flex-grow: 1; }
    .menu-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: #9ca3af; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 8px; transition: all 0.2s ease; }
    .menu-item:hover { background-color: #1f2937; color: #ffffff; }
    .menu-item.active { background-color: #d97706; color: #ffffff !important; font-weight: 600; }
    .submenu { padding-left: 1rem; background-color: #1f2937; overflow: hidden; }
    .submenu-item { display: block; padding: 0.6rem 1rem; color: #9ca3af; text-decoration: none; font-size: 0.85rem; position: relative; padding-left: 2.5rem; border-radius: 8px; margin: 2px 0; }
    .submenu-item::before { content: '›'; position: absolute; left: 1.5rem; font-weight: bold; color: #6b7280; }
    .submenu-item:hover, .submenu-item.active { color: #ffffff; background-color: #374151; }
    .dropdown-toggle::after { display: block; margin-left: auto; transition: transform .2s ease-in-out; }
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
                <li><a href="<?= BASE_URL; ?>admin/produk/stock-logs" class="submenu-item <?= $activePage === 'stock_logs' ? 'active' : '' ?>">Histori Stok</a></li>
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
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.5px;">Histori Stok</h3>
                    <p class="text-muted small mb-0">Melacak setiap perubahan inventaris masuk dan keluar.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white border-bottom-0 p-3"><h6 class="fw-bold text-dark mb-0"><i class="bi bi-funnel-fill me-2"></i>Filter Laporan</h6></div>
                <div class="card-body pt-0">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3"><label class="form-label small fw-bold">Tanggal Mulai</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small fw-bold">Tanggal Selesai</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small fw-bold">Tipe Log</label><select name="type" class="form-select"><option value="">Semua Tipe</option><option value="in" <?= ($filters['type'] ?? '') === 'in' ? 'selected' : '' ?>>In (Masuk)</option><option value="sale" <?= ($filters['type'] ?? '') === 'sale' ? 'selected' : '' ?>>Sale (Penjualan)</option><option value="adjustment" <?= ($filters['type'] ?? '') === 'adjustment' ? 'selected' : '' ?>>Adjustment</option></select></div>
                        <div class="col-md-3 d-flex gap-2"><button type="submit" class="btn btn-dark w-100"><i class="bi bi-filter me-1"></i> Terapkan</button><a href="<?= BASE_URL ?>admin/produk/stock-logs" class="btn btn-outline-secondary w-100">Reset</a></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="bg-light text-uppercase small">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 150px;">Waktu</th>
                                    <th>Produk & SKU</th>
                                    <th class="text-center">Tipe</th>
                                    <th class="text-center">Jumlah</th>
                                    <th>Admin / PIC</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): 
                                        $dotColor = '#6c757d'; $textSign = '';
                                        switch ($log['type']) {
                                            case 'in':
                                                $dotColor = '#198754'; $textSign = '+'; break;
                                            case 'sale':
                                                $dotColor = '#dc3545'; $textSign = '-'; break;
                                            case 'adjustment':
                                                $dotColor = '#0d6efd'; $textSign = '-'; break; // Penyesuaian manual (pengurangan)
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?= date('d/m/Y', strtotime($log['created_at'])) ?><br><small><?= date('H:i', strtotime($log['created_at'])) ?> WIB</small></td>
                                        <td>
                                            <div class="fw-bold text-uppercase"><?= htmlspecialchars($log['nama_produk']) ?></div>
                                            <div class="text-muted small" style="font-size: 10px;">SKU: <?= htmlspecialchars($log['sku']) ?> | <?= htmlspecialchars($log['varian_ukuran']) ?> / <?= htmlspecialchars($log['varian_warna']) ?></div>
                                        </td>
                                        <td class="text-center"><span class="badge rounded-pill text-uppercase" style="font-size: 9px; padding: 5px 10px; background-color: <?= $dotColor ?>1A; color: <?= $dotColor ?>; border: 1px solid <?= $dotColor ?>40;"><?= $log['type'] ?></span></td>
                                        <td class="text-center fw-bold <?= $textSign === '-' ? 'text-danger' : ($textSign === '+' ? 'text-success' : '') ?>"><?= $textSign . $log['jumlah'] ?></td>
                                        <td><?php if ($log['type'] === 'sale'): ?><span class="text-muted fst-italic small">System (Checkout)</span><?php else: ?><?= htmlspecialchars($log['admin_nama'] ?? 'System') ?><?php endif; ?></td>
                                        <td><span class="text-muted small"><?= htmlspecialchars($log['keterangan']) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>Belum ada histori perubahan stok tercatat.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>