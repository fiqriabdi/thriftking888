<footer class="bg-white border-top py-5 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h6 class="fw-bold mb-4" style="letter-spacing: 2px; font-family: 'Tenor Sans', sans-serif;">ABOUT THRIFTKING888</h6>
                <p class="text-muted small lh-lg">
                    Kami menyediakan koleksi thrifting pilihan yang telah melewati proses kurasi ketat untuk menjamin kualitas terbaik bagi gaya unikmu.
                </p>
            </div>
            
            <div class="col-md-2 offset-md-1">
                <h6 class="fw-bold mb-4" style="letter-spacing: 2px; font-family: 'Tenor Sans', sans-serif;">HELP</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Shipping Policy</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Returns & Exchanges</a></li>
                    <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Contact Us</a></li>
                </ul>
            </div>
            
            <div class="col-md-2">
                <h6 class="fw-bold mb-4" style="letter-spacing: 2px; font-family: 'Tenor Sans', sans-serif;">CATEGORIES</h6>
                <ul class="list-unstyled small">
                    <?php if (!empty($global_categories)): ?>
                        <?php foreach($global_categories as $cat): ?>
                            <li class="mb-2"><a href="<?= $base_url ?>produk/<?= htmlspecialchars($cat['slug']) ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($cat['nama_kategori']) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="mb-2"><a href="<?= $base_url ?>produk" class="text-decoration-none text-muted">Produk</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h6 class="fw-bold mb-4" style="letter-spacing: 2px; font-family: 'Tenor Sans', sans-serif;">NEWSLETTER</h6>
                <div class="input-group">
                    <input type="email" class="form-control form-control-sm rounded-0 border-dark shadow-none" placeholder="Email Address">
                    <button class="btn btn-dark rounded-0 btn-sm px-3">JOIN</button>
                </div>
            </div>
        </div>
        
        <hr class="my-5 text-muted opacity-25">
        
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row">
            <p class="small text-muted mb-0" style="font-family: 'Inter', sans-serif;">© <?= date('Y') ?> <?= htmlspecialchars($global_settings['nama_toko'] ?? 'Thrift King 888') ?>. All Rights Reserved.</p>
            <div class="social-icons mt-3 mt-md-0">
                <a href="<?= htmlspecialchars($global_settings['instagram_url'] ?? '#') ?>" target="_blank" class="text-dark mx-2 transition-opacity"><i class="bi bi-instagram fs-5"></i></a>
                <a href="<?= htmlspecialchars($global_settings['facebook_url'] ?? '#') ?>" target="_blank" class="text-dark mx-2 transition-opacity"><i class="bi bi-facebook fs-5"></i></a>
                <a href="<?= htmlspecialchars($global_settings['tiktok_url'] ?? '#') ?>" target="_blank" class="text-dark mx-2 transition-opacity"><i class="bi bi-tiktok fs-5"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- [DIUBAH] Tombol Scroll Atas & Bawah -->
