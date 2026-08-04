<?php
require_once APP_ROOT . '/helpers/Security.php';

require_once APP_ROOT . '/Controllers/Admin/UserController.php';

class ProfilController {
    private $userController;

    public function __construct($db_connection) {
        $this->userController = new UserController($db_connection);
    }

    /**
     * Memproses pembaruan data profil pengguna.
     * @param int $userId
     * @param array $postData
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateProfil($userId, $postData) {
        $nama = trim($postData['nama'] ?? '');
        $no_hp = trim($postData['no_hp'] ?? '');
        $alamat = trim($postData['alamat'] ?? '');

        if (empty($nama)) {
            return ['success' => false, 'message' => 'Nama lengkap tidak boleh kosong.'];
        }

        $currentUser = $this->userController->show($userId);
        if ($this->userController->update($userId, $nama, $currentUser['email'], 'pelanggan', $no_hp, $alamat, null)) {
            $_SESSION['user']['nama'] = $nama;
            $_SESSION['user']['no_hp'] = $no_hp;
            $_SESSION['user']['alamat'] = $alamat;
            return ['success' => true, 'message' => 'Profil berhasil diperbarui.'];
        }

        return ['success' => false, 'message' => 'Gagal memperbarui profil. Terjadi kesalahan data.'];
    }

    /**
     * Memproses pembaruan kata sandi pengguna.
     * @param int $userId
     * @param array $postData
     * @return array ['success' => bool, 'message' => string]
     */
    public function updatePassword($userId, $postData) {
        $new_pass = $postData['new_pass'] ?? '';
        $confirm_pass = $postData['confirm_pass'] ?? '';

        if (strlen($new_pass) < 8) {
            return ['success' => false, 'message' => 'Password minimal 8 karakter.'];
        } elseif (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
            return ['success' => false, 'message' => 'Password harus mengandung huruf besar dan angka.'];
        } elseif ($new_pass !== $confirm_pass) {
            return ['success' => false, 'message' => 'Konfirmasi password tidak cocok.'];
        }

        $currentUser = $this->userController->show($userId);
        if ($this->userController->update($userId, $currentUser['nama'], $currentUser['email'], 'pelanggan', $currentUser['no_hp'], $currentUser['alamat'], $new_pass)) {
            return ['success' => true, 'message' => 'Password berhasil diganti.'];
        }

        return ['success' => false, 'message' => 'Gagal memperbarui password.'];
    }

    /**
     * Menghapus foto profil pengguna.
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteProfilePicture($userId) {
        // 1. Ambil data user saat ini untuk mendapatkan nama file foto
        $currentUser = $this->userController->show($userId);
        $photoToDelete = $currentUser['foto_profil'] ?? null;

        if (empty($photoToDelete)) {
            return ['success' => false, 'message' => 'Tidak ada foto profil untuk dihapus.'];
        }

        // 2. Update database, set kolom foto_profil menjadi NULL
        if ($this->userController->updateProfilePicture($userId, null)) {
            // 3. Hapus file fisik dari storage
            $targetDir = APP_ROOT . '/public/storage/profil/';
            safeUnlink($targetDir, $photoToDelete);

            // 4. Update sesi
            $_SESSION['user']['foto_profil'] = null;
            
            return ['success' => true, 'message' => 'Foto profil berhasil dihapus.'];
        } else {
            return ['success' => false, 'message' => 'Gagal menghapus foto profil dari database.'];
        }
    }
    /**
     * Memproses unggahan foto profil.
     * @param int $userId
     * @param array $fileData Data dari $_FILES['foto_profil']
     * @return array ['success' => bool, 'message' => string]
     */
    public function processProfilePictureUpload($userId, $fileData) {
        // 1. Validasi File
        $file_validation = validateFile($fileData, ['image/jpeg', 'image/png'], 2 * 1024 * 1024); // Max 2MB
        if (!$file_validation['valid']) {
            return ['success' => false, 'message' => $file_validation['message']];
        }

        // 2. Ambil data user saat ini untuk mendapatkan nama file foto lama
        $currentUser = $this->userController->show($userId);
        $oldPhoto = $currentUser['foto_profil'] ?? null;

        // 3. Siapkan direktori dan nama file baru
        $targetDir = APP_ROOT . '/public/storage/profil/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $newFilename = "user_{$userId}_" . time() . "." . $extension;
        $destinationPath = $targetDir . $newFilename;

        // 4. Pindahkan file yang diunggah
        if (!move_uploaded_file($fileData['tmp_name'], $destinationPath)) {
            return ['success' => false, 'message' => 'Gagal memindahkan file yang diunggah.'];
        }

        // 5. Update database dengan nama file baru
        if ($this->userController->updateProfilePicture($userId, $newFilename)) {
            if ($oldPhoto) safeUnlink($targetDir, $oldPhoto);
            $_SESSION['user']['foto_profil'] = $newFilename;
            return ['success' => true, 'message' => 'Foto profil berhasil diperbarui.'];
        } else {
            safeUnlink($targetDir, $newFilename);
            return ['success' => false, 'message' => 'Gagal memperbarui foto profil di database.'];
        }
    }
}