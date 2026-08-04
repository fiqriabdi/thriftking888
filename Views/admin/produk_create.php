<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../../Config/konstanta.php';
}

require_once APP_ROOT . '/Middleware/auth.php';
auth::requireRole('admin');
require_once APP_ROOT . '/Controllers/Admin/produkcontroller.php';
require_once APP_ROOT . '/helpers/Security.php';

$controller = new produkcontroller(Database::getConnection()); // Pastikan produkcontroller menerima koneksi sebagai parameter
$categories = $controller->getCategories();
$validation_errors = [];

// Generate CSRF token untuk form
$csrf_token = generateCSRFToken();

$old = [
    'nama_produk'   => '',
    'category_id'   => '', // Menggunakan category_id
    'harga_reguler' => '',
    'harga_jual'    => '',
    'varian_ukuran' => 'S',
    'varian_warna'  => '',
    'stok'          => 1,
    'deskripsi'     => '',
    'brand'         => '',
    'kondisi'       => '',
    'weight'        => 500
];
$allowedExtensions = ['jpg', 'jpeg', 'png'];
$max_files = 10;

if (isset($_POST['submit'])) {
    // Validasi CSRF token terlebih dahulu
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $validation_errors[] = 'Permintaan tidak valid (CSRF token).';
    } else {
        $old['nama_produk'] = trim($_POST['nama_produk'] ?? '');
        $old['category_id'] = $_POST['category_id'] ?? ''; // Ambil category_id
        $old['harga_reguler'] = trim($_POST['harga_reguler'] ?? '');
        $old['harga_jual'] = trim($_POST['harga_jual'] ?? '');
        $old['varian_ukuran'] = $_POST['varian_ukuran'] ?? 'S';
        $old['varian_warna'] = trim($_POST['varian_warna'] ?? '');
        $old['stok'] = trim($_POST['stok'] ?? '1');
        $old['deskripsi'] = trim($_POST['deskripsi'] ?? '');
        $old['brand'] = trim($_POST['brand'] ?? '');
        $old['kondisi'] = trim($_POST['kondisi'] ?? '');
        $old['weight'] = trim($_POST['weight'] ?? '500');

        // Gunakan validator dari Controller
        $data = [
            'nama_produk'   => $old['nama_produk'],
            'category_id'   => $old['category_id'],
            'harga_reguler' => $old['harga_reguler'],
            'harga_jual'    => $old['harga_jual'],
            'varian_ukuran' => $old['varian_ukuran'],
            'varian_warna'  => $old['varian_warna'],
            'stok'          => $old['stok'],
            'deskripsi'     => $old['deskripsi'],
            'brand'         => $old['brand'],
            'kondisi'       => $old['kondisi'],
            'weight'        => $old['weight'],            
            'status'        => 'active', 
        ];

        $validation_errors = $controller->validateProductData($data, $_FILES['gambar'] ?? null, false);

        if (empty($validation_errors)) {
            // Konversi tipe data sebelum simpan
            $data['category_id'] = (int)$data['category_id'];
            $data['harga_reguler'] = (int)$data['harga_reguler'];
            $data['harga_jual'] = (int)$data['harga_jual'];
            $data['stok'] = (int)$data['stok'];
            $data['weight'] = (int)$data['weight'];

            if ($controller->store($data, $_FILES['gambar'])) {
                $_SESSION['success_msg'] = "Produk berhasil ditambahkan!";
                header('Location: ' . BASE_URL . 'admin/produk?pesan=tambah_berhasil');
                exit();
            }

            $validation_errors[] = 'Gagal menyimpan data produk ke database.';
        }
    }
}
?>

