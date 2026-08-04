<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('pelanggan');
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/helpers/Security.php';
require_once APP_ROOT . '/helpers/Format.php'; // FIX: Tambahkan helper format untuk formatRupiah()
require_once APP_ROOT . '/Models/notification.php';
require_once APP_ROOT . '/Controllers/Customer/PembayaranController.php'; // [REFAKTOR] Panggil Controller

$db = Database::getConnection();

// 1. Ambil ID dari URL
$id_trx = intval($id ?? $_GET['id'] ?? 0); // Sempurnakan: Dukung ID dari Router

if ($id_trx <= 0) {
    header('Location: ' . BASE_URL . 'pelanggan/pesanan');
    exit();
}

// 2. Ambil data transaksi milik pelanggan yang login
$user_id = intval($_SESSION['user']['id']);
$stmt = mysqli_prepare($db, "SELECT * FROM orders WHERE id = ? AND user_id = ?"); // Menggunakan tabel 'orders'
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $id_trx, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $trx = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    $trx = null;
}

// 3. PROTEKSI: Cek apakah transaksi ada dan statusnya benar
if (!$trx) {
    header('Location: ' . BASE_URL . 'pelanggan/pesanan');
    exit();
}

// Jika status sudah bukan 'menunggu_pembayaran', jangan izinkan upload lagi
if ($trx['status_order'] !== 'unpaid') {
    header('Location: ' . BASE_URL . 'pelanggan/pesanan'); 
    exit();
}

$pageTitle = 'Pembayaran - ThriftKing888';
$msg = '';
$msg_type = ''; // 'success' or 'error'

// 3.5 [REFAKTOR] Logika ini tetap di View karena untuk menampilkan pilihan di form
$list_rekening = [];
$q_rek = mysqli_query($db, "SELECT * FROM bank_accounts WHERE is_active = 1");
if ($q_rek) $list_rekening = mysqli_fetch_all($q_rek, MYSQLI_ASSOC);

// 4. LOGIKA UPLOAD BUKTI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [REFAKTOR] Pindahkan logika ke Controller
    $pembayaranCtrl = new PembayaranController($db);
    $result = $pembayaranCtrl->processPayment($id_trx, $user_id, $_POST, $_FILES);
    
    if ($result['success']) {
        // Redirect ke halaman daftar pesanan setelah sukses
        header('Location: ' . BASE_URL . 'pelanggan/pesanan?status=payment_sent');
        exit();
    } else {
        $msg = $result['message'];
        $msg_type = 'error';
    } 
 } 
  ?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .text-tenor { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    .confirm-card { border-radius: 0px !important; border: 1px solid #e5e5e5; box-shadow: none !important; }
    .form-control, .form-select { border-radius: 0px !important; border-color: #dcdcdc; }
    .form-control:focus, .form-select:focus { border-color: #000; box-shadow: none; }
</style>

<div class="container mt-5 mb-5" style="max-width: 900px;">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card confirm-card">
                <div class="card-header bg-dark text-white text-center py-4 border-0">
                    <h5 class="mb-0 fw-bold text-tenor">Pembayaran</h5>
                    <small class="opacity-75">ORDER ID: #<?= htmlspecialchars($trx['invoice_code'], ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <div class="card-body p-4">
                    <?php if ($msg): ?>
                        <div class="alert alert-<?= $msg_type === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show rounded-0 small" role="alert">
                            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mb-4 bg-light py-4 border">
                        <p class="text-muted mb-1 small text-capitalize" style="letter-spacing: 1px;">Total Tagihan Anda</p>
                        <h2 class="fw-bold text-danger mb-0">
                            <?= formatRupiah($trx['total_pembayaran']) ?>
                        </h2>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-capitalize" style="letter-spacing: 0.5px;">Pilih Rekening Tujuan</label>
                            <select name="metode_bank" class="form-select" required>
                                <?php if (!empty($list_rekening)) : ?>
                                    <?php foreach ($list_rekening as $rek) : ?>
                                        <option value="<?= htmlspecialchars($rek['nama_bank']) ?>">
                                            <?= htmlspecialchars($rek['nama_bank'] . " - " . $rek['nomor_rekening'] . " (" . $rek['atas_nama'] . ")") ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <option value="BCA">BANK BCA - 8880912345</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-capitalize" style="letter-spacing: 0.5px;">Unggah Bukti Transfer</label>
                            <input type="file" name="bukti_bayar" class="form-control" required>
                            <div class="form-text small" style="font-size: 10px;">Gunakan format JPG, JPEG, atau PNG. Maksimal 5MB.</div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 fw-bold py-3 text-capitalize" style="letter-spacing: 2px;">
                            Konfirmasi
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>