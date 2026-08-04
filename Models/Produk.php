<?php
/**
 * File: Models/produk.php
 * Model Produk - Versi Enterprise Refactored
 */
require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../helpers/Security.php';

class Produk extends BaseModel {

    public function getAll($search = null, $kategori = null, $limit = null, $offset = null, $sort = 'latest', $onlyActive = false) {
        $sql = "SELECT p.*, pv.harga_jual, pv.stok, pv.varian_ukuran, pv.varian_warna, pi.nama_foto as gambar_utama, c.nama_kategori 
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.deleted_at IS NULL";

        $params = [];
        $types = '';

        if ($onlyActive) {
            $sql .= " AND p.status = 'active'";
        }

        if ($kategori) {
            $sql .= " AND c.slug = ?";
            $params[] = $kategori;
            $types .= 's';
        }
        if ($search) {
            $sql .= " AND (p.nama_produk LIKE ? OR c.nama_kategori LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 's';
            $types .= 's';
        }

        // Sanitasi parameter sorting (Order By tidak bisa menggunakan Prepared Statement)
        $valid_sorts = [
            'latest' => 'p.created_at DESC',
            'price_asc' => 'pv.harga_jual ASC',
            'price_desc' => 'pv.harga_jual DESC'
        ];
        $orderBy = $valid_sorts[$sort] ?? $valid_sorts['latest'];
        $sql .= ' GROUP BY p.id ORDER BY ' . $orderBy;

        if ($limit) {
            $sql .= ' LIMIT ?' . ($offset !== null ? ' OFFSET ?' : '');
            $params[] = (int)$limit;
            $types .= 'i';
            
            if ($offset !== null) {
                $params[] = (int)$offset;
                $types .= 'i';
            }
        }

        if ($stmt = mysqli_prepare($this->db, $sql)) {
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        }
        return [];
    }

