<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
require_once APP_ROOT . '/helpers/Security.php';

$controller = new produkcontroller(Database::getConnection()); // Pastikan produkcontroller menerima koneksi sebagai parameter
$db = Database::getConnection();
$categories = $controller->getCategories();
$product_id = intval($id ?? $_GET['id'] ?? 0); // Mendukung Clean URL dan Query String

// Generate CSRF token untuk form
$csrf_token = generateCSRFToken();

// Logika AJAX Reorder dan Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validasi CSRF token untuk AJAX requests
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }
    
    if ($_POST['action'] === 'reorder') {
        $order = $_POST['order'] ?? [];
        $result = $controller->reorderImages($order);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    } elseif ($_POST['action'] === 'delete_image') {
        $imageId = intval($_POST['image_id'] ?? 0);
        $result = $controller->deleteProductImage($imageId);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    }
}
// Asumsi: show() di controller sekarang mengembalikan data produk, varian utama, dan gambar utama
$p = $controller->show($product_id); 

if ($p) {
    $galeri = [];
    // Ambil semua gambar berdasarkan sort_order
    $stmt = mysqli_prepare($db, "SELECT id, nama_foto, is_primary FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $galeri = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
    }
}

if (!$p || !isset($p['variant_id'])) { // Pastikan produk dan varian utamanya ditemukan
    header('Location: ' . BASE_URL . 'admin/produk_index.php');
    exit();
}

$error_message = '';
$allowedExtensions = ['jpg', 'jpeg', 'png'];
$max_files = 10;
$old = [ // Menggunakan data dari $p yang sudah di-join
    'nama_produk'   => $p['nama_produk'],
    'category_id'   => $p['category_id'],
    'harga_reguler' => $p['harga_reguler'],
    'harga_jual'    => $p['harga_jual'],
    'varian_ukuran' => $p['varian_ukuran'],
    'varian_warna'  => $p['varian_warna'],
    'stok'          => $p['stok'],
    'deskripsi'     => $p['deskripsi'],
    'brand'         => $p['brand'],
    'kondisi'       => $p['kondisi'],
    'weight'        => $p['weight'],
    'status'        => $p['status'],
    'sku'           => $p['sku']
];

