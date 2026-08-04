<?php
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireGuest(); // Mencegah user yang sudah login untuk mendaftar lagi
require_once APP_ROOT . '/Controllers/Auth/authcontroller.php';
require_once APP_ROOT . '/Config/koneksi.php';

$auth = new authcontroller(Database::getConnection()); // Menggunakan koneksi terpusat
$error = "";
$old_nama = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_nama = trim($_POST['nama'] ?? '');
    $old_email = trim($_POST['email'] ?? '');
    $error = $auth->register($old_nama, $old_email, $_POST['password']);
}
?>
<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="text-center mb-4">
                <h2 class="fw-light">Register Now</h2>
                <p class="small text-muted text-capitalize" style="letter-spacing: 2px;">Welcome</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-0" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="small text-capitalize fw-bold"> Nama</label>
                    <input type="text" name="nama" class="form-control rounded-0" value="<?= htmlspecialchars($old_nama, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="small text-capitalize  fw-bold">Email</label>
                    <input type="email" name="email" class="form-control rounded-0" value="<?= htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="small text-capitalize  fw-bold">Password</label>
                    <input type="password" name="password" class="form-control rounded-0" minlength="6" required>
                    <div class="form-text small text-muted">Minimal 6 karakter.</div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-dark rounded-0 py-2">REGISTER</button>
                    <a href="<?= BASE_URL ?>auth/login" class="btn btn-outline-dark rounded-0 py-2">LOG IN</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>