<?php $pageTitle = 'Tambah Produk Baru'; $activePage = 'produk'; require_once APP_ROOT . '/Views/layouts/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Tambah Koleksi Baru (Multi-Photo)</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Info Drag & Drop -->
                    <div class="alert alert-info py-2 small border-0 mb-4">
                        <i class="bi bi-info-circle me-2"></i> Geser gambar untuk mengatur urutan. Gambar pertama otomatis menjadi <strong>Sampul Utama</strong>.
                    </div>

                    <?php if (!empty($validation_errors)) : ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0 small fw-bold">
                                <?php foreach($validation_errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <form id="formProduk" action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-12 mb-3">
                                <label class="d-block small fw-bold mb-2">FOTO-FOTO PRODUK</label>
                                <input type="file" id="gambarInput" class="form-control mb-3" multiple accept="image/jpeg, image/png">
                                <!-- Input tersembunyi yang akan mengirim file dengan urutan yang benar -->
                                <input type="file" name="gambar[]" id="gambarFinal" class="d-none" multiple>

                                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-3 p-3 bg-light border" style="min-height: 150px;">
                                    <!-- Image previews will be inserted here -->
                                </div>
                                <div class="form-text mt-2 small text-muted">
                                    Tips: Pilih banyak foto sekaligus dengan menahan tombol <strong>Ctrl</strong> (Windows) atau <strong>Command</strong> (Mac). Maksimal 10 file.
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="small fw-bold">NAMA PRODUK <span class="text-danger">*</span></label>
                                <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Vintage Harrington Jacket" value="<?= htmlspecialchars($old['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                           <div class="col-md-6">
                                <label class="small fw-bold text-uppercase">Kategori Produk <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select rounded-0 shadow-none" required>
                                    <option value="" disabled <?= empty($old['category_id']) ? 'selected' : '' ?>>-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ((string)$old['category_id'] === (string)$cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">HARGA REGULER (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="harga_reguler" class="form-control" placeholder="0" value="<?= htmlspecialchars($old['harga_reguler'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">HARGA JUAL (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="harga_jual" class="form-control" placeholder="0" value="<?= htmlspecialchars($old['harga_jual'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">VARIAN UKURAN <span class="text-danger">*</span></label>
                                <select name="varian_ukuran" class="form-select" required>
                                    <option value="S" <?= $old['varian_ukuran'] === 'S' ? 'selected' : '' ?>>S</option>
                                    <option value="M" <?= $old['varian_ukuran'] === 'M' ? 'selected' : '' ?>>M</option>
                                    <option value="L" <?= $old['varian_ukuran'] === 'L' ? 'selected' : '' ?>>L</option>
                                    <option value="XL" <?= $old['varian_ukuran'] === 'XL' ? 'selected' : '' ?>>XL</option>
                                    <option value="ALL" <?= $old['varian_ukuran'] === 'ALL' ? 'selected' : '' ?>>ALL (Semua Ukuran)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">VARIAN WARNA (Opsional)</label>
                                <input type="text" name="varian_warna" class="form-control" placeholder="Contoh: Merah, Biru" value="<?= htmlspecialchars($old['varian_warna'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">BRAND (Opsional)</label>
                                <input type="text" name="brand" class="form-control" placeholder="Contoh: Nike, Adidas" value="<?= htmlspecialchars($old['brand'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">KONDISI (Opsional)</label>
                                <input type="text" name="kondisi" class="form-control" placeholder="Contoh: 9/10, Like New" value="<?= htmlspecialchars($old['kondisi'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">STOK <span class="text-danger">*</span></label>
                                <input type="number" name="stok" class="form-control" value="<?= htmlspecialchars($old['stok'], ENT_QUOTES, 'UTF-8') ?>" min="0" required>
                            </div>

                            <div class="col-md-6">
                                <label class="small fw-bold">BERAT (gram) <span class="text-danger">*</span></label>
                                <input type="number" name="weight" class="form-control" value="<?= htmlspecialchars($old['weight'], ENT_QUOTES, 'UTF-8') ?>" min="1" required>
                            </div>

                            <div class="col-md-12">
                                <label class="small fw-bold">DESKRIPSI BARANG</label>
                                <textarea name="deskripsi" class="form-control" rows="4" placeholder="Jelaskan kondisi barang..."><?= htmlspecialchars($old['deskripsi'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="col-md-12 mt-4 d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>admin/produk" class="btn btn-light border">Batal</a>
                                <button type="submit" name="submit" class="btn btn-dark px-5">Simpan Produk</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>

<!-- SortableJS untuk fitur Drag & Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const gambarInput = document.getElementById('gambarInput');
    const gambarFinal = document.getElementById('gambarFinal');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const formProduk = document.getElementById('formProduk');
    
    let selectedFiles = [];

    // 1. Menangani pemilihan file
    gambarInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const maxFiles = 10;
            
            if (selectedFiles.length + this.files.length > maxFiles) {
                alert(`Maksimal ${maxFiles} gambar diizinkan.`);
                return;
            }

            Array.from(this.files).forEach(file => {
                selectedFiles.push(file);
            });
            
            renderPreviews();
            this.value = ''; // Reset input agar bisa pilih file yang sama lagi jika dihapus
        }
    });

    // 2. Merender kotak pratinjau
    function renderPreviews() {
        imagePreviewContainer.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const imgWrapper = document.createElement('div');
            imgWrapper.className = 'position-relative bg-white border p-1 shadow-sm gallery-item';
            imgWrapper.style.width = '120px';
            imgWrapper.style.height = '120px';
            imgWrapper.style.cursor = 'move';
            imgWrapper.dataset.index = index;

            const img = document.createElement('img');
            img.className = 'w-100 h-100';
            img.style.objectFit = 'cover';
            img.src = '<?= BASE_URL ?>assets/img/no-image.png'; // Placeholder sementara

            const reader = new FileReader();
            reader.onload = (e) => img.src = e.target.result;
            reader.readAsDataURL(file);

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-1';
            deleteBtn.style.padding = '1px 5px';
            deleteBtn.innerHTML = '<i class="bi bi-x"></i>';
            deleteBtn.onclick = function() {
                selectedFiles.splice(index, 1);
                renderPreviews();
            };

            if (index === 0) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-dark position-absolute top-0 start-0 m-1';
                badge.style.fontSize = '8px';
                badge.innerText = 'UTAMA';
                imgWrapper.appendChild(badge);
            }

            imgWrapper.appendChild(img);
            imgWrapper.appendChild(deleteBtn);
            imagePreviewContainer.appendChild(imgWrapper);
        });
    }

    // 3. Inisialisasi SortableJS
    Sortable.create(imagePreviewContainer, {
        animation: 150,
        ghostClass: 'bg-warning',
        onEnd: function() {
            // Bangun ulang array selectedFiles berdasarkan urutan DOM yang baru
            const newOrder = Array.from(imagePreviewContainer.querySelectorAll('.gallery-item'))
                .map(item => parseInt(item.dataset.index));
            
            const reorderedFiles = newOrder.map(index => selectedFiles[index]);
            selectedFiles = reorderedFiles;
            renderPreviews(); // Render ulang untuk memperbarui lencana UTAMA
        }
    });

    // 4. Sinkronisasi file ke input final sebelum form dikirim
    formProduk.addEventListener('submit', function(e) {
        if (selectedFiles.length === 0) {
            alert('Setidaknya satu gambar produk wajib diunggah.');
            e.preventDefault();
            return;
        }

        // Memasukkan array file yang sudah diurutkan ke dalam FileList input asli
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        gambarFinal.files = dataTransfer.files;
    });
});
</script>
