<?php
require_once __DIR__ . '/../Config/koneksi.php';

class Notification {
    private $db;

    public function __construct($db_connection) {
        $this->db = $db_connection;
    }

    public function create($recipientRole, $recipientId, $type, $relatedId, $message, $linkUrl = null) {
        $stmt = mysqli_prepare($this->db, "INSERT INTO notifications (recipient_role, recipient_id, type, related_id, message, link_url) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'siisss', $recipientRole, $recipientId, $type, $relatedId, $message, $linkUrl);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function getUnread($recipientRole, $recipientId = null) {
        $sql = "SELECT * FROM notifications WHERE recipient_role = ? AND is_read = FALSE"; // Anda perlu menambahkan kolom link_url di SELECT jika ingin menggunakannya
        if ($recipientId !== null) $sql .= " AND recipient_id = ?";
        $sql .= " ORDER BY created_at DESC LIMIT 10";
        
        $stmt = mysqli_prepare($this->db, $sql);
        if ($recipientId !== null) {
            mysqli_stmt_bind_param($stmt, 'si', $recipientRole, $recipientId);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $recipientRole);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $data;
    }

    public function countUnread($recipientRole, $recipientId = null) {
        $sql = "SELECT COUNT(*) as total FROM notifications WHERE recipient_role = ? AND is_read = FALSE";
        if ($recipientId !== null) $sql .= " AND recipient_id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        if ($recipientId !== null) mysqli_stmt_bind_param($stmt, 'si', $recipientRole, $recipientId);
        else mysqli_stmt_bind_param($stmt, 's', $recipientRole);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $total);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $total ?: 0;
    }

    public function markAsRead($recipientRole, $recipientId = null) {
        $sql = "UPDATE notifications SET is_read = TRUE WHERE recipient_role = ? AND is_read = FALSE";
        if ($recipientId !== null) $sql .= " AND recipient_id = ?";
        $stmt = mysqli_prepare($this->db, $sql);
        if ($recipientId !== null) mysqli_stmt_bind_param($stmt, 'si', $recipientRole, $recipientId);
        else mysqli_stmt_bind_param($stmt, 's', $recipientRole);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Menandai satu notifikasi spesifik sebagai sudah dibaca berdasarkan ID-nya.
     *
     * @param int $notificationId ID notifikasi yang akan ditandai.
     * @param string $recipientRole Role penerima (untuk keamanan).
     * @return bool
     */
    public function markAsReadById($notificationId, $recipientRole) {
        $stmt = mysqli_prepare($this->db, "UPDATE notifications SET is_read = TRUE WHERE id = ? AND recipient_role = ?");
        mysqli_stmt_bind_param($stmt, 'is', $notificationId, $recipientRole);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
}