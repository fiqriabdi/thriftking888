<?php
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Controllers/Admin/kategoricontroller.php';
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Security.php';

$controller = new kategoricontroller(Database::getConnection());
$msg = "";
$error = "";

// Generate CSRF token untuk form
$csrf_token = generateCSRFToken();

// Handle Post untuk Tambah Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_kategori'])) {
    // Validasi CSRF token terlebih dahulu
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Permintaan tidak valid (CSRF token).';
    } else {
        $res = $controller->store($_POST['nama_kategori']);
        if ($res === true) {
            $msg = "Kategori berhasil ditambahkan.";
        } else {
            $error = $res;
        }
    }
}

// Handle Post untuk Update Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_kategori'])) {
    // Validasi CSRF token terlebih dahulu
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Permintaan tidak valid (CSRF token).';
    } else {
        $res = $controller->update(intval($_POST['id']), $_POST['nama_kategori']);
        if ($res === true) {
            header("Location: " . BASE_URL . "admin/kategori?pesan=update_berhasil");
            exit();
        } else {
            $error = $res;
        }
    }
}

$edit_cat = null;
// REVISI: Kondisi untuk mode edit disesuaikan.
// Mode edit aktif jika metode request adalah GET dan ada ID di URL.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($id)) {
    $edit_cat = $controller->show(intval($id));
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_kategori'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Permintaan tidak valid (CSRF token).';
    } else {
        if ($controller->destroy(intval($id))) {
            header("Location: " . BASE_URL . "admin/kategori?pesan=hapus_berhasil");
            exit();
        } else {
            $error = "Kategori tidak bisa dihapus karena masih digunakan oleh produk.";
        }
    }
}

if (isset($_GET['pesan']) && $_GET['pesan'] == 'hapus_berhasil') $msg = "Kategori berhasil dihapus.";
if (isset($_GET['pesan']) && $_GET['pesan'] == 'update_berhasil') $msg = "Kategori berhasil diperbarui.";

$categories = $controller->index();
$pageTitle = 'Manajemen Kategori';
require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-0">
                <div class="card-header bg-dark text-white fw-bold text-capitalize small">
                    <?= $edit_cat ? 'Edit Kategori' : 'Tambah Kategori' ?>
                </div>
                <div class="card-body">
                    <?php if($error): ?><div class="alert alert-danger small rounded-0"><?= $error ?></div><?php endif; ?>
                    <?php if($msg): ?><div class="alert alert-success small rounded-0"><?= $msg ?></div><?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        
                        <?php if($edit_cat): ?><input type="hidden" name="id" value="<?= $edit_cat['id'] ?>"><?php endif; ?>
                        <div class="mb-3">
                            <label class="small fw-bold">Nama Kategori</label>
                            <input type="text" name="nama_kategori" class="form-control rounded-0" placeholder="Misal: Vintage" value="<?= $edit_cat ? htmlspecialchars($edit_cat['nama_kategori']) : '' ?>" required>
                        </div>
                        <button type="submit" name="<?= $edit_cat ? 'edit_kategori' : 'tambah_kategori' ?>" class="btn btn-dark w-100 rounded-0"><?= $edit_cat ? 'UPDATE' : 'Simpan' ?></button>
                        <?php if($edit_cat): ?><a href="<?= BASE_URL ?>admin/kategori" class="btn btn-link w-100 mt-2 text-decoration-none text-dark small">Batal Edit</a><?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="mt-3">
                <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-0">
                <div class="card-header bg-dark text-white fw-bold text-capitalize small">Daftar Kategori</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small">
                                <tr>
                                    <th class="ps-3">Nama Kategori</th>
                                    <th>Slug</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach($categories as $cat): ?>
                                <tr>
                                    <td class="ps-3 fw-bold small"><?= htmlspecialchars($cat['nama_kategori']) ?></td>
                                    <td><code class="small"><?= $cat['slug'] ?></code></td>
                                    <td class="text-end pe-3">
                                        <a href="<?= BASE_URL ?>admin/kategori/edit/<?= $cat['id'] ?>" 
                                           class="btn btn-outline-warning btn-sm rounded-0 me-1">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>admin/kategori/delete/<?= $cat['id'] ?>" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" name="delete_kategori" class="btn btn-outline-danger btn-sm rounded-0" onclick="return confirm('Hapus kategori ini?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($categories)): ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada kategori.</td></tr>
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