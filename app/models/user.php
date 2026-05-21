<?php
/**
 * File: app/models/user.php
 * User Model dengan Password Hashing Security
 */

class user {
    private $conn;
    private $table = 'user';

    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Register user baru dengan password yang di-hash
     */
    public function register($nama, $email, $password, $no_hp = '', $alamat = '') {
        // Validasi input
        if (empty($nama) || empty($email) || empty($password)) {
            return [
                'success' => false,
                'error' => 'Nama, email, dan password tidak boleh kosong'
            ];
        }

        if (strlen($password) < 6) {
            return [
                'success' => false,
                'error' => 'Password minimal 6 karakter'
            ];
        }

        // Cek email sudah terdaftar
        $stmt = mysqli_prepare($this->conn, "SELECT id FROM $this->table WHERE email = ?");
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error'];
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);

        if (mysqli_num_rows($result) > 0) {
            return [
                'success' => false,
                'error' => 'Email sudah terdaftar'
            ];
        }

        // Hash password dengan BCRYPT (algorithm terbaik)
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12  // Cost factor 12 = balance antara security & speed
        ]);

        // Insert user baru
        $stmt = mysqli_prepare($this->conn,
            "INSERT INTO $this->table 
             (nama, email, password, no_hp, alamat, role, created_at) 
             VALUES (?, ?, ?, ?, ?, 'pelanggan', NOW())");

        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error'];
        }

        mysqli_stmt_bind_param(
            $stmt,
            'sssss',
            $nama,
            $email,
            $hashed_password,
            $no_hp,
            $alamat
        );

        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);

            return [
                'success' => true,
                'user_id' => $user_id,
                'message' => 'Registrasi berhasil'
            ];
        } else {
            mysqli_stmt_close($stmt);
            return [
                'success' => false,
                'error' => 'Gagal mendaftar: ' . mysqli_error($this->conn)
            ];
        }
    }

    /**
     * Login dengan verifikasi password hash
     */
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'error' => 'Email dan password harus diisi'
            ];
        }

        $stmt = mysqli_prepare($this->conn,
            "SELECT id, nama, email, password, role, no_hp, alamat 
             FROM $this->table 
             WHERE email = ? AND role IN ('pelanggan', 'admin')
             LIMIT 1");

        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error'];
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            // Log failed login attempt (optional)
            error_log("Login failed: Email not found - $email");
            return [
                'success' => false,
                'error' => 'Email atau password salah'
            ];
        }

        // Verifikasi password dengan hash yang tersimpan
        if (!password_verify($password, $user['password'])) {
            // Log failed login attempt
            error_log("Login failed: Invalid password for - $email");
            return [
                'success' => false,
                'error' => 'Email atau password salah'
            ];
        }

        // Login berhasil
        return [
            'success' => true,
            'user_id' => $user['id'],
            'nama' => $user['nama'],
            'email' => $user['email'],
            'role' => $user['role'],
            'no_hp' => $user['no_hp'],
            'alamat' => $user['alamat']
        ];
    }

    /**
     * Update password dengan verifikasi password lama
     * @param int $user_id
     * @param string $old_password
     * @param string $new_password
     */
    public function updatePasswordSecure($user_id, $old_password, $new_password) {
        $user_id = intval($user_id);

        // Validasi input
        if (empty($old_password) || empty($new_password)) {
            return [
                'success' => false,
                'error' => 'Password lama dan baru harus diisi'
            ];
        }

        if (strlen($new_password) < 6) {
            return [
                'success' => false,
                'error' => 'Password baru minimal 6 karakter'
            ];
        }

        if ($old_password === $new_password) {
            return [
                'success' => false,
                'error' => 'Password baru tidak boleh sama dengan password lama'
            ];
        }

        // Ambil password user saat ini
        $stmt = mysqli_prepare($this->conn,
            "SELECT password FROM $this->table WHERE id = ?");

        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error'];
        }

        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            return ['success' => false, 'error' => 'User tidak ditemukan'];
        }

        // Verifikasi password lama
        if (!password_verify($old_password, $user['password'])) {
            error_log("Password change failed: Invalid old password for user $user_id");
            return [
                'success' => false,
                'error' => 'Password lama tidak sesuai'
            ];
        }

        // Hash password baru
        $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);

        // Update password
        $stmt = mysqli_prepare($this->conn,
            "UPDATE $this->table SET password = ?, updated_at = NOW() WHERE id = ?");

        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error'];
        }

        mysqli_stmt_bind_param($stmt, 'si', $hashed_new_password, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            // Log password change
            error_log("Password changed successfully for user $user_id");

            return [
                'success' => true,
                'message' => 'Password berhasil diubah'
            ];
        } else {
            mysqli_stmt_close($stmt);
            return [
                'success' => false,
                'error' => 'Gagal mengubah password'
            ];
        }
    }

    /**
     * Update password tanpa verifikasi (untuk admin reset)
     * @param int $user_id
     * @param string $new_password
     */
    public function updatePassword($user_id, $new_password) {
        $user_id = intval($user_id);

        if (empty($new_password) || strlen($new_password) < 6) {
            return false;
        }

        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);

        $stmt = mysqli_prepare($this->conn,
            "UPDATE $this->table SET password = ?, updated_at = NOW() WHERE id = ?");

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'si', $hashed_password, $user_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }

    /**
     * Update profil user (tanpa password)
     */
    public function updateProfil($user_id, $nama, $no_hp, $alamat) {
        $user_id = intval($user_id);

        // Validasi input
        if (empty($nama) || strlen($nama) < 3) {
            return [
                'success' => false,
                'error' => 'Nama minimal 3 karakter'
            ];
        }

        // Sanitasi input
        $nama = htmlspecialchars(trim($nama), ENT_QUOTES, 'UTF-8');
        $no_hp = htmlspecialchars(trim($no_hp), ENT_QUOTES, 'UTF-8');
        $alamat = htmlspecialchars(trim($alamat), ENT_QUOTES, 'UTF-8');

        $stmt = mysqli_prepare($this->conn,
            "UPDATE $this->table 
             SET nama = ?, no_hp = ?, alamat = ?, updated_at = NOW() 
             WHERE id = ?");

        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error'];
        }

        mysqli_stmt_bind_param($stmt, 'sssi', $nama, $no_hp, $alamat, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return [
                'success' => true,
                'message' => 'Profil berhasil diperbarui'
            ];
        } else {
            mysqli_stmt_close($stmt);
            return [
                'success' => false,
                'error' => 'Gagal memperbarui profil'
            ];
        }
    }

    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT id, nama, email, no_hp, alamat, role, password, created_at 
             FROM $this->table 
             WHERE email = ? 
             LIMIT 1");

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return $user;
    }

    /**
     * Find user by ID
     */
    public function findById($user_id) {
        $user_id = intval($user_id);

        $stmt = mysqli_prepare($this->conn,
            "SELECT id, nama, email, no_hp, alamat, role, created_at 
             FROM $this->table 
             WHERE id = ? 
             LIMIT 1");

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return $user;
    }

    /**
     * Get all users (admin only)
     */
    public function getAll($role = null) {
        if ($role !== null && $role !== 'admin' && $role !== 'pelanggan') {
            return [];
        }

        $query = "SELECT id, nama, email, role, no_hp, alamat, created_at 
                  FROM $this->table";

        if ($role) {
            $query .= " WHERE role = '" . mysqli_real_escape_string($this->conn, $role) . "'";
        }

        $query .= " ORDER BY created_at DESC";

        $result = mysqli_query($this->conn, $query);
        return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    }

    /**
     * Delete user (admin only)
     */
    public function delete($user_id) {
        $user_id = intval($user_id);

        $stmt = mysqli_prepare($this->conn,
            "DELETE FROM $this->table WHERE id = ?");

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $result;
    }
}
?>
