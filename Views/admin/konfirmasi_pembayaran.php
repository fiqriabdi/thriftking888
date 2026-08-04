<?php
/**
 * File: admin/konfirmasi_pembayaran.php
 * Halaman admin untuk memverifikasi pembayaran yang masuk.
 */

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Middleware/auth.php';
require_once APP_ROOT . '/Controllers/Admin/PesananController.php';
require_once APP_ROOT . '/helpers/Format.php';
require_once APP_ROOT . '/helpers/Security.php'; // [DITAMBAHKAN] Untuk CSRF

auth::requireRole('admin');

$pesanan_controller = new pesanancontroller($conn);

// [DITAMBAHKAN] Generate CSRF token
$csrf_token = generateCSRFToken();

// 1. PROSES AKSI UPDATE STATUS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // [DITAMBAHKAN] Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Permintaan tidak valid (CSRF token).';
    } else {
        $id = intval($_POST['id']);
        $status = $_POST['status']; // 'processing' (approve) atau 'cancelled' (reject)
        
        $status_map_notification = ['processing' => 'Diproses', 'cancelled'  => 'Dibatalkan'];
        $status_label_notification = $status_map_notification[$status] ?? strtoupper($status);
        
        $update_result = $pesanan_controller->updateStatus($id, $status);
        if ($update_result === true) { // [PERBAIKAN] Cek secara eksplisit untuk boolean true
            $_SESSION['success'] = "Pesanan #$id berhasil diperbarui ke status: " . $status_label_notification;
        } else {
            $error_reason = is_string($update_result) ? $update_result : "Terjadi kesalahan tidak diketahui.";
            $_SESSION['error'] = "Gagal memperbarui status pesanan #$id. Penyebab: " . $error_reason;
        }
    }
    header("Location: " . BASE_URL . "admin/konfirmasi-pembayaran");
    exit;
}

// 2. LOGIKA PAGINATION
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = 15; // Jumlah item per halaman
$offset = ($current_page - 1) * $items_per_page;

// 3. HITUNG TOTAL DATA UNTUK PAGINATION
$count_query = "SELECT COUNT(o.id) as total 
                FROM orders o 
                JOIN payments p ON o.id = p.order_id 
                WHERE p.bukti_transfer IS NOT NULL";
