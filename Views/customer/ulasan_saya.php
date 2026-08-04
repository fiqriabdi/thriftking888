<?php
/**
 * File: Views/customer/ulasan_saya.php
 * Riwayat ulasan yang telah diberikan pelanggan - ThriftKing888
 */

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('pelanggan');

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Controllers/Admin/ulasancontroller.php';

$db = Database::getConnection();
$ulasanCtrl = new ulasancontroller($db);
$user_id = $_SESSION['user']['id'];
$reviews = $ulasanCtrl->getByUser($user_id);

$pageTitle = 'Ulasan Saya - ThriftKing888';
require_once APP_ROOT . '/Views/layouts/header.php';
require_once APP_ROOT . '/Views/layouts/navbar.php';
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .page-title { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    .card { border: 1px solid #e5e5e5; border-radius: 0px !important; box-shadow: none; transition: 0.3s ease; }
    .card:hover { border-color: #000; }
    .product-img { object-fit: cover; border-right: 1px solid #e5e5e5; }
    .text-vintage { color: #5D4037 !important; }
    .status-badge { font-size: 9px; letter-spacing: 0.5px; text-transform: capitalize; padding: 5px 10px; border-radius: 0; }
</style>

<div class="container py-5" style="max-width: 800px;">
    <div class="d-flex align-items-center mb-4">
       <!-- <a href="<?= BASE_URL ?>pelanggan/profil#my-reviews" class="text-dark me-3" title="Kembali ke Profil"><i class="bi bi-arrow-left fs-4"></i></a>-->
        <h4 class="fw-bold text-capitalize page-title mb-0" style="font-size: 18px;">Ulasan Saya</h4>
    </div>

    <?php if (!empty($reviews)): ?>
        <p class="text-muted small mb-4">Terima kasih telah berbagi pengalaman belanja Anda. Berikut adalah riwayat ulasan yang telah Anda berikan.</p>
        
        <?php foreach ($reviews as $rev): 
            $safe_nama = htmlspecialchars($rev['nama_produk'], ENT_QUOTES, 'UTF-8');
            $safe_gambar = htmlspecialchars($rev['gambar_utama'] ?? 'no-image.png', ENT_QUOTES, 'UTF-8');
            
            // Pemetaan class badge agar konsisten dengan pesanan.php
            $status_badge_class = 'bg-secondary';
            if ($rev['status'] === 'approved') $status_badge_class = 'bg-success';
            if ($rev['status'] === 'pending') $status_badge_class = 'bg-warning text-dark';
            if ($rev['status'] === 'rejected') $status_badge_class = 'bg-danger';
            
        ?>
            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="d-flex align-items-start">
                        <img src="<?= BASE_URL ?>assets/img/products/<?= $safe_gambar ?>" 
                             class="product-img" width="120" height="120" 
                             alt="<?= $safe_nama ?>"
                             onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';">
                        
                        <div class="p-3 flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="fw-bold text-capitalize text-truncate mb-0" style="font-size: 13px; letter-spacing: 0.5px;"><?= $safe_nama ?></h6>
                                <span class="badge status-badge <?= $status_badge_class ?>"><?= htmlspecialchars($rev['status']) ?></span>
                            </div>
                            <div class="text-vintage mb-2" style="font-size: 12px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star-fill <?= $i <= $rev['rating'] ? 'text-vintage' : 'text-muted opacity-40' ?> me-1"></i>
                                <?php endfor; ?>
                                <span class="text-muted ms-2 small" style="font-size: 10px;"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <div class="bg-light p-2 mb-0">
                                <div class="fw-bold small text-capitalize mb-1" style="font-size: 11px;"><?= htmlspecialchars($rev['judul'], ENT_QUOTES, 'UTF-8') ?></div>
                                <p class="text-muted small mb-0 lh-sm" style="font-size: 12px;"><?= nl2br(htmlspecialchars($rev['isi'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-chat-left-dots text-muted opacity-50" style="font-size: 4rem;"></i>
            </div>
            <h5 class="fw-bold text-capitalize page-title" style="font-size: 14px;">Belum Ada Ulasan</h5>
            <p class="text-muted small">Anda belum memberikan ulasan untuk produk manapun.</p>
            <a href="<?= BASE_URL ?>pelanggan/menunggu-ulasan" class="btn btn-dark px-5 py-2 mt-3 text-capitalize small fw-bold" style="border-radius:0;">Beri Ulasan</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>