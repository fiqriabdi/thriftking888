<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../Config/konstanta.php';
require_once __DIR__ . '/../../Config/koneksi.php';
require_once __DIR__ . '/../../Models/user.php';

class AuthController {
    private $notificationModel;
    private $db;
    protected $userModel;

    public function __construct($conn) {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $this->db = $conn;
        $this->userModel = new user($conn); // Kirim koneksi ke model user
        require_once __DIR__ . '/../../Models/Notification.php'; // Pastikan nama file ini 'Notification.php' (N besar)
        $this->notificationModel = new Notification($conn);
    }

    public function login($email, $password) {
        try {
            $user = $this->userModel->getByEmail($email);

            if ($user && password_verify($password, $user['password'])) {
                // Rekam waktu login terakhir ke database
                $this->userModel->updateLastLogin($user['id']);

                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'nama'  => $user['nama'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                    'no_hp' => $user['no_hp'],
                    'alamat' => $user['alamat'],
                    'foto_profil' => $user['foto_profil'] ?? null
                ];

                // --- TAMBAHAN: GABUNGKAN KERANJANG SESI KE DATABASE ---
                if ($user['role'] === 'pelanggan') {
                    require_once APP_ROOT . '/Models/cart.php';
                    $cartModel = new CartModel($this->db);
                    
                    if (!empty($_SESSION['keranjang'])) {
                        $cartModel->mergeCart($user['id'], $_SESSION['keranjang']);
                    }
                    // Sinkronkan kembali data terbaru dari DB ke Session
                    $_SESSION['keranjang'] = $cartModel->getItems($user['id']);
                }
                
                if ($user['role'] === 'admin') {
                    header("Location: " . BASE_URL . "admin/dashboard");
                } else {
                    header("Location: " . BASE_URL);
                }
                exit();
            }
            return "Email atau Password salah!";
        } catch (mysqli_sql_exception $e) {
            error_log("Login DB Error: " . $e->getMessage());
            return "Maaf, sistem sedang mengalami gangguan koneksi ke server. Silakan coba beberapa saat lagi.";
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
        header('Location: ' . BASE_URL . 'auth/login');
        exit();
    }

    public function register($nama, $email, $password) {
        $nama = trim($nama);
        $email = trim($email);
        $password = trim($password);

        if ($nama === '' || $email === '' || $password === '') {
            return "Semua field harus diisi.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Format email tidak valid.";
        }

        if (strlen($password) < 6) {
            return "Password minimal 6 karakter.";
        }

        try {
            if ($this->userModel->existsByEmail($email)) {
                return "Email sudah terdaftar. Silakan gunakan email lain atau login.";
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $role = 'pelanggan';

            $result = $this->userModel->create($nama, $email, $hashed_password, $role);
            if ($result) {
                header("Location: " . BASE_URL . "auth/login?pesan=registrasi_berhasil");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Register DB Error: " . $e->getMessage());
            return "Terjadi gangguan pada server saat pendaftaran. Silakan coba lagi.";
        }

        return "Registrasi Gagal! Terjadi kesalahan pada sistem, silakan coba lagi nanti.";
    }

    public function requestReset($email) {
        $user = $this->userModel->getByEmail($email);
        if (!$user) return "Email tidak terdaftar.";

        $token = bin2hex(random_bytes(32)); 
        if ($this->userModel->storeResetToken($email, $token)) {
            // TAMBAHKAN NOTIFIKASI UNTUK ADMIN
            $link_notif = BASE_URL . 'admin/pengguna/edit/' . $user['id'];
            $this->notificationModel->create('admin', null, 'password_reset', $user['id'], "Pelanggan {$user['nama']} meminta reset password.", $link_notif);

            // Kirim email ke pelanggan
            $resetLink = BASE_URL . "auth/reset-password/" . $token;
            $mailSent = $this->sendPasswordResetEmail($user['email'], $user['nama'], $resetLink);

            if ($mailSent) {
                return "Link untuk mereset password telah dikirim ke email Anda. Silakan periksa kotak masuk atau folder spam.";
            } else {
                return "Gagal mengirim email reset. Silakan hubungi administrator.";
            }
        }
        return "Gagal membuat permintaan reset.";
    }

    public function requestWhatsAppReset($email) {
        $email = trim($email);

        $user = $this->userModel->getByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => "Email tidak terdaftar di sistem kami."];
        }

        if (empty($user['no_hp'])) {
            return ['success' => false, 'message' => "Akun Anda tidak memiliki nomor WhatsApp terdaftar. Silakan hubungi admin secara manual."];
        }

        // 1. Buat token unik
        $token = bin2hex(random_bytes(32));
        if (!$this->userModel->storeResetToken($email, $token)) {
            return ['success' => false, 'message' => "Gagal membuat token keamanan. Coba lagi."];
        }

        // 2. Buat link reset dan link WhatsApp
        $resetLink = BASE_URL . "auth/reset-password/" . $token;
        $waMessage = urlencode("Halo {$user['nama']}, berikut adalah link untuk mereset password akun ThriftKing888 Anda: {$resetLink}");
        $waLink = "https://wa.me/" . preg_replace('/[^0-9]/', '', $user['no_hp']) . "?text=" . $waMessage;

        // 3. Buat notifikasi untuk admin yang berisi link WhatsApp
        $notifMessage = "Klik untuk kirim link reset ke '{$user['nama']}' via WhatsApp.";
        // Simpan waLink di kolom 'related_id' atau kolom lain jika ada, atau langsung di message.
        // Untuk sekarang, kita akan buat link notifikasinya langsung ke waLink.
        $notifCreated = $this->notificationModel->create('admin', null, 'password_reset', $user['id'], $notifMessage, $waLink);

        if ($notifCreated) {
            return ['success' => true, 'message' => "Permintaan Anda telah dikirim. Admin akan segera mengirimkan kode password ke nomor WhatsApp Anda yang terdaftar."];
        } else {
            return ['success' => false, 'message' => "Gagal membuat permintaan. Silakan coba lagi atau hubungi admin."];
        }
    }

    /**
     * Membuat notifikasi umum ke admin dari pelanggan anonim (tanpa login/email).
     *
     * @param string $keterangan Pesan dari pelanggan.
     * @return array Hasil operasi, berisi status sukses dan pesan.
     */
    public function requestAnonymousManualReset($keterangan) {
        if (empty(trim($keterangan))) {
            return ['success' => false, 'message' => "Keterangan tidak boleh kosong."];
        }

        $message = "Permintaan reset password dari pengunjung. Pesan: \"{$keterangan}\"";
        $notifCreated = $this->notificationModel->create('admin', null, 'password_reset', null, $message, null); // Pass null for link_url

        if ($notifCreated) {
            return ['success' => true, 'message' => "Permintaan Anda telah dikirim. Admin akan segera menindaklanjuti."];
        }
        return ['success' => false, 'message' => "Gagal membuat permintaan. Silakan coba lagi."];
    }

    private function sendPasswordResetEmail($email, $nama, $link) {
        $mail = new PHPMailer(true);

        try {
            // Konfigurasi Server SMTP (Gunakan detail dari penyedia email Anda, contoh: Gmail, Mailtrap, dll)
            $mail->isSMTP();
            $mail->Host       = 'smtp.mailtrap.io'; // Ganti dengan SMTP server Anda
            $mail->SMTPAuth   = true;
            $mail->Username   = 'username_anda'; // Ganti dengan username SMTP
            $mail->Password   = 'password_anda'; // Ganti dengan password SMTP
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587; // Port SMTP (587 untuk TLS, 465 untuk SSL)

            // Pengirim dan Penerima
            $mail->setFrom('no-reply@thriftking888.com', 'ThriftKing888');
            $mail->addAddress($email, $nama);

            // Konten Email
            $mail->isHTML(true);
            $mail->Subject = 'Permintaan Reset Password Akun ThriftKing888';
            $mail->Body    = "Halo {$nama},<br><br>Kami menerima permintaan untuk mereset password akun Anda. Klik link di bawah ini untuk melanjutkan:<br><a href='{$link}'>{$link}</a><br><br>Jika Anda tidak merasa meminta ini, abaikan saja email ini.<br><br>Terima kasih,<br>Tim ThriftKing888";
            $mail->AltBody = "Halo {$nama},\n\nSilakan salin dan tempel link berikut di browser Anda untuk mereset password: {$link}\n\nTerima kasih,\nTim ThriftKing888";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function verifyToken($token) {
        return $this->userModel->getEmailByToken($token);
    }

    public function executeReset($token, $new_password) {
        $email = $this->userModel->getEmailByToken($token);
        if (!$email) return "Token tidak valid atau sudah kadaluarsa.";
        
        $user = $this->userModel->getByEmail($email);
        if ($this->userModel->updatePassword($user['id'], $new_password)) {
            $this->userModel->deleteResetToken($email);
            return true; 
        }
        return "Gagal memperbarui password.";
    }
}
