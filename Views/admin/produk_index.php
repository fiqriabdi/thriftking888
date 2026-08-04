<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

// 1. Memanggil Middleware
require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin'); 

require_once APP_ROOT . '/helpers/Security.php';

// 2. Memanggil Controller
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
$controller = new produkcontroller(Database::getConnection()); // Pastikan produkcontroller menerima koneksi sebagai parameter      
$pageTitle = 'Manajemen Produk';
$activePage = 'produk';

$csrf_token = generateCSRFToken();

// 3. Logika Hapus Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($id)) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_msg'] = 'Permintaan tidak valid (CSRF token).';
    } else {
        $controller->destroy(intval($id));
        $_SESSION['success_msg'] = "Produk berhasil dihapus secara lunak!";
    }
    header('Location: ' . BASE_URL . 'admin/produk');
    exit();
}

$search = trim($_GET['search'] ?? '');
$kategori_filter = trim($_GET['kategori'] ?? '');

// 4. Handler AJAX untuk Pencarian Real-time
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $data = $controller->index($kategori_filter ?: null, $search ?: null);
    $no = 1; // Inisialisasi nomor untuk AJAX
    if (!empty($data)) {
        foreach($data as $p) {
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <?php 
                        $imgPath = !empty($p['gambar_utama']) 
                            ? BASE_URL . 'assets/img/products/' . htmlspecialchars(basename($p['gambar_utama']), ENT_QUOTES, 'UTF-8')
                            : BASE_URL . 'assets/img/no-image.png';
                    ?>
                    <img src="<?= $imgPath ?>" 
                         class="rounded" width="50" height="50" style="object-fit: cover;"
                         onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';" alt="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8') ?>">
                </td>
                <td class="fw-bold"><?= htmlspecialchars($p['nama_produk']) ?></td>
                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($p['nama_kategori'] ?? 'N/A') ?></span></td>
                <td>Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></td>
                <td><?= strtoupper(htmlspecialchars($p['varian_ukuran'])) ?></td>
                <td>
                    <span class="badge <?= $p['status'] === 'tersedia' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= htmlspecialchars(ucfirst($p['status'])) ?>
                    </span>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <a href="<?= BASE_URL ?>admin/produk/edit/<?= intval($p['id']) ?>" class="btn btn-warning">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        <form method="POST" action="<?= BASE_URL ?>admin/produk/delete/<?= intval($p['id']) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus koleksi ini?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo "<tr><td colspan='8' class='text-center py-4 text-muted'>Produk tidak ditemukan.</td></tr>";
    }
    exit;
}

// 4. Ambil Data (Sekarang returnnya adalah ARRAY)
$data = $controller->index($kategori_filter ?: null, $search ?: null);
?>
<?php $categories = $controller->getCategories(); // Ambil data kategori untuk filter ?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0">KELOLA PRODUK</h5>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>admin/produk/recycle-bin" class="btn btn-light btn-sm">
                            <i class="bi bi-trash"></i> Recycle Bin (<?= $controller->countSoftDeletedProducts() ?>)
                        </a>
                        <a href="<?= BASE_URL ?>admin/produk/create" class="btn btn-light btn-sm">
                            <i class="bi bi-plus-lg"></i> Tambah Produk Baru
                        </a>
                    </div>
                </div>
                <div class="card-body">

                    <form id="filterForm" action="" method="GET" class="row g-3 mb-4 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Cari Produk</label>
                            <input type="text" id="searchInput" name="search" class="form-control" placeholder="Ketik nama produk untuk mencari..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Filter Kategori</label>
                            <select name="kategori" class="form-select">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $kategori_filter === $cat['slug'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!--<div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-dark">Terapkan Filter</button>
                        </div> -->
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>Foto</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Ukuran</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="productTableBody">
                                <?php if (!empty($data)) : ?>
                                    <?php 
                                    $no = 1; // Inisialisasi nomor untuk tampilan awal
                                    foreach($data as $p) : 
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td>
                                            <?php 
                                                $imgPath = !empty($p['gambar_utama']) ? BASE_URL . 'assets/img/products/' . htmlspecialchars(basename($p['gambar_utama']), ENT_QUOTES, 'UTF-8') : BASE_URL . 'assets/img/no-image.png';
                                            ?>
                                            <img src="<?= $imgPath ?>" 
                                                 class="rounded" width="50" height="50" style="object-fit: cover;"
                                                 onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';" alt="<?= htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8') ?>">
                                        </td>
                                        <td class="fw-bold"><?= htmlspecialchars($p['nama_produk']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($p['nama_kategori'] ?? 'N/A') ?></span></td>
                                        <td>Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></td>
                                        <td><?= strtoupper(htmlspecialchars($p['varian_ukuran'])) ?></td>
                                        <td>
                                            <?php if ($p['status'] === 'tersedia') : ?>
                                                <span class="badge bg-success">Tersedia</span>
                                            <?php else : ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($p['status'])) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_URL ?>admin/produk/edit/<?= intval($p['id']) ?>" class="btn btn-warning">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </a>
                                                <form method="POST" action="<?= BASE_URL ?>admin/produk/delete/<?= intval($p['id']) ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus koleksi ini?')">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Belum ada data produk.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            
            <div class="mt-3">
                <a href="<?= BASE_URL ?>admin/dashboard" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    const tableBody = document.getElementById('productTableBody');

    const fetchProducts = () => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData).toString();
        
        fetch(`${window.location.pathname}?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            tableBody.innerHTML = html;
        })
        .catch(err => console.error('AJAX Error:', err));
    };

    // Pencarian saat mengetik (dengan debounce 300ms)
    let timeout = null;
    searchInput.addEventListener('keyup', function() {
        clearTimeout(timeout);
        timeout = setTimeout(fetchProducts, 300);
    });

    filterForm.addEventListener('change', fetchProducts);
    filterForm.addEventListener('submit', (e) => e.preventDefault());
});
</script>
<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>
