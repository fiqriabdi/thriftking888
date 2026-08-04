<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Admin Panel';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - ThriftKing888</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #f4f6f9;
            color: #222;
            font-family: 'Inter', sans-serif;
        }
        .sidebar {
            background: #1f2a37;
            color: #e9edf5;
            min-height: 100vh;
            padding: 24px 18px;
        }
        .sidebar h5 {
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
        }
        .sidebar a {
            color: #cfd8e9;
            text-decoration: none;
            padding: 12px 14px;
            display: block;
            border-radius: 10px;
            margin-bottom: 6px;
            transition: background .2s ease, color .2s ease;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background: #293948;
            color: #fff;
        }
        .sidebar .sidebar-subtitle {
            font-size: 13px;
            color: #9aa6b5;
            margin-bottom: 1rem;
        }
        .sidebar .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 1rem;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
        }
        .sidebar .user-badge i {
            font-size: 1.15rem;
        }
        .main-content {
            padding: 30px 28px;
        }
        .admin-page-title {
            margin-bottom: 1.5rem;
        }
        .card {
            border-radius: 16px;
        }
        .btn-light-outline {
            border: 1px solid #dee2e6;
        }
        .table thead th {
            border-bottom: 2px solid #dee2e6;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 24px;
            left: 18px;
            right: 18px;
        }
        .sidebar-footer a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #adb8cc;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar position-relative">
                <h5><i class="fas fa-crown me-2"></i>Admin Panel</h5>
                <div class="sidebar-subtitle">Halo, <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'Administrator', ENT_QUOTES, 'UTF-8') ?></div>
                <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-line me-2"></i> Dashboard</a>
                <a href="pengguna.php" class="<?= $activePage === 'pengguna' ? 'active' : '' ?>"><i class="fas fa-users me-2"></i> Pengguna</a>
                <a href="produk_index.php" class="<?= $activePage === 'produk' ? 'active' : '' ?>"><i class="fas fa-box me-2"></i> Produk</a>
                <a href="pesanan.php" class="<?= $activePage === 'pesanan' ? 'active' : '' ?>"><i class="fas fa-shopping-cart me-2"></i> Pesanan</a>
                <a href="ulasan.php" class="<?= $activePage === 'ulasan' ? 'active' : '' ?>"><i class="fas fa-comments me-2"></i> Ulasan</a>
                <a href="laporan.php" class="<?= $activePage === 'laporan' ? 'active' : '' ?>"><i class="fas fa-chart-bar me-2"></i> Laporan</a>
                <a href="settings.php" class="<?= $activePage === 'settings' ? 'active' : '' ?>"><i class="fas fa-cog me-2"></i> Pengaturan</a>
                <div class="sidebar-footer">
                    <a href="<?= BASE_URL ?>auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center admin-page-title">
                    <div>
                        <h2 class="mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                </div>
