<?php
/**
 * File: pelanggan/checkout.php
 * Halaman proses checkout pesanan - ThriftKing888
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('pelanggan');

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Controllers/Customer/checkoutcontroller.php';
require_once APP_ROOT . '/Controllers/Admin/usercontroller.php';
require_once APP_ROOT . '/helpers/Security.php';
require_once APP_ROOT . '/helpers/Format.php'; // INTEGRASI: Ambil fungsi formatRupiah()

$checkoutCtrl = new checkoutcontroller($conn);

// Generate CSRF token untuk form
$csrf_token = generateCSRFToken();

// Ambil data profil pelanggan untuk pre-fill form checkout
$user_ctrl = new usercontroller($conn);
$u = $user_ctrl->show($_SESSION['user']['id']);

// --- LOGIKA ANTI-TENDANG: CEK BELI LANGSUNG ATAU KERANJANG ---
$keranjang = [];

if (isset($_GET['beli_langsung'])) {
    // Jika lewat tombol "Beli Langsung" dari detail.php
    $id_produk_variant = intval($_GET['beli_langsung']); // Mengasumsikan ini adalah ID varian produk
    $stmt = mysqli_prepare($conn, "SELECT pv.id as variant_id, pv.harga_jual, pv.stok, p.nama_produk, pi.nama_foto as gambar
                                   FROM product_variants pv
                                   JOIN products p ON pv.product_id = p.id
                                   LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                                   WHERE pv.id = ? AND pv.stok > 0");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id_produk_variant);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $p = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    } else {
        $p = null;
    }
    if ($p) {
        // Buat struktur keranjang sementara (hanya untuk halaman ini)
        $keranjang[$p['variant_id']] = [ // Gunakan variant_id sebagai kunci
            'nama_produk' => $p['nama_produk'],
            'harga_jual' => $p['harga_jual'],
            'jumlah' => 1,
            'gambar' => $p['gambar'] ?? 'default.jpg' // Tambahkan gambar
        ];
    }
} elseif (isset($_SESSION['keranjang']) && !empty($_SESSION['keranjang'])) {
    // Jika belanja normal lewat keranjang
    $keranjang = $_SESSION['keranjang'];
}

// VALIDASI AKHIR: Jika benar-benar kosong, baru balikkan ke katalog
if (empty($keranjang)) {
    header('Location: ' . BASE_URL . 'katalog');
    exit();
}

// Hitung Subtotal Produk
$total_produk = 0;
foreach($keranjang as $item) { 
    $total_produk += ($item['harga_jual'] * $item['jumlah']); 
}

$data_ongkir = $checkoutCtrl->getShippingData();

// PROSES POST: SAAT KLIK PLACE ORDER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $checkoutCtrl->processCheckout($_SESSION['user']['id'], $_POST, $keranjang, $data_ongkir);

    if ($result['success']) {
        // [REVISI] Jangan hanya unset session, tapi bersihkan dari database juga.
        if (!isset($_GET['beli_langsung'])) {
            // Panggil controller keranjang untuk membersihkan data di session dan database.
            require_once APP_ROOT . '/Controllers/Customer/KeranjangController.php';
            $keranjangCtrlForClear = new KeranjangController($conn);
            $keranjangCtrlForClear->ajaxClearCart($_SESSION['user']['id']);
        }
        $_SESSION['success_msg'] = "Pesanan berhasil dibuat!";
        header('Location: ' . BASE_URL . 'pelanggan/pembayaran/' . $result['order_id']);
        exit();
    } else {
        $checkout_error = $result['message'];
    }
}

$pageTitle = 'Checkout - ThriftKing888';

require_once APP_ROOT . '/Views/layouts/header.php'; 
require_once APP_ROOT . '/Views/layouts/navbar.php'; 
?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .text-tenor { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    
    /* Override Gaya Form & Card menjadi Vault Minimalis (Tanpa Shadow/Lengkungan) */
    .checkout-card { border-radius: 0px !important; border: 1px solid #e5e5e5; box-shadow: none !important; }
    .form-control, .form-select { border-radius: 0px !important; border-color: #dcdcdc; padding: 12px; font-size: 0.9rem; color: #111; }
    .form-control:focus, .form-select:focus { border-color: #000; box-shadow: none; }
    .summary-box { background-color: #fafafa; border: 1px solid #e5e5e5; }
    /* Hanya sticky pada layar Tablet (Medium) ke atas */
    @media (min-width: 768px) {
        .summary-box { position: sticky; top: 120px; }
    }
    
    /* Utility kustom pencegah text overflow hantaman flex */
    .min-w-0 { min-width: 0 !important; }
</style>

<div class="container mt-5 mb-5" style="max-width: 1100px;">
    <div class="row g-5">
        <!-- Kolom Kiri: Formulir Pengiriman -->
        <div class="col-md-7">
            <h4 class="text-capitalize fw-bold text-tenor mb-4" style="font-size: 18px;">Checkout</h4>
            <h4 class="text-capitalize fw-bold text-tenor mb-4" style="font-size: 18px;">Informasi Pengiriman</h4>
            
            <?php if (isset($checkout_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-0 small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($checkout_error, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card checkout-card p-4">
                <form action="" method="POST" id="checkout_form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="use_saved_address" checked>
                        <label class="form-check-label small fw-bold text-capitalize" for="use_saved_address" style="letter-spacing: 0.5px; cursor: pointer;">
                            Gunakan Alamat dari Profil
                        </label>
                    </div>

                    <div class="mb-4">
                        <label class="small fw-bold text-muted text-capitalize mb-2" style="letter-spacing: 0.5px;">Nama Penerima</label>
                        <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" value="<?= htmlspecialchars($u['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="small fw-bold text-muted text-capitalize mb-2" style="letter-spacing: 0.5px;">Nomor Telepon Penerima</label>
                        <input type="text" name="no_hp" class="form-control" placeholder="Contoh: 08123456789" value="<?= htmlspecialchars($u['no_hp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="small fw-bold text-muted text-capitalize mb-2" style="letter-spacing: 0.5px;">Kota / Wilayah Tujuan</label>
                        <p class="small text-muted" style="font-size: 0.75rem; margin-top: -5px;">Biaya ongkos kirim disesuaikan berdasarkan estimasi jarak dan wilayah tujuan.</p>
                        <select name="kota" id="kota" class="form-select" required onchange="hitungTotal()">
                            <option value="" data-ongkir="0">Pilih</option>
                            <?php if (!empty($data_ongkir)): ?>
                                <?php foreach ($data_ongkir as $grup => $cities): ?>
                                    <optgroup label="<?= htmlspecialchars(str_replace('_', ' ', $grup)) ?>">
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?= htmlspecialchars($city['kota']) ?>" data-ongkir="<?= $city['biaya'] ?>">
                                                <?= htmlspecialchars($city['kota']) ?> (<?= formatRupiah($city['biaya']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="small fw-bold text-muted text-capitalize mb-2" style="letter-spacing: 0.5px;">Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="4" placeholder="Nama jalan, nomor rumah, RT/RW, kecamatan, dan kode pos" required><?= htmlspecialchars($u['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

    <button type="submit" id="btn_submit_order" class="btn btn-dark w-100 py-3 rounded-0 text-capitalize fw-bold" style="letter-spacing: 2px; font-size: 12px;">
        Buat Pesanan
    </button>
</form>
            </div>
        </div>
        
        <!-- Kolom Kanan: Ringkasan Belanja Antarmuka Fleksibel Anti-Tabrakan -->
        <div class="col-md-5">
           
            <h3 class="text-capitalize fw-bold text-tenor mb-4" style="font-size: 18px;">Rincian Pembayaran</h3>
            <div class="card checkout-card summary-box p-4">
                <div class="order-items mb-4">
                    <?php foreach($keranjang as $item): ?>
                        <!-- PERBAIKAN: Layout flexbox dikunci menggunakan align-items-start, gap-3, dan min-w-0 -->
                        <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
                            <div class="min-w-0 flex-grow-1">
                                <h6 class="mb-1 fw-bold small text-capitalize text-truncate" style="letter-spacing: 0.5px;">
                                    <?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                                </h6>
                                <small class="text-muted d-block">Jumlah: <?= $item['jumlah'] ?>x</small>
                            </div>
                            <!-- Sisi kanan terkunci kukuh agar nominal harga tidak pernah tergeser turun/bertabrakan -->
                            <span class="small fw-bold text-nowrap flex-shrink-0 text-end">
                                <?= formatRupiah($item['harga_jual'] * $item['jumlah']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <hr style="border-color: #e5e5e5;">
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small text-capitalize" style="letter-spacing: 0.5px;">Subtotal Produk</span>
                    <span class="small fw-bold"><?= formatRupiah($total_produk) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small text-capitalize" style="letter-spacing: 0.5px;">Subtotal Pengiriman</span>
                    <span class="small fw-bold text-dark" id="tampilan_ongkir">Rp 0</span>
                </div>

                <hr style="border-color: #e5e5e5;">
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <h6 class="text-capitalize fw-bold mb-0" style="letter-spacing: 1px; font-size: 14px;">Total Pembayaran</h6>
                    <h5 class="fw-bold mb-0 text-black" id="total_akhir" style="font-size: 18px;">
                        <?= formatRupiah($total_produk) ?>
                    </h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Sinkronisasi Dinamis Sisi Klien -->
<script>
// Simpan data profil ke dalam object JavaScript
const savedProfileData = <?= json_encode([
    'nama' => $u['nama'] ?? '',
    'no_hp' => $u['no_hp'] ?? '',
    'alamat' => $u['alamat'] ?? '',
    'kota' => $u['city_name'] ?? ''
]) ?>;

document.getElementById('use_saved_address').addEventListener('change', function() {
    const namaInput = document.querySelector('input[name="nama"]');
    const noHpInput = document.querySelector('input[name="no_hp"]');
    const alamatInput = document.querySelector('textarea[name="alamat"]');
    const kotaSelect = document.getElementById('kota');

    if (this.checked) {
        namaInput.value = savedProfileData.nama;
        noHpInput.value = savedProfileData.no_hp;
        alamatInput.value = savedProfileData.alamat;
        if (savedProfileData.kota) {
            kotaSelect.value = savedProfileData.kota;
            hitungTotal();
        }
    } else {
        // Kosongkan kolom jika ingin input alamat baru
        namaInput.value = '';
        noHpInput.value = '';
        alamatInput.value = '';
        kotaSelect.value = '';
        hitungTotal();
        namaInput.focus();
    }
});

function hitungTotal() {
    const selectKota = document.getElementById('kota');
    const ongkir = parseInt(selectKota.options[selectKota.selectedIndex].getAttribute('data-ongkir')) || 0;
    const totalProduk = <?= $total_produk ?>;
    
    // Set format tampilan mata uang lokal Indonesia secara dinamis
    document.getElementById('tampilan_ongkir').innerText = "Rp " + ongkir.toLocaleString('id-ID');
    
    const grandTotal = (totalProduk) + ongkir;
    document.getElementById('total_akhir').innerText = "Rp " + grandTotal.toLocaleString('id-ID');
}

// Cegah Double Submission
document.querySelector('form').addEventListener('submit', function(e) {
    const btn = document.getElementById('btn_submit_order');
    if (btn.disabled) return;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
});

// Inisialisasi awal jika ada voucher dari post-back
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('kota').value) hitungTotal();
});
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>