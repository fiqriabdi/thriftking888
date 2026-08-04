<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Models/user.php';

$userModel = new user(Database::getConnection());
// Mendukung variabel $id dari Router (Clean URL) atau $_GET (Query String)
$id = intval($id ?? $_GET['id'] ?? 0);
$u = $userModel->findById($id);

if (!$u) {
    header('Location: ' . BASE_URL . 'admin/pengguna');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'pelanggan';
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    // Validasi Dasar
    if ($nama === '' || $email === '') {
        $error_message = 'Nama dan Email wajib diisi.';
    } elseif ($userModel->existsByEmail($email, $id)) {
        $error_message = 'Email sudah digunakan oleh pengguna lain.';
    } else {
        if ($error_message === '') {
            if ($userModel->updateById($id, $nama, $email, $role, $no_hp, $alamat, null)) { // Password di-pass sebagai null
                $success_message = 'Data pengguna berhasil diperbarui.';
                $u = $userModel->findById($id); // Refresh data
            } else {
                $error_message = 'Gagal memperbarui data.';
            }
        }
    }
}

require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-0">
                <div class="card-header bg-dark text-white text-uppercase py-3">
                    <h6 class="mb-0 fw-bold" style="letter-spacing: 1px;">Edit Pengguna: <?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?></h6>
                </div>
                <div class="card-body p-4">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success rounded-0 small fw-bold mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i><?= $success_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger rounded-0 small fw-bold mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_message ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control rounded-0" value="<?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Email</label>
                                <input type="email" name="email" class="form-control rounded-0" value="<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Role</label>
                                <select name="role" class="form-select rounded-0">
                                    <option value="pelanggan" <?= $u['role'] === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-end">

                                <!--<a href="<?= BASE_URL ?>admin/reset-password" class="btn btn-outline-danger w-100 rounded-0">
                                    <i class="bi bi-key-fill me-2"></i> Buka Halaman Reset Password
                                </a>-->

                            </div>

                            <div class="col-md-12">
                                <label class="small fw-bold text-uppercase mb-1">Nomor Telepon</label>
                                <input type="text" name="no_hp" class="form-control rounded-0" value="<?= htmlspecialchars($u['no_hp'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="small fw-bold text-uppercase mb-1">Alamat Lengkap</label>
                                <textarea name="alamat" class="form-control rounded-0" rows="3"><?= htmlspecialchars($u['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-4 opacity-10">
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>admin/pengguna" class="btn btn-outline-dark rounded-0 px-4 small fw-bold">KEMBALI</a>
                            <button type="submit" class="btn btn-dark rounded-0 px-4 small fw-bold">SIMPAN PERUBAHAN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>