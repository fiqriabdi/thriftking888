<?php
require_once APP_ROOT . '/Controllers/Auth/authcontroller.php';
$auth = new authcontroller($conn);

$token = $token ?? ''; // Menggunakan $token dari Router::getParam()
$error = "";
$success = "";

// 1. Verifikasi Token di awal
$email = $auth->verifyToken($token);
if (!$email) {
    die("Token tidak valid atau sudah kadaluarsa. Silakan minta reset password kembali.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        $result = $auth->executeReset($token, $password);
        if ($result === true) {
            header("Location: " . BASE_URL . "auth/login?pesan=reset_berhasil");
            exit();
        } else {
            $error = $result;
        }
    }
}
?>
<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-4 card p-4 rounded-0 border shadow-sm">
            <h5 class="fw-bold text-uppercase mb-3">Set Password Baru</h5>
            <p class="small text-muted">Untuk email: <strong><?= htmlspecialchars($email) ?></strong></p>
            
            <?php if($error): ?><div class="alert alert-danger small rounded-0"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">PASSWORD BARU</label>
                    <input type="password" name="password" class="form-control rounded-0" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">KONFIRMASI PASSWORD</label>
                    <input type="password" name="confirm_password" class="form-control rounded-0" required>
                </div>
                <button type="submit" class="btn btn-dark w-100 rounded-0">UPDATE PASSWORD</button>
            </form>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>