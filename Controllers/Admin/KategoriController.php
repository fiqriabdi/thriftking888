<?php
require_once APP_ROOT . '/Config/konstanta.php';
require_once APP_ROOT . '/helpers/Format.php';
require_once APP_ROOT . '/Middleware/auth.php';

class KategoriController {
    private $db;

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function index() {
        $sql = "SELECT * FROM categories ORDER BY nama_kategori ASC";
        $result = mysqli_query($this->db, $sql);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function show($id) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "SELECT * FROM categories WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function store($nama) {
        $nama = trim($nama);
        if (empty($nama)) return "Nama kategori tidak boleh kosong.";

        $slug = slugify($nama);
        
        // Cek apakah slug sudah ada
        $check = mysqli_prepare($this->db, "SELECT id FROM categories WHERE slug = ?");
        mysqli_stmt_bind_param($check, 's', $slug);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            return "Kategori dengan nama serupa sudah ada.";
        }
        mysqli_stmt_close($check);

        $stmt = mysqli_prepare($this->db, "INSERT INTO categories (nama_kategori, slug) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $nama, $slug);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success ? true : "Gagal menyimpan kategori.";
    }

    public function update($id, $nama) {
        $id = intval($id);
        $nama = trim($nama);
        if (empty($nama)) return "Nama kategori tidak boleh kosong.";

        $slug = slugify($nama);
        
        // Cek apakah slug sudah ada di kategori lain
        $check = mysqli_prepare($this->db, "SELECT id FROM categories WHERE slug = ? AND id != ?");
        mysqli_stmt_bind_param($check, 'si', $slug, $id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            return "Kategori dengan nama serupa sudah ada.";
        }
        mysqli_stmt_close($check);

        $stmt = mysqli_prepare($this->db, "UPDATE categories SET nama_kategori = ?, slug = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $nama, $slug, $id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success ? true : "Gagal memperbarui kategori.";
    }

    public function destroy($id) {
        $id = intval($id);
        
        // Cek apakah kategori sedang digunakan oleh produk
        $check = mysqli_prepare($this->db, "SELECT id FROM products WHERE category_id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, 'i', $id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            mysqli_stmt_close($check);
            return false; // Tidak boleh dihapus jika masih ada produk
        }
        mysqli_stmt_close($check);

        $stmt = mysqli_prepare($this->db, "DELETE FROM categories WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }
}