if (isset($_POST['update'])) {
    // Validasi CSRF token terlebih dahulu
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Permintaan tidak valid (CSRF token).';
    } else {
        $old['nama_produk'] = trim($_POST['nama_produk'] ?? '');
        $old['category_id'] = $_POST['category_id'] ?? '';
        $old['harga_reguler'] = trim($_POST['harga_reguler'] ?? '');
        $old['harga_jual'] = trim($_POST['harga_jual'] ?? '');
        $old['varian_ukuran'] = trim($_POST['varian_ukuran'] ?? '');
        $old['varian_warna'] = trim($_POST['varian_warna'] ?? '');
        $old['stok'] = trim($_POST['stok'] ?? '');
        $old['deskripsi'] = trim($_POST['deskripsi'] ?? '');
        $old['brand'] = trim($_POST['brand'] ?? '');
        $old['kondisi'] = trim($_POST['kondisi'] ?? '');
        $old['weight'] = trim($_POST['weight'] ?? '');
        $old['status'] = $_POST['status'] ?? 'active';
        $old['sku'] = trim($_POST['sku'] ?? '');

        if ($old['nama_produk'] === '') {
            $error_message = 'Nama produk wajib diisi.';
        } elseif (!is_numeric($old['category_id']) || (int)$old['category_id'] <= 0) {
            $error_message = 'Kategori produk wajib dipilih.';
        } elseif (!is_numeric($old['harga_reguler']) || (int)$old['harga_reguler'] <= 0) {
            $error_message = 'Harga reguler harus berupa angka positif.';
        } elseif (!is_numeric($old['harga_jual']) || (int)$old['harga_jual'] <= 0) {
            $error_message = 'Harga jual harus berupa angka positif.';
        } elseif ((int)$old['harga_jual'] > (int)$old['harga_reguler']) {
            $error_message = 'Harga jual tidak boleh lebih tinggi dari harga reguler.';
        } elseif (!in_array($old['varian_ukuran'], ['S', 'M', 'L', 'XL', 'ALL'], true)) {
            $error_message = 'Ukuran produk tidak valid (S, M, L, XL, ALL).';
        } elseif (!is_numeric($old['stok']) || (int)$old['stok'] < 0) {
            $error_message = 'Stok harus berupa angka nol atau lebih.';
        } elseif (!is_numeric($old['weight']) || (int)$old['weight'] <= 0) {
            $error_message = 'Berat produk harus berupa angka positif.';
        }

        if ($error_message === '') {
            $data = [
                'nama_produk'   => $old['nama_produk'],
                'category_id'   => (int)$old['category_id'],
                'harga_reguler' => (int)$old['harga_reguler'],
                'harga_jual'    => (int)$old['harga_jual'],
                'varian_ukuran' => $old['varian_ukuran'],
                'varian_warna'  => $old['varian_warna'],
                'stok'          => (int)$old['stok'],
                'deskripsi'     => $old['deskripsi'],
                'brand'         => $old['brand'],
                'kondisi'       => $old['kondisi'],
                'weight'        => (int)$old['weight'],
                'status'        => $old['status'],
                'sku'           => $old['sku'],
            ];

            if ($controller->update($product_id, $p['variant_id'], $data, $_FILES['gambar'] ?? null)) { 
                header('Location: ' . BASE_URL . 'admin/produk?pesan=update_berhasil');
                exit();
            }

            $error_message = 'Gagal memperbarui produk. Silakan coba lagi.';
        }
    }
}
?>

