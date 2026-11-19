-- ============================================================================
-- SKEMA DATABASE & DATA AWAL WILAYAH BANDUNG
-- Aplikasi : Sistem Informasi Geografis Distribusi Makanan - PT Maju Jaya Food
-- Database : maju_jaya_gis
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `maju_jaya_gis`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `maju_jaya_gis`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `shipments`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `warehouses`;
DROP TABLE IF EXISTS `admins`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. TABEL ADMINS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABEL WAREHOUSES (Titik Awal Pengiriman / Gudang Distribusi)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `address` TEXT NOT NULL,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABEL CUSTOMERS (Titik Tujuan Pengiriman: Toko, Minimarket, Restoran)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(120) NOT NULL,
    `category` ENUM('Toko', 'Minimarket', 'Restoran') NOT NULL,
    `pic_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `address` TEXT NOT NULL,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABEL PRODUCTS (Katalog Barang Makanan Distribusi)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(120) NOT NULL,
    `category` VARCHAR(60) NOT NULL,
    `unit` VARCHAR(30) NOT NULL,
    `weight_per_unit` DECIMAL(8,2) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABEL SHIPMENTS (Data & Monitoring Pengiriman)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `shipments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `shipment_code` VARCHAR(20) NOT NULL UNIQUE,
    `warehouse_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `total_weight` DECIMAL(10,2) NOT NULL,
    `vehicle_type` VARCHAR(50) NOT NULL,
    `driver_name` VARCHAR(100) NOT NULL,
    `distance_km` DECIMAL(8,2) NOT NULL,
    `duration_minutes` INT NOT NULL,
    `route_geometry` LONGTEXT NULL,
    `status` ENUM('Menunggu', 'Dalam pengiriman', 'Selesai dikirim') NOT NULL DEFAULT 'Menunggu',
    `shipment_date` DATE NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_shipments_warehouse`
        FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_shipments_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_shipments_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATA AWAL (SEEDER)
-- ============================================================================

-- 1. Akun Admin (Username: admin | Password: admin123)
INSERT INTO `admins` (`id`, `name`, `username`, `password`) VALUES
(1, 'Administrator Logistik', 'admin', '$2y$12$LexOOyqe/zXHu3m3UHYr0u8a4yjZ4zbK0IJkdKprphljkUj7sbAQu');

-- 2. Gudang Utama PT Maju Jaya Food (Bandung)
INSERT INTO `warehouses` (`id`, `name`, `address`, `latitude`, `longitude`) VALUES
(1, 'Gudang Utama PT Maju Jaya Food', 'Jl. Soekarno-Hatta No. 450, Batununggal, Kota Bandung, Jawa Barat', -6.94309700, 107.63354500);

-- 3. 10 Pelanggan Riil Kawasan Bandung & Sekitarnya
INSERT INTO `customers` (`id`, `code`, `name`, `category`, `pic_name`, `phone`, `address`, `latitude`, `longitude`) VALUES
(1,  'PLG-001', 'Toko Berkah',          'Toko',       'H. Asep Sunandar',   '081223456701', 'Jl. Buah Batu No. 182, Lengkong, Kota Bandung',              -6.94881200, 107.63489000),
(2,  'PLG-002', 'Minimarket Sejahtera', 'Minimarket', 'Rina Kartika',       '081223456702', 'Jl. Ir. H. Juanda (Dago) No. 115, Coblong, Kota Bandung',    -6.89041500, 107.61321000),
(3,  'PLG-003', 'Restoran Sunda Rasa',  'Restoran',   'Chef Tatang Sudrajat','081223456703', 'Jl. LLRE Martadinata (Riau) No. 68, Bandung Wetan, Bandung', -6.90823000, 107.62145000),
(4,  'PLG-004', 'Toko Sembako Makmur',  'Toko',       'Yudi Hermawan',      '081223456704', 'Jl. Raya Kopo No. 310, Bojongloa Kidul, Kota Bandung',       -6.94211000, 107.58892000),
(5,  'PLG-005', 'Minimarket Surya',     'Minimarket', 'Dewi Anggraeni',     '081223456705', 'Jl. Dr. Setiabudi No. 190, Sukasari, Kota Bandung',          -6.86342000, 107.59412000),
(6,  'PLG-006', 'Restoran Nusantara',   'Restoran',   'Hendra Gunawan',     '081223456706', 'Jl. Asia Afrika No. 92, Sumur Bandung, Kota Bandung',        -6.92145000, 107.60781000),
(7,  'PLG-007', 'Toko Pangan Jaya',     'Toko',       'Agus Salim',         '081223456707', 'Jl. Terusan Jakarta No. 140, Antapani, Kota Bandung',        -6.91562000, 107.66123000),
(8,  'PLG-008', 'Minimarket Prima',     'Minimarket', 'Siti Nurhaliza',     '081223456708', 'Jl. Raya Cibabat No. 215, Cimahi Utara, Kota Cimahi',        -6.88512000, 107.55432000),
(9,  'PLG-009', 'Restoran Dapur Kita',  'Restoran',   'Lukman Hakim',       '081223456709', 'Jl. Dr. Djunjunan (Pasteur) No. 88, Sukajadi, Kota Bandung', -6.89432000, 107.58612000),
(10, 'PLG-010', 'Toko Grosir Sarijadi', 'Toko',       'Bambang Sutrisno',   '081223456710', 'Jl. Sarijadi Raya No. 54, Sukasari, Kota Bandung',           -6.87654000, 107.57689000);

