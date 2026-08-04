<?php
/**
 * Trait untuk mencatat aktivitas Audit Log secara konsisten
 */
trait Loggable {
    protected function logActivity($action, $details) {
        $db = Database::getConnection();
        $user = auth::getUser();
        $userId = $user['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = mysqli_prepare($db, "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $userId, $action, $details, $ip);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    /**
     * Mencatat aktivitas dari konteks statis (misalnya, dari file view/prosedural).
     *
     * @param string $action Tipe aksi yang dicatat.
     * @param string $details Deskripsi detail dari aksi.
     */
    public static function logActivityStatic($action, $details) {
        $db = Database::getConnection();
        
        // Ambil user ID dari session jika ada
        $userId = null;
        if (isset($_SESSION['user']['id'])) {
            $userId = $_SESSION['user']['id'];
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = mysqli_prepare($db, "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $userId, $action, $details, $ip);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}