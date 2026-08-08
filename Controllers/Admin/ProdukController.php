<?php
/**
 * File: Controllers/Admin/produkcontroller.php
 * Final Refactored Version
 */

require_once APP_ROOT . '/Config/konstanta.php';
require_once APP_ROOT . '/Models/Produk.php';
require_once APP_ROOT . '/Models/Cart.php';
require_once APP_ROOT . '/helpers/Format.php';
require_once APP_ROOT . '/Middleware/auth.php';
require_once APP_ROOT . '/helpers/Loggable.php'; // Tambahkan ini
require_once APP_ROOT . '/helpers/Security.php';


class ProdukController {
    use Loggable; // Gunakan trait Loggable
    private $db;
    private $model;

    /**
     * Constructor dengan Dependency Injection.
     * Koneksi database disuntikkan dari luar untuk memudahkan Unit Testing.
     */
    public function __construct($db_connection) {
        $this->db = $db_connection;
        $this->model = new Produk($this->db);
    }

    // --- FITUR KATALOG & DETAIL ---
    
    /**
     * Menghasilkan data katalog terpaginasi untuk View
     */
    public function getKatalogPaginated($kategori = null, $search = null, $page = 1, $perPage = 6, $sort = 'latest') {
        $offset = ($page - 1) * $perPage;
        // PERBAIKAN: Set ke 'true' untuk memfilter hanya produk dengan status 'active'
        $totalItems = $this->model->countAll($search, $kategori, true);
        $totalPages = ceil($totalItems / $perPage);
        // PERBAIKAN: Set ke 'true' untuk memfilter hanya produk dengan status 'active'
        $products = $this->model->getAll($search, $kategori, $perPage, $offset, $sort, true);

        return [
            'products'     => $products,
            'total_pages'  => $totalPages,
            'current_page' => $page,
            'total_items'  => $totalItems
        ];
    }

