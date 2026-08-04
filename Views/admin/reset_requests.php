<?php
require_once APP_ROOT . '/Config/koneksi.php';
require_once APP_ROOT . '/Middleware/auth.php';

auth::requireRole('admin');

$pageTitle = 'Permintaan Reset Password';
$activePage = '';
$conn = Database::getConnection();

// Ambil data dari tabel password_resets yang kita buat tadi
$query = "SELECT pr.*, u.nama 
          FROM password_resets pr 
          JOIN users u ON pr.email = u.email 
          ORDER BY pr.created_at DESC";
$result = mysqli_query($conn, $query);

// Logika jika Admin ingin menghapus/membatalkan request
if (isset($_GET['delete'])) {
    $reset_id = intval($_GET['delete']);
    
    mysqli_begin_transaction($conn);
    try {
        // 1. Ambil email dari request yang akan dihapus untuk mencari notifikasi terkait
        $stmt_get_email = mysqli_prepare($conn, "SELECT email FROM password_resets WHERE id = ?");
        mysqli_stmt_bind_param($stmt_get_email, 'i', $reset_id);
        mysqli_stmt_execute($stmt_get_email);
        $res_email = mysqli_stmt_get_result($stmt_get_email);
        $row_email = mysqli_fetch_assoc($res_email);
        $email_to_clear = $row_email['email'] ?? null;
        mysqli_stmt_close($stmt_get_email);

        // 2. Hapus permintaan dari tabel password_resets
        $stmt_del_req = mysqli_prepare($conn, "DELETE FROM password_resets WHERE id = ?");
        mysqli_stmt_bind_param($stmt_del_req, 'i', $reset_id);
        mysqli_stmt_execute($stmt_del_req);
        mysqli_stmt_close($stmt_del_req);

        // 3. Hapus notifikasi yang terkait dengan user tersebut
        if ($email_to_clear) {
            $stmt_del_notif = mysqli_prepare($conn, "DELETE FROM notifications WHERE type = 'password_reset' AND recipient_role = 'admin' AND related_id = (SELECT id FROM users WHERE email = ?)");
            mysqli_stmt_bind_param($stmt_del_notif, 's', $email_to_clear);
            mysqli_stmt_execute($stmt_del_notif);
            mysqli_stmt_close($stmt_del_notif);
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Failed to clear reset request: " . $e->getMessage());
    }
    header('Location: ' . BASE_URL . 'admin/reset-requests');
}
?>

<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white p-3">
            <h5 class="mb-0">Permintaan Reset Password (Admin Panel)</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">Daftar ini adalah antrean user yang meminta reset. Secara modular, bagian ini siap dihubungkan ke API Mailer di masa depan.</p>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu Request</th>
                            <th>Nama Pelanggan</th>
                            <th>Email</th>
                            <th>Token Keamanan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= date('d/m/H:i', strtotime($row['created_at'])) ?></td>
                                <td class="fw-bold"><?= $row['nama'] ?></td>
                                <td><?= $row['email'] ?></td>
                                <td><code class="bg-light p-1"><?= $row['token'] ?></code></td>
                                <td>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Selesaikan/Hapus permintaan ini?')">Selesai</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Tidak ada permintaan reset aktif.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/Views/layouts/footer.php'; ?>
