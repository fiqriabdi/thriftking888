<?php
// Pastikan header sudah disertakan terlebih dahulu karena variabel ini berasal dari header.
$base_url = defined('BASE_URL') ? BASE_URL : ($base_url ?? '/thriftking888/');
$isLoggedIn = $isLoggedIn ?? false;
$userRole = $userRole ?? null;
$userDisplayName = $userDisplayName ?? 'User';
$notif_count = $notif_count ?? 0;
$cart_item_count = $cart_item_count ?? 0;
$search_query = htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8');
$current_category = htmlspecialchars($_GET['kategori'] ?? ($kategori ?? ''), ENT_QUOTES, 'UTF-8');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">

<style>
    /* Desain Utama Navbar Modern & Responsif */
    .navbar-thriftking888 {
        background-color: #ffffff;
        border-bottom: 1px solid #e5e7e9;
        padding: 0.8rem 0;
    }
    
    /* Font Logo Karakter Thrift King 888 Anda */
    .brand-logo {
        font-family: 'Playfair Display', serif;
        letter-spacing: 5px;
        font-weight: 800;
        font-size: 1.5rem;
        text-decoration: none;
    }

    /* Font Menu Navigasi Sesuai Request (Kapital, Renggang, Bold Ringan) */
    .nav-link-item {
        font-size: 0.75rem;
        font-weight: 600;
        color: #212529;
        text-decoration: none;
        letter-spacing: 1.5px;
        transition: color 0.2s ease-in-out;
    }
    
    .nav-link-item:hover {
        color: #6c757d;
    }

    /* Kotak Pencarian Lebar Permanen */
    .search-wrapper {
        /* max-width: 650px; */ /* Dihapus agar lebih fleksibel saat zoom */
        width: 100%;
    }
    
    .search-group {
        background-color: #f3f4f5;
        border: 1px solid #e5e7e9;
        border-radius: 8px;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        width: 100%;
        padding: 2px 8px;
    }

    /* Efek Fokus Kotak Pencarian */
    .search-group:focus-within {
        background-color: #ffffff;
        border-color: #000000;
        box-shadow: 0 0 0 1px #000000;
    }

    .search-group input {
        border: none;
        background: transparent;
        box-shadow: none;
        font-size: 0.85rem;
        padding: 0.5rem 0.75rem;
    }

    .search-group input:focus {
        background: transparent;
        box-shadow: none;
        border: none;
    }

    .search-group .btn-search-submit {
        background: transparent;
        border: none;
        color: #6c757d;
        padding: 0 0.5rem;
    }

    /* Ikon Menu Kanan */
    .nav-icon-link {
        color: #333333;
        font-size: 1.3rem;
        position: relative;
        padding: 0.5rem;
        display: inline-flex;
    }

    /* Badge Penghitung */
    .badge-counter {
        font-size: 0.6rem;
        padding: 0.25em 0.45em;
        transform: translate(15%, -15%);
    }

    .divider-line {
        width: 1px;
        height: 24px;
        background-color: #e5e7e9;
        margin: 0 0.5rem;
    }

    /* Efek Hover Dropdown Otomatis (Hanya Desktop) */
    @media (min-width: 992px) {
        .dropdown-menu {
            margin-top: 0;
            display: block;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
            top: calc(100% + 10px); /* Mulai 10px di bawah posisi akhir */
            transition: opacity 0.2s ease, top 0.2s ease, visibility 0.2s;
            pointer-events: none;
        }
        .dropdown:hover > .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            top: 100%; /* Posisi akhir tepat di bawah pemicu */
            pointer-events: auto;
        }
        /* Menambahkan area transparan di atas dropdown agar hover tidak terputus */
        .dropdown-menu::before {
            content: "";
            position: absolute;
            top: -10px;
            left: 0;
            right: 0;
            height: 10px;
            background: transparent;
        }
    }

    /* Dropdown Notifikasi & User */

    .notif-dropdown { width: 320px; max-width: 95vw; padding: 0; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; } /* Tambahkan max-width */
    .notif-header { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; font-size: 0.85rem; }
    .notif-item { padding: 12px 15px; border-bottom: 1px solid #f8f8f8; display: flex; align-items: center; text-decoration: none; color: #333; }
    .notif-item.cart-item { padding: 8px 15px; } /* Padding lebih kecil untuk item keranjang */
    .notif-item:hover { background-color: #f9f9f9; }
    #admin-notif-list, #customer-notif-list { max-height: 350px; overflow-y: auto; }
    
    .user-dropdown-menu {
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        border-radius: 8px;
    }
    
    .user-dropdown-menu .dropdown-item {
        font-size: 0.8rem;
        padding: 0.6rem 1.2rem;
    }

    /* Perbaikan untuk teks yang terlalu panjang saat zoom */
    .notif-item div {
        min-width: 0;
        word-wrap: break-word;
    }

    /* Optimalisasi Zoom & Responsivitas Layout */
    .navbar-icons-wrapper {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0; /* Mencegah ikon terhimpit saat zoom tinggi */
    }
    
    .brand-logo { 
        flex-shrink: 0; /* Logo tetap pada ukurannya saat zoom */
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light sticky-top navbar-thriftking888 shadow-sm">
    <div class="container d-flex align-items-center justify-content-between flex-wrap flex-lg-nowrap gap-2">
        <div class="d-flex align-items-center gap-4 order-1">
            <a class="brand-logo text-dark me-2" href="<?= $base_url ?>">
                THRIFT<span class="text-muted">KING</span>888
            </a>

            <div class="d-none d-sm-flex align-items-center gap-3">
                <a class="nav-link-item text-uppercase" href="<?= $base_url ?>produk/thrifting">Thrifting</a>

                <!--<a class="nav-link-item text-uppercase" href="<?= $base_url ?>produk/vintage">Vintage</a>-->

            </div>
        </div>

        <div class="search-wrapper flex-grow-1 mx-lg-3 order-3 order-lg-2 w-100 w-lg-auto">
            <form action="<?= $base_url ?>produk" method="get">
                <input type="hidden" name="kategori" value="<?= $current_category ?>">
                <div class="search-group">
                    <input type="text" name="search" value="<?= $search_query ?>"
                    class="form-control shadow-none" placeholder="Cari kaos Thrift, atau produk thrift lainnya...">
                    <button type="submit" class="btn-search-submit" aria-label="Submit Search">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="d-flex align-items-center gap-1 order-2 order-lg-3 navbar-icons-wrapper">
            
            <?php if ($isLoggedIn && $userRole === 'admin'): ?>
                <div class="dropdown">
                    <a href="#" id="adminNotifToggle" class="nav-icon-link" data-bs-toggle="dropdown" title="Admin Notifications">
                        <i class="bi bi-bell"></i>
                        <span id="admin-notif-badge" class="position-absolute top-0 start-50 badge rounded-pill bg-danger badge-counter" style="display: <?= $notif_count > 0 ? 'inline-block' : 'none' ?>;">
                            <?= intval($notif_count) ?>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow">
                        <div class="notif-header">Notifikasi Sistem</div>
                        <div id="admin-notif-list" class="p-3 text-center small text-muted">Cek dashboard untuk detail lengkap.</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && ($userRole === 'pelanggan' || !$userRole)): ?>
                <!-- Ikon Inbox (Chat & Ulasan) -->
                <div class="dropdown">
                    <a href="#" class="nav-icon-link" data-bs-toggle="dropdown" title="Inbox">
                        <i class="bi bi-chat-dots"></i>
                        <?php $inbox_total = intval($review_pending_count ?? 0); ?>
                        <span id="chat-badge" class="position-absolute top-0 start-50 badge rounded-pill bg-danger badge-counter" style="display: <?= $inbox_total > 0 ? 'inline-block' : 'none' ?>;">
                            <?= $inbox_total ?>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow">
                        <div class="notif-header">Kotak Masuk</div>
                        <?php
                        $whatsapp_number = $global_settings['no_hp'] ?? ''; // REVISI: Menggunakan 'no_hp' sesuai database
                        if (!empty($whatsapp_number)): 
                            // Format nomor WA: hilangkan 0 di depan, ganti dengan 62
                            $formatted_wa = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $whatsapp_number));
                            $wa_message = urlencode("Halo, saya ingin bertanya tentang produk di ThriftKing888.");
                            $wa_link = "https://wa.me/{$formatted_wa}?text={$wa_message}";
                        ?>
                        <a href="<?= $wa_link ?>" class="notif-item" target="_blank" title="Chat via WhatsApp">
                            <div class="me-2 text-success"><i class=""></i></div>
                            <div><div class="small fw-bold">Chat</div><div class="text-muted small" style="font-size:0.7rem">Tanya admin tentang produk.</div></div>
                        </a>
                        <?php else: ?>
                        <!--<a href="#" class="notif-item disabled" title="Fitur chat belum diaktifkan oleh admin.">
                            <div class="me-2 text-muted"><i class="bi bi-chat-left-text fs-5"></i></div>
                            <div><div class="small fw-bold">Chat</div><div class="text-muted small" style="font-size:0.7rem">Fitur dinonaktifkan</div></div>
                        </a>-->
                        <?php endif; ?>
                        <a href="<?= $base_url ?>pelanggan/menunggu-ulasan" class="notif-item">
                            <div class="me-2 text-warning"><i class=""></i></div>
                            <div><div class="small fw-bold">Menunggu Diulas</div><div class="text-muted small" style="font-size:0.7rem">Ada <?= $inbox_total ?> produk menunggu ulasanmu.</div></div>
                        </a>
                        <a href="<?= $base_url ?>pelanggan/ulasan-saya" class="notif-item">
                            <div class="me-2 text-info"><i class=""></i></div>
                            <div><div class="small fw-bold">Ulasan Saya</div><div class="text-muted small" style="font-size:0.7rem">Lihat riwayat ulasan yang Anda berikan.</div></div>
                        </a>
                    </div>
                </div>

                <div class="dropdown">
                    <a href="#" class="nav-icon-link" data-bs-toggle="dropdown" title="Notifikasi">
                        <i class="bi bi-bell"></i>
                        <span id="customer-notif-badge" class="position-absolute top-0 start-50 badge rounded-pill bg-danger badge-counter" style="display: <?= $notif_count > 0 ? 'inline-block' : 'none' ?>;">
                            <?= intval($notif_count) ?>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow">
                        <div class="notif-header">Notifikasi (<?= intval($notif_count) ?>)</div>
                        <?php if ($notif_count > 0): ?>
                            <a href="<?= $base_url ?>pelanggan/pesanan" class="notif-item">
                                <div class="me-2 text-primary"><i class="bi bi-box-seam fs-"></i></div>
                                <div><div class="small fw-bold">Pesanan Aktif</div><div class="text-muted small" style="font-size:0.7rem">Ada pesanan berjalan.</div></div>
                            </a>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted small">Tidak ada notifikasi baru</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($userRole === 'pelanggan' || !$isLoggedIn): ?>
                <div class="dropdown">
                    <a href="<?= $base_url ?>pelanggan/keranjang" class="nav-icon-link" data-bs-toggle="dropdown" title="Keranjang Belanja">
                        <i class="bi bi-cart3"></i>
                        <span id="cart-badge" class="position-absolute top-0 start-50 badge rounded-pill bg-danger badge-counter" style="display: <?= $cart_item_count > 0 ? 'inline-block' : 'none' ?>;">
                            <?= intval($cart_item_count) ?>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow">
                        <div class="notif-header">Keranjang Belanja (<?= intval($cart_item_count) ?>)</div>
                        <?php
                        $cart_items_detail = $_SESSION['keranjang'] ?? [];
                        // Ambil 3 item teratas untuk ditampilkan
                        $top_items_to_display = array_slice($cart_items_detail, 0, 3, true); 
                        
                        if ($cart_item_count > 0):
                        ?>
                            <div class="cart-dropdown-list" style="max-height: 250px; overflow-y: auto;">
                            <?php foreach ($top_items_to_display as $item):
                                $img_src = !empty($item['gambar_utama']) 
                                    ? BASE_URL . 'assets/img/products/' . htmlspecialchars($item['gambar_utama'], ENT_QUOTES, 'UTF-8')
                                    : BASE_URL . 'assets/img/no-image.png';
                            ?>
                                <a href="<?= BASE_URL ?>detail/<?= htmlspecialchars($item['product_id'], ENT_QUOTES, 'UTF-8') ?>" class="notif-item cart-item">
                                    <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>" 
                                         class="me-2 rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <div class="small fw-bold text-truncate"><?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small" style="font-size:0.7rem">
                                            <?= intval($item['jumlah']) ?> x <?= formatRupiah($item['harga_jual']) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            </div>
                            <?php if (count($cart_items_detail) > 3): ?>
                                <a href="<?= $base_url ?>pelanggan/keranjang" class="notif-item border-top">
                                    <div class="me-2 text-primary"><i class="bi bi-three-dots fs-5"></i></div>
                                    <div><div class="small fw-bold">Lihat Semua (<?= $cart_item_count ?>)</div><div class="text-muted small" style="font-size:0.7rem">Lihat atau Checkout sekarang.</div></div>
                                </a>
                            <?php else: ?>
                                <!-- Tombol Lihat Keranjang Lengkap (tanpa ikon) -->
                                <a href="<?= $base_url ?>pelanggan/keranjang" class="notif-item border-top">
                                    <div><div class="small fw-bold">Lihat Keranjang Lengkap</div><div class="text-muted small" style="font-size:0.7rem">Lihat atau Checkout sekarang.</div></div>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="p-3 text-align text-muted small">Keranjang belanja kosong.</div>
                            <!-- Tombol Buka Halaman Keranjang (tanpa ikon) -->
                            <a href="<?= $base_url ?>pelanggan/keranjang" class="notif-item border-top">
                                <div><div class="small fw-bold">Lihat Keranjang</div><div class="text-muted small" style="font-size:0.7rem">Lihat halaman keranjang Anda.</div></div>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="divider-line d-none d-sm-block"></div>

            <?php if($isLoggedIn): ?>
                <div class="dropdown ms-1">
                    <a class="text-decoration-none text-dark small fw-bold d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5"></i>
                        <span class="d-none d-sm-inline text-truncate" style="font-size: 0.8rem; letter-spacing: 0.5px;"><?= htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu shadow mt-2">
                        <?php if($userRole === 'admin'): ?>
                            <li><a class="dropdown-item  black" href="<?= $base_url ?>admin/dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= $base_url ?>admin/profil"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="<?= $base_url ?>pelanggan/profil"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="<?= $base_url ?>pelanggan/pesanan"><i class="bi bi-bag-check me-2"></i>Pesanan</a></li>
                            <!-- <li><a class="dropdown-item" href="<?= $base_url ?>pelanggan/profil"><i class="bi bi-gear me-2"></i>Pengaturan Akun</a></li> -->
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= $base_url ?>auth/logout"><i class="bi bi-power me-2"></i>Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center gap-2 ms-1">
                    <a href="<?= $base_url ?>auth/login" class="btn btn-sm btn-dark px-3 rounded-2 fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Login</a>
                    <a href="<?= $base_url ?>auth/register" class="btn btn-sm btn-outline-dark px-3 rounded-2 fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Register</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</nav>