$count_result = mysqli_query($conn, $count_query);
$total_items = mysqli_fetch_assoc($count_result)['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);


// 4. AMBIL DATA RIWAYAT KONFIRMASI DENGAN PAGINATION
$query = "SELECT
            o.*,
            u.nama as nama_pelanggan,
            p.bukti_transfer,
            p.metode_pembayaran,
            ba.nomor_rekening,
            (SELECT oi.nama_produk_snapshot FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) as nama_produk,
            (SELECT pi.nama_foto FROM product_images pi JOIN product_variants pv ON pi.product_id = pv.product_id JOIN order_items oi ON pv.id = oi.product_variant_id WHERE oi.order_id = o.id AND pi.sort_order = 0 ORDER BY oi.id ASC LIMIT 1) as gambar_produk
          FROM orders o
          JOIN users u ON o.user_id = u.id
          JOIN payments p ON o.id = p.order_id
          LEFT JOIN bank_accounts ba ON p.metode_pembayaran = ba.nama_bank
          WHERE p.bukti_transfer IS NOT NULL
          ORDER BY FIELD(o.status_order, 'pending_confirmation') DESC, o.updated_at DESC
          LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $items_per_page, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pending_confirmations = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($stmt);

// AMBIL JUMLAH UNTUK BADGE
$q_pending_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status_order = 'pending_confirmation'");
$res_pending_count = mysqli_fetch_assoc($q_pending_count);
$pending_confirmation_count = $res_pending_count['total'] ?? 0;


$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$pageTitle = 'Konfirmasi Pembayaran';
$activePage = 'konfirmasi-pembayaran';

// --- LOGIKA UNTUK SIDEBAR AKTIF ---
$katalog_pages = ['produk', 'kategori', 'stock_logs', 'produk_create', 'produk_edit'];
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
    /* Menggunakan gaya yang konsisten dengan halaman admin lainnya */
    body { background-color: #f3f4f6 !important; font-family: 'Inter', sans-serif; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    .main-content-area { flex-grow: 1; padding: 30px; }
    .admin-sidebar { width: 260px; background-color: #111827; color: #9ca3af; padding: 24px 16px; display: flex; flex-direction: column; flex-shrink: 0; }
    .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 0 12px 24px 12px; border-bottom: 1px solid #1f2937; margin-bottom: 12px; }
    .sidebar-brand h5 { color: #ffffff; font-weight: 700; margin: 0; letter-spacing: 1px; font-size: 1.15rem; }
    .sidebar-brand i { color: #fbbf24; }
    .table-responsive-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 20px; border: 1px solid #e5e7eb; }
    .modern-table th { background: #ffffff; color: #4b5563; font-size: 0.75rem; text-transform: uppercase; padding: 12px 16px; border-bottom: 2px solid #f3f4f6; }
    
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

<style>
    /* [DITAMBAHKAN] Gaya untuk baris yang bisa diklik dan box alamat tersembunyi */
    .clickable-row { cursor: pointer; }
    .address-reveal-box { display: none; }
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

            <a href="#katalogSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isKatalogOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isKatalogOpen ? 'active' : '' ?>"><i class="bi bi-box-seam-fill"></i> <span>Data Produk</span></a>
            <ul class="collapse list-unstyled submenu <?= $isKatalogOpen ? 'show' : '' ?>" id="katalogSubmenu">
                <li><a href="<?= BASE_URL; ?>admin/produk" class="submenu-item <?= in_array($activePage, ['produk', 'produk_index', 'produk_create', 'produk_edit']) ? 'active' : '' ?>"> Produk</a></li>
                <li><a href="<?= BASE_URL; ?>admin/kategori" class="submenu-item <?= $activePage === 'kategori' ? 'active' : '' ?>">Kategori</a></li>
                <li><a href="<?= BASE_URL; ?>admin/produk/stock-logs" class="submenu-item <?= $activePage === 'stock_logs' ? 'active' : '' ?>">Laporan Stok</a></li>
            </ul>

            <a href="#penjualanSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isPenjualanOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isPenjualanOpen ? 'active' : '' ?>"><i class="bi bi-cart-fill"></i> <span>Data Pesanan</span></a>
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

            <a href="#sistemSubmenu" data-bs-toggle="collapse" aria-expanded="<?= $isSistemOpen ? 'true' : 'false' ?>" class="menu-item dropdown-toggle <?= $isSistemOpen ? 'active' : '' ?>"><i class="bi bi-gear-fill"></i> <span>Sistem</span></a>
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
    <!-- Main Content -->
    <div class="main-content-area">
        <div class="container-fluid p-0">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold m-0">KONFIRMASI PEMBAYARAN</h3>
                    <p class="text-muted small mb-0">Verifikasi bukti transfer yang diunggah oleh pelanggan.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="table-responsive-card">
                <div class="table-responsive">
                    <table class="table modern-table align-middle">
                        <thead>
                            <tr>
                                <th>Produk </th>
                                <th>Pelanggan</th>
                                <th>Total Pembayaran</th>
                                <th>Metode Pembayaran</th>
                                <th class="text-center">Bukti Transfer</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pending_confirmations)): ?>
                                <?php foreach ($pending_confirmations as $p): ?>
                                <?php
                                    // Pemetaan Status ke Bahasa Indonesia
                                    $status_map = [
                                        'unpaid'               => 'Belum Bayar',
                                        'pending_confirmation' => 'Menunggu Konfirmasi',
                                        'processing'           => 'Diproses',
                                        'shipped'              => 'Dikirim',
                                        'completed'            => 'Selesai',
                                        'cancelled'            => 'Dibatalkan'
                                    ];
                                    $status_label = $status_map[$p['status_order']] ?? str_replace('_', ' ', $p['status_order']);
                                ?>
                                <tr class="clickable-row" onclick="toggleShippingAddress(event, <?= $p['id'] ?>)">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php 
                                                $img_path = !empty($p['gambar_produk']) 
                                                    ? BASE_URL . 'assets/img/products/' . htmlspecialchars($p['gambar_produk']) 
                                                    : BASE_URL . 'assets/img/no-image.png';
                                            ?>
                                            <img src="<?= $img_path ?>" class="rounded-3 border" width="45" height="45" style="object-fit: cover;" onerror="this.src='<?= BASE_URL ?>assets/img/no-image.png'">
                                            <div>
                                                <div class="fw-bold text-dark text-truncate" style="max-width: 200px;"><?= htmlspecialchars($p['nama_produk'] ?? 'Produk Dihapus') ?></div>
                                                <div class="text-muted small">#<?= htmlspecialchars($p['invoice_code']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark text-wrap"><?= htmlspecialchars($p['nama_penerima']) ?></div>
                                        
                                        <?php if ($p['nama_pelanggan'] !== $p['nama_penerima']): ?>
                                            <div class="badge bg-light text-dark border fw-normal mb-1" style="font-size: 9px;">
                                                <i class="bi bi-gift-fill text-danger me-1"></i>Dropship / Hadiah
                                            </div>
                                            <div class="text-muted small mb-1" style="font-size: 10px;">Pembeli: <?= htmlspecialchars($p['nama_pelanggan']) ?></div>
                                        <?php endif; ?>

                                        <div class="text-muted small"><?= htmlspecialchars($p['no_hp_penerima']) ?></div>
                                        <div id="address-<?= $p['id'] ?>" class="address-reveal-box mt-2 p-2 bg-light rounded shadow-sm">
                                            <div class="fw-bold text-uppercase text-warning mb-1" style="font-size: 9px;">Alamat Pengiriman:</div>
                                            <div class="text-dark small lh-sm"><?= nl2br(htmlspecialchars($p['alamat_pengiriman'])) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-danger"><?= formatRupiah($p['total_pembayaran']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size: 13px;">
                                            <?= strtoupper(htmlspecialchars($p['metode_pembayaran'] ?? 'N/A')) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($p['nomor_rekening'] ?? 'N/A') ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($p['bukti_transfer']): ?>
                                            <a href="<?= BASE_URL ?>public/storage/bukti_bayar/<?= htmlspecialchars($p['bukti_transfer']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye-fill"></i> Lihat Bukti
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($p['status_order'] === 'pending_confirmation'): ?>
                                            <div class="btn-group">
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menyetujui pembayaran ini? Stok akan dikurangi.')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="status" value="processing">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Setujui Pembayaran">
                                                        <i class="bi bi-check-lg"></i> Setujui
                                                    </button>
                                                </form>
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menolak pembayaran ini? Status pesanan akan menjadi Dibatalkan.')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Tolak Pembayaran">
                                                        <i class="bi bi-x-lg"></i> Tolak
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border fw-bold text-uppercase"><?= $status_label ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-check2-circle fs-2 d-block mb-2"></i>
                                        Tidak ada pembayaran yang perlu dikonfirmasi saat ini.
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
    </div>
</div>

<script>
    // [DITAMBAHKAN] Fungsi untuk menampilkan/menyembunyikan alamat saat baris diklik
    function toggleShippingAddress(event, orderId) {
        // Mencegah event trigger jika yang diklik adalah tombol/link di dalam baris
        if (event.target.closest('a, button')) return;
        const addressBox = document.getElementById('address-' + orderId);
        if (addressBox) {
            addressBox.style.display = addressBox.style.display === 'block' ? 'none' : 'block';
        }
    }
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>