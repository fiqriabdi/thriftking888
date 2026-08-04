<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Controllers/Admin/usercontroller.php';

$controller = new usercontroller(Database::getConnection());
$error_message = '';
$old = [
    'nama'     => '',
    'email'    => '',
    'password' => '',
    'role'     => 'pelanggan',
    'no_hp'    => '',
    'alamat'   => ''
];

if (isset($_POST['submit'])) {
    $old['nama']     = trim($_POST['nama'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $old['password'] = $_POST['password'] ?? '';
    $old['role']     = $_POST['role'] ?? 'pelanggan';
    $old['no_hp']    = trim($_POST['no_hp'] ?? '');
    $old['alamat']   = trim($_POST['alamat'] ?? '');

    if ($old['nama'] === '' || $old['email'] === '' || $old['password'] === '') {
        $error_message = 'Nama, Email, dan Password wajib diisi.';
    } elseif (strlen($old['password']) < 8) {
        $error_message = 'Password minimal harus 8 karakter.';
    } elseif (!preg_match('/[A-Z]/', $old['password']) || !preg_match('/[0-9]/', $old['password'])) {
        $error_message = 'Password harus mengandung kombinasi huruf besar dan angka.';
    } else {
        if ($controller->store($old)) {
            header('Location: ' . BASE_URL . 'admin/pengguna.php?pesan=tambah_berhasil');
            exit();
        } else {
            $error_message = 'Gagal menyimpan data pengguna. Email mungkin sudah terdaftar.';
        }
    }
}

$pageTitle = 'Tambah Pengguna Baru';
require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if (!empty($error_message)) : ?>
                <div class="alert alert-danger rounded-0 small fw-bold mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 rounded-0">
                <div class="card-header bg-dark text-white text-uppercase py-3">
                    <h6 class="mb-0 fw-bold" style="letter-spacing: 1px;"><i class="bi bi-person-plus me-2"></i> TAMBAH PENGGUNA BARU</h6>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control rounded-0" value="<?= htmlspecialchars($old['nama'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nama Lengkap" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Email Address</label>
                                <input type="email" name="email" class="form-control rounded-0" value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>" placeholder="email@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Password</label>
                                <input type="password" name="password" class="form-control rounded-0" placeholder="Min 8 Karakter (A-Z & 0-9)" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-uppercase mb-1">Role Pengguna</label>
                                <select name="role" class="form-select rounded-0">
                                    <option value="pelanggan" <?= $old['role'] === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                                    <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="small fw-bold text-uppercase mb-1">Nomor WhatsApp / HP</label>
                                <input type="text" name="no_hp" class="form-control rounded-0" value="<?= htmlspecialchars($old['no_hp'], ENT_QUOTES, 'UTF-8') ?>" placeholder="0812xxxx">
                            </div>
                            <div class="col-md-12">
                                <label class="small fw-bold text-uppercase mb-1">Alamat Lengkap</label>
                                <textarea name="alamat" class="form-control rounded-0" rows="3" placeholder="Alamat pengiriman..."><?= htmlspecialchars($old['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4 opacity-10">
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>admin/pengguna.php" class="btn btn-outline-dark rounded-0 px-4 small fw-bold">BATAL</a>
                            <button type="submit" name="submit" class="btn btn-dark rounded-0 px-4 small fw-bold">SIMPAN PENGGUNA</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>