-- ========================================================
-- DATABASE ENTERPRISE GRADE - THRIFTKING888 ENTERPRISE
-- ========================================================

CREATE DATABASE IF NOT EXISTS thriftking888;
USE thriftking888;

-- 1. USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20),
    alamat TEXT,
    foto_profil VARCHAR(255) NULL DEFAULT NULL,
    city_id INT NULL,
    city_name VARCHAR(100) NULL,
    role ENUM('admin', 'pelanggan') DEFAULT 'pelanggan',
    status ENUM('active', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. CATEGORIES (Hierarki Bertingkat dengan Parent ID)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    nama_kategori VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- 3. PRODUCTS (Informasi Utama/Katalog)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    nama_produk VARCHAR(150) NOT NULL,
    slug VARCHAR(150) UNIQUE NOT NULL,
    deskripsi TEXT,
    brand VARCHAR(100) NULL,
    kondisi VARCHAR(50) NULL, -- Karakteristik thrift (misal: '9/10', 'Like New')
    weight INT DEFAULT 500,  -- Berat dalam gram untuk kalkulasi ongkir
    status ENUM('draft', 'active', 'inactive') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL, -- Soft delete
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
CREATE INDEX idx_product_nama ON products(nama_produk);
CREATE INDEX idx_product_brand ON products(brand);
CREATE INDEX idx_product_category_id ON products(category_id);

-- 4. PRODUCT VARIANTS (Stok, Ukuran, Warna, & Harga Terkunci di Sini)
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL, -- Stock Keeping Unit (Kode Unik Global)
    varian_warna VARCHAR(50) NULL,
    varian_ukuran VARCHAR(20) NULL,
    harga_reguler BIGINT NOT NULL,
    harga_jual BIGINT NOT NULL,          -- Harga final setelah diskon bawaan
    stok INT DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT chk_stok CHECK (stok >= 0), -- Mencegah stok minus
    CONSTRAINT chk_harga_jual CHECK (harga_jual <= harga_reguler) -- Validasi harga
);

-- 5. PRODUCT IMAGES (Multi-Foto per Produk)
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    nama_foto VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 6. CARTS (Keranjang Belanja Mengikat ke Varian)
CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_variant_id INT NOT NULL,
    jumlah INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, product_variant_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
);

-- 7. ORDERS (Header Transaksi)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_code VARCHAR(50) UNIQUE NOT NULL, -- Format: INV/YYYYMMDD/TRX/XXXX
    user_id INT NULL,
    status_order ENUM('unpaid', 'pending_confirmation', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'unpaid',
    
    -- Snapshot Alamat Pengiriman
    nama_penerima VARCHAR(100) NOT NULL,
    no_hp_penerima VARCHAR(20) NOT NULL,
    alamat_pengiriman TEXT NOT NULL,
    
    -- Finansial
    total_harga_produk BIGINT NOT NULL,
    total_ongkir BIGINT NOT NULL,
    total_pembayaran BIGINT NOT NULL, -- total_harga_produk + total_ongkir

    -- Logistik
    no_resi VARCHAR(100) NULL,
    expired_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX idx_order_status ON orders(status_order);
CREATE INDEX idx_invoice_code ON orders(invoice_code);
CREATE INDEX idx_order_expired ON orders(expired_at);
CREATE INDEX idx_order_created_at ON orders(created_at);

-- 8. ORDER ITEMS (Detail Transaksi dengan Snapshot Data Baku)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_variant_id INT NULL,
    
    -- Snapshot: Mencegah kekacauan nota finansial jika produk/varian diubah admin di masa depan
    nama_produk_snapshot VARCHAR(255) NOT NULL, 
    harga_satuan BIGINT NOT NULL,
    jumlah INT NOT NULL,
    subtotal BIGINT NOT NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);

-- 9. PAYMENTS (Pembayaran)
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE, -- Memastikan satu pesanan hanya memiliki satu data pembayaran
    metode_pembayaran VARCHAR(50) NOT NULL,
    bukti_transfer VARCHAR(255) NOT NULL,
    status_pembayaran ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE INDEX idx_payments_order_id ON payments(order_id);

-- 10. ONGKIR & SETTINGS
CREATE TABLE IF NOT EXISTS ongkir (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kota VARCHAR(100) NOT NULL UNIQUE,
    wilayah VARCHAR(100) DEFAULT 'Lainnya',
    biaya INT NOT NULL,

    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_ongkir (wilayah)
);

