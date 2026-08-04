<?php
/**
 * File: Views/customer/pesanan.php
 * Halaman riwayat transaksi pelanggan - ThriftKing888
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
require_once APP_ROOT . '/helpers/Format.php'; // Integrasi fungsi formatRupiah()
require_once APP_ROOT . '/Models/transaksi.php'; // Tambahkan model transaksi
require_once APP_ROOT . '/Controllers/Admin/ProdukController.php'; // Tambahkan ProdukController

// --- HANDLER AJAX: BATALKAN PESANAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        $order_id = intval($_POST['order_id'] ?? 0);
        $user_id = intval($_SESSION['user']['id']);

        if ($order_id <= 0) {
            throw new Exception("ID Pesanan tidak valid.");
        }

        $transaksiModel = new transaksi(Database::getConnection());
        $order = $transaksiModel->getOrderById($order_id);

        if ($_POST['ajax_action'] === 'batalkan_pesanan') {
            // Validasi: Pastikan pesanan ada, milik user, dan statusnya masih 'unpaid'
            if (!$order || $order['user_id'] !== $user_id || $order['status_order'] !== 'unpaid') {
                throw new Exception("Pesanan tidak dapat dibatalkan.");
            }
            if ($transaksiModel->updateOrderStatus($order_id, 'cancelled')) {
                echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibatalkan.']);
            } else {
                throw new Exception("Gagal memperbarui status.");
            }
        } elseif ($_POST['ajax_action'] === 'selesaikan_pesanan') {
            // Validasi: Pastikan status sudah 'shipped'
            if (!$order || $order['user_id'] !== $user_id || $order['status_order'] !== 'shipped') {
                throw new Exception("Pesanan belum dikirim atau sudah selesai.");
            }
            $update_result = $transaksiModel->updateOrderStatus($order_id, 'completed');
            if ($update_result === true) {
                echo json_encode(['success' => true, 'message' => 'Terima kasih! Pesanan telah selesai.']);
            } else {
                $error_reason = is_string($update_result) ? $update_result : "Terjadi kesalahan tidak diketahui.";
                throw new Exception("Gagal menyelesaikan pesanan. Penyebab: " . $error_reason);
            }
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Sinkronisasi koneksi database
$db_connection = isset($conn) ? $conn : Database::getConnection();
$produkController = new ProdukController($db_connection); // Instantiate ProdukController
$user_id = intval($_SESSION['user']['id']);

// Filter status dari URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
// Sesuaikan dengan ENUM di database agar filter berfungsi
$allowed_statuses = ['unpaid', 'pending_confirmation', 'processing', 'shipped', 'completed', 'cancelled'];
if (!in_array($status_filter, $allowed_statuses, true)) { // Menambahkan 'cancelled' ke status yang diizinkan
    $status_filter = '';
}

// --- HITUNG JUMLAH PESANAN PER STATUS (Untuk Badge) ---
$counts = [
    'unpaid' => 0,
    'pending_confirmation' => 0,
    'processing' => 0,
    'shipped' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$stmt_count = mysqli_prepare($db_connection, "SELECT status_order, COUNT(*) as total FROM orders WHERE user_id = ? GROUP BY status_order");
mysqli_stmt_bind_param($stmt_count, 'i', $user_id);
mysqli_stmt_execute($stmt_count);
$res_count = mysqli_stmt_get_result($stmt_count);
while ($c_row = mysqli_fetch_assoc($res_count)) {
    if (isset($counts[$c_row['status_order']])) {
        $counts[$c_row['status_order']] = $c_row['total'];
    }
}
mysqli_stmt_close($stmt_count);

// Query mengambil data transaksi beserta detail produk pertama
$query = "SELECT 
            o.*, 
            pay.bukti_transfer,
            pv.product_id,
            p.nama_produk as nama_produk_pertama,
            pi.nama_foto as gambar_produk_pertama,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_item
          FROM orders o
          LEFT JOIN payments pay ON o.id = pay.order_id
          LEFT JOIN order_items oi ON oi.id = (
              SELECT id FROM order_items WHERE order_id = o.id LIMIT 1
          )
          LEFT JOIN product_variants pv ON oi.product_variant_id = pv.id
          LEFT JOIN products p ON pv.product_id = p.id          LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
          WHERE o.user_id = ?";

if (!empty($status_filter)) {
    $query .= " AND o.status_order = ?";
}

$query .= " ORDER BY o.created_at DESC";

$stmt = mysqli_prepare($db_connection, $query);
if ($stmt) {
    if (!empty($status_filter)) {
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $status_filter);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $result = false;
}

$base_url = defined('BASE_URL') ? BASE_URL : '';
$pageTitle = 'Daftar Transaksi Pesanan - ThriftKing888';
?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php require_once APP_ROOT . '/Views/layouts/navbar.php'; ?>

<style>
    body { font-family: 'Inter', sans-serif; background-color: #fff; color: #111; }
    .page-title { font-family: 'Tenor Sans', sans-serif; letter-spacing: 2px; }
    
    /* Layout Minimalis Premium */
    .card { border: 1px solid #e5e5e5; border-radius: 0px !important; box-shadow: none; }
    .btn { border-radius: 0px !important; letter-spacing: 1px; font-size: 11px; transition: 0.2s ease; text-transform: capitalize; }
    .badge { border-radius: 0px !important; font-size: 10px; font-weight: 600; letter-spacing: 0.5px; padding: 6px 10px; text-transform: capitalize; }
    
    /* Navigasi Filter Status */
    .nav-status-link { font-size: 12px; letter-spacing: 1px; text-transform: capitalize; transition: all 0.2s ease; }
    .nav-status-link:hover { color: #000 !important; }
    .invoice-link { color: #6c757d; transition: color 0.2s; text-decoration: none; }
    .invoice-link:hover { color: #000; text-decoration: underline; }
    
    /* Utility kustom mencegah flexbox overflow pada judul panjang */
    .min-w-0 { min-width: 0 !important; }

    /* Pembatas responsif khusus layar medium ke atas */
    .order-card-clickable { cursor: pointer; }
    .items-reveal-box {
        border-top: 1px dashed #e0e0e0;
        padding-top: 1.5rem;
    }

    @media (min-width: 768px) {
        .border-start-md {
            border-left: 1px solid #e5e5e5 !important;
        }
    }
</style>

<div class="container py-5" style="max-width: 900px;">
    <h4 class="fw-bold text-capitalize page-title mb-4" style="font-size: 18px;">Daftar Transaksi</h4>

    <div class="d-flex gap-4 mb-4 border-bottom pb-2 overflow-auto" style="white-space: nowrap; scrollbar-width: none; -ms-overflow-style: none;">
        <a href="<?= $base_url ?>pelanggan/pesanan"
           class="nav-status-link text-decoration-none pb-2 <?= empty($status_filter) ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">Semua</a>
        
        <a href="<?= $base_url ?>pelanggan/pesanan?status=unpaid"
           class="nav-status-link text-decoration-none pb-2 <?= $status_filter === 'unpaid' ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">
           Menunggu Pembayaran <?= $counts['unpaid'] > 0 ? '<span class="ms-1 fw-normal text-muted" style="font-size: 11px;">(' . $counts['unpaid'] . ')</span>' : '' ?>
        </a>
        
        <a href="<?= $base_url ?>pelanggan/pesanan?status=pending_confirmation"
           class="nav-status-link text-decoration-none pb-2 <?= $status_filter === 'pending_confirmation' ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">
           Konfirmasi <?= $counts['pending_confirmation'] > 0 ? '<span class="ms-1 fw-normal text-muted" style="font-size: 11px;">(' . $counts['pending_confirmation'] . ')</span>' : '' ?>
        </a>
        
        <a href="<?= $base_url ?>pelanggan/pesanan?status=processing"
           class="nav-status-link text-decoration-none pb-2 <?= $status_filter === 'processing' ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">
           Diproses <?= $counts['processing'] > 0 ? '<span class="ms-1 fw-normal text-muted" style="font-size: 11px;">(' . $counts['processing'] . ')</span>' : '' ?>
        </a>
        
        <a href="<?= $base_url ?>pelanggan/pesanan?status=shipped"
           class="nav-status-link text-decoration-none pb-2 <?= $status_filter === 'shipped' ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">
           Dikirim <?= $counts['shipped'] > 0 ? '<span class="ms-1 fw-normal text-muted" style="font-size: 11px;">(' . $counts['shipped'] . ')</span>' : '' ?>
        </a>
        
        <a href="<?= $base_url ?>pelanggan/pesanan?status=completed"
           class="nav-status-link text-decoration-none pb-2 <?= $status_filter === 'completed' ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">
           Pesanan Selesai <?= $counts['completed'] > 0 ? '<span class="ms-1 fw-normal text-muted" style="font-size: 11px;">(' . $counts['completed'] . ')</span>' : '' ?>
        </a>

        <a href="<?= $base_url ?>pelanggan/pesanan?status=cancelled"
           class="nav-status-link text-decoration-none pb-2 <?= $status_filter === 'cancelled' ? 'text-dark fw-bold border-dark border-bottom' : 'text-muted' ?>" style="border-bottom-width: 2px !important;">
           Dibatalkan <?= $counts['cancelled'] > 0 ? '<span class="ms-1 fw-normal text-muted" style="font-size: 11px;">(' . $counts['cancelled'] . ')</span>' : '' ?>
        </a>
    </div>
    
    <?php if ($result && mysqli_num_rows($result) > 0) : ?>
        <?php while ($row = mysqli_fetch_assoc($result)) : 
            $safe_kode = htmlspecialchars($row['invoice_code'], ENT_QUOTES, 'UTF-8');
            $safe_nama = htmlspecialchars($row['nama_produk_pertama'] ?? 'Produk', ENT_QUOTES, 'UTF-8');
            $safe_gambar = htmlspecialchars($row['gambar_produk_pertama'] ?? 'placeholder.png', ENT_QUOTES, 'UTF-8');
            $total_item_safe = intval($row['total_item']);
        ?>
            <div class="card mb-4 p-2 order-card-clickable" id="order-card-<?= intval($row['id']) ?>" data-order-id="<?= intval($row['id']) ?>">
                <div class="card-body p-3">
                    
                    <div class="d-flex align-items-center mb-3 justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <i class="bi bi-bag text-dark fw-bold"></i>
                            <span class="fw-bold small text-capitalize" style="letter-spacing: 0.5px;">Pesanan</span> 
                            <span class="small text-muted border-start ps-2">
                                <?= date('d M Y', strtotime($row['created_at'])) ?>
                            </span>
                            
                            <?php 
                            $s = $row['status_order'];
                            $status_map = [
                                'unpaid' => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-warning text-dark'],
                                'pending_confirmation' => ['label' => 'Menunggu Konfirmasi', 'class' => 'bg-info text-white'],
                                'processing' => ['label' => 'Diproses', 'class' => 'bg-primary text-white'],
                                'shipped' => ['label' => 'Dikirim', 'class' => 'bg-info text-white'],
                                'completed' => ['label' => 'Selesai', 'class' => 'bg-success text-white'],
                                'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-danger text-white']
                            ];
                            
                            $current_status = $status_map[$s] ?? ['label' => $s, 'class' => 'bg-light text-dark'];
                            ?>
                            <span class="badge <?= $current_status['class'] ?> ms-1">
                                <?= $current_status['label'] ?>
                            </span>
                            <a href="<?= $base_url ?>pelanggan/pesanan/<?= intval($row['id']) ?>" class="small d-none d-md-inline ms-2 fw-mono invoice-link">
                                #<?= $safe_kode ?>
                            </a>
                        </div>
                    </div>

                    <div class="row align-items-center g-3">
                        <div class="col-12 col-md-8">
                            <div class="d-flex align-items-center w-100">
                                <div class="position-relative flex-shrink-0 me-3">
                                    <img src="<?= !empty($row['gambar_produk_pertama']) ? $base_url . 'assets/img/products/' . $safe_gambar : $base_url . 'assets/img/no-image.png'; ?>" 
                                         class="rounded-0 border" width="75" height="75" style="object-fit: cover;" 
                                         onerror="this.onerror=null;this.src='<?= $base_url ?>assets/img/no-image.png';" 
                                         alt="<?= $safe_nama ?>">
                                </div>
                                
                                <div class="ms-3 flex-grow-1 min-w-0">
                                    <h6 class="mb-1 fw-bold text-capitalize text-truncate" style="font-size: 14px; letter-spacing: 0.5px;">
                                        <?= $safe_nama ?>
                                    </h6>
                                    <p class="text-muted small mb-0" style="font-size: 12px;">
                                        <?= $total_item_safe ?> Item Pakaian
                                    </p>
                                    <?php if($total_item_safe > 1) : ?>
                                        <p class="text-muted mt-1 mb-0 text-truncate" style="font-size: 11px; letter-spacing: 0.5px;">
                                            +<?= $total_item_safe - 1 ?> produk lainnya
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4 border-start-md ps-md-4 d-flex d-md-block justify-content-between align-items-center">
                            <div class="mb-md-3">
                                <span class="text-muted small text-capitalize d-block" style="font-size: 10px; letter-spacing: 1px;">Total Belanja</span>
                                <p class="mb-0 fw-bold text-danger" style="font-size: 15px;">
                                    <?= formatRupiah($row['total_pembayaran']) ?>
                                </p>
                            </div>
                            
                            <div class="d-flex d-md-block gap-2">                                
                                <a href="<?= $base_url ?>pelanggan/pesanan/<?= intval($row['id']) ?>" class="btn btn-outline-dark btn-sm px-3 fw-bold w-100 mb-md-2 text-capitalize">Detail</a>
                                <!-- Tombol Bayar dan Batalkan hanya jika status unpaid -->
                                        <?php if ($s === 'unpaid'): ?>
                                    <a href="<?= $base_url ?>pelanggan/pembayaran/<?= intval($row['id']) ?>" class="btn btn-dark btn-sm px-3 fw-bold w-100 text-capitalize">Bayar</a>
                                    <button type="button" onclick="batalkanPesanan(<?= intval($row['id']) ?>)" class="btn btn-outline-danger btn-sm px-3 fw-bold w-100 text-capitalize mt-md-2">Batalkan</button>
                                <?php elseif ($s === 'completed'): ?>
                                    <?php
                                        $show_review_button = false;
                                        $review_link = '';

                                        if ($total_item_safe > 1) {
                                            $show_review_button = true;
                                            $review_link = $base_url . 'pelanggan/menunggu-ulasan';
                                        } elseif ($total_item_safe === 1) {
                                            $single_product_id = intval($row['product_id']);
                                            $product_details = $produkController->show($single_product_id); // Checks p.deleted_at IS NULL
                                            if ($product_details) {
                                                $show_review_button = true;
                                                $review_link = $base_url . 'pelanggan/ulasan/' . $single_product_id;
                                            }
                                        }
                                    ?>
                                    <?php if ($show_review_button): ?>
                                        <a href="<?= $review_link ?>" class="btn btn-outline-dark btn-sm px-3 fw-bold w-100 text-capitalize">Beri Ulasan</a>
                                    <?php endif; ?>
                                <?php elseif ($s === 'shipped'): ?>
                                    <button type="button" onclick="selesaikanPesanan(<?= intval($row['id']) ?>)" class="btn btn-primary btn-sm px-3 fw-bold w-100 text-capitalize">Selesaikan</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- [DITAMBAHKAN] Placeholder untuk daftar item produk -->
                    <div class="items-reveal-box mt-4" id="items-reveal-<?= intval($row['id']) ?>" style="display: none;">
                        <!-- Konten item akan dimuat di sini via AJAX -->
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    <?php else : ?>
        <div class="text-center py-5 bg-white border border-dashed rounded-0">
            <i class="bi bi-inbox text-muted mb-3 d-block" style="font-size: 48px;"></i>
            <h5 class="fw-bold text-capitalize" style="font-size: 14px; letter-spacing: 1px;">
                <?= !empty($status_filter) ? 'Tidak ada transaksi dengan status ini' : 'Belum ada rekaman transaksi' ?>
            </h5>
            <p class="text-muted small mb-4">Mulai jelajahi katalog vault kami untuk menemukan item vintage favoritmu.</p>
            <a href="<?= $base_url ?>" class="btn btn-dark px-5 py-2 fw-bold text-capitalize">Mulai Belanja</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderCards = document.querySelectorAll('.order-card-clickable');

    orderCards.forEach(card => {
        card.addEventListener('click', function(event) {
            // Mencegah trigger jika yang diklik adalah tombol atau link
            if (event.target.closest('a, button')) {
                return;
            }

            const orderId = this.dataset.orderId;
            const revealBox = document.getElementById(`items-reveal-${orderId}`);

            if (!revealBox) return;

            // Toggle tampilan jika sudah ada isinya
            if (revealBox.dataset.loaded === 'true') {
                revealBox.style.display = revealBox.style.display === 'none' ? 'block' : 'none';
                return;
            }

            // Jika belum, fetch data via AJAX
            fetchItems(orderId, revealBox);
        });
    });

    async function fetchItems(orderId, box) {
        box.style.display = 'block';
        box.innerHTML = `
            <div class="text-center text-muted small py-3">
                <span class="spinner-border spinner-border-sm"></span> Memuat item...
            </div>`;

        try {
            const response = await fetch(`<?= BASE_URL ?>pelanggan/pesanan/items/${orderId}`);
            if (!response.ok) {
                throw new Error(`Gagal memuat data: ${response.statusText}`);
            }
            const data = await response.json();

            if (data.success && data.items.length > 0) {
                let itemsHtml = '<h6 class="fw-bold small text-capitalize text-muted mb-3" style="letter-spacing: 1px;">Detail Item</h6>';
                data.items.forEach(item => {
                    const itemImage = item.nama_foto 
                        ? `<?= BASE_URL ?>assets/img/products/${item.nama_foto}`
                        : `<?= BASE_URL ?>assets/img/no-image.png`;
                    
                    const hargaSatuan = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(item.harga_satuan);
                    const subtotal = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(item.subtotal);

                    itemsHtml += `
                        <div class="d-flex align-items-center mb-3 border-bottom pb-3">
                            <img src="${itemImage}" width="60" height="60" class="rounded-0 border me-3" style="object-fit: cover;" onerror="this.src='<?= BASE_URL ?>assets/img/no-image.png'">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-bold text-dark small text-truncate">${item.nama_produk_snapshot}</div>
                                <div class="text-muted small">${item.jumlah} x ${hargaSatuan}</div>
                            </div>
                            <div class="fw-bold text-dark small text-nowrap ps-3">${subtotal}</div>
                        </div>
                    `;
                });
                box.innerHTML = itemsHtml;
                box.dataset.loaded = 'true';
            } else {
                throw new Error(data.message || 'Tidak ada item ditemukan.');
            }
        } catch (error) {
            box.innerHTML = `<div class="text-center text-danger small py-3">${error.message}</div>`;
            box.dataset.loaded = 'false'; // Allow re-fetching on next click
        }
    }
});

async function batalkanPesanan(orderId) {
    if (!confirm('Apakah Anda yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat dibatalkan.')) {
        return;
    }

    const formData = new FormData();
    formData.append('ajax_action', 'batalkan_pesanan');
    formData.append('order_id', orderId);

    try {
        const response = await fetch('<?= BASE_URL ?>pelanggan/pesanan', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            // Perbarui UI: Ubah badge status, sembunyikan tombol aksi
            const orderCard = document.querySelector(`#order-card-${orderId}`);
            if (orderCard) {
                const statusBadge = orderCard.querySelector('.badge');
                if (statusBadge) {
                    statusBadge.className = 'badge bg-danger text-white ms-1'; // Ubah kelas badge
                    statusBadge.innerText = 'CANCELLED'; // Ubah teks status
                }
                // Sembunyikan tombol Bayar dan Batalkan
                const actionButtonsContainer = orderCard.querySelector('.d-flex.d-md-block.gap-2');
                if (actionButtonsContainer) {
                    actionButtonsContainer.innerHTML = ''; // Hapus semua tombol di dalamnya
                }
            }
            // Atau, lebih sederhana, reload halaman untuk memperbarui semua status
            window.location.reload();
        } else {
            alert('Gagal membatalkan pesanan: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Terjadi kesalahan sistem saat membatalkan pesanan.');
    }
}

async function selesaikanPesanan(orderId) {
    if (!confirm('Konfirmasi bahwa Anda telah menerima pesanan dengan baik?')) {
        return;
    }

    const formData = new FormData();
    formData.append('ajax_action', 'selesaikan_pesanan');
    formData.append('order_id', orderId);

    try {
        const response = await fetch('<?= BASE_URL ?>pelanggan/pesanan', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await response.json();
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Terjadi kesalahan sistem.');
    }
}
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>