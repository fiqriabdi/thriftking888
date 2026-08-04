<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('pelanggan');
// Menggunakan Controller (Konsep MVC) menggantikan panggilan Model langsung
require_once APP_ROOT . '/Controllers/Admin/UserController.php';
require_once APP_ROOT . '/Controllers/Customer/ProfilController.php'; // [REFAKTOR] Panggil controller profil
require_once APP_ROOT . '/helpers/Format.php';
require_once APP_ROOT . '/Config/koneksi.php';

$conn = Database::getConnection();
$userController = new UserController($conn); 
$userSession = $_SESSION['user'];

$pageTitle = 'Biodata Diri - ThriftKing888';
$errorMessage = '';
$successMessage = '';

// Ambil pesan sukses dari URL jika ada (hasil redirect)
if (isset($_GET['status']) && $_GET['status'] === 'profil_updated') {
    $successMessage = 'Profil berhasil diperbarui.';
} elseif (isset($_GET['status']) && $_GET['status'] === 'foto_deleted') {
    $successMessage = 'Foto profil berhasil dihapus.';
} elseif (isset($_GET['status']) && $_GET['status'] === 'foto_updated') {
    $successMessage = 'Foto profil berhasil diperbarui.';
} elseif (isset($_GET['status']) && $_GET['status'] === 'password_updated') {
    $successMessage = 'Password berhasil diganti.';
}

// --- PENGAMBILAN DATA TERPUSAT ---
// 1. Ambil data user ter-update dari database SEBELUM memproses form
$u = $userController->show($userSession['id']);

// 2. Ambil data spesifik untuk setiap role
$orders = [];
$reviews = [];

require_once APP_ROOT . '/Models/transaksi.php';
$transaksiModel = new transaksi($conn);
$orders = $transaksiModel->getOrdersByUser($userSession['id']);

require_once APP_ROOT . '/Controllers/Admin/UlasanController.php';
$ulasanCtrl = new UlasanController($conn);
$reviews = $ulasanCtrl->getByUser($userSession['id']);
// --- END PENGAMBILAN DATA ---