    /**
     * Mengambil semua produk yang di-soft delete
     */
    public function getSoftDeleted() {
        $sql = "SELECT p.*, pv.harga_jual, pv.stok, pi.nama_foto as gambar_utama, c.nama_kategori 
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.deleted_at IS NOT NULL
                GROUP BY p.id ORDER BY p.deleted_at DESC";
        
        if ($stmt = mysqli_prepare($this->db, $sql)) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        }
        return [];
    }

    public function countSoftDeleted() {
        $sql = "SELECT COUNT(*) as total FROM products WHERE deleted_at IS NOT NULL";
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return 0;
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row['total'] ?? 0;
    }

    /**
     * Mengambil detail lengkap produk termasuk yang di-soft delete
     */
    public function getByIdIncludingSoftDeleted($id) {
        $id = intval($id);
        $sql = "SELECT p.*, pv.id as variant_id, pv.sku, pv.harga_reguler, pv.harga_jual, pv.stok, pv.varian_ukuran, pv.varian_warna, pi.nama_foto as gambar_utama, c.nama_kategori FROM products p LEFT JOIN product_variants pv ON p.id = pv.product_id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0 LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? LIMIT 1";
        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    /**
     * Menghitung total produk berdasarkan filter untuk keperluan pagination
     */
    public function countAll($search = null, $kategori = null, $onlyActive = false) {
        $sql = "SELECT COUNT(DISTINCT p.id) as total 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.deleted_at IS NULL";

        $params = [];
        $types = '';

        if ($onlyActive) {
            $sql .= " AND p.status = 'active'";
        }

        if ($kategori) {
            $sql .= " AND c.slug = ?";
            $params[] = $kategori;
            $types .= 's';
        }
        if ($search) {
            $sql .= " AND (p.nama_produk LIKE ? OR c.nama_kategori LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types .= 's';
            $types .= 's';
        }

        $stmt = mysqli_prepare($this->db, $sql);
        if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        return $row['total'] ?? 0;
    }

    /**
     * Mengambil detail lengkap produk beserta varian utama
     */
    public function getById($id) {
        $id = intval($id);
        $sql = "SELECT p.*, pv.id as variant_id, pv.sku, pv.harga_reguler, pv.harga_jual, 
                       pv.stok, pv.varian_ukuran, pv.varian_warna, pi.nama_foto as gambar_utama,
                       c.nama_kategori
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.deleted_at IS NULL
                LIMIT 1";

        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return null;
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    /**
     * Digunakan oleh addToCart di Controller
     */
    public function getByIdWithVariantAndImage($product_id, $variant_id = null) {
        $product_id = intval($product_id);
        $sql = "SELECT p.id, p.nama_produk, pv.id as variant_id, pv.harga_jual, pv.stok, 
                       pv.varian_warna, pv.varian_ukuran, pi.nama_foto as gambar_utama
                FROM products p
                JOIN product_variants pv ON p.id = pv.product_id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.sort_order = 0
                WHERE p.id = ? AND p.deleted_at IS NULL";
        
        $params = [$product_id];
        $types = 'i';

        if ($variant_id !== null) { // Cek secara eksplisit untuk null
            $sql .= " AND pv.id = ?";
            $params[] = intval($variant_id);
            $types .= 'i';
        }
        $sql .= " LIMIT 1";

        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) {
            error_log("Prepared statement failed in getByIdWithVariantAndImage: " . mysqli_error($this->db));
            return null;
        }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $data;
    }

    public function insertProduct($product_data, $variant_data, $images) {
        mysqli_begin_transaction($this->db);
        try {
            // 1. Insert ke Products
            $stmt = mysqli_prepare($this->db, "INSERT INTO products (category_id, nama_produk, slug, deskripsi, brand, kondisi, weight, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isssssis', $product_data['category_id'], $product_data['nama_produk'], $product_data['slug'], $product_data['deskripsi'], $product_data['brand'], $product_data['kondisi'], $product_data['weight'], $product_data['status']);
            mysqli_stmt_execute($stmt);
            $product_id = mysqli_insert_id($this->db);

            // 2. Insert Varian (Default)
            $stmt_v = mysqli_prepare($this->db, "INSERT INTO product_variants (product_id, sku, varian_warna, varian_ukuran, harga_reguler, harga_jual, stok) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_v, 'isssiii', $product_id, $variant_data['sku'], $variant_data['varian_warna'], $variant_data['varian_ukuran'], $variant_data['harga_reguler'], $variant_data['harga_jual'], $variant_data['stok']);
            mysqli_stmt_execute($stmt_v);
            $variant_id = mysqli_insert_id($this->db);

            // 3. Insert Images
            $stmt_i = mysqli_prepare($this->db, "INSERT INTO product_images (product_id, nama_foto, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            foreach ($images as $index => $foto) {
                $is_primary = ($index === 0) ? 1 : 0;
                mysqli_stmt_bind_param($stmt_i, 'isii', $product_id, $foto, $is_primary, $index);
                mysqli_stmt_execute($stmt_i);
            }

            mysqli_commit($this->db);
            return ['product_id' => $product_id, 'variant_id' => $variant_id];
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            return false;
        }
    }

    public function updateProduct($id, $product_data, $variant_id, $variant_data, $image_data = null) {
        mysqli_begin_transaction($this->db);
        try {
            // Update Products
            $stmt = mysqli_prepare($this->db, "UPDATE products SET category_id = ?, nama_produk = ?, slug = ?, deskripsi = ?, brand = ?, kondisi = ?, weight = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'isssssisi', $product_data['category_id'], $product_data['nama_produk'], $product_data['slug'], $product_data['deskripsi'], $product_data['brand'], $product_data['kondisi'], $product_data['weight'], $product_data['status'], $id);
            mysqli_stmt_execute($stmt);

            // Update Varian
            $stmt_v = mysqli_prepare($this->db, "UPDATE product_variants SET sku = ?, varian_warna = ?, varian_ukuran = ?, harga_reguler = ?, harga_jual = ?, stok = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_v, 'sssiiii', $variant_data['sku'], $variant_data['varian_warna'], $variant_data['varian_ukuran'], $variant_data['harga_reguler'], $variant_data['harga_jual'], $variant_data['stok'], $variant_id);
            mysqli_stmt_execute($stmt_v);

            // Update Images jika ada yang baru
            if ($image_data) {
                $stmt_del_imgs = mysqli_prepare($this->db, "DELETE FROM product_images WHERE product_id = ?");
                if ($stmt_del_imgs) {
                    mysqli_stmt_bind_param($stmt_del_imgs, 'i', $id);
                    mysqli_stmt_execute($stmt_del_imgs);
                    mysqli_stmt_close($stmt_del_imgs);
                }
                $stmt_i = mysqli_prepare($this->db, "INSERT INTO product_images (product_id, nama_foto, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($image_data as $index => $foto) {
                    $is_primary = ($index === 0) ? 1 : 0;
                    mysqli_stmt_bind_param($stmt_i, 'isii', $id, $foto, $is_primary, $index);
                    mysqli_stmt_execute($stmt_i);
                }
            }

            mysqli_commit($this->db);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            return false;
        }
    }

    public function delete($id) {
        $id = intval($id);
        // Menggunakan Soft Delete sesuai skema SQL
        $sql = "UPDATE products SET deleted_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }

    /**
     * Mengembalikan produk yang di-soft delete
     */
    public function restore($id) {
        $id = intval($id);
        $sql = "UPDATE products SET deleted_at = NULL WHERE id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }

    /**
     * Menghapus produk secara permanen
     */
    public function forceDelete($id) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        return mysqli_stmt_execute($stmt);
    }

    /**
     * Memperbarui urutan gambar berdasarkan ID
     */
    public function updateImagesOrder($imageOrders) {
        mysqli_begin_transaction($this->db);
        try {
            $stmt = mysqli_prepare($this->db, "UPDATE product_images SET sort_order = ?, is_primary = ? WHERE id = ?");
            foreach ($imageOrders as $index => $imageId) {
                $isPrimary = ($index === 0) ? 1 : 0;
                mysqli_stmt_bind_param($stmt, 'iii', $index, $isPrimary, $imageId);
                mysqli_stmt_execute($stmt);
            }
            mysqli_commit($this->db);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            return false;
        }
    }

    /**
     * Menghapus gambar produk berdasarkan ID gambar.
     * Juga menghapus file fisik dan memperbarui urutan gambar yang tersisa.
     * @param int $imageId ID dari gambar yang akan dihapus.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function deleteImageById($imageId) {
        mysqli_begin_transaction($this->db);
        try {
            // 1. Ambil detail gambar untuk menghapus file fisik
            $stmt = mysqli_prepare($this->db, "SELECT product_id, nama_foto FROM product_images WHERE id = ?");
            if (!$stmt) throw new Exception("Gagal menyiapkan statement untuk mengambil detail gambar.");
            mysqli_stmt_bind_param($stmt, 'i', $imageId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $image_info = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$image_info) {
                throw new Exception("Gambar tidak ditemukan.");
            }

            $product_id = $image_info['product_id'];
            $file_name = $image_info['nama_foto'];
            $target_dir = APP_ROOT . '/public/assets/img/products/';

            // Validate filename to prevent path traversal
            if (!function_exists('isSafeFilename') || !isSafeFilename($file_name)) {
                throw new Exception("Nama file gambar tidak valid: " . $file_name);
            }

            // Use realpath to ensure the file is inside the target directory
            $file_path = $target_dir . $file_name;
            $realDir = realpath($target_dir);
            $realFile = realpath($file_path);
            if ($realDir === false || $realFile === false || strpos($realFile, $realDir) !== 0) {
                throw new Exception("Invalid file path for image: " . $file_name);
            }

            // 2. Hapus file fisik
            if (is_file($realFile)) {
                if (!safeUnlink($target_dir, $file_name)) {
                    throw new Exception("Gagal menghapus file gambar: " . $realFile);
                }
            }

            // 3. Hapus entri dari database
            $stmt = mysqli_prepare($this->db, "DELETE FROM product_images WHERE id = ?");
            if (!$stmt) throw new Exception("Gagal menyiapkan statement untuk menghapus entri gambar.");
            mysqli_stmt_bind_param($stmt, 'i', $imageId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // 4. Setelah penghapusan, panggil updateImagesOrder untuk mengatur ulang sort_order dan is_primary
            // Ini akan mengambil semua gambar yang tersisa untuk produk ini dan mengurutkannya kembali.
            $stmt = mysqli_prepare($this->db, "SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
            if (!$stmt) throw new Exception("Gagal menyiapkan statement untuk mengambil ID gambar yang tersisa.");
            mysqli_stmt_bind_param($stmt, 'i', $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $remaining_image_ids = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $remaining_image_ids[] = $row['id'];
            }
            mysqli_stmt_close($stmt);

            if (!empty($remaining_image_ids)) {
                $this->updateImagesOrder($remaining_image_ids);
            }

            mysqli_commit($this->db);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->db);
            error_log("Error deleting product image: " . $e->getMessage());
            return false;
        }
    }
}