<?php
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Models/user.php';

$userModel = new user(Database::getConnection());
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($email) || empty($new_password)) {
        $msg = 'Email dan Password Baru wajib diisi.';
        $msg_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $msg = 'Password baru minimal 6 karakter.';
        $msg_type = 'danger';
    } else {
        $user = $userModel->getByEmail($email);
        if (!$user) {
            $msg = "Pengguna dengan email '{$email}' tidak ditemukan.";
            $msg_type = 'danger';
        } elseif ($user['role'] === 'admin' && $user['id'] !== $_SESSION['user']['id']) {
            // Melarang admin mereset password admin lain, tapi mengizinkan reset password diri sendiri.
            $msg = "Reset password untuk administrator lain tidak diizinkan.";
            $msg_type = 'danger';
        } else {
            if ($userModel->updatePassword($user['id'], $new_password)) {
                // Log aktivitas menggunakan Trait Loggable
                require_once APP_ROOT . '/helpers/Loggable.php';
                Loggable::logActivityStatic("manual_password_reset", "Password untuk {$user['nama']} ({$email}) direset secara manual.");

                if ($user['id'] === $_SESSION['user']['id']) {
                    $msg = "Password Anda berhasil diubah. Silakan gunakan password baru saat login berikutnya.";
                } else {
                    $msg = "Password untuk <strong>{$user['nama']}</strong> berhasil direset menjadi: <br><code class='fs-5'>{$new_password}</code><br>Silakan informasikan password baru ini kepada pelanggan.";
                }
                $msg_type = 'success';
            } else {
                $msg = "Gagal memperbarui password di database.";
                $msg_type = 'danger';
            }
        }
    }
}

// Logika untuk judul dan deskripsi dinamis
$form_title = 'Reset Password';
$form_description = 'Gunakan fitur ini untuk membantu pelanggan yang tidak bisa mengakses akunnya. Password baru akan ditampilkan di sini untuk Anda teruskan kepada pelanggan.';

$current_email_in_form = $_GET['email'] ?? $_POST['email'] ?? '';
if ($current_email_in_form === ($_SESSION['user']['email'] ?? '')) {
    $form_title = 'Ubah Password Saya (Admin)';
    $form_description = 'Gunakan form ini untuk mengubah password Anda sendiri. Setelah berhasil, Anda harus login kembali dengan password baru.';
}

$pageTitle = 'Reset Password ';
require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0 text-capitalize"><?= htmlspecialchars($form_title) ?></h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small"><?= htmlspecialchars($form_description) ?></p>

                    <?php if ($msg): ?>
                        <div class="alert alert-<?= $msg_type ?> small"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email Pelanggan</label>
                            <input type="email" name="email" class="form-control" placeholder="Masukkan email akun..." value="<?= htmlspecialchars($current_email_in_form) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Password Baru</label>
                            <div class="input-group">
                                <input type="text" name="new_password" id="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
                                <button class="btn btn-outline-secondary" type="button" id="generatePassword">Generate</button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger fw-bold text-capitalize">Reset Password </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light text-center">
                     <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('generatePassword').addEventListener('click', function() {
    // Membuat password acak yang mudah dibaca (menghilangkan karakter ambigu)
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('new_password').value = password;
});
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>