    /**
     * Merender kartu produk (HTML fragment) untuk reuse di View dan AJAX
     */
    public function renderProductCards($products) {
        if (empty($products)) return '';

        ob_start();
        foreach ($products as $p) {
            $produk_nama     = htmlspecialchars($p['nama_produk'], ENT_QUOTES, 'UTF-8');
            $produk_kategori = htmlspecialchars($p['nama_kategori'] ?? 'Katalog', ENT_QUOTES, 'UTF-8');
            $produk_gambar   = htmlspecialchars($p['gambar_utama'] ?? '', ENT_QUOTES, 'UTF-8');
            $produk_id       = intval($p['id']);
            ?>
            <div class="col-6 col-lg-4 fade-in-item">
                <div class="card product-card h-100">
                    <div class="image-container">
                        <?php if ($p['stok'] > 0 && $p['stok'] < 3) : ?>
                            <div class="position-absolute top-0 end-0 bg-warning text-dark small fw-bold px-2 py-1 m-2" style="font-size: 9px; letter-spacing: 1px; z-index: 3;">LIMITED</div>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>detail/<?= $produk_id ?>">
                            <img src="<?= !empty($produk_gambar) ? BASE_URL . 'assets/img/products/' . $produk_gambar : BASE_URL . 'assets/img/no-image.png' ?>" 
                                 alt="<?= $produk_nama ?>"
                                 onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.png';">
                        </a> 
                        <a href="<?= BASE_URL ?>detail/<?= $produk_id ?>" class="btn-overlay text-capitalize">Lihat Detail</a>
                    </div>
                    <div class="card-body text-center px-0 pb-0">
                        <span class="text-muted text-capitalize" style="font-size: 10px; letter-spacing: 1px;">
                            <?= $produk_kategori ?>
                        </span>
                        <h6 class="fw-bold mb-1 mt-1 text-truncate text-capitalize" style="font-size: 13px; letter-spacing: 0.5px;" title="<?= $produk_nama ?>">
                            <?= $produk_nama ?>
                        </h6>
                        <p class="text-danger fw-bold mb-0" style="font-size: 14px;">
                            <?= formatRupiah($p['harga_jual']) ?>
                        </p>
                        <?php if ($p['stok'] <= 0) : ?>
                            <div class="text-secondary fw-bold text-capitalize mt-1" style="font-size: 9px; letter-spacing: 1px;">[ Sold Out ]</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    public function index($kategori = null, $search = null, $limit = null, $offset = null, $sort = 'latest', $onlyAvailable = false) {
        return $this->model->getAll($search, $kategori, $limit, $offset, $sort, $onlyAvailable);
    }

    public function getSoftDeletedProducts() {
        return $this->model->getSoftDeleted();
    }

    /**
     * Menghapus gambar produk berdasarkan ID gambar.
     */
    public function deleteProductImage($imageId) {
        $imageId = intval($imageId);
        if ($imageId <= 0) return false;
        $res = $this->model->deleteImageById($imageId);
        if ($res) $this->logActivity("DELETE_IMAGE", "Menghapus gambar produk ID: " . $imageId);
        return $res;
    }

    public function reorderImages($imageIds) {
        if (empty($imageIds)) return false;
        $res = $this->model->updateImagesOrder($imageIds);
        if ($res) $this->logActivity("REORDER_IMAGE", "Mengubah urutan gambar produk.");
        return $res;
    }

    public function countSoftDeletedProducts() {
        return $this->model->countSoftDeleted();
    }

    /**
     * Mengambil semua kategori untuk keperluan dropdown di View
     */
    public function getCategories() {
        $sql = "SELECT id, nama_kategori, slug FROM categories ORDER BY nama_kategori ASC";
        $result = mysqli_query($this->db, $sql);
        if (!$result) return []; // Kembalikan array kosong jika query gagal
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function count($kategori = null, $search = null) {
        return $this->model->countAll($search, $kategori);
    }

    public function show($id) {
        return $this->model->getById($id);
    }

    /**
     * Fungsi terpusat untuk menangani unggah gambar produk
     */
    public function handleImageUploads($files) {
        $uploaded_images = [];
        $target_dir = APP_ROOT . '/public/assets/img/products/';
        $allowed = ['jpg', 'jpeg', 'png'];

        // Pastikan direktori target ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        if (!isset($files['name']) || !is_array($files['name'])) return [];

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            // Validasi MIME Type asli
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);

            
            // Gunakan pembanding yang lebih kompatibel dengan PHP versi lama jika diperlukan
            if (strpos($mime, 'image/') !== 0) continue;

            $new_name = uniqid('prod_', true) . '_' . time() . '.jpg'; // Simpan sebagai .jpg untuk kompresi terbaik
            if ($this->compressAndSaveImage($files['tmp_name'][$i], $target_dir . $new_name)) {
                $uploaded_images[] = $new_name;
            }
        }
        return $uploaded_images;
    }

    /**
     * Mengompres, mengubah ukuran, dan menyimpan gambar
     */
    private function compressAndSaveImage($sourcePath, $destinationPath, $quality = 75) {
        // Dapatkan data gambar
        $imgInfo = getimagesize($sourcePath);
        if (!$imgInfo) return false;

        // Buat resource gambar dari file temp
        $imageString = file_get_contents($sourcePath);
        $image = imagecreatefromstring($imageString);
        if (!$image) return false;

        // --- OPTIMASI: RESIZE (Opsional) ---
        $maxWidth = 1200;
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = floor($height * ($maxWidth / $width));
            
            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            
            // Tangani transparansi jika sumbernya PNG/GIF (ubah ke putih)
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $canvas;
        } else {
            // Jika tidak di-resize tapi butuh background putih (untuk PNG transparan)
            $canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
            $image = $canvas;
        }

        // --- OPTIMASI: COMPRESSION ---
        // Simpan sebagai JPEG dengan kualitas tertentu
        $success = imagejpeg($image, $destinationPath, $quality);
        
        // Bebaskan memori
        imagedestroy($image);
        
        return $success;
    }

