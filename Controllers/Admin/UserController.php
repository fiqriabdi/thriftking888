<?php
require_once __DIR__ . '/../../Models/user.php';
require_once __DIR__ . '/../../helpers/Loggable.php';
require_once __DIR__ . '/../../Middleware/auth.php';

class UserController {
    use Loggable;
    private $model;
    private $db;

    // Terima koneksi dari view/index.php
    public function __construct($db_connection) {
        $this->db = $db_connection;
        // Kirim koneksi ke model user
        $this->model = new user($this->db);
    }

    /**
     * Mengambil daftar pengguna dengan filter dan pagination.
     *
     * @param int $page Halaman saat ini.
     * @param int $limit Jumlah item per halaman (0 untuk semua).
     * @param string|null $status_filter Filter berdasarkan status.
     * @param string|null $search_query Kata kunci pencarian.
     * @return array Data pengguna dan detail pagination.
     */
    public function index($page = 1, $limit = 15, $status_filter = null, $search_query = null) {
        // Base query
        $sql_base = "FROM users";
        $where_clauses = [];
        $params = [];
        $types = '';

        // Filtering logic
        if ($search_query) {
            $where_clauses[] = "(nama LIKE ? OR email LIKE ?)";
            $search_param = "%{$search_query}%";
            array_push($params, $search_param, $search_param);
            $types .= 'ss';
        }
        if ($status_filter === 'online') {
            $where_clauses[] = "last_login >= NOW() - INTERVAL 5 MINUTE";
        }

        $where_sql = !empty($where_clauses) ? " WHERE " . implode(' AND ', $where_clauses) : '';

        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) as total " . $sql_base . $where_sql;
        $stmt_count = mysqli_prepare($this->db, $count_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt_count, $types, ...$params);
        }
        mysqli_stmt_execute($stmt_count);
        $total_users = mysqli_stmt_get_result($stmt_count)->fetch_assoc()['total'] ?? 0;
        mysqli_stmt_close($stmt_count);

        // Get paginated data
        $data_sql = "SELECT * " . $sql_base . $where_sql . " ORDER BY FIELD(role, 'admin', 'pelanggan'), created_at DESC";
        if ($limit > 0) {
            $total_pages = ceil($total_users / $limit);
            $offset = ($page - 1) * $limit;
            $data_sql .= " LIMIT ? OFFSET ?";
            array_push($params, $limit, $offset);
            $types .= 'ii';
        }

        $stmt_data = mysqli_prepare($this->db, $data_sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt_data, $types, ...$params);
        }
        mysqli_stmt_execute($stmt_data);
        $users = mysqli_stmt_get_result($stmt_data)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_data);

        return ['users' => $users, 'total_pages' => (int)($total_pages ?? 0), 'current_page' => (int)$page];
    }

    public function store($data) {
        if ($this->model->existsByEmail($data['email'])) {
            return false;
        }

        $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $res = $this->model->create(
            $data['nama'],
            $data['email'],
            $hashed_password,
            $data['role'],
            $data['no_hp'] ?? null,
            $data['alamat'] ?? null
        );
        if ($res) {
            $this->logActivity("TAMBAH_PENGGUNA", "Menambah user: " . $data['nama']);
        }
        return $res;
    }

    public function show($id) {
        return $this->model->findById($id);
    }

    public function update($id, $nama, $email, $role, $no_hp, $alamat, $password_baru = null) {
        if ($this->model->existsByEmail($email, $id)) {
            return false;
        }

        $hashed_password = ($password_baru !== null && $password_baru !== '') ? password_hash($password_baru, PASSWORD_BCRYPT, ['cost' => 12]) : null;
        $res = $this->model->updateById(
            $id,
            $nama,
            $email,
            $role,
            $no_hp,
            $alamat,
            $hashed_password
        );
        if ($res) {
            $this->logActivity("UPDATE_PENGGUNA", "Update user ID: $id (" . $nama . ")");
        }
        return $res;
    }

    /**
     * Memperbarui foto profil pengguna.
     * @param int $id ID pengguna.
     * @param string|null $filename Nama file foto baru.
     * @return bool
     */
    public function updateProfilePicture($id, $filename) {
        $res = $this->model->updateProfilePicture($id, $filename);
        if ($res) {
            $this->logActivity("UPDATE_FOTO_PROFIL", "Update foto profil untuk user ID: $id");
        }
        return $res;
    }

    public function destroy($id) {
        $id = intval($id);
        $user = $this->model->findById($id);

        if (!$user) {
            return false;
        }

        if ($user['role'] === 'admin') {
            return false;
        }

        $res = $this->model->deleteById($id);
        if ($res) {
            $this->logActivity("HAPUS_PENGGUNA", "Menghapus user ID: $id (" . $user['nama'] . ")");
        }
        return $res;
    }

    /**
     * Mengambil statistik ringkas untuk Dashboard Admin
     */
    public function getAdminStats() {
        $stats = ['produk' => 0, 'pesanan' => 0, 'pelanggan' => 0];
        
        $q_produk = mysqli_query($this->db, "SELECT COUNT(*) as total FROM products");
        if ($q_produk) $stats['produk'] = mysqli_fetch_assoc($q_produk)['total'];

        $q_pesanan = mysqli_query($this->db, "SELECT COUNT(*) as total FROM orders WHERE status_order = 'unpaid'");
        if ($q_pesanan) $stats['pesanan'] = mysqli_fetch_assoc($q_pesanan)['total'];

        $q_pelanggan = mysqli_query($this->db, "SELECT COUNT(*) as total FROM users WHERE role = 'pelanggan'");
        if ($q_pelanggan) $stats['pelanggan'] = mysqli_fetch_assoc($q_pelanggan)['total'];

        return $stats;
    }
}
