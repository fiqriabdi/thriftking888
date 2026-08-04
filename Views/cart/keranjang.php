<?php
/**
 * File: cart/keranjang.php
 * Halaman daftar keranjang belanja - ThriftKing888
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('pelanggan');
require_once APP_ROOT . '/Controllers/Customer/KeranjangController.php';
require_once APP_ROOT . '/helpers/Format.php'; // INTEGRASI: Ambil fungsi formatRupiah()

$db = Database::getConnection();
$cartCtrl = new KeranjangController($db);
$userId = $_SESSION['user']['id'] ?? null;

$pageTitle = 'Keranjang - ThriftKing888';

// --- PROSES REQUEST VIA CONTROLLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'hapus_item') {
        echo json_encode($cartCtrl->ajaxRemoveItem($userId, intval($_POST['variant_id'])));
        exit;
    }
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'bersihkan_keranjang') {
        echo json_encode($cartCtrl->ajaxClearCart($userId));
        exit;
    }
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_qty') {
        echo json_encode($cartCtrl->ajaxUpdateQty($userId, intval($_POST['variant_id']), intval($_POST['qty'])));
        exit;
    }
}

// Tangani hapus/bersihkan via URL (Non-AJAX Fallback)
if (isset($_GET['tambah']) || isset($_GET['hapus']) || isset($_GET['bersihkan'])) {
    $cartCtrl->handleRequest($userId, $_GET);
    header('Location: ' . BASE_URL . 'pelanggan/keranjang');
    exit();
}

// Ambil data keranjang untuk ditampilkan
$keranjang = $cartCtrl->index($userId);
if (!is_array($keranjang)) $keranjang = [];

$total_belanja = 0;
?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .text-tenor { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    
    /* UI Vault Minimalis Studio */
    .card { border: 1px solid #e5e5e5; border-radius: 0px !important; box-shadow: none !important; }
    .btn { border-radius: 0px !important; letter-spacing: 1px; font-size: 11px; transition: 0.2s ease; text-transform: uppercase; }
    .badge { border-radius: 0px !important; font-size: 9px; font-weight: 600; letter-spacing: 0.5px; padding: 5px 8px; text-transform: uppercase; }
    
    /* Kustomisasi Tabel Keranjang */
    .table th { font-size: 11px; letter-spacing: 1px; font-weight: 600; border-bottom: 1px solid #e5e5e5; padding: 15px 10px; }
    .table td { border-bottom: 1px solid #e5e5e5; padding: 20px 10px; vertical-align: middle; }
    
    .summary-box { background-color: #fafafa; border: 1px solid #e5e5e5; z-index: 10; }
    /* Hanya sticky pada layar Desktop (Large) ke atas */
    @media (min-width: 992px) {
        .summary-box { position: sticky; top: 120px; }
    }
    .quantity-badge { border-radius: 0px !important; font-size: 13px; background-color: #fff; border: 1px solid #e5e5e5; min-width: 40px; text-center: center; }
    .quantity-control { display: flex; align-items: center; border: 1px solid #e5e5e5; width: fit-content; margin: 0 auto; }
    .qty-btn { background: #fff; border: none; padding: 5px 10px; cursor: pointer; font-weight: bold; }
    .qty-input { width: 35px; border: none; text-align: center; font-size: 12px; font-weight: 600; outline: none; }
    
    /* Utility kustom pencegah kebocoran lebar flex / teks bertumpuk */
    .min-w-0 { min-width: 0 !important; }
</style>

<div class="container mt-5 mb-5" style="max-width: 1150px;">
    <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-3">
        <div>
            <h4 class="fw-bold text-capitalize text-tenor mb-1" style="font-size: 20px;">Keranjang</h4>
            <p class="text-muted small mb-0">Halo <strong><?= htmlspecialchars($_SESSION['user']['nama'], ENT_QUOTES, 'UTF-8') ?></strong>, periksa kembali daftar item pilihanmu.</p>
        </div>
        <?php if (!empty($keranjang)) : ?>
            <button type="button" class="btn btn-link text-danger small text-decoration-none fw-bold text-capitalize p-0 border-0" style="font-size: 11px; letter-spacing: 0.5px;" onclick="bersihkanKeranjang()">
                <i class="bi bi-trash me-1"></i> Bersihkan Keranjang
            </button>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <?php if (!empty($keranjang)) : ?>
                <div class="card p-2">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-muted text-capitalize">
                                    <th class="ps-3" style="width: 45%;">Item Detail</th>
                                    <th style="width: 18%;">Harga</th>
                                    <th class="text-center" style="width: 12%;">Qty</th>
                                    <th style="width: 18%;">Total</th>
                                    <th class="text-center" style="width: 7%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($keranjang as $id => $item) : 
                                    $subtotal = (int)$item['harga_jual'] * (int)$item['jumlah'];
                                    $is_available = (int)$item['stok'] > 0;
                                    if ($is_available) $total_belanja += $subtotal;
                                ?>
                                <tr id="row-item-<?= $id ?>" class="<?= !$is_available ? 'opacity-50' : '' ?>">
                                    <td class="ps-3 position-relative">
                                        <div class="d-flex align-items-center min-w-0 w-100">
                                            <?php $img = !empty($item['gambar_utama']) ? htmlspecialchars($item['gambar_utama'], ENT_QUOTES, 'UTF-8') : 'placeholder.png'; ?>
                                            <img src="<?= BASE_URL ?>assets/img/products/<?= $img ?>" width="75" height="75" class="rounded-0 border flex-shrink-0" style="object-fit: cover;" alt="<?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';">
                                            
                                            <div class="ms-3 min-w-0 flex-grow-1">
                                                <h6 class="mb-1 fw-bold text-uppercase text-truncate" style="font-size: 13px; letter-spacing: 0.5px; max-width: 240px;" title="<?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                                                </h6>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['varian_ukuran'] . (!empty($item['varian_warna']) ? ' / ' . $item['varian_warna'] : ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!$is_available) : ?>
                                                    <div class="text-danger fw-bold mt-1" style="font-size: 10px; letter-spacing: 1px;">SUDAH TERJUAL</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-muted small fw-medium"><?= formatRupiah($item['harga_jual']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border fw-bold" style="font-size: 12px;">
                                            1x
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark small" id="subtotal-<?= $id ?>"><?= formatRupiah($item['harga_jual'] * $item['jumlah']) ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="hapusItem(<?= $id ?>)" title="Hapus Item">
                                            <i class="bi bi-x-lg" style="font-size: 14px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="<?= BASE_URL ?>katalog" class="text-dark text-decoration-none fw-bold text-capitalize" style="font-size: 11px; letter-spacing: 1px;">
                        <i class="bi bi-chevron-left me-1"></i> Kembali ke Katalog
                    </a>
                </div>
                
            <?php else : ?>
                <div class="text-center py-5 bg-white border border-dashed rounded-0">
                    <div class="mb-3">
                        <i class="bi bi-cart-x text-muted opacity-50" style="font-size: 48px;"></i>
                    </div>
                    <h5 class="fw-bold text-capitalize mb-2" style="font-size: 13px; letter-spacing: 1px;">Keranjang Kosong</h5>
                    <p class="text-muted small mb-4">Sepertinya kamu belum menambahkan item ke dalam tas belanja.</p>
                    <a href="<?= BASE_URL ?>katalog" class="btn btn-dark px-5 py-2.5 fw-bold text-capitalize">Mulai Jelajah</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card summary-box p-4">
                <h6 class="fw-bold text-capitalize text-tenor mb-4" style="font-size: 14px;">Ringkasan Belanja</h6>
                
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-muted small text-capitalize" style="letter-spacing: 0.5px;">Total Item (<span id="summary-unique-count"><?= count($keranjang) ?></span>)</span>
                    <span class="small fw-bold text-dark item-total-price"><?= formatRupiah($total_belanja) ?></span>
                </div>
                
                <div class="d-flex justify-content-between mb-4">
                    <span class="text-muted small text-capitalize" style="letter-spacing: 0.5px;">Estimasi Pengiriman</span>
                    <span class="text-muted small fw-bold text-capitalize" style="letter-spacing: 0.5px;">Dihitung saat Checkout</span>
                </div>

                <div class="p-3 bg-white border mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold small text-capitalize text-muted" style="letter-spacing: 0.5px;">Total</span>
                        <span class="fw-bold text-black small text-capitalize" id="summary-total" style="font-size: 18px;"> <?= formatRupiah($total_belanja) ?></span>
                    </div>
                </div>
                
                <?php if (!empty($keranjang)) : ?>
                    <a href="<?= BASE_URL ?>pelanggan/checkout" class="btn btn-dark w-100 py-3 fw-bold text-capitalize <?= ($total_belanja <= 0) ? 'disabled' : '' ?>" style="letter-spacing: 2px; font-size: 12px;">Lanjut ke Checkout</a>
                <?php else : ?>
                    <button type="button" class="btn btn-dark w-100 py-3 fw-bold text-capitalize disabled" style="letter-spacing: 2px; font-size: 12px;">Lanjut ke Checkout</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function hapusItem(id) {
    const formData = new FormData();
    formData.append('ajax_action', 'hapus_item');
    formData.append('variant_id', id);

    fetch('<?= BASE_URL ?>pelanggan/keranjang', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // --- PERBAIKAN UX: Hapus elemen dari DOM secara dinamis ---
            const itemRow = document.getElementById('row-item-' + id);
            if (itemRow) {
                itemRow.style.transition = 'opacity 0.3s ease';
                itemRow.style.opacity = '0';
                setTimeout(() => {
                    itemRow.remove();
                    updateCartSummary(data.cart_summary); // Update ringkasan
                    // Cek jika keranjang menjadi kosong
                    if (document.querySelectorAll('tbody tr').length === 0) {
                        window.location.reload(); // Reload untuk menampilkan pesan keranjang kosong
                    }
                }, 300);
            } else {
                window.location.reload(); // Fallback jika elemen tidak ditemukan
            }
        } else {
            alert(data.message || 'Gagal menghapus item.');
        }
    })
    .catch(() => alert('Terjadi kesalahan koneksi saat menghapus item.'));
}

function bersihkanKeranjang() {
    if (!confirm('Apakah Anda yakin ingin menghapus semua item dari keranjang?')) return;

    const formData = new FormData();
    formData.append('ajax_action', 'bersihkan_keranjang');

    fetch('<?= BASE_URL ?>pelanggan/keranjang', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Gagal membersihkan keranjang.');
        }
    })
    .catch(() => alert('Terjadi kesalahan saat membersihkan keranjang.'));
}

// --- FUNGSI HELPER BARU ---
function updateCartSummary(summary) {
    if (!summary) return;

    const formatRupiah = (number) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
    }

    const summaryUniqueCount = document.getElementById('summary-unique-count');
    const itemTotalPrice = document.querySelector('.item-total-price');
    const summaryTotal = document.getElementById('summary-total');
    const checkoutButton = document.querySelector('a[href*="checkout"]');

    if (summaryUniqueCount) summaryUniqueCount.innerText = summary.unique_items_count;
    if (itemTotalPrice) itemTotalPrice.innerText = formatRupiah(summary.total_belanja);
    if (summaryTotal) summaryTotal.innerText = formatRupiah(summary.total_belanja);

    if (checkoutButton) {
        summary.total_belanja > 0 ? checkoutButton.classList.remove('disabled') : checkoutButton.classList.add('disabled');
    }
}
</script>
