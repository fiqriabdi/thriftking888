<?php

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireGuest(); // Mencegah user yang sudah login melihat form login lagi
require_once APP_ROOT . '/Controllers/Auth/authcontroller.php';
require_once APP_ROOT . '/Config/koneksi.php';

$auth = new authcontroller(Database::getConnection()); // Kirim koneksi ke controller auth  
$error = "";
$message = "";
$old_email = "";

if (isset($_GET['pesan']) && $_GET['pesan'] === 'registrasi_berhasil') {
    $message = "<div class='alert alert-success alert-dismissible fade show rounded-0 border-0 shadow-sm' role='alert'>" .
               "Registrasi berhasil! Silakan login." .
               "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>" .
               "</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_email = trim($_POST['email'] ?? '');
    $error = $auth->login($_POST['email'], $_POST['password']);
}
?>
<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<style>
    .shake-error {
        animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
    }
    @keyframes shake {
        10%, 90% { transform: translate3d(-1px, 0, 0); }
        20%, 80% { transform: translate3d(2px, 0, 0); }
        30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
        40%, 60% { transform: translate3d(4px, 0, 0); }
    }
    .alert-error-login { background-color: #fff5f5; border-left: 4px solid #d9534f !important; color: #b91c1c; }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="text-center mb-4">
                <h2 class="fw-light">LOGIN</h2>
                <p class="small text-muted text-capitalize" style="letter-spacing: 2px;">Welcome</p>
            </div>

            <?= $message ?>
            <?php if($error): ?>
                <div class="alert alert-error-login alert-dismissible fade show rounded-0 shadow-sm shake-error" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-octagon-fill fs-5 me-2"></i>
                        <span class="small fw-bold"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="small text-capitalize  fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control rounded-0 <?= $error ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($old_email, ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="small text-capitalize  fw-bold">Password</label>
                    <input type="password" name="password" class="form-control rounded-0 <?= $error ? 'is-invalid' : '' ?>" required>
                    <div class="text-end mt-1">
                        <a href="<?= BASE_URL ?>auth/forgot-password" class="text-muted small text-decoration-none">Lupa Password?</a>
                    </div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-dark rounded-0 py-2">LOGIN</button>
                    <a href="<?= BASE_URL ?>auth/register" class="btn btn-outline-dark rounded-0 py-2">REGISTER</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>
