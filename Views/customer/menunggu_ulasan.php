<?php
/**
 * File: Views/customer/menunggu_ulasan.php
 * Daftar produk yang belum diulas oleh pelanggan - ThriftKing888
 */

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('pelanggan');

require_once APP_ROOT . '/Models/transaksi.php';
require_once APP_ROOT . '/Config/koneksi.php';

$db = Database::getConnection();
$transaksiModel = new transaksi($db);
$user_id = $_SESSION['user']['id'];
$items = $transaksiModel->getPendingReviews($user_id);

$pageTitle = 'Menunggu Ulasan - ThriftKing888';
require_once APP_ROOT . '/Views/layouts/header.php';
require_once APP_ROOT . '/Views/layouts/navbar.php';
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .page-title { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    .card { border: 1px solid #e5e5e5; border-radius: 0px !important; box-shadow: none; transition: 0.3s ease; }
    .card:hover { border-color: #000; }
    .btn-review { border-radius: 0px !important; letter-spacing: 1px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
    .product-img { object-fit: cover; border-right: 1px solid #e5e5e5; }
</style>

<div class="container py-5" style="max-width: 800px;">
    <div class="d-flex align-items-center mb-4">
        <!-- <a href="<?= BASE_URL ?>pelanggan/profil#orders" class="text-dark me-3" title="Kembali ke Profil"><i class="bi bi-arrow-left fs-4"></i></a> -->
        <h4 class="fw-bold text-capitalize page-title mb-0" style="font-size: 18px;">Menunggu Ulasan</h4>
    </div>

    <?php if (!empty($items)): ?>
        <p class="text-muted small mb-4">Tunjukkan apresiasimu! Beri ulasan untuk produk vintage yang telah kamu terima.</p>
        
        <?php foreach ($items as $item): 
            $safe_nama = htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8');
            $safe_gambar = htmlspecialchars($item['nama_foto'] ?? 'no-image.png', ENT_QUOTES, 'UTF-8');
            $safe_invoice = htmlspecialchars($item['invoice_code'], ENT_QUOTES, 'UTF-8');
        ?>
            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="d-flex align-items-center">
                        <img src="<?= BASE_URL ?>assets/img/products/<?= $safe_gambar ?>" 
                             class="product-img" width="100" height="100" 
                             alt="<?= $safe_nama ?>"
                             onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';">
                        
                        <div class="p-3 flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="text-muted fw-mono" style="font-size: 10px;">#<?= $safe_invoice ?></span>
                                <span class="text-muted" style="font-size: 10px;"><?= date('d M Y', strtotime($item['order_date'])) ?></span>
                            </div>
                            <h6 class="fw-bold text-capitalize text-truncate mb-2" style="font-size: 13px; letter-spacing: 0.5px;"><?= $safe_nama ?></h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small italic" style="font-size: 11px;">Belum dinilai</span>
                                <a href="<?= BASE_URL ?>pelanggan/ulasan/<?= intval($item['product_id']) ?>" class="btn btn-dark btn-review px-4 py-2">Tulis Ulasan</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-patch-check text-muted opacity-50" style="font-size: 4rem;"></i>
            </div>
            <h5 class="fw-bold text-capitalize page-title" style="font-size: 14px;">Semua Produk Telah Diulas</h5>
            <p class="text-muted small">Terima kasih telah berbagi pengalaman belanja kamu di ThriftKing888.</p>
            <a href="<?= BASE_URL ?>pelanggan/pesanan" class="btn btn-outline-dark btn-review px-5 py-2 mt-3">Kembali ke Pesanan</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>