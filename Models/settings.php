<?php
// c:\xampp\htdocs\thriftking888\Models\settings.php
require_once __DIR__ . '/basemodel.php';

class SettingsModel extends BaseModel {

    /**
     * Mengambil pengaturan global toko dari database.
     * Selalu mengembalikan array, meskipun kosong.
     *
     * @return array Pengaturan toko.
     */
    public function getSettings() {
        try {
            $stmt = mysqli_prepare($this->db, "SELECT * FROM settings WHERE id = 1 LIMIT 1");
            if (!$stmt) {
                throw new Exception("Gagal menyiapkan query: " . mysqli_error($this->db));
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            // Mengembalikan data jika ada, atau array kosong jika tidak ada. Mencegah error 'null'.
            return $data ?: [];
        } catch (Exception $e) {
            error_log("Error in getSettings: " . $e->getMessage());
            return []; // Kembalikan array kosong jika terjadi error
        }
    }

    /**
     * Memperbarui pengaturan global toko.
     *
     * @param array $data Data pengaturan yang akan disimpan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateSettings($data) {
        try {
            $stmt = mysqli_prepare($this->db, "UPDATE settings SET nama_toko=?, email=?, no_hp=?, alamat=?, logo=? WHERE id = 1");
            if (!$stmt) {
                throw new Exception("Gagal menyiapkan query update: " . mysqli_error($this->db));
            }
            mysqli_stmt_bind_param($stmt, 'sssss', $data['nama_toko'], $data['email'], $data['no_hp'], $data['alamat'], $data['logo']);
            return mysqli_stmt_execute($stmt);
        } catch (Exception $e) {
            error_log("Error in updateSettings: " . $e->getMessage());
            return false;
        }
    }
}