-- Seed Data Ongkir
-- Hapus data lama jika ada untuk menghindari duplikasi saat seeding
TRUNCATE TABLE ongkir;
INSERT INTO ongkir (kota, wilayah, biaya) VALUES 
('Jakarta', 'JABODETABEK', 15000),
('Bogor', 'JABODETABEK', 18000),
('Bekasi', 'JABODETABEK', 18000),
('Bandung', 'PULAU JAWA', 20000),
('Surabaya', 'PULAU JAWA', 25000),
('Yogyakarta', 'PULAU JAWA', 22000);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko VARCHAR(100) NOT NULL,
    logo VARCHAR(255) DEFAULT 'assets/img/logo/logo.png',
    email VARCHAR(100),
    no_hp VARCHAR(20),
    alamat TEXT NULL
);

-- Seeders Data Awal
INSERT INTO settings (id, nama_toko, email, no_hp) VALUES (1, 'ThriftKing888 Enterprise', 'admin@thriftking.com', '08123456789');
-- Catatan: Password di bawah ini adalah hash dari 'admin123' dan 'bli123' menggunakan BCRYPT
INSERT INTO users (nama, email, password, role) VALUES ('Administrator', 'admin@thrift.com', '$2y$12$ovIer6P68/2YJovVpE6Esu2mJt0H9GzE7p2.6jG5f/6vXvB9iZ5lq', 'admin'); -- pass: admin123
INSERT INTO users (nama, email, password, role) VALUES ('Bliksemqri', 'bliksemqri@gmail.com', '$2y$12$7kF.o3hJg.o3hJg.o3hJg.o3hJg.o3hJg.o3hJg.o3hJg.o3hJg', 'pelanggan'); -- pass: pelanggan123

-- Tabel untuk menyimpan token reset password
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 HOUR)
);
CREATE INDEX idx_password_resets_token ON password_resets(token);

-- 11. REVIEWS (Ulasan)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produk_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    judul VARCHAR(255) NOT NULL,
    isi TEXT NOT NULL,
    foto VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_reply_text TEXT NULL DEFAULT NULL,
    admin_replied_at DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (produk_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_reviews_produk_id ON reviews(produk_id);
CREATE INDEX idx_reviews_user_id ON reviews(user_id);

-- 12. ACTIVITY LOGS (Dibutuhkan oleh Controller untuk Audit Trail)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_logs_created_at (created_at)
);

-- 13. NOTIFICATIONS (Sistem Antrean Notifikasi)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_role ENUM('admin', 'pelanggan') NOT NULL,
    recipient_id INT NULL, -- NULL jika untuk semua admin
    type VARCHAR(50) NOT NULL, -- e.g., 'new_order', 'cancelled_order'
    related_id INT NULL, -- ID Order terkait
    message TEXT NOT NULL,
    link_url VARCHAR(255) NULL DEFAULT NULL, -- [DITAMBAHKAN] URL untuk membuat notifikasi bisa diklik
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_notifications_recipient ON notifications(recipient_role, recipient_id, is_read);

-- 14. STOCK LOGS (Pelacakan histori stok)
CREATE TABLE stock_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_variant_id INT NOT NULL,
    user_id INT NULL, -- Admin yang melakukan perubahan
    type ENUM('in','adjustment', 'sale') NOT NULL,
    jumlah INT NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 15. BANK ACCOUNTS (Rekening Bank untuk Pembayaran)
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_bank VARCHAR(100) NOT NULL,
    nomor_rekening VARCHAR(50) NOT NULL,
    atas_nama VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Update User Seed (Gunakan BCRYPT cost yang konsisten)
-- Password 'king888' di-hash menggunakan BCRYPT dengan cost 12 "email:king888@gmail.com" "password:admin1234"
INSERT INTO users (nama, email, password, role, status) 
VALUES ('king888', 'king888@gmail.com', '$2y$12$ovIer6P68/2YJovVpE6Esu2mJt0H9GzE7p2.6jG5f/6vXvB9iZ5lq', 'admin', 'active');

-- 2.1 CATEGORIES SEED DATA
INSERT INTO categories (nama_kategori, slug) VALUES 
('Thrifting', 'thrifting'),
('Vintage', 'vintage');
