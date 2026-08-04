<?php
require_once APP_ROOT . '/Models/transaksi.php';
require_once APP_ROOT . '/helpers/Security.php';

class PembayaranController {
    private $transaksiModel;

    public function __construct($db_connection) {
        $this->transaksiModel = new Transaksi($db_connection);
    }

    /**
     * Memproses unggahan bukti pembayaran.
     * @return array ['success' => bool, 'message' => string]
     */
    public function processPayment($order_id, $user_id, $post_data, $files) {
        // 1. Validasi CSRF Token
        if (!isset($post_data['csrf_token']) || !verifyCSRFToken($post_data['csrf_token'])) {
            return ['success' => false, 'message' => 'Permintaan tidak valid (CSRF token).'];
        }

        // 2. Validasi File Upload
        if (!isset($files['bukti_bayar']) || $files['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Gagal mengunggah file. Pastikan Anda telah memilih file.'];
        }
        $file_validation = validateFile($files['bukti_bayar'], ['image/jpeg', 'image/png'], 5 * 1024 * 1024);
        if (!$file_validation['valid']) {
            return ['success' => false, 'message' => $file_validation['message']];
        }

        // 3. Proses dan Pindahkan File
        $tujuanDir = APP_ROOT . '/public/storage/bukti_bayar/';
        if (!is_dir($tujuanDir)) mkdir($tujuanDir, 0755, true);
        
        $ekstensiFile = strtolower(pathinfo($files['bukti_bayar']['name'], PATHINFO_EXTENSION));
        $namaBaru = "BUKTI_" . $order_id . "_" . time() . "." . $ekstensiFile;
        $tujuanAkhir = $tujuanDir . $namaBaru;

        if (!move_uploaded_file($files['bukti_bayar']['tmp_name'], $tujuanAkhir)) {
            return ['success' => false, 'message' => 'Gagal memindahkan file yang diunggah.'];
        }

        // 4. Siapkan data dan panggil Model
        $payment_data = [
            'metode_pembayaran' => $post_data['metode_bank'],
        ];

        $result = $this->transaksiModel->createPaymentAndConfirmOrder($order_id, $user_id, $payment_data, $namaBaru);

        if ($result === true) {
            return ['success' => true, 'message' => 'Pembayaran berhasil dikonfirmasi.'];
        } else {
            // Jika penyimpanan ke DB gagal, hapus file yang sudah diunggah
            safeUnlink($tujuanDir, $namaBaru);
            // [PERBAIKAN] Tampilkan pesan error spesifik dari Model
            return ['success' => false, 'message' => is_string($result) ? $result : 'Gagal menyimpan data pembayaran. Terjadi kesalahan sistem.'];
        }
    }
}