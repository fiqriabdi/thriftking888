<?php
require_once APP_ROOT . '/helpers/Loggable.php';
require_once APP_ROOT . '/Models/SettingsModel.php';

class SettingsController {
    use Loggable;
    private $db;
    private $model;

    public function __construct($db_connection) {
        $this->db = $db_connection;
        $this->model = new SettingsModel($this->db);
    }

    public function index() {
        return $this->model->getSettings();
    }

    /**
     * Menghapus logo toko.
     * @return array Hasil operasi.
     */
    public function deleteLogo() {
        $current_settings = $this->index();
        $logo_to_delete = $current_settings['logo'] ?? null;

        if (empty($logo_to_delete)) {
            return ['success' => false, 'errors' => ['Tidak ada logo untuk dihapus.']];
        }

        // Update database terlebih dahulu, set kolom logo menjadi NULL
        if ($this->model->updateSettings(['logo' => null])) {
            // Jika DB berhasil diupdate, hapus file fisik
            $uploadDir = APP_ROOT . '/public/assets/img/logo/';
            safeUnlink($uploadDir, $logo_to_delete);
            $this->logActivity("DELETE_LOGO", "Admin menghapus logo toko.");
            return ['success' => true, 'message' => 'Logo toko berhasil dihapus.'];
        }
        return ['success' => false, 'errors' => ['Gagal menghapus logo dari database.']];
    }

    public function update($post_data, $files) {
        $errors = [];
        $data = [
            'nama_toko' => trim($post_data['nama_toko'] ?? ''),
            'email' => filter_var(trim($post_data['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'no_hp' => trim($post_data['no_hp'] ?? ''),
            'alamat' => trim($post_data['alamat'] ?? ''),


            //'instagram_url' => filter_var(trim($post_data['instagram_url'] ?? ''), FILTER_SANITIZE_URL),
            //'facebook_url' => filter_var(trim($post_data['facebook_url'] ?? ''), FILTER_SANITIZE_URL),
            //'tiktok_url' => filter_var(trim($post_data['tiktok_url'] ?? ''), FILTER_SANITIZE_URL),
        ];

        if (empty($data['nama_toko'])) $errors[] = 'Nama Toko wajib diisi.';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
        if (empty($data['no_hp'])) $errors[] = 'Nomor WhatsApp wajib diisi.';

        // Handle file upload
        $old_settings = $this->index();
        $old_logo = $old_settings['logo'] ?? null;

        if (isset($files['logo']) && $files['logo']['error'] === UPLOAD_ERR_OK) {
            $file = $files['logo'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            $file_validation = validateFile($file, $allowed_mimes, $max_size);
            if (!$file_validation['valid']) {
                $errors[] = $file_validation['message'];
            } else {
                $uploadDir = APP_ROOT . '/public/assets/img/logo/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_filename = 'logo_' . time() . '.' . $ext;
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $new_filename)) {
                    $data['logo'] = $new_filename;
                    if ($old_logo) {
                        safeUnlink($uploadDir, $old_logo);
                    }
                } else {
                    $errors[] = 'Gagal mengunggah logo baru.';
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        if ($this->model->updateSettings($data)) {
            $this->logActivity("UPDATE_SETTINGS", "Admin memperbarui pengaturan toko.");
            return ['success' => true, 'message' => 'Pengaturan toko berhasil diperbarui.'];
        } else {
            return ['success' => false, 'errors' => ['Gagal menyimpan pengaturan ke database.']];
        }
    }
}