-- 4. 8 Produk Makanan Distribusi
INSERT INTO `products` (`id`, `code`, `name`, `category`, `unit`, `weight_per_unit`, `stock`) VALUES
(1, 'BRG-001', 'Makanan Kemasan Ringan',    'Makanan Kemasan', 'Kardus', 2.50,  150),
(2, 'BRG-002', 'Minyak Goreng Kemasan 2L',  'Bahan Pokok',     'Kardus', 12.00, 80),
(3, 'BRG-003', 'Bumbu Masak Instan',        'Bahan Pokok',     'Box',    5.00,  200),
(4, 'BRG-004', 'Minuman Teh Kemasan Botol', 'Minuman',         'Kardus', 8.50,  120),
(5, 'BRG-005', 'Saus Sambal & Tomat Botol', 'Bahan Pokok',     'Kardus', 10.00, 95),
(6, 'BRG-006', 'Frozen Nugget Ayam 500g',   'Frozen Food',     'Box',    10.00, 60),
(7, 'BRG-007', 'Tepung Terigu Serbaguna',   'Bahan Pokok',     'Bal',    25.00, 70),
(8, 'BRG-008', 'Biskuit Kaleng Assorted',   'Makanan Kemasan', 'Kardus', 6.00,  110);

-- 5. 6 Data Pengiriman Awal (Shipments)
INSERT INTO `shipments` (
    `id`, `shipment_code`, `warehouse_id`, `customer_id`, `product_id`,
    `quantity`, `total_weight`, `vehicle_type`, `driver_name`,
    `distance_km`, `duration_minutes`, `route_geometry`,
    `status`, `shipment_date`, `notes`
) VALUES
(
    1, 'KRM001', 1, 1, 1,
    20, 50.00, 'Mobil Boks', 'Andi',
    1.15, 5,
    '{"type":"LineString","coordinates":[[107.633545,-6.943097],[107.634120,-6.945500],[107.634890,-6.948812]]}',
    'Selesai dikirim', '2026-11-16', 'Pengiriman makanan kemasan ringan diterima oleh H. Asep Sunandar.'
),
(
    2, 'KRM002', 1, 2, 2,
    15, 180.00, 'Mobil Boks', 'Budi',
    8.20, 24,
    '{"type":"LineString","coordinates":[[107.633545,-6.943097],[107.631200,-6.925400],[107.620100,-6.908900],[107.613210,-6.890415]]}',
    'Selesai dikirim', '2026-11-17', 'Stok minyak goreng bulanan untuk Minimarket Sejahtera Dago.'
),
(
    3, 'KRM003', 1, 3, 5,
    12, 120.00, 'Pickup Boks', 'Dedi',
    5.40, 16,
    '{"type":"LineString","coordinates":[[107.633545,-6.943097],[107.630500,-6.928100],[107.625800,-6.915400],[107.621450,-6.908230]]}',
    'Dalam pengiriman', '2026-11-18', 'Pasokan saus sambal dan tomat untuk dapur utama Restoran Sunda Rasa.'
),
(
    4, 'KRM004', 1, 6, 6,
    10, 100.00, 'Van Pendingin', 'Rudi',
    4.65, 14,
    '{"type":"LineString","coordinates":[[107.633545,-6.943097],[107.622400,-6.935100],[107.613900,-6.926800],[107.607810,-6.921450]]}',
    'Dalam pengiriman', '2026-11-18', 'Wajib suhu dingin stabil (-18C) selama perjalanan ke Asia Afrika.'
),
(
    5, 'KRM005', 1, 4, 7,
    14, 350.00, 'Mobil Boks', 'Andi',
    6.10, 18,
    '{"type":"LineString","coordinates":[[107.633545,-6.943097],[107.618200,-6.943500],[107.601500,-6.942800],[107.588920,-6.942110]]}',
    'Menunggu', '2026-11-18', 'Menunggu antrean muat barang di Gudang Utama Soekarno-Hatta.'
),
(
    6, 'KRM006', 1, 7, 4,
    25, 212.50, 'Pickup Boks', 'Budi',
    5.85, 17,
    '{"type":"LineString","coordinates":[[107.633545,-6.943097],[107.644800,-6.936200],[107.654200,-6.924500],[107.661230,-6.915620]]}',
    'Menunggu', '2026-11-18', 'Jadwal pengiriman sore ke Toko Pangan Jaya Antapani.'
);