    /**
     * Validasi data produk sebelum disimpan atau diperbarui.
     * @param array $data Data produk dari form.
     * @param array|null $files Data file gambar dari $_FILES.
     * @param bool $is_update Menandakan apakah ini operasi update.
     * @param bool $has_existing_images Menandakan apakah produk sudah memiliki gambar yang tersimpan (hanya relevan untuk update).
     * @return array Array error messages. Kosong jika valid.
     */
    public function validateProductData($data, $files = null, $is_update = false, $has_existing_images = false) {
        $errors = [];

        if (empty($data['nama_produk'])) {
            $errors[] = 'Nama produk wajib diisi.';
        }

        if (!isset($data['category_id']) || !is_numeric($data['category_id']) || (int)$data['category_id'] <= 0) {
            $errors[] = 'Kategori produk wajib dipilih.';
        }

        if (!isset($data['harga_reguler']) || !is_numeric($data['harga_reguler']) || (int)$data['harga_reguler'] <= 0) {
            $errors[] = 'Harga reguler harus berupa angka positif.';
        }

        if (!isset($data['harga_jual']) || !is_numeric($data['harga_jual']) || (int)$data['harga_jual'] <= 0) {
            $errors[] = 'Harga jual harus berupa angka positif.';
        } elseif ((int)$data['harga_jual'] > (int)$data['harga_reguler']) {
            $errors[] = 'Harga jual tidak boleh lebih tinggi dari harga reguler.';
        }

        if (!in_array($data['varian_ukuran'], ['S', 'M', 'L', 'XL', 'ALL'], true)) {
            $errors[] = 'Ukuran produk tidak valid (S, M, L, XL, ALL).';
        }

        if (!isset($data['stok']) || !is_numeric($data['stok']) || (int)$data['stok'] < 0) {
            $errors[] = 'Stok harus berupa angka nol atau lebih.';
        }

        if (!isset($data['weight']) || !is_numeric($data['weight']) || (int)$data['weight'] <= 0) {
            $errors[] = 'Berat produk harus berupa angka positif.';
        }

        // Validasi gambar:
        // Untuk CREATE: harus ada file yang diupload.
        // Untuk UPDATE: jika tidak ada file baru yang diupload, harus ada gambar yang sudah ada.
        //               jika ada file baru, validasi file baru tersebut.
        $has_new_files = ($files && !empty($files['name'][0]) && $files['error'][0] === UPLOAD_ERR_OK);

        if ($has_new_files && !function_exists('imagecreatefromstring')) {
            $errors[] = 'Fitur pengolahan gambar tidak tersedia di server (GD Library nonaktif). Mohon hubungi administrator.';
        }

        if (!$is_update && !$has_new_files) { // CREATE operation, no files uploaded
            $errors[] = 'Setidaknya satu gambar produk wajib diunggah.';
        } elseif ($is_update && !$has_new_files && !$has_existing_images) { // UPDATE operation, no new files, no existing files
            $errors[] = 'Produk harus memiliki setidaknya satu gambar. Unggah gambar baru atau pastikan ada gambar yang sudah ada.';
        } elseif ($has_new_files) { // New files are being uploaded (either create or update)
            $allowed = ['jpg', 'jpeg', 'png'];
            $max_files = 10;
            if (count($files['name']) > $max_files) {
                $errors[] = "Maksimal {$max_files} gambar diizinkan.";
            }
            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] === UPLOAD_ERR_OK && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $allowed)) {
                    $errors[] = "File gambar '{$name}' memiliki format tidak didukung. Hanya JPG, JPEG, PNG.";
                }
            }
        }

        return $errors;
    }

    // --- FITUR ADMIN: TAMBAH PRODUK ---
    public function store($data, $files = null) {
        $uploaded = [];
        if ($files) {
            $uploaded = $this->handleImageUploads($files);
        }
        // Asumsi validasi sudah dilakukan oleh validateProductData sebelum memanggil store
        $list_gambar = $uploaded; 

        // Menggunakan koneksi dari properti $this->db, bukan global
        mysqli_begin_transaction($this->db);

        try {
            // Data untuk tabel products
            $product_data = [
                'category_id' => $data['category_id'], // Asumsi kategori sudah berupa ID
                'nama_produk' => $data['nama_produk'],
                'slug'        => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['nama_produk']))), // Generate slug
                'deskripsi'   => $data['deskripsi'],
                'brand'       => $data['brand'] ?? null,
                'kondisi'     => $data['kondisi'] ?? null,
                'weight'      => $data['weight'] ?? 500,
                'status'      => $data['status'] ?? 'active'
            ];

            // Data untuk tabel product_variants (varian default)
            $sku = !empty($data['sku']) ? strtoupper(trim($data['sku'])) : $this->generateSKU($data['category_id']);
            
            $variant_data = [
                'sku'           => $sku,
                'varian_warna'  => $data['varian_warna'] ?? null,
                'varian_ukuran' => $data['varian_ukuran'] ?? null,
                'harga_reguler' => $data['harga_reguler'],
                'harga_jual'    => $data['harga_jual'],
                'stok'          => $data['stok']
            ];

            $insert_result = $this->model->insertProduct($product_data, $variant_data, $list_gambar);
            if (!$insert_result) {
                throw new Exception("Gagal menyimpan data produk.");
            }

            // Catat penambahan stok awal ke stock_logs jika stok > 0
            if ($data['stok'] > 0) {
                $stmt_log = mysqli_prepare($this->db, "INSERT INTO stock_logs (product_variant_id, user_id, type, jumlah, keterangan) VALUES (?, ?, 'in', ?, ?)");
                $user = auth::getUser();
                $userId = $user['id'] ?? null;
                $keterangan = "Penambahan produk baru: " . $data['nama_produk'] . " (Stok Awal)";
                mysqli_stmt_bind_param($stmt_log, 'iiis', $insert_result['variant_id'], $userId, $data['stok'], $keterangan);
                mysqli_stmt_execute($stmt_log);
            }
            mysqli_commit($this->db);
            $this->logActivity("TAMBAH_PRODUK", "Berhasil menambah produk baru: " . $data['nama_produk']);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            error_log("Store Product Error: " . $e->getMessage());
            foreach ($uploaded as $img) {
                // Hapus file yang telah diupload jika rollback, gunakan safeUnlink
                $target_dir = APP_ROOT . '/public/assets/img/products/';
                if (function_exists('safeUnlink')) {
                    safeUnlink($target_dir, $img);
                } else {
                    $p = $target_dir . $img;
                    if (file_exists($p)) @unlink($p);
                }
            }
            return false;
        }
    }

    // --- FITUR ADMIN: UPDATE PRODUK ---
    public function update($id, $variant_id, $data, $files = null) {
        $id = intval($id);
        $variant_id = intval($variant_id);
        
        // Ambil data lama untuk mengecek perubahan stok
        $old_product = $this->model->getById($id);
        $old_stock = (int)($old_product['stok'] ?? 0);

        // Data untuk tabel products
        $product_data = [
            'category_id' => $data['category_id'], // Asumsi kategori sudah berupa ID
            'nama_produk' => $data['nama_produk'],
            'slug'        => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['nama_produk']))), // Generate slug
            'deskripsi'   => $data['deskripsi'],
            'brand'       => $data['brand'] ?? null,
            'kondisi'     => $data['kondisi'] ?? null,
            'weight'      => $data['weight'] ?? 500,
            'status'      => $data['status'] ?? 'active'
        ];

        // Data untuk tabel product_variants
        $sku = !empty($data['sku']) ? strtoupper(trim($data['sku'])) : $this->generateSKU($data['category_id']);

        $variant_data = [
            'sku'           => $sku,
            'varian_warna'  => $data['varian_warna'] ?? null,
            'varian_ukuran' => $data['varian_ukuran'] ?? null,
            'harga_reguler' => $data['harga_reguler'],
            'harga_jual'    => $data['harga_jual'],
            'stok'          => $data['stok']
        ];

        $image_data = null;
        if ($files && !empty($files['name'][0])) {
            $new_images = $this->handleImageUploads($files);
            if (!empty($new_images)) {
                $image_data = $new_images;
                // Hapus entry database lama sebelum memasukkan yang baru
                $this->deleteOldGalleryFiles($id); 
            }
        }

        $result = $this->model->updateProduct($id, $product_data, $variant_id, $variant_data, $image_data);
        if ($result) {
            // Jika stok berubah, catat ke stock_logs
            $new_stock = (int)$data['stok'];
            if ($old_stock !== $new_stock) {
                $diff = $new_stock - $old_stock; // Positif jika nambah, negatif jika kurang
                $log_type = ($diff > 0) ? 'in' : 'adjustment'; // Sesuaikan dengan ENUM DB: 'in' atau 'adjustment'
                $jumlah_log = abs($diff); // Jumlah yang berubah selalu positif

                $stmt_log = mysqli_prepare($this->db, "INSERT INTO stock_logs (product_variant_id, user_id, type, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)");
                $user = auth::getUser();
                $userId = $user['id'] ?? null;
                $keterangan = "Penyesuaian manual oleh admin (Dari {$old_stock} menjadi {$new_stock})";
                
                mysqli_stmt_bind_param($stmt_log, 'iisis', $variant_id, $userId, $log_type, $jumlah_log, $keterangan);
                mysqli_stmt_execute($stmt_log);
                mysqli_stmt_close($stmt_log);
            }

            $this->logActivity("UPDATE_PRODUK", "Berhasil mengubah produk ID: $id (Nama: " . $data['nama_produk'] . ")");
        }
        
        return $result;
    }

    // --- HELPER METODE ---
    private function deleteOldMainImage($product_id) {
        // Ambil nama foto utama dari tabel product_images
        $stmt = mysqli_prepare($this->db, "SELECT nama_foto FROM product_images WHERE product_id = ? AND is_primary = TRUE LIMIT 1");
        if (!$stmt) return;
        mysqli_stmt_bind_param($stmt, 'i', $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && !empty($row['nama_foto'])) {
            $target_dir = APP_ROOT . '/public/assets/img/products/';
            $file_name = $row['nama_foto'];
            if (function_exists('safeUnlink')) {
                safeUnlink($target_dir, $file_name);
            } else {
                $path = $target_dir . $file_name;
                if (file_exists($path)) @unlink($path);
            }
        }
    }

    private function deleteOldGalleryFiles($produk_id) {
        $stmt = mysqli_prepare($this->db, "SELECT nama_foto FROM product_images WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $produk_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $target_dir = APP_ROOT . '/public/assets/img/products/';
            $file_name = $row['nama_foto'];
            if (function_exists('safeUnlink')) {
                safeUnlink($target_dir, $file_name);
            } else {
                $path = $target_dir . $file_name;
                if (file_exists($path)) @unlink($path);
            }
        }
        mysqli_stmt_close($stmt);
    }

    private function deleteOldGalleryEntries($produk_id) {
        $stmt = mysqli_prepare($this->db, "DELETE FROM product_images WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $produk_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    }

    /**
     * Menghasilkan SKU otomatis berdasarkan kategori
     */
    private function generateSKU($category_id) {
        $prefix = "TK"; // Thrift King
        
        // Ambil inisial kategori
        $stmt = mysqli_prepare($this->db, "SELECT nama_kategori FROM categories WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $category_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cat = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        $catInit = $cat ? strtoupper(substr($cat['nama_kategori'], 0, 3)) : "GEN";
        $random = strtoupper(substr(md5(uniqid()), 0, 5));
        $timestamp = date('y');

        return "{$prefix}-{$catInit}-{$timestamp}-{$random}";
    }

    public function destroy($id) {
        $id = intval($id);
        $produk = $this->model->getById($id);
        
        if ($produk) {
            $res = $this->model->delete($id);
            if ($res) {
                $this->logActivity("HAPUS_PRODUK", "Menghapus produk ID: $id (Nama: " . $produk['nama_produk'] . ")");
            }
            return $res;
        }
        return false;
    }

    public function restore($id) {
        $id = intval($id);
        $produk = $this->model->getByIdIncludingSoftDeleted($id); // Perlu method baru di model
        if ($produk) {
            $res = $this->model->restore($id);
            if ($res) {
                $this->logActivity("RESTORE_PRODUK", "Mengembalikan produk ID: $id (Nama: " . $produk['nama_produk'] . ")");
            }
            return $res;
        }
        return false;
    }

    public function forceDelete($id) {
        $id = intval($id);
        $produk = $this->model->getByIdIncludingSoftDeleted($id); // Perlu method baru di model
        
        // BUG FIX: Hapus file gambar fisik sebelum menghapus data dari DB
        if ($produk) {
            $this->deleteOldGalleryFiles($id);
        }
        $res = $this->model->forceDelete($id);
        $this->logActivity("FORCE_DELETE_PRODUK", "Menghapus permanen produk ID: $id (Nama: " . ($produk['nama_produk'] ?? 'N/A') . ")");
        return $res;
    }

    /**
     * Membersihkan file gambar di folder assets yang tidak memiliki referensi di database.
     */
    public function cleanupUnusedImages() {
        $target_dir = APP_ROOT . '/public/assets/img/products/';
        
        if (!is_dir($target_dir)) return 0;

        // 1. Ambil semua file dari folder
        $files_in_folder = scandir($target_dir);
        
        // Filter untuk menghilangkan . , .. dan file sistem/placeholder
        $files_in_folder = array_filter($files_in_folder, function($file) {
            return !in_array($file, ['.', '..', 'no-image.png', 'placeholder.png', '.gitignore', 'index.php']);
        });

        // 2. Ambil semua nama file yang terdaftar di database
        $sql = "SELECT nama_foto FROM product_images";
        $result = mysqli_query($this->db, $sql);
        $db_images = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $db_filenames = array_column($db_images, 'nama_foto');

        // 3. Cari perbedaan (file ada di folder tapi tidak ada di DB)
        $unused_files = array_diff($files_in_folder, $db_filenames);

        $deleted_count = 0;
        foreach ($unused_files as $file) {
            if (function_exists('safeUnlink')) {
                if (safeUnlink($target_dir, $file)) {
                    $deleted_count++;
                }
            } else {
                $path = $target_dir . $file;
                if (file_exists($path) && is_file($path)) {
                    if (@unlink($path)) {
                        $deleted_count++;
                    }
                }
            }
        }

        if ($deleted_count > 0) {
            $this->logActivity("CLEANUP_IMAGES", "Sistem membersihkan $deleted_count file gambar yatim yang tidak terpakai.");
        }

        return $deleted_count;
    }

    /**
     * Mengambil histori stok dengan filter rentang tanggal dan tipe log
     */
    public function getStockHistory($filters = []) {
        $sql = "SELECT sl.*, p.nama_produk, pv.varian_warna, pv.varian_ukuran, pv.sku, u.nama as admin_nama
                FROM stock_logs sl
                JOIN product_variants pv ON sl.product_variant_id = pv.id
                JOIN products p ON pv.product_id = p.id
                LEFT JOIN users u ON sl.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($filters['type'])) {
            $sql .= " AND sl.type = ?";
            $params[] = $filters['type'];
            $types .= "s";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(sl.created_at) >= ?";
            $params[] = $filters['start_date'];
            $types .= "s";
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(sl.created_at) <= ?";
            $params[] = $filters['end_date'];
            $types .= "s";
        }

        $sql .= " ORDER BY sl.created_at DESC";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    }

    public function countRecentStockLogs() {
        $sql = "SELECT COUNT(*) as total FROM stock_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $result = mysqli_query($this->db, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['total'] ?? 0;
    }

    /**
     * Menghapus log aktivitas yang sudah lebih dari X hari agar database tetap ringan.
     * @param int $days Batas hari penyimpanan log (default 30 hari).
     * @return bool
     */
    public function cleanupOldActivityLogs($days = 30) {
        $sql = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $days);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }
        return false;
    }

    /**
     * Menghapus seluruh log aktivitas secara permanen.
     * @return bool
     */
    public function deleteAllActivityLogs() {
        $sql = "DELETE FROM activity_logs";
        if (mysqli_query($this->db, $sql)) {
            // Catat aksi ini sebagai log pertama setelah pembersihan
            $this->logActivity("CLEAR_ALL_LOGS", "Administrator melakukan pembersihan total seluruh riwayat log aktivitas.");
            return true;
        }
        return false;
    }

}

// ... (sesuaikan fungsi lainnya seperti deleteOldGalleryEntries, dll dengan $this->db)
