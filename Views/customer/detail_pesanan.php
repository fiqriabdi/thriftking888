<?php
if (!defined('APP_ROOT')) require_once __DIR__ . '/../../Config/konstanta.php';

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireAnyRole(['pelanggan', 'admin']);

require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Models/transaksi.php';
require_once APP_ROOT . '/helpers/Format.php';

$db = Database::getConnection();
$transaksiModel = new transaksi($db);

// Variable $id didapatkan dari Router::dispatch via extract()
$order_id = intval($id ?? 0);
$user = $_SESSION['user'];

$order = $transaksiModel->getOrderById($order_id);

// Validasi: Pastikan pesanan ada. Jika bukan admin, harus milik user yang sedang login.
if (!$order || ($order['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Pesanan tidak ditemukan atau Anda tidak memiliki akses.</div></div>");
}

$items = $transaksiModel->getOrderItems($order_id);
$pageTitle = 'Detail Pesanan ' . $order['invoice_code'];

require_once APP_ROOT . '/Views/layouts/header.php';
require_once APP_ROOT . '/Views/layouts/navbar.php';
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .invoice-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee; }
    .invoice-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; margin-bottom: 30px; }
    .status-badge { font-size: 11px; padding: 5px 12px; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
    .info-label { color: #888; font-size: 11px; margin-bottom: 2px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
    .info-value { font-weight: 600; color: #333; font-size: 15px; }
    .table-invoice thead th { background: #fdfdfd; border-top: 1px solid #eee; font-size: 11px; color: #777; text-transform: uppercase; letter-spacing: 1px; }
    .product-sku-weight { color: #999; font-size: 12px; margin-top: 4px; line-height: 1.4; }
    .summary-row { font-size: 14px; margin-bottom: 8px; color: #555; }
    .total-bill { font-size: 20px; font-weight: 800; color: #000; border-top: 1px dashed #eee; padding-top: 15px; margin-top: 15px; }
    
    @media print {
        .d-print-none { display: none !important; }
        .invoice-container { box-shadow: none; border: none; padding: 0; }
    }
</style>

<div class="container py-5">
    <div class="invoice-container mx-auto" style="max-width: 850px;">
        <!-- Header Transaksi -->
        <div class="invoice-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1" style="letter-spacing: 2px; font-size: 1.25rem;">Detail Transaksi</h4>
            
                <!-- Invoice Code sebagai link ke halaman lain (misal halaman cetak atau detail itu sendiri) -->
                <span class="text-primary fw-bold" style="font-size: 15px;"><?= $order['invoice_code'] ?></span>
            </div>
            <div class="text-end">
                <?php
                    $status = $order['status_order'];
                    // Pemetaan Status ke Bahasa Indonesia (Hanya untuk Tampilan)
                    $status_map = [
                        'unpaid'               => 'Belum Bayar',
                        'pending_confirmation' => 'Menunggu Konfirmasi',
                        'processing'           => 'Diproses',
                        'shipped'              => 'Dikirim',
                        'completed'            => 'Selesai',
                        'cancelled'            => 'Dibatalkan'
                    ];
                    $status_label = $status_map[$status] ?? $status;

                    $status_color_class = 'text-dark';
                    if ($status === 'completed') $status_color_class = 'text-success';
                    if ($status === 'unpaid') $status_color_class = 'text-warning';
                ?>
                <span class="fw-bold text-uppercase <?= $status_color_class ?>" style="font-size: 12px; letter-spacing: 0.5px;"><?= $status_label ?></span>
            </div>
        </div>

        <!-- Baris Informasi Penjual, Pembeli, dan Logistik -->
        <div class="row mb-5">
            <div class="col-md-4">
                <div class="info-label">Diterbitkan Atas Nama</div>
                <div class="info-value mb-3">Penjual: <span class="text-dark"><?= htmlspecialchars($global_settings['nama_toko'] ?? 'THRIFTKING888') ?></span></div>
                
                <div class="info-label">Untuk</div>
                <div class="info-value">Pembeli: <?= htmlspecialchars($order['nama_pelanggan']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="info-label">Tanggal Pembelian</div>
                <div class="info-value"><?= date('d F Y', strtotime($order['created_at'])) ?></div>
                
                <div class="info-label mt-3">Metode Pembayaran</div>
                <div class="info-value text-uppercase" style="font-size: 12px;">
                    <?= !empty($order['metode_pembayaran']) ? htmlspecialchars($order['metode_pembayaran']) : 'Belum Dipilih' ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-label">Alamat Pengiriman</div>
                <div class="info-value"><?= htmlspecialchars($order['nama_penerima']) ?></div>
                <div class="text-muted small mt-1" style="line-height: 1.5;">
                    <?= $order['no_hp_penerima'] ?><br>
                    <?= nl2br(htmlspecialchars($order['alamat_pengiriman'])) ?>
                </div>
            </div>
        </div>

        <!-- Daftar Produk dengan SKU & Berat -->
        <div class="table-responsive mb-4">
            <table class="table table-invoice align-middle">
                <thead>
                    <tr>
                        <th class="ps-0">Info Produk</th>
                        <th class="text-center">Jumlah</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-end pe-0">Total Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="ps-0" style="width: 50%;">
                            <div class="d-flex align-items-center">
                                <img src="<?= BASE_URL ?>assets/img/products/<?= htmlspecialchars($item['nama_foto'] ?? 'no-image.png', ENT_QUOTES, 'UTF-8') ?>" width="50" height="50" class="border rounded me-3 object-fit-cover" onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';">
                                <div>
                                    <div class="fw-bold text-dark text-uppercase" style="font-size: 14px;"><?= htmlspecialchars($item['nama_produk_snapshot']) ?></div>
                                    <div class="product-sku-weight">
                                        SKU: <?= !empty($item['sku']) ? $item['sku'] : '-' ?> | 
                                        Berat: <?= !empty($item['weight']) ? $item['weight'] . ' gr' : '-' ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center" style="font-size: 14px;"><?= $item['jumlah'] ?></td>
                        <td class="text-end" style="font-size: 14px;"><?= formatRupiah($item['harga_satuan']) ?></td>
                        <td class="text-end pe-0 fw-bold" style="font-size: 14px;"><?= formatRupiah($item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Ringkasan Biaya Transparan -->
        <div class="row">
            <div class="col-md-6 offset-md-6 mt-4">
                <h6 class="fw-bold text-uppercase" style="font-size: 13px; letter-spacing: 1px;">Rincian Pembayaran</h6>
                <div class="d-flex justify-content-between summary-row small">
                    <span>Subtotal Produk</span>
                    <span><?= formatRupiah($order['total_harga_produk']) ?></span>
                </div>
                <div class="d-flex justify-content-between summary-row small">
                    <span>Subtotal Pengiriman</span>
                    <span><?= formatRupiah($order['total_ongkir']) ?></span>
                </div>
                <?php if (($order['total_diskon'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between summary-row text-success small">
                    <span>Voucher Diskon</span>
                    <span>-<?= formatRupiah($order['total_diskon']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between total-bill">
                    <span class="fw-bold" style="font-size: 15px;">TOTAL BELANJA</span>
                    <span class="fw-bold text-black" style="font-size: 18px;"><?= formatRupiah($order['total_pembayaran']) ?></span>
                </div>
            </div>
        </div>

        <!-- Catatan Kaki Invoice -->
        <div class="mt-5 pt-4 border-top text-center text-muted d-print-none">
            <p style="font-size: 12px;">Simpan rincian ini sebagai bukti transaksi yang sah.</p>
            <div class="mt-3">
                <?php $back_url = ($user['role'] === 'admin') ? 'admin/pesanan' : 'pelanggan/pesanan'; ?>
                <a href="<?= BASE_URL . $back_url ?>" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>