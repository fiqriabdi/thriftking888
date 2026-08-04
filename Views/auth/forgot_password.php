<?php
// Logika pemrosesan form akan ditangani oleh public/index.php
// Variabel $msg akan dikirim dari sana.
$msg = $msg ?? '';
$msg_type = $msg_type ?? 'info'; // 'info' atau 'danger'

// CSRF Token untuk keamanan form
require_once APP_ROOT . '/helpers/Security.php';
$csrf_token = generateCSRFToken();

?>
<?php require_once APP_ROOT . '/Views/layouts/header.php'; ?>
<?php
// Ambil nomor WA toko setelah header dimuat agar fungsi dan variabel tersedia
// [PERBAIKAN] Menggunakan key 'no_hp' sesuai dengan kolom di database 'settings'
$wa_number = formatWhatsappNumber($global_settings['no_hp'] ?? '');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card rounded-0 border shadow-sm p-4">
                
                    <!-- Opsi 1: Minta link otomatis -->
                    <div id="auto-reset">
                        <h5 class="fw-bold text-uppercase mb-3">Minta Reset Password</h5>
                        <p class="small text-muted">Masukkan email akun Anda. Admin akan menerima notifikasi untuk mengirimkan <strong> kode password</strong> ke nomor WhatsApp yang terdaftar.</p>
                        
                        <?php if($msg): ?>
                            <div class="alert alert-<?= $msg_type === 'info' ? 'success' : 'danger' ?> small rounded-0"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form method="POST" <?= ($msg && $msg_type === 'info') ? 'style="display:none;"' : '' ?>>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-2">
                                <label class="small fw-bold">EMAIL TERDAFTAR</label>
                                <input type="email" name="email" class="form-control rounded-0" placeholder="Masukkan email akun Anda" required>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 rounded-0">KIRIM PERMINTAAN</button>
                        </form>
                    </div>
                    
                    <div class="text-center my-4">
                        <span class="text-muted small">ATAU</span>
                    </div>

                    <!-- Opsi 2: Hubungi admin langsung -->
                    <div id="manual-reset">
                        <h5 class="fw-bold text-uppercase mb-3">Hubungi Admin Langsung</h5>
                        <p class="small text-muted">Jika Anda tidak memiliki nomor WhatsApp yang terdaftar atau mengalami kendala lain, Anda bisa langsung menghubungi admin kami.</p>
                        
                        <?php if($wa_number): ?>
                            <a id="wa_button" href="https://wa.me/<?= htmlspecialchars($wa_number) ?>?text=Halo%20Admin%20ThriftKing888,%20saya%20butuh%20bantuan%20reset%20password." class="btn btn-success w-100 rounded-0" target="_blank" rel="noopener noreferrer">
                                <i class="bi bi-whatsapp me-2"></i> CHAT VIA WHATSAPP
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning small rounded-0">Nomor WhatsApp admin belum diatur. Fitur ini belum tersedia.</div>
                        <?php endif; ?>
                    </div>
                
                <hr class="my-4">
                <div class="text-center">
                    <a href="<?= BASE_URL ?>auth/login" class="text-muted small">Kembali ke Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
