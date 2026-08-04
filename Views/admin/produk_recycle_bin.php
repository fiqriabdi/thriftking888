<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin'); 

require_once APP_ROOT . '/helpers/Security.php';
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
$controller = new produkcontroller(Database::getConnection());

$pageTitle = 'Recycle Bin - ThriftKing888';
$activePage = 'produk';

$csrf_token = generateCSRFToken();

// Ambil ID dari Router params (disediakan oleh dispatch -> extract)
$id_param = isset($id) ? intval($id) : 0;

// Logika Aksi (Restore atau Force Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_param > 0) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = 'Permintaan tidak valid (CSRF token).';
    } else {
        if (strpos($_SERVER['REQUEST_URI'], 'restore') !== false) {
            if ($controller->restore($id_param)) {
                $_SESSION['success_msg'] = "Produk berhasil dikembalikan ke katalog!";
            }
        } elseif (strpos($_SERVER['REQUEST_URI'], 'force-delete') !== false) {
            if ($controller->forceDelete($id_param)) {
                $_SESSION['success_msg'] = "Produk telah dihapus secara permanen.";
            }
        }
    }
    header('Location: ' . BASE_URL . 'admin/produk/recycle-bin');
    exit();
}

$deletedProducts = $controller->getSoftDeletedProducts();
require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0"><i class="bi bi-trash3-fill me-2"></i>Recycle Bin Produk</h5>
                    <a href="<?= BASE_URL ?>admin/produk" class="btn btn-light btn-sm fw-bold">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">Daftar produk di bawah ini telah dihapus secara lunak. Anda dapat mengembalikannya ke katalog atau menghapusnya secara permanen.</p>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-uppercase small">
                                <tr>
                                    <th>Nama Produk</th>
                                    <th>Dihapus Pada</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($deletedProducts)) : ?>
                                    <?php foreach($deletedProducts as $p) : ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($p['nama_produk']) ?></td>
                                        <td><span class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['deleted_at'])) ?></span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" action="<?= BASE_URL ?>admin/produk/restore/<?= intval($p['id']) ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-success px-3">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?= BASE_URL ?>admin/produk/force-delete/<?= intval($p['id']) ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-danger px-3" onclick="return confirm('PERINGATAN: Menghapus permanen tidak dapat dibatalkan. Lanjutkan?')">
                                                        <i class="bi bi-x-circle me-1"></i> Hapus Permanen
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <i class="bi bi-trash3 display-4 d-block mb-3 opacity-25"></i>
                                            Recycle bin kosong.
                                        </td>
                                    </tr>
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