// --- LOGIKA PROGRAM (POST REQUEST) VIA CONTROLLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profilCtrl = new ProfilController($conn);
    
    // [BARU] 4. Logika Hapus Foto Profil
    if (isset($_POST['hapus_foto'])) {
        $result = $profilCtrl->deleteProfilePicture($userSession['id']);
        if ($result['success']) {
            // Menggunakan status yang berbeda untuk pesan yang berbeda
            header("Location: " . BASE_URL . "pelanggan/profil?status=foto_deleted");
            exit;
        } else {
            $errorMessage = $result['message'];
        }
    }

    // [BARU] 3. Logika Update Foto Profil
    if (isset($_POST['update_foto'])) {
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $result = $profilCtrl->processProfilePictureUpload($userSession['id'], $_FILES['foto_profil']);
            if ($result['success']) {
                header("Location: " . BASE_URL . "pelanggan/profil?status=foto_updated");
                exit;
            } else {
                $errorMessage = $result['message'];
            }
        } else {
            $errorMessage = "Anda harus memilih sebuah file untuk diunggah.";
        }
    }
    // 1. Logika Update Informasi Profil
    if (isset($_POST['update_profil'])) {
        $result = $profilCtrl->updateProfil($userSession['id'], $_POST);
        if ($result['success']) {
            header("Location: " . BASE_URL . "pelanggan/profil?status=profil_updated");
            exit;
        } else {
            $errorMessage = $result['message'];
        }
    }

    // 2. Logika Update Password Akun
    if (isset($_POST['update_pass'])) {
        $result = $profilCtrl->updatePassword($userSession['id'], $_POST);
        if ($result['success']) {
            header("Location: " . BASE_URL . "pelanggan/profil?status=password_updated");
            exit;
        } else {
            $errorMessage = $result['message'];
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
                            $foto_profil_nav = !empty($u['foto_profil']) 
                                ? BASE_URL . 'public/storage/profil/' . htmlspecialchars($u['foto_profil'], ENT_QUOTES, 'UTF-8')
                                : 'https://ui-avatars.com/api/?name=' . urlencode($u['nama'] ?? 'User') . '&background=random';
                        ?>
                        <img src="<?= $foto_profil_nav ?>" class="rounded-circle border object-fit-cover" width="50" height="50" alt="Avatar"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($u['nama'] ?? 'User') ?>&background=random';">
                        <div class="ms-3 overflow-hidden">
                            <h6 class="fw-bold mb-0 text-truncate" style="font-size: 15px;"><?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?></h6>
                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-dark' : 'bg-success bg-opacity-10 text-success border border-success' ?> mt-1 fw-normal" style="font-size: 10px;">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </div>
                    </div>

                    <ul class="nav flex-column sidebar-nav" id="myTab" role="tablist">
                        <li class="nav-item mb-2">
                            <a class="nav-link fw-bold text-dark d-flex align-items-center justify-content-between" data-bs-toggle="collapse" href="#menuAkun" role="button" aria-expanded="true">
                                <span class="text-capitalize">Profil Saya</span>
                                    <i class="bi bi-chevron-down" style="font-size: 12px;"></i>
                            </a>
                            <div class="collapse show" id="menuAkun">
                                <ul class="list-unstyled mb-0 ps-3">
                                    <li><a href="#profile" class="nav-link-sub active d-block py-2" data-bs-toggle="pill">Biodata Diri</a></li>
                                    <li><a href="#security" class="nav-link-sub d-block py-2" data-bs-toggle="pill">Keamanan</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link fw-bold text-dark" href="<?= BASE_URL ?>pelanggan/keranjang">
                                <span class="text-capitalize">Keranjang</span>
                                <!--<?php if ($cart_item_count > 0): ?>
                                    <span class="badge bg-dark rounded-pill"><?= $cart_item_count ?></span>

                                <?php endif; ?>-->
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link fw-bold text-dark" data-bs-toggle="pill" href="#orders">
                                <span class="text-capitalize">Pesanan</span>
                            </a>
                        </li>
                        <li class="nav-item mb-1">
                            <a class="nav-link fw-bold text-dark" data-bs-toggle="pill" href="#my-reviews">
                                <span class="text-capitalize">Ulasan</span>
                            </a>
                        </li>

                        <li class="nav-item mt-4 border-top pt-3">
                            <a class="nav-link text-danger fw-bold text-capitalize" href="<?= BASE_URL ?>auth/logout">logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-9 col-md-8">
            <div class="tab-content border-0" id="mainTabContent">
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3 p-4">
                        
                        <div class="d-flex align-items-center pb-3 mb-4 custom-tabs">
                            <h5 class="fw-bold mb-0 text-dark">Biodata Diri</h5>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-4 text-center px-4 border-end-md">
                                <form action="" method="POST" enctype="multipart/form-data" id="formFotoProfil">
                                    <div class="border rounded-3 p-2 mb-3 shadow-sm mx-auto" style="aspect-ratio: 1/1; max-width: 200px; overflow: hidden;">
                                        <?php
                                            $foto_profil_src = BASE_URL . 'public/storage/profil/' . ($u['foto_profil'] ?? '');
                                            $fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($u['nama'] ?? 'User') . '&background=random&size=300';
                                        ?>
                                        <img src="<?= !empty($u['foto_profil']) ? $foto_profil_src : $fallback_avatar ?>" 
                                             class="img-fluid w-100 h-100 object-fit-cover rounded-2" 
                                             alt="Foto Profil" id="previewFotoProfil"
                                             onerror="this.onerror=null; this.src='<?= $fallback_avatar ?>';">
                                    </div>
                                    <input type="file" name="foto_profil" id="inputFotoProfil" class="d-none" accept="image/jpeg, image/png">
                                    <input type="hidden" name="update_foto" value="1">
                                    <button type="button" id="btnPilihFoto" class="btn border fw-bold w-100 py-2 mb-2 text-secondary shadow-sm" style="font-size: 14px;">Pilih Foto</button>
                                    <button type="submit" id="btnSimpanFoto" class="btn btn-success fw-bold w-100 py-2 mb-2 d-none" style="font-size: 14px;">Simpan Foto</button>
                                    <?php if (!empty($u['foto_profil'])): ?>
                                        <button type="submit" name="hapus_foto" class="btn btn-outline-danger fw-bold w-100 py-2 mb-2" style="font-size: 14px;" onclick="return confirm('Anda yakin ingin menghapus foto profil?')">Hapus Foto</button>
                                    <?php endif; ?>
                                    <p class="text-muted small mb-0" style="font-size: 11px; line-height: 1.4;">Besar file: maksimum 2 MB. Ekstensi: .JPG .JPEG .PNG</p>
                                </form>
                            </div>

                            <div class="col-md-8 ps-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0" style="font-size: 15px;">Informasi Pribadi</h6>
                                    <button class="btn text-success fw-bold p-0 text-decoration-none shadow-none text-edit-hover" data-bs-toggle="modal" data-bs-target="#modalEditProfil">
                                        <i class="bi bi-pencil-square me-1"></i> Ubah Biodata
                                    </button>
                                </div>
                                
                                <table class="table table-borderless table-sm mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted py-2" style="width: 35%; font-size: 14px;">Nama </td>
                                            <td class="py-2 text-dark" style="font-size: 14px;"><?= htmlspecialchars($u['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2" style="font-size: 14px;">Email</td>
                                            <td class="py-2 text-dark" style="font-size: 14px;">
                                                <?= htmlspecialchars($u['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success ms-2 fw-normal" style="font-size: 10px;">Terverifikasi</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2" style="font-size: 14px;">Nomor HP / WA</td>
                                            <td class="py-2 text-dark" style="font-size: 14px;">
                                                <?= htmlspecialchars(!empty($u['no_hp']) ? $u['no_hp'] : 'Belum ditambahkan', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2 align-top" style="font-size: 14px;">Alamat </td>
                                            <td class="py-2 text-dark" style="font-size: 14px;">
                                                <?= nl2br(htmlspecialchars(!empty($u['alamat']) ? $u['alamat'] : 'Belum ada alamat tersimpan.', ENT_QUOTES, 'UTF-8')) ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3 p-4 min-vh-50">
                        <div class="pb-3 mb-4 custom-tabs">
                            <h5 class="fw-bold mb-0 text-dark">Keamanan Akun</h5>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-7">
                                <form action="" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-bold" style="font-size: 13px;">Password Baru</label>
                                        <input type="password" name="new_pass" class="form-control" required placeholder="Minimal 8 karakter">
                                        <small class="text-muted" style="font-size: 11px;">Harus kombinasi huruf besar dan angka.</small>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label text-muted fw-bold" style="font-size: 13px;">Ulangi Password Baru</label>
                                        <input type="password" name="confirm_pass" class="form-control" required>
                                    </div>
                                    <button type="submit" name="update_pass" class="btn btn-success fw-bold px-4 py-2">Simpan Password</button>
                                </form>
                            </div>
                            <div class="col-md-5 mt-4 mt-md-0">
                                <div class="bg-light p-3 rounded-3 border">
                                    <h6 class="fw-bold text-dark" style="font-size: 13px;"><i class="bi bi-shield-check text-success me-2"></i>Tips Keamanan</h6>
                                    <p class="text-muted mb-0" style="font-size: 12px; line-height: 1.5;">Gunakan kombinasi huruf kapital, angka, dan simbol. Jangan berikan password Anda kepada siapapun, pihak ThriftKing888 tidak pernah menanyakan password Anda.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="orders" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3 p-4 min-vh-50">
                        <div class="pb-3 mb-4 custom-tabs">
                            <h5 class="fw-bold mb-0 text-dark">Daftar Pesanan</h5>
                        </div>

                        <?php if (!empty($orders)) : ?>
                            <?php foreach ($orders as $order) : ?>
                                <div class="card border rounded-3 mb-4 shadow-none">
                                    <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-bag-check text-success me-2"></i>
                                            <span class="fw-bold text-dark" style="font-size: 13px;"><?= formatDate($order['created_at']) ?></span>
                                            <span class="text-muted mx-2">|</span>
                                            <span class="text-muted" style="font-size: 13px;"><?= $order['invoice_code'] ?></span>
                                        </div>
                                        <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($order['status_order']) {
                                                case 'unpaid': $status_class = 'bg-warning text-dark'; $status_text = 'Belum Bayar'; break;
                                                case 'pending_confirmation': $status_class = 'bg-info text-white'; $status_text = 'Menunggu Konfirmasi'; break;
                                                case 'processing': $status_class = 'bg-primary'; $status_text = 'Diproses'; break;
                                                case 'shipped': $status_class = 'bg-primary'; $status_text = 'Dikirim'; break;
                                                case 'completed': $status_class = 'bg-success'; $status_text = 'Selesai'; break;
                                                case 'cancelled': $status_class = 'bg-danger'; $status_text = 'Dibatalkan'; break;
                                                default: $status_class = 'bg-secondary'; $status_text = ucfirst($order['status_order']); break;
                                            }
                                        ?>
                                        <span class="badge <?= $status_class ?> px-2 py-1" style="font-size: 11px;"><?= $status_text ?></span>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-8 d-flex">
                                                <?php $img_path = !empty($order['gambar_produk']) ? BASE_URL . 'assets/img/products/' . htmlspecialchars($order['gambar_produk']) : BASE_URL . 'assets/img/no-image.png'; ?>
                                                <img src="<?= $img_path ?>" class="rounded-2 border object-fit-cover" width="60" height="60">
                                                <div class="ms-3">
                                                    <h6 class="fw-bold mb-1 text-dark" style="font-size: 14px;"><?= htmlspecialchars($order['produk_pertama'], ENT_QUOTES, 'UTF-8') ?></h6>
                                                    <?php if ($order['total_item'] > 1): ?>
                                                        <span class="text-muted" style="font-size: 12px;">+<?= ($order['total_item']-1) ?> produk lainnya</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-md-end mt-3 mt-md-0 border-start-md">
                                                <span class="text-muted d-block" style="font-size: 12px;">Total Belanja</span>
                                                <h6 class="fw-bold mb-0 text-dark"><?= formatRupiah($order['total_pembayaran']) ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light border-top py-2 text-end">
                                        <a href="<?= BASE_URL ?>pelanggan/pesanan/<?= $order['id'] ?>" class="btn btn-outline-dark fw-bold py-1 px-3" style="font-size: 12px;">Detail Transaksi</a>
                                        <?php if ($order['status_order'] === 'completed'): ?>
                                            <?php
                                                // Jika item > 1, arahkan ke list. Jika 1, langsung ke ulasan produk.
                                                $review_url = ($order['total_item'] > 1)
                                                    ? BASE_URL . 'pelanggan/menunggu-ulasan'
                                                    : BASE_URL . 'pelanggan/ulasan/' . intval($order['product_id'] ?? 0);
                                            ?>
                                            <?php if (!empty($order['product_id'])): // Hanya tampilkan jika product_id ada ?>
                                                <a href="<?= $review_url ?>" class="btn btn-dark fw-bold py-1 px-3 ms-2" style="font-size: 12px;">Beri Ulasan</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="text-center py-5 my-auto">
                                <img src="<?= BASE_URL ?>assets/img/empty-cart.png" alt="Empty" width="120" class="mb-3 opacity-50" onerror="this.style.display='none'">
                                <h6 class="text-dark fw-bold">Belum ada pesanan</h6>
                                <p class="text-muted small">Yuk, mulai belanja dan temukan barang vintage impianmu!</p>
                                <a href="<?= BASE_URL ?>" class="btn btn-success fw-bold px-4 py-2 mt-2">Mulai Belanja</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-pane fade" id="my-reviews" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3 p-4 min-vh-50">
                        <div class="pb-3 mb-4 custom-tabs">
                            <h5 class="fw-bold mb-0 text-dark">Ulasan Saya</h5>
                        </div>

                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $rev): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= BASE_URL ?>assets/img/products/<?= htmlspecialchars($rev['gambar_utama'] ?? 'no-image.png') ?>" width="40" height="40" class="rounded border object-fit-cover me-3">
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark" style="font-size: 13px;"><?= htmlspecialchars($rev['nama_produk']) ?></h6>
                                                <div class="text-warning mt-1" style="font-size: 11px;">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?= $i <= $rev['rating'] ? '' : 'text-muted opacity-25' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="text-muted ms-2"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-light p-3 rounded-2">
                                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 13px;"><?= htmlspecialchars($rev['judul']) ?></h6>
                                        <p class="text-muted mb-0" style="font-size: 13px;"><?= nl2br(htmlspecialchars($rev['isi'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-chat-left-text fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="text-dark fw-bold">Belum ada ulasan</h6>
                                <p class="text-muted small">Anda belum memberikan ulasan untuk produk apapun.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditProfil" tabindex="-1" aria-labelledby="modalEditProfilLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalEditProfilLabel">Ubah Biodata Diri</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 13px;">Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 13px;">Nomor HP / WhatsApp</label>
                        <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($u['no_hp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: 08123456789">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted fw-bold" style="font-size: 13px;">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="3" placeholder="Tuliskan alamat lengkap..."><?= htmlspecialchars($u['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="update_profil" class="btn btn-success fw-bold py-2">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Global fixes */
    body { background-color: #f3f4f5; }
    .min-vh-50 { min-height: 50vh; }
    .form-control:focus { border-color: #000; box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1); }
    
    /* Tombol Aksi dengan Tema Monokrom (Hitam & Putih) */
    .btn-success { background-color: #000 !important; border-color: #000 !important; color: white !important; }
    .btn-success:hover { background-color: #222 !important; border-color: #222 !important; }
    .btn-outline-success { color: #000 !important; border-color: #000 !important; }
    .btn-outline-success:hover { background-color: #000 !important; color: white !important; }
    .text-success { color: #000 !important; }
    .text-edit-hover:hover { text-decoration: underline !important; color: #333 !important; }

    /* Navigasi Sidebar Kiri */
    .sidebar-nav .nav-link { padding: 10px 15px; border-radius: 8px; font-size: 14px; transition: 0.2s; }
    .sidebar-nav .nav-link:hover { background-color: #f8f9fa; }
    .sidebar-nav .nav-link.active { background-color: #f3f4f5; color: #000 !important; }
    .sidebar-nav .nav-link.active i { color: #000 !important; }
    
    /* Sub-menu sidebar (Biodata & Keamanan) */
    .nav-link-sub { color: #6d7588; text-decoration: none; font-size: 13px; font-weight: 500; transition: color 0.2s; }
    .nav-link-sub:hover { color: #000; }
    .nav-link-sub.active { color: #000; font-weight: 700; }

    /* Title Tabs Atas (Garis bawah ) */
    .custom-tabs { border-bottom: 1px solid #e5e7e9; }
    .custom-tabs h5 { position: relative; padding-bottom: 10px; }
    .custom-tabs h5::after {
        content: ''; position: absolute; left: 0; bottom: -1px;
        width: 100%; height: 3px; background-color: #03ac0e; border-radius: 4px 4px 0 0;
        background-color: #000; /* [REVISI] Warna garis bawah */
    }

    /* Helper Border Desktop */
    @media (min-width: 768px) {
        .border-end-md { border-right: 1px solid #e5e7e9 !important; }
        .border-start-md { border-left: 1px solid #e5e7e9 !important; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnPilihFoto = document.getElementById('btnPilihFoto');
    const btnSimpanFoto = document.getElementById('btnSimpanFoto');
    const inputFotoProfil = document.getElementById('inputFotoProfil');
    const previewFotoProfil = document.getElementById('previewFotoProfil');

    if (btnPilihFoto && inputFotoProfil) {
        btnPilihFoto.addEventListener('click', function() {
            inputFotoProfil.click();
        });
    }

    if (inputFotoProfil && previewFotoProfil) {
        inputFotoProfil.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                // Tampilkan preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewFotoProfil.src = e.target.result;
                }
                reader.readAsDataURL(file);

                // Tukar tombol
                btnPilihFoto.classList.add('d-none');
                btnSimpanFoto.classList.remove('d-none');
            }
        });
    }
});
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>