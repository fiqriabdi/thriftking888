<?php
require_once APP_ROOT . '/Middleware/auth.php';
require_once APP_ROOT . '/helpers/Format.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';

// Ambil tanggal filter (default ke bulan ini jika kosong)
$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$filter_error = '';

function validateDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (!validateDate($tgl_mulai)) {
    $tgl_mulai = date('Y-m-01');
}
if (!validateDate($tgl_selesai)) {
    $tgl_selesai = date('Y-m-d');
}

if (strtotime($tgl_selesai) < strtotime($tgl_mulai)) {
    $filter_error = 'Periode akhir tidak boleh sebelum periode awal. Tanggal selesai disesuaikan dengan tanggal mulai.';
    $tgl_selesai = $tgl_mulai;
}

$db = Database::getConnection();
$sql = "SELECT
            o.*,
            u.nama AS pembeli,
            MIN(oi.nama_produk_snapshot) AS produk_pertama,
            MIN(pi.nama_foto) AS gambar_produk,
            COUNT(oi.id) AS total_item
        FROM
            orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN product_variants pv ON oi.product_variant_id = pv.id
        LEFT JOIN product_images pi ON pv.product_id = pi.product_id AND pi.sort_order = 0
        WHERE
            o.status_order = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY o.id
        ORDER BY o.created_at DESC";
$stmt = mysqli_prepare($db, $sql);

$result = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $tgl_mulai, $tgl_selesai);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$pageTitle = 'Laporan Penjualan';
$activePage = 'laporan';

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
    @media print {
        .no-print { display: none !important; }
        body { background-color: #fff !important; }
        .main-content-area { padding: 0; }
        .card { box-shadow: none; border: 1px solid #ddd; }
    }
</style>

<div class="dashboard-wrapper">
    <div class="admin-sidebar no-print">
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
                    <!-- <?php if ($pending_confirmation_count > 0): ?>
                        <span class="badge bg-warning rounded-pill"><?= $pending_confirmation_count ?></span>
                    <?php endif; ?> -->
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
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.5px;">Laporan Penjualan</h3>
                    <p class="text-muted small mb-0">Rekapitulasi pendapatan dari pesanan yang telah selesai.</p>
                </div>
            </div>

            <div class="card mb-4 no-print">
                <div class="card-header bg-white border-bottom-0 p-3">
                    <h6 class="fw-bold text-dark mb-0"><i class=""> </i></h6></div>
                <div class="card-body pt-0">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-4"><label class="form-label small fw-bold">Tanggal Mulai</label><input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai) ?>"></div>
                        <div class="col-md-4"><label class="form-label small fw-bold">Tanggal Selesai</label><input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($tgl_selesai) ?>"></div>
                        <div class="col-md-4 d-flex gap-2"><button type="submit" class="btn btn-dark w-100"><i class=""></i>Terapkan</button><a href="<?= BASE_URL ?>admin/laporan?tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary w-100">Reset</a></div>
                    </form>
                    <?php if (!empty($filter_error)) : ?><div class="alert alert-warning py-2 small mt-3 mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $filter_error ?></div><?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Penjualan (<?= date('d M Y', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?>)</h6>
                    <?php if ($result && mysqli_num_rows($result) > 0) : ?><button onclick="window.print()" class="btn btn-sm btn-outline-dark d-print-none"><i class="bi bi-printer me-2"></i>Cetak</button><?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-capitalize small">
                                <tr>
                                    <th class="ps-4 py-3">No</th>
                                    <th class="ps-4 py-3">Pesanan</th>
                                    <th class="py-3">Pelanggan</th>
                                    <th class="text-end pe-4 py-3">Total Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                $total_pendapatan = 0;
                                if ($result && mysqli_num_rows($result) > 0) : 
                                    while ($row = mysqli_fetch_assoc($result)) : 
                                        $total_pendapatan += $row['total_pembayaran'];
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php 
                                                $img_path = !empty($row['gambar_produk']) 
                                                    ? BASE_URL . 'assets/img/products/' . htmlspecialchars($row['gambar_produk']) 
                                                    : BASE_URL . 'assets/img/no-image.png';
                                            ?>
                                            <img src="<?= $img_path ?>" class="rounded-3 border" width="45" height="45" style="object-fit: cover;" onerror="this.src='<?= BASE_URL ?>assets/img/no-image.png'">
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($row['produk_pertama'] ?? 'Produk Dihapus') ?></div>
                                                <div class="text-muted small">#<?= htmlspecialchars($row['invoice_code']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($row['pembeli']) ?></div>
                                        <div class="text-muted small"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-success"><?= formatRupiah($row['total_pembayaran']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else : ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                                        Tidak ada data penjualan pada periode ini.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if ($total_pendapatan > 0): ?>
                            <tfoot class="bg-dark text-white fw-bold">
                                <tr>
                                    <td colspan="3" class="text-end pe-4 py-3">TOTAL PENDAPATAN</td>
                                    <td class="text-end pe-4 py-3 fs-6"><?= formatRupiah($total_pendapatan) ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3 no-print">
                <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small ">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>