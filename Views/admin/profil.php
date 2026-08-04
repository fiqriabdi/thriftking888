<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
// Menggunakan Controller (Konsep MVC) menggantikan panggilan Model langsung
require_once APP_ROOT . '/Controllers/Admin/UserController.php';
require_once APP_ROOT . '/helpers/Format.php';
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Security.php'; // Untuk CSRF Token

$conn = Database::getConnection();
$userController = new UserController($conn); 
$userSession = $_SESSION['user'];

$pageTitle = 'Profil Admin - ThriftKing888';
$errorMessage = '';
$successMessage = '';

// Generate CSRF token untuk semua form di halaman ini
$csrf_token = generateCSRFToken();

// Ambil pesan sukses dari URL jika ada (hasil redirect)
if (isset($_GET['status']) && $_GET['status'] === 'profil_updated') {
    $successMessage = 'Profil berhasil diperbarui.';
} elseif (isset($_GET['status']) && $_GET['status'] === 'password_updated') {
    $successMessage = 'Password berhasil diganti.';
}

// --- PENGAMBILAN DATA TERPUSAT ---
// 1. Ambil data user ter-update dari database SEBELUM memproses form
$u = $userController->show($userSession['id']);

// 2. Ambil data statistik khusus untuk admin
$stats = $userController->getAdminStats();
// --- END PENGAMBILAN DATA ---

// --- LOGIKA PROGRAM (POST REQUEST) VIA CONTROLLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errorMessage = 'Permintaan tidak valid (CSRF token).';
    } else {
        // 1. Logika Update Informasi Profil
        if (isset($_POST['update_profil'])) {
            $nama   = trim($_POST['nama'] ?? '');
            $no_hp  = trim($_POST['no_hp'] ?? '');
            $alamat = trim($_POST['alamat'] ?? ''); // Alamat tidak ada di form admin, akan jadi string kosong

            if (empty($nama)) {
                $errorMessage = 'Nama lengkap tidak boleh kosong.';
            } else {
                if ($userController->update($userSession['id'], $nama, $userSession['email'], 'admin', $no_hp, $alamat, null)) {
                    $_SESSION['user']['nama']   = $nama;
                    header("Location: " . BASE_URL . "admin/profil?status=profil_updated");
                    exit;
                } else {
                    $errorMessage = 'Gagal memperbarui profil. Terjadi kesalahan data.';
                }
            }
        }

        // 2. Logika Update Password Akun
        if (isset($_POST['update_pass'])) {
            $new_pass     = $_POST['new_pass'] ?? '';
            $confirm_pass = $_POST['confirm_pass'] ?? '';

            if (strlen($new_pass) < 8) {
                $errorMessage = 'Password minimal 8 karakter.';
            } elseif (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
                $errorMessage = 'Password harus mengandung huruf besar dan angka.';
            } elseif ($new_pass !== $confirm_pass) {
                $errorMessage = 'Konfirmasi password tidak cocok.';
            } else {
                if ($userController->update($userSession['id'], $u['nama'], $u['email'], 'admin', $u['no_hp'], $u['alamat'], $new_pass)) {
                    header("Location: " . BASE_URL . "admin/profil?status=password_updated");
                    exit;
                } else {
                    $errorMessage = 'Gagal memperbarui password.';
                }
            }
        }
    }
}
?>

<?php 
require_once APP_ROOT . '/Views/layouts/header.php'; 
require_once APP_ROOT . '/Views/layouts/navbar.php'; 
?>

<div class="container py-5" style="max-width: 1150px;">
    <?php if ($successMessage) : ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage) : ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row align-items-start">
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                        <?php
                            $admin_foto_src = !empty($u['foto_profil']) ? BASE_URL . 'public/storage/profil/' . htmlspecialchars($u['foto_profil'], ENT_QUOTES, 'UTF-8') : 'https://ui-avatars.com/api/?name=' . urlencode($u['nama'] ?? 'User') . '&background=random';
                        ?>
                        <img src="<?= $admin_foto_src ?>" class="rounded-circle border object-fit-cover" width="50" height="50" alt="Avatar" 
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($u['nama'] ?? 'User') ?>&background=random';">
                        <div class="ms-3 overflow-hidden">
                            <h6 class="fw-bold mb-0 text-truncate" style="font-size: 15px;"><?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?></h6>
                            <span class="badge bg-dark mt-1 fw-normal" style="font-size: 10px;">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </div>
                    </div>

                    <ul class="nav flex-column sidebar-nav" id="myTab" role="tablist">
                        <li class="nav-item mb-1">
                            <a class="nav-link text-dark fw-bold active text-capitalize" data-bs-toggle="pill" href="#dashboard">
                                Dashboard 
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link text-dark fw-bold text-capitalize" data-bs-toggle="pill" href="#profile">
                                Profil 
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link text-dark fw-bold text-capitalize" href="<?= BASE_URL ?>admin/settings">
                                Pengaturan Toko
                            </a>
                        </li>

                        <li class="nav-item mt-4 border-top pt-3">
                            <a class="nav-link text-danger fw-bold text-capitalize" href="<?= BASE_URL ?>auth/logout">
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-9 col-md-8">
            <div class="tab-content border-0" id="mainTabContent">
                <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3 p-4 min-vh-50">
                        <h5 class="fw-bold mb-1">Admin Control Panel</h5>
                        <p class="text-muted small mb-4">Ringkasan data operasi ThriftKing888 saat ini.</p>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 rounded-3 p-3 h-100 shadow-none">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-box-seam fs-2 text-primary me-3"></i>
                                        <div>
                                            <small class="text-primary fw-bold text-uppercase">Total Produk</small>
                                            <h4 class="fw-bold mb-0 mt-1"><?= $stats['produk'] ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 rounded-3 p-3 h-100 shadow-none">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-receipt fs-2 text-success me-3"></i>
                                        <div>
                                            <small class="text-success fw-bold text-uppercase">Pesanan Baru</small>
                                            <h4 class="fw-bold mb-0 mt-1"><?= $stats['pesanan'] ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border border-info border-opacity-25 bg-info bg-opacity-10 rounded-3 p-3 h-100 shadow-none">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-people fs-2 text-info me-3"></i>
                                        <div>
                                            <small class="text-info fw-bold text-uppercase">Total Pelanggan</small>
                                            <h4 class="fw-bold mb-0 mt-1"><?= $stats['pelanggan'] ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="<?= BASE_URL ?>admin/produk" class="btn btn-dark px-4 me-2">Kelola Produk</a>
                            <a href="<?= BASE_URL ?>admin/dashboard" class="btn btn-outline-dark px-4">Ke Dashboard Utama</a>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="profile" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3 p-4">
                        <div class="d-flex align-items-center pb-3 mb-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark">Pengaturan Profil</h5>
                        </div>

                        <div class="row">
                            <div class="col-lg-7">
                                <h6 class="fw-bold mb-3">Informasi Pribadi</h6>
                                <form action="" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">Nama Lengkap</label>
                                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">Email (tidak dapat diubah)</label>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">Nomor HP</label>
                                        <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($u['no_hp'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <button type="submit" name="update_profil" class="btn btn-dark px-4">Simpan Informasi</button>
                                </form>
                            </div>

                            <div class="col-lg-5 mt-5 mt-lg-0 border-start-lg">
                                <h6 class="fw-bold mb-3 ps-lg-3">Ubah Password</h6>
                                <form action="" method="POST" class="ps-lg-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">Password Baru</label>
                                        <input type="password" name="new_pass" class="form-control" required placeholder="Min. 8 karakter">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">Konfirmasi Password</label>
                                        <input type="password" name="confirm_pass" class="form-control" required>
                                    </div>
                                    <button type="submit" name="update_pass" class="btn btn-outline-dark px-4">Ubah Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
    /* Global fixes */
    body { background-color: #f3f4f5; }
    .min-vh-50 { min-height: 50vh; }
    .form-control:focus { border-color: #000; box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1); }

    /* Navigasi Sidebar Kiri */
    .sidebar-nav .nav-link { padding: 10px 15px; border-radius: 8px; font-size: 14px; transition: 0.2s; }
    .sidebar-nav .nav-link:hover { background-color: #f8f9fa; }
    .sidebar-nav .nav-link.active { background-color: #f3f4f5; color: #000 !important; }
    .sidebar-nav .nav-link.active i { color: #000 !important; }

    @media (min-width: 992px) {
        .border-start-lg { border-left: 1px solid #dee2e6 !important; }
    }
</style>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>