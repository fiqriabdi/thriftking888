<?php
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';

$db = Database::getConnection();
$msg = '';
$msg_type = '';

// 1. LOGIKA HAPUS (DELETE)
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    $stmt = mysqli_prepare($db, "DELETE FROM ongkir WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_hapus);
    if (mysqli_stmt_execute($stmt)) {
        header('Location: ' . BASE_URL . 'admin/ongkir?status=deleted');
        exit();
    }
}

// 2. LOGIKA TAMBAH/UPDATE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kota = mysqli_real_escape_string($db, $_POST['kota']);
    $wilayah = mysqli_real_escape_string($db, $_POST['wilayah']);
    $biaya = intval($_POST['biaya']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (isset($_POST['id_ongkir']) && !empty($_POST['id_ongkir'])) {
        // Mode Update
        $id_ongkir = intval($_POST['id_ongkir']);
        $stmt = mysqli_prepare($db, "UPDATE ongkir SET kota=?, wilayah=?, biaya=?, is_active=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssiii', $kota, $wilayah, $biaya, $is_active, $id_ongkir);
        $action = "diperbarui";
    } else {
        // Mode Tambah
        $stmt = mysqli_prepare($db, "INSERT INTO ongkir (kota, wilayah, biaya, is_active) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssii', $kota, $wilayah, $biaya, $is_active);
        $action = "ditambahkan";
    }

    try {
        if (mysqli_stmt_execute($stmt)) {
            header('Location: ' . BASE_URL . 'admin/ongkir?status=success&msg=' . urlencode("Data ongkir berhasil $action!"));
            exit();
        } else {
            throw new Exception(mysqli_stmt_error($stmt));
        }
    } catch (Exception $e) {
        $msg = "Terjadi kesalahan: " . (strpos($e->getMessage(), 'Duplicate entry') !== false ? 'Kota sudah ada.' : $e->getMessage());
        $msg_type = "danger";
    }
}

// 3. AMBIL DATA UNTUK EDIT
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $stmt_edit = mysqli_prepare($db, "SELECT * FROM ongkir WHERE id = ?");
    mysqli_stmt_bind_param($stmt_edit, 'i', $id_edit);
    mysqli_stmt_execute($stmt_edit);
    $res_edit = mysqli_stmt_get_result($stmt_edit);
    $edit_data = mysqli_fetch_assoc($res_edit);
    mysqli_stmt_close($stmt_edit);
}

// 4. AMBIL SEMUA DATA UNTUK TABEL
$list_ongkir = mysqli_query($db, "SELECT * FROM ongkir ORDER BY wilayah, kota ASC");

if (isset($_GET['msg'])) $msg = $_GET['msg'];

$pageTitle = 'Manajemen Ongkos Kirim';
$activePage = 'ongkir';

$q_pending_count = mysqli_query($db, "SELECT COUNT(*) as total FROM orders WHERE status_order = 'pending_confirmation'");
$res_pending_count = mysqli_fetch_assoc($q_pending_count);
$pending_confirmation_count = $res_pending_count['total'] ?? 0;

$katalog_pages = ['produk', 'produk_index', 'produk_create', 'produk_edit', 'kategori', 'stock_logs'];
$penjualan_pages = ['pesanan', 'ulasan', 'laporan', 'konfirmasi-pembayaran'];
$data_master_pages = ['bank-rekening', 'ongkir'];
$sistem_pages = ['pengguna', 'settings', 'activity_log', 'reset-password'];

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
    .form-control, .form-select { border-radius: 8px; padding: 10px 15px; }
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
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.5px;">Manajemen Ongkos Kirim</h3>
                    <p class="text-muted small mb-0">Atur wilayah dan biaya pengiriman yang tersedia untuk pelanggan.</p>
                </div>
            </div>

            <?php if ($msg || (isset($_GET['status']) && $_GET['status'] === 'deleted')): ?>
                <div class="alert alert-<?= $msg_type ?: 'success' ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= $msg ?: ($_GET['status'] === 'deleted' ? 'Data ongkir berhasil dihapus.' : 'Perubahan disimpan.') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-white border-bottom-0 p-3"><h6 class="fw-bold text-dark mb-0"><?= $edit_data ? 'Edit Ongkir' : 'Tambah Ongkir Baru' ?></h6></div>
                        <div class="card-body pt-0">
                            <form action="<?= BASE_URL ?>admin/ongkir" method="POST">
                                <input type="hidden" name="id_ongkir" value="<?= $edit_data['id'] ?? '' ?>">
                                <div class="mb-3"><label class="form-label small fw-bold text-capitalize">Nama Kota</label><input type="text" name="kota" class="form-control" placeholder="Contoh: Jakarta" value="<?= $edit_data['kota'] ?? '' ?>" required></div>
                                <div class="mb-3"><label class="form-label small fw-bold text-capitalize">Wilayah</label><input type="text" name="wilayah" class="form-control" placeholder="Contoh: Jabodetabek" value="<?= $edit_data['wilayah'] ?? '' ?>"></div>
                                <div class="mb-3"><label class="form-label small fw-bold text-capitalize">Biaya (Rp)</label><input type="number" name="biaya" class="form-control" placeholder="Contoh: 15000" value="<?= $edit_data['biaya'] ?? '' ?>" required></div>
                                <div class="mb-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (!isset($edit_data) || $edit_data['is_active'] == 1) ? 'checked' : '' ?>><label class="form-check-label small fw-bold" for="is_active">Aktifkan Opsi Ongkir</label></div></div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-dark py-2 fw-bold text-capitalize" style="letter-spacing: 1px;"><?= $edit_data ? 'Simpan Perubahan' : 'Tambah Ongkir' ?></button>
                                    <?php if ($edit_data): ?><a href="<?= BASE_URL ?>admin/ongkir" class="btn btn-light border py-2 small fw-bold text-capitalize text-muted">Batal Edit</a><?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="small text-capitalize text-muted" style="letter-spacing: 0.5px;">
                                            <th class="ps-4 py-3">Kota </th>
                                            <th class="py-3">Wilayah</th>
                                            <th class="py-3">Biaya</th>
                                            <th class="text-center py-3">Status</th>
                                            <th class="text-center pe-4 py-3">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($list_ongkir) > 0) : ?>
                                            <?php while ($row = mysqli_fetch_assoc($list_ongkir)) : ?>
                                                <tr>
                                                    <td class="ps-4"><div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($row['kota']) ?></div></td>
                                                    <td class="small text-muted"><?= htmlspecialchars($row['wilayah']) ?></td>
                                                    <td><code class="bg-light p-1 rounded text-danger small fw-bold"><?= formatRupiah($row['biaya']) ?></code></td>
                                                    <td class="text-center">
                                                        <?php if ($row['is_active'] == 1) : ?>
                                                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-1 rounded-pill" style="font-size: 0.7rem;">AKTIF</span>
                                                        <?php else : ?>
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-3 py-1 rounded-pill" style="font-size: 0.7rem;">NON-AKTIF</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center pe-4">
                                                        <div class="btn-group">
                                                            <a href="<?= BASE_URL ?>admin/ongkir?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary border-0" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                                            <a href="<?= BASE_URL ?>admin/ongkir?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Hapus data ongkir ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else : ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-truck fs-2 d-block mb-2 opacity-25"></i>Belum ada data ongkos kirim.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>