<?php $pageTitle = 'Edit Produk'; $activePage = 'produk'; require_once APP_ROOT . '/Views/layouts/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark fw-bold">Edit Koleksi ThriftKing</div>
                <div class="card-body p-4">
                    <!-- Info Drag & Drop -->
                    <div class="alert alert-info py-2 small border-0 mb-4">
                        <i class="bi bi-info-circle me-2"></i> Geser gambar untuk mengatur urutan. Gambar pertama otomatis menjadi <strong>Sampul Utama</strong>.
                    </div>

                    <?php if ($error_message !== '') : ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <form id="formEditProduk" action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">KELOLA GALERI (DRAG & DROP)</label>
                                <div id="sortable-gallery" class="d-flex flex-wrap gap-3 mb-3 p-3 bg-light border">
                                    <?php foreach ($galeri as $index => $img): ?>
                                        <div class="position-relative bg-white border p-1 shadow-sm gallery-item" data-id="<?= $img['id'] ?>" style="width: 120px; height: 120px; cursor: move;">
                                            <img src="<?= BASE_URL ?>assets/img/products/<?= htmlspecialchars($img['nama_foto']) ?>" class="w-100 h-100" style="object-fit: cover;">
                                            <?php if($index === 0): ?>
                                                <span class="badge bg-dark position-absolute top-0 start-0 m-1" style="font-size: 8px;">UTAMA</span>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 delete-image-btn" data-id="<?= $img['id'] ?>" title="Hapus Gambar" style="font-size: 0.6rem; padding: 0.1rem 0.3rem;">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <label class="form-label small fw-bold mt-2">TAMBAH FOTO BARU</label>
                                <input type="file" name="gambar[]" class="form-control mb-2" multiple>
                                <small class="text-muted">Kosongkan jika tidak ingin menambah foto baru.</small>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">NAMA PRODUK</label>
                                <input type="text" name="nama_produk" class="form-control" value="<?= htmlspecialchars($old['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">KATEGORI <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($old['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">HARGA REGULER (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="harga_reguler" class="form-control" value="<?= htmlspecialchars($old['harga_reguler'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">HARGA JUAL (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="harga_jual" class="form-control" value="<?= htmlspecialchars($old['harga_jual'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">VARIAN UKURAN <span class="text-danger">*</span></label>
                                <select name="varian_ukuran" class="form-select" required>
                                    <option value="S" <?= $old['varian_ukuran'] === 'S' ? 'selected' : '' ?>>S</option>
                                    <option value="M" <?= $old['varian_ukuran'] === 'M' ? 'selected' : '' ?>>M</option>
                                    <option value="L" <?= $old['varian_ukuran'] === 'L' ? 'selected' : '' ?>>L</option>
                                    <option value="XL" <?= $old['varian_ukuran'] === 'XL' ? 'selected' : '' ?>>XL</option>
                                    <option value="ALL" <?= $old['varian_ukuran'] === 'ALL' ? 'selected' : '' ?>>ALL (Semua Ukuran)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">VARIAN WARNA</label>
                                <input type="text" name="varian_warna" class="form-control" value="<?= htmlspecialchars($old['varian_warna'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: Merah, Biru">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">SKU (Stock Keeping Unit) <span class="text-muted small">(Opsional)</span></label>
                                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($old['sku'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Biarkan kosong untuk generate otomatis">
                                <small class="text-muted" style="font-size: 0.7rem;">Contoh: TK-VINT-24-A1B2C</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">STOK <span class="text-danger">*</span></label>
                                <input type="number" name="stok" class="form-control" value="<?= htmlspecialchars($old['stok'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">BRAND</label>
                                <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($old['brand'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">KONDISI</label>
                                <input type="text" name="kondisi" class="form-control" value="<?= htmlspecialchars($old['kondisi'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Misal: 9/10">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">BERAT (gram)</label>
                                <input type="number" name="weight" class="form-control" value="<?= htmlspecialchars($old['weight'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">STATUS PRODUK</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="draft" <?= $old['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">DESKRIPSI</label>
                                <textarea name="deskripsi" class="form-control" rows="4"><?= htmlspecialchars($old['deskripsi'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>admin/produk" class="btn btn-light border">Kembali</a>
                            <button type="submit" name="update" class="btn btn-warning px-4 fw-bold">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('sortable-gallery');
    if (el) {
        Sortable.create(el, {
            animation: 150,
            ghostClass: 'bg-warning',
            onEnd: function() {
                const order = Array.from(el.querySelectorAll('.gallery-item')).map(item => item.dataset.id);
                
                // Kirim urutan baru ke server via AJAX
                const formData = new FormData();
                formData.append('action', 'reorder');
                formData.append('csrf_token', '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>');
                order.forEach(id => formData.append('order[]', id));

                fetch('<?= BASE_URL ?>admin/produk/reorder-images', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        // Refresh halaman untuk memperbarui lencana "UTAMA" atau lakukan manipulasi DOM sederhana
                        window.location.reload();
                    }
                });
            }
        });

        // Handle image deletion
        el.addEventListener('click', function(event) {
            if (event.target.classList.contains('delete-image-btn') || event.target.closest('.delete-image-btn')) {
                const deleteButton = event.target.closest('.delete-image-btn');
                const imageId = deleteButton.dataset.id;

                if (confirm('Apakah Anda yakin ingin menghapus gambar ini?')) {
                    const formData = new FormData();
                    formData.append('action', 'delete_image');
                    formData.append('image_id', imageId);
                    formData.append('csrf_token', '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>');

                    fetch('<?= BASE_URL ?>admin/produk/delete-image/' + imageId, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            deleteButton.closest('.gallery-item').remove();
                            window.location.reload(); // Reload to update primary badge and order
                        } else {
                            alert('Gagal menghapus gambar.');
                        }
                    }).catch(error => { console.error('Error:', error); alert('Terjadi kesalahan saat menghapus gambar.'); });
                }
            }
        });
    }
});
</script>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>
