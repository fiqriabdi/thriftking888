<?php
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Models/transaksi.php';
require_once APP_ROOT . '/helpers/Format.php';
require_once APP_ROOT . '/Models/settings.php'; // [DITAMBAHKAN]

$db = Database::getConnection();
$transaksiModel = new transaksi($db);
$settingsModel = new SettingsModel($db); // [DITAMBAHKAN]

// ID didapatkan dari Router via extract()
$order_id = intval($id ?? 0);
$order = $transaksiModel->getOrderById($order_id);
$items = $transaksiModel->getOrderItems($order_id);
$settings = $settingsModel->getSettings(); // [DITAMBAHKAN] Ambil data settings

if (!$order) {
    die("Pesanan tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Pengiriman - <?= $order['invoice_code'] ?></title>
    <style>
        body { font-family: 'Courier', monospace; color: #000; padding: 20px; }
        .logo-container { text-align: center; margin-bottom: 10px; }
        .label-container { border: 2px dashed #000; padding: 20px; max-width: 500px; margin: auto; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .section { margin-bottom: 15px; }
        .section-title { font-weight: bold; text-decoration: underline; font-size: 14px; }
        .content { font-size: 16px; margin-top: 5px; }
        .item-list { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .item-list th, .item-list td { border: 1px solid #000; padding: 5px; text-align: left; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .label-container { border: 1px solid #000; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; cursor:pointer;">CETAK SEKARANG</button>
    <button onclick="window.close()" style="padding: 10px 20px; cursor:pointer;">TUTUP</button>
</div>

<div class="label-container">
    <div class="logo-container">
        <?php if (!empty($settings['logo'])): ?>
            <img src="<?= BASE_URL . 'assets/img/logo/' . htmlspecialchars($settings['logo']) ?>" alt="Logo Toko" style="max-height: 60px; max-width: 180px;">
        <?php else: ?>
            <h2 style="margin:0;"><?= htmlspecialchars($settings['nama_toko'] ?? 'THRIFTKING888') ?></h2>
        <?php endif; ?>
    </div>
    <div class="header">
        <!-- Nama toko sekarang ditampilkan di atas atau sebagai fallback logo -->
        <small><?= $order['invoice_code'] ?></small>
    </div>

    <div class="section">
        <div class="section-title">PENERIMA:</div>
        <div class="content">
            <strong><?= strtoupper(htmlspecialchars($order['nama_penerima'])) ?></strong><br>
            Telp: <?= htmlspecialchars($order['no_hp_penerima'] ?? '-') ?><br>
            Alamat: <?= nl2br(htmlspecialchars($order['alamat_pengiriman'])) ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">DAFTAR BARANG:</div>
        <table class="item-list">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nama_produk_snapshot']) ?></td>
                    <td><?= $item['jumlah'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section" style="border-top: 1px solid #000; pt-2">
        <div class="section-title">PENGIRIM:</div>
        <div class="content" style="font-size: 12px;">
            <strong><?= htmlspecialchars($settings['nama_toko'] ?? 'ThriftKing888') ?></strong> (<?= htmlspecialchars($settings['no_hp'] ?? '-') ?>)<br>
            <?= nl2br(htmlspecialchars($settings['alamat'] ?? 'Alamat belum diatur.')) ?>
        </div>
    </div>

    <div class="footer">
        Terima kasih telah berbelanja di ThriftKing888!<br>
        Waktu Cetak: <?= date('d/m/Y H:i') ?>
    </div>
</div>

<script>
    // Otomatis buka dialog print saat halaman dimuat
    window.onload = function() {
        // window.print();
    };
</script>

</body>
</html>