<a href="#" class="scroll-btn scroll-to-top shadow-sm" title="Kembali ke atas">
    <i class="bi bi-chevron-up"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base_url ?>assets/js/site.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cek pesan dari Session (via PHP)

    // --- [DIUBAH] Logika untuk Tombol Scroll Atas & Bawah ---
    const scrollToTopBtn = document.querySelector('.scroll-to-top');

    if (scrollToTopBtn) {
        window.addEventListener('scroll', function() {

            // Tampilkan tombol "Up" jika scroll lebih dari 300px
            if (scrollPosition > 300) {
                scrollToTopBtn.classList.add('show');
            } else {
                scrollToTopBtn.classList.remove('show');
            }
        });

        scrollToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Variabel state untuk memantau perubahan jumlah notifikasi secara real-time
    let lastCustomerNotifCount = <?= (int)($notif_count ?? 0) ?>;
    let lastAdminNotifCount = <?= (int)($notif_count ?? 0) ?>;

    // --- AJAX POLLING NOTIFIKASI (Pelanggan) ---
    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'pelanggan'): ?>
    function updateBadges() {
        fetch('<?= BASE_URL ?>api/counts')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    lastCustomerNotifCount = data.notif_count;

                    // Update Badge Inbox (Ulasan)
                    const chatBadge = document.getElementById('chat-badge');
                    if (chatBadge) {
                        chatBadge.innerText = data.review_count;
                        chatBadge.style.display = data.review_count > 0 ? 'inline-block' : 'none';
                    }
                    // Update Badge Notifikasi (Pesanan)
                    const notifBadge = document.getElementById('customer-notif-badge');
                    if (notifBadge) {
                        notifBadge.innerText = data.notif_count;
                        notifBadge.style.display = data.notif_count > 0 ? 'inline-block' : 'none';
                    }
                    // Update Badge Keranjang
                    const cartBadge = document.getElementById('cart-badge');
                    if (cartBadge) {
                        cartBadge.innerText = data.cart_count;
                        cartBadge.style.display = data.cart_count > 0 ? 'inline-block' : 'none';
                    }
                }
            }).catch(e => console.error("Polling error:", e));
    }
    setInterval(updateBadges, 30000); // Poll setiap 30 detik
    <?php endif; ?>

    // --- AJAX POLLING NOTIFIKASI (Admin Global) ---
    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
    function fetchAdminNotifs() {
        const adminBadge = document.getElementById('admin-notif-badge');
        const adminNotifList = document.getElementById('admin-notif-list');
        
        fetch('<?= BASE_URL ?>admin/notifications/check')
            .then(res => res.json())
            .then(data => {
                lastAdminNotifCount = data.count;

                if (data.count > 0) {
                    if (adminBadge) {
                        adminBadge.innerText = data.count;
                        adminBadge.style.display = 'inline-block';
                        adminBadge.classList.add('animate-pulse');
                    }
                    if (adminNotifList && data.notifications) {
                        adminNotifList.innerHTML = '';
                        data.notifications.forEach(n => {
                            let icon = 'bi-receipt text-secondary'; // Default icon
                            let link = n.link_url || '<?= BASE_URL ?>admin/dashboard'; // Default link

                            // Menentukan ikon dan link berdasarkan tipe notifikasi
                            switch(n.type) {
                                case 'new_review':
                                    icon = 'bi-chat-left-text text-info';
                                    link = '<?= BASE_URL ?>admin/ulasan?status=pending';
                                    break;
                                case 'payment_sent':
                                    icon = 'bi-credit-card text-success';
                                    link = '<?= BASE_URL ?>admin/konfirmasi-pembayaran';
                                    break;
                                case 'password_reset':
                                    icon = 'bi-key-fill text-warning';
                                    link = n.link_url || '<?= BASE_URL ?>admin/reset-password'; // Gunakan link WA, fallback ke halaman reset manual
                                    break;
                                case 'new_order':
                                    icon = 'bi-box-seam text-primary';
                                    link = n.link_url || '<?= BASE_URL ?>admin/pesanan';
                                    break;
                                case 'cancelled_order':
                                    icon = 'bi-x-circle-fill text-danger';
                                    link = n.link_url || '<?= BASE_URL ?>admin/pesanan/dibatalkan';
                                    break;
                            }

                            adminNotifList.innerHTML += `
                                <a href="${link}" class="notif-item" data-id="${n.id}">
                                    <div class="me-2"><i class="bi ${icon} fs-5"></i></div>
                                    <div><div class="small fw-bold">${n.message}</div><div class="text-muted small" style="font-size:0.7rem">${new Date(n.created_at).toLocaleTimeString()}</div></div>
                                </a>`;
                        });
                    }
                } else {
                    if (adminBadge) adminBadge.style.display = 'none';
                    if (adminNotifList) adminNotifList.innerHTML = '<div class="p-3 text-center text-muted small">Tidak ada notifikasi baru</div>';
                }
            }).catch(err => console.warn("Admin polling error:", err));
    }
    setInterval(fetchAdminNotifs, 30000);
    fetchAdminNotifs();

    // Handle mark as read saat dropdown diklik
    const adminNotifListContainer = document.getElementById('admin-notif-list');
    if (adminNotifListContainer) {
        adminNotifListContainer.addEventListener('click', function(e) {
            const notifItem = e.target.closest('.notif-item');
            if (!notifItem) return;

            e.preventDefault(); // Hentikan navigasi link sementara

            const notifId = notifItem.dataset.id;
            const destinationUrl = notifItem.href;

            // Kirim request untuk menandai notifikasi ini sebagai sudah dibaca
            const formData = new FormData();
            formData.append('id', notifId);

            fetch('<?= BASE_URL ?>admin/notifications/mark-read', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Hapus item dari DOM
                    notifItem.style.transition = 'opacity 0.3s ease';
                    notifItem.style.opacity = '0';
                    setTimeout(() => notifItem.remove(), 300);

                    // Update badge counter
                    const adminBadge = document.getElementById('admin-notif-badge');
                    let currentCount = parseInt(adminBadge.innerText);
                    let newCount = currentCount - 1;
                    adminBadge.innerText = newCount;
                    if (newCount <= 0) {
                        adminBadge.style.display = 'none';
                        adminNotifListContainer.innerHTML = '<div class="p-3 text-center text-muted small">Tidak ada notifikasi baru</div>';
                    }
                }
            })
            .catch(error => {
                console.error('Failed to mark notification as read:', error);
            })
            .finally(() => {
                window.location.href = destinationUrl; // Lanjutkan navigasi ke tujuan link
            });
        });
    }
    <?php endif; ?>
});
</script>
</body>
</html>