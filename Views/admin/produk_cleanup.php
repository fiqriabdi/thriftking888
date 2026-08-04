<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
// Keamanan: Hanya admin yang bisa menjalankan cleanup
auth::requireRole('admin');

require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
$controller = new produkcontroller(Database::getConnection());

$deletedCount = $controller->cleanupUnusedImages();

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    echo json_encode(['success' => true, 'deleted' => $deletedCount]);
    exit;
}

$pageTitle = 'Image Cleanup - ThriftKing888';
require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container py-5">
    <div class="card border-0 shadow-sm text-center p-5">
        <div class="card-body">
            <i class="bi bi-stars display-1 text-warning"></i>
            <h2 class="fw-bold mt-4">Pembersihan Selesai!</h2>
            <p class="text-muted">Sistem berhasil memindai folder assets dan menghapus file yang tidak lagi digunakan.</p>
            
            <div class="alert alert-info d-inline-block px-5 rounded-pill">
                <strong class="fs-4"><?= $deletedCount ?></strong> file gambar berhasil dihapus.
            </div>
            
            <div class="mt-4">
                <a href="<?= BASE_URL ?>admin/produk" class="btn btn-dark px-4 py-2 rounded-0 text-uppercase small">Kembali ke Produk</a>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>