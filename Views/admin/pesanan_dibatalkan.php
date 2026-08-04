<?php
/**
 * File: admin/pesanan_dibatalkan.php
 * Halaman admin untuk melihat riwayat pesanan yang dibatalkan - ThriftKing888
 */

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Middleware/auth.php';
require_once APP_ROOT . '/Controllers/Admin/PesananController.php';
require_once APP_ROOT . '/helpers/Format.php';

auth::requireRole('admin');

$pesanan_controller = new PesananController($conn);

// Ambil data pesanan yang dibatalkan
$current_page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = 15;
$paginated_data = $pesanan_controller->getCancelledOrders($current_page, $items_per_page);
$cancelled_orders = $paginated_data['orders'];
$total_pages = $paginated_data['total_pages'];

$pageTitle = 'Pesanan Dibatalkan';
$activePage = 'pesanan_dibatalkan'; // Untuk menandai menu aktif

// --- LOGIKA UNTUK SIDEBAR AKTIF ---
$katalog_pages = ['produk', 'produk_index', 'produk_create', 'produk_edit', 'kategori', 'stock_logs'];
$penjualan_pages = ['pesanan', 'pesanan_dibatalkan', 'ulasan', 'laporan', 'konfirmasi-pembayaran'];
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
    .table-responsive-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 20px; border: 1px solid #e5e7eb; }
    .modern-table th { background: #ffffff; color: #4b5563; font-size: 0.75rem; text-transform: uppercase; padding: 12px 16px; border-bottom: 2px solid #f3f4f6; }
    .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
    .badge-cancelled { background-color: #f3f4f6; color: #6b7280; }
    /* [DITAMBAHKAN] Gaya untuk baris yang bisa diklik dan box alamat tersembunyi */
    .clickable-row { cursor: pointer; }
    .address-reveal-box { display: none; }


    /* --- Gaya Sidebar Dropdown Baru --- */
    .sidebar-menu { display: flex; flex-direction: column; gap: 4px; flex-grow: 1; }
    .menu-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: #9ca3af; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 8px; transition: all 0.2s ease; }
    .menu-item:hover { background-color: #1f2937; color: #ffffff; }
    .menu-item.active { background-color: #d97706; color: #ffffff !important; font-weight: 600; }
    .submenu { padding-left: 1rem; background-color: #1f2937; overflow: hidden; }
    .submenu-item { display: block; padding: 0.6rem 1rem; color: #9ca3af; text-decoration: none; font-size: 0.85rem; position: relative; padding-left: 2.5rem; border-radius: 8px; margin: 2px 0; }
    .submenu-item::before { content: '›'; position: absolute; left: 1.5rem; font-weight: bold; color: #6b7280; }
    .submenu-item:hover, .submenu-item.active { color: #ffffff; background-color: #374151; }
    .menu-item.dropdown-toggle::after { display: block; margin-left: auto; transition: transform .2s ease-in-out; }
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
                <li><a href="<?= BASE_URL; ?>admin/pesanan/dibatalkan" class="submenu-item <?= $activePage === 'pesanan_dibatalkan' ? 'active' : '' ?>">Pesanan Dibatalkan</a></li>
                <li><a href="<?= BASE_URL; ?>admin/konfirmasi-pembayaran" class="submenu-item <?= $activePage === 'konfirmasi-pembayaran' ? 'active' : '' ?>">Data Pembayaran</a></li>
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

    <!-- Main Content -->
    <div class="main-content-area">
        <div class="container-fluid p-0">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold m-0">PESANAN DIBATALKAN</h3>
                    <p class="text-muted small mb-0">Riwayat pesanan yang dibatalkan oleh pelanggan.</p>
                </div>
            </div>

            <div class="table-responsive-card">
                <div class="table-responsive">
                    <table class="table modern-table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 320px;">PRODUK</th>
                                <th style="min-width: 180px;">PELANGGAN</th>
                                <th style="min-width: 160px;">TOTAL PEMBAYARAN</th>
                                <th style="min-width: 140px;" class="text-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($cancelled_orders)): ?>
                                <?php foreach ($cancelled_orders as $p): ?>
                                <tr class="clickable-row" onclick="toggleShippingAddress(event, <?= $p['id'] ?>)">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php 
                                                $img_path = !empty($p['gambar_produk']) 
                                                    ? BASE_URL . 'assets/img/products/' . htmlspecialchars($p['gambar_produk']) 
                                                    : BASE_URL . 'assets/img/no-image.png';
                                            ?>
                                            <img src="<?= $img_path ?>" class="rounded-3 border flex-shrink-0" width="45" height="45" style="object-fit: cover;" onerror="this.src='<?= BASE_URL ?>assets/img/no-image.png'">
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-bold text-dark" style="font-size: 13px; line-height: 1.4;">
                                                    <?= htmlspecialchars($p['produk_pertama'] ?? 'Item Produk') ?>
                                                    <?= ($p['total_item'] ?? 1) > 1 ? '<span class="text-muted d-inline-block" style="font-size: 11px;"> +'.($p['total_item']-1).' lainnya</span>' : '' ?>
                                                </div>
                                                <div class="text-muted small" style="font-size: 11px;">#<?= htmlspecialchars($p['invoice_code']) ?> • <?= date('d/m/y H:i', strtotime($p['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark text-wrap"><?= htmlspecialchars($p['nama_penerima']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($p['no_hp_penerima']) ?></div>
                                        <div id="address-<?= $p['id'] ?>" class="address-reveal-box mt-2 p-2 bg-light rounded shadow-sm">
                                            <div class="fw-bold text-uppercase text-secondary mb-1" style="font-size: 9px;">Alamat Pengiriman:</div>
                                            <div class="text-dark small lh-sm"><?= nl2br(htmlspecialchars($p['alamat_pengiriman'])) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-danger"><?= formatRupiah($p['total_pembayaran']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="status-badge badge-cancelled">Dibatalkan</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">Tidak ada data pesanan yang dibatalkan.</td>
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
            
            <div class="mt-4">
                <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
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