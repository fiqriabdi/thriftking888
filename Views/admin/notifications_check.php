<?php
/**
 * File: Views/admin/notifications_check.php
 * Endpoint API internal untuk sinkronisasi notifikasi admin secara real-time.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    require_once __DIR__ . '/../../Config/koneksi.php';
    require_once __DIR__ . '/../../Models/notification.php';

    $notifModel = new Notification(Database::getConnection());
    $unreadCount = $notifModel->countUnread('admin');
    $notifications = $notifModel->getUnread('admin');

    echo json_encode([
        'success'       => true,
        'count'         => intval($unreadCount),
        'notifications' => $notifications
    ]);
} catch (Throwable $e) {
    error_log("Admin Notif Check Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}