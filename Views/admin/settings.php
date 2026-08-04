<?php
// 1. Keamanan & Koneksi
if (!defined('APP_ROOT')) require_once __DIR__ . '/../../Config/konstanta.php';
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php'; // Pastikan Database::getConnection() tersedia
require_once APP_ROOT . '/Controllers/Admin/SettingsController.php'; // Panggil Controller baru
require_once APP_ROOT . '/helpers/Security.php';

$db = Database::getConnection();
$settingsController = new SettingsController($db);
$s = $settingsController->index(); // Ambil pengaturan dari Controller

// [DITAMBAHKAN] Generate CSRF token untuk form
$csrf_token = generateCSRFToken();

// 3. Proses Update Jika Tombol Simpan Diklik
$success_message = '';
$error_messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [DITAMBAHKAN] Logika Hapus Logo
    if (isset($_POST['delete_logo'])) {
        $result = $settingsController->deleteLogo();
        if ($result['success']) {
            $_SESSION['success_msg'] = $result['message'];
            header('Location: ' . BASE_URL . 'admin/settings');
            exit();
        }
        $error_messages = $result['errors'];
    } elseif (isset($_POST['update_settings'])) { // Logika Update yang sudah ada
    // [DITAMBAHKAN] Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_messages[] = 'Permintaan tidak valid (CSRF token).';
    } else {
        $result = $settingsController->update($_POST, $_FILES);
        if ($result['success']) {
            $_SESSION['success_msg'] = $result['message']; // Gunakan flash message
            header('Location: ' . BASE_URL . 'admin/settings');
            exit();
        } else {
            $error_messages = $result['errors'];
        }
    }
    }
}

// Tangkap flash message
if (isset($_SESSION['success_msg'])) {
    $success_message = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

$pageTitle = 'Pengaturan Toko';
$activePage = 'settings';
require_once APP_ROOT . '/Views/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if (!empty($success_message)) : ?>
                <div class="alert alert-success border-0 shadow-sm py-2 mb-4 small"><i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error_messages)) : ?>
                <div class="alert alert-danger border-0 shadow-sm py-2 mb-4 small">
                    <ul class="mb-0">
                        <?php foreach ($error_messages as $error) : ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card shadow border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4 text-center">
                    <h4 class="mb-1 fw-bold text-capitalize" style="letter-spacing: 0.5px;">Pengaturan Identitas Toko</h4>
                    <p class="text-muted small mb-0 text-capitalize opacity-75" style="font-size:0.7rem;">Sistem Panel Konfigurasi Utama ThriftKing888</p>
                </div>
                <div class="card-body p-4 bg-light">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- [DITAMBAHKAN] Input CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row g-4">
                            <div class="col-md-4 text-center border-end border-2 border-white d-flex flex-column align-items-center justify-content-center">
                                <label class="form-label small fw-bold d-block mb-3 text-capitalize text-secondary">Logo Toko Saat Ini</label>
                                <div class="bg-white p-2 shadow-sm rounded-circle mb-3 d-flex align-items-center justify-content-center" style="width: 130px; height: 130px; overflow:hidden;">
                                    <?php 
                                        $logo_src = !empty($s['logo']) ? BASE_URL . 'assets/img/logo/' . htmlspecialchars($s['logo'], ENT_QUOTES, 'UTF-8') : '';
                                    ?>
                                    <?php if ($logo_src) : ?>
                                        <img id="previewLogo" src="<?= $logo_src ?>" class="img-fluid" style="object-fit: contain; max-height: 110px;" onerror="this.style.display='none'; this.parentElement.innerHTML += '<i class=\'bi bi-shop fs-1 text-muted\'></i>';">
                                    <?php else: ?>
                                        <i class="bi bi-shop fs-1 text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="file" name="logo" class="form-control" id="logoInput" onchange="previewImage(this)">
                                </div>
                                <?php if (!empty($s['logo'])): ?>
                                    <button type="submit" name="delete_logo" class="btn btn-sm btn-outline-danger w-100 mt-2" onclick="return confirm('Anda yakin ingin menghapus logo? Tampilan akan kembali menggunakan nama toko.')">Hapus Logo</button>
                                <?php endif; ?>
                                <small class="text-muted mt-2" style="font-size:0.65rem;">Format: PNG, JPG, JPEG (Max 2MB)</small>
                            </div>

                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-capitalize text-secondary">Nama Platform / Toko</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-shop"></i></span>
                                        <input type="text" name="nama_toko" class="form-control border-start-0" value="<?= htmlspecialchars($s['nama_toko'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-capitalize text-secondary">Email Official</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope-at"></i></span>
                                        <input type="email" name="email" class="form-control border-start-0" value="<?= htmlspecialchars($s['email'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-capitalize text-secondary">No. Handphone / WhatsApp Customer Service</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-whatsapp"></i></span>
                                        <input type="text" name="no_hp" class="form-control border-start-0" value="<?= htmlspecialchars($s['no_hp'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-capitalize text-secondary">Alamat Toko / Gudang</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted align-items-start pt-2"><i class="bi bi-geo-alt"></i></span>
                                        <textarea name="alamat" class="form-control border-start-0" rows="3" placeholder="Alamat lengkap untuk pengiriman..."><?= htmlspecialchars($s['alamat'] ?? '') ?></textarea>
                                    </div>
                                    <small class="text-muted" style="font-size:0.7rem;">Alamat ini akan digunakan sebagai alamat pengirim pada label cetak.</small>
                                </div>
                                
                                <!--<hr class="my-4">
                                 <h6 class="fw-bold text-uppercase small text-secondary">Link Media Sosial</h6>
                                 <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted" style="width: 45px;"><i class="bi bi-instagram"></i></span>
                                        <input type="url" name="instagram_url" class="form-control border-start-0" value="<?= htmlspecialchars($s['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/username">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted" style="width: 45px;"><i class="bi bi-facebook"></i></span>
                                        <input type="url" name="facebook_url" class="form-control border-start-0" value="<?= htmlspecialchars($s['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/username">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted" style="width: 45px;"><i class="bi bi-tiktok"></i></span>
                                        <input type="url" name="tiktok_url" class="form-control border-start-0" value="<?= htmlspecialchars($s['tiktok_url'] ?? '') ?>" placeholder="https://tiktok.com/@username">
                                    </div>
                                </div>-->


                                <div class="pt-2">
                                    <button type="submit" name="update_settings" class="btn btn-dark w-100 rounded-pill py-2 shadow-sm text-capitalize">
                                        Simpan Semua Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-white border-0 text-center pb-4">
                    <small class="text-muted italic">Sistem otomatis akan menghapus logo lama saat Anda mengunggah logo baru.</small>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewLogo').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<style>
    .hover-dark:hover { color: #000 !important; font-weight: bold; }
    input:focus { box-shadow: none !important; border: 1px solid #000 !important; }
</style>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>