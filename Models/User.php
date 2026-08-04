<?php
class user  {   
    private $db;

    public function __construct($db) { 
        $this->db = $db;
    }

    // Fungsi untuk Register/Tambah User
    public function create($nama, $email, $password, $role, $no_hp = null, $alamat = null) {
        $stmt = mysqli_prepare($this->db, "INSERT INTO users (nama, email, password, role, no_hp, alamat) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ssssss', $nama, $email, $password, $role, $no_hp, $alamat);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function existsByEmail($email, $excludeId = null) {
        if ($excludeId !== null) {
            $stmt = mysqli_prepare($this->db, "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            if (!$stmt) return false;
            
            $excludeId = intval($excludeId);
            mysqli_stmt_bind_param($stmt, 'si', $email, $excludeId);
        } else {
            $stmt = mysqli_prepare($this->db, "SELECT id FROM users WHERE email = ? LIMIT 1");
            if (!$stmt) return false;
            
            mysqli_stmt_bind_param($stmt, 's', $email);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_fetch_assoc($result) ? true : false;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    public function updateById($id, $nama, $email, $role, $no_hp, $alamat, $password = null) {
        $id = intval($id);

        if ($password !== null) {
            $stmt = mysqli_prepare($this->db, "UPDATE users SET nama = ?, email = ?, role = ?, no_hp = ?, alamat = ?, password = ? WHERE id = ?");
            if (!$stmt) return false;
            
            mysqli_stmt_bind_param($stmt, 'ssssssi', $nama, $email, $role, $no_hp, $alamat, $password, $id);
        } else {
            $stmt = mysqli_prepare($this->db, "UPDATE users SET nama = ?, email = ?, role = ?, no_hp = ?, alamat = ? WHERE id = ?");
            if (!$stmt) return false;
            
            mysqli_stmt_bind_param($stmt, 'sssssi', $nama, $email, $role, $no_hp, $alamat, $id);
        }

        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function getAll($only_online = false, $search = null) {
        $query = "SELECT * FROM users WHERE 1=1";
        $params = [];
        $types = "";

        if ($only_online) {
            $query .= " AND last_login >= (NOW() - INTERVAL 5 MINUTE)";
        }

        if ($search) {
            $query .= " AND (nama LIKE ? OR email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }

        $query .= " ORDER BY created_at DESC";

        if ($stmt = mysqli_prepare($this->db, $query)) {
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        }
        return [];
    }

    public function findById($id) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "SELECT * FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $user;
    }

    public function deleteById($id) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "DELETE FROM users WHERE id = ?");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Memperbarui foto profil pengguna.
     * @param int $id ID pengguna.
     * @param string|null $filename Nama file foto baru atau NULL untuk menghapus.
     * @return bool
     */
    public function updateProfilePicture($id, $filename) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "UPDATE users SET foto_profil = ? WHERE id = ?");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'si', $filename, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    // Mengambil data user berdasarkan email (Untuk Autentikasi & Profil)
    public function getByEmail($email) {
        $stmt = mysqli_prepare($this->db, "SELECT * FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) return null;
        
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $user;
    }

    // Update Data Profil Pelanggan
    public function updateProfil($id, $nama, $no_hp, $alamat) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "UPDATE users SET nama = ?, no_hp = ?, alamat = ? WHERE id = ?");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'sssi', $nama, $no_hp, $alamat, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    // Ganti Password dengan Hashing Aman (BCRYPT)
    public function updatePassword($id, $new_pass) {
        $id = intval($id);
        $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = mysqli_prepare($this->db, "UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'si', $hashed_password, $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function storeResetToken($email, $token) {
        // Hapus token lama dengan prepared statement
        $stmt_del = mysqli_prepare($this->db, "DELETE FROM password_resets WHERE email = ?");
        if ($stmt_del) {
            mysqli_stmt_bind_param($stmt_del, 's', $email);
            mysqli_stmt_execute($stmt_del);
            mysqli_stmt_close($stmt_del);
        }

        $stmt = mysqli_prepare($this->db, "INSERT INTO password_resets (email, token) VALUES (?, ?)");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'ss', $email, $token);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    public function getEmailByToken($token) {
        $stmt = mysqli_prepare($this->db, "SELECT email FROM password_resets WHERE token = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        return $row['email'] ?? null;
    }

    public function deleteResetToken($email) {
        $stmt = mysqli_prepare($this->db, "DELETE FROM password_resets WHERE email = ?");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 's', $email);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    public function updateLastLogin($id) {
        $id = intval($id);
        $stmt = mysqli_prepare($this->db, "UPDATE users SET last_login = NOW() WHERE id = ?");
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
}