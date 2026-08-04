<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../Config/koneksi.php';
require_once __DIR__ . '/../../Models/notification.php';

$notifModel = new Notification(Database::getConnection());
$result = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    // Menandai satu notifikasi berdasarkan ID
    $result = $notifModel->markAsReadById(intval($_POST['id']), 'admin');
} else {
    // Perilaku lama: menandai semua notifikasi (jika masih diperlukan)
    $result = $notifModel->markAsRead('admin');
}

header('Content-Type: application/json');
echo json_encode([
    'success' => $result
]);