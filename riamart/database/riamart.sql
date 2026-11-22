-- Ria Mart — database MySQL / MariaDB
-- Untuk XAMPP / phpMyAdmin. Charset UTF-8 (utf8mb4).
-- Skema + 24 produk contoh. Tidak memuat akun, pesanan, atau kata sandi bawaan.
-- Impor ke server MySQL/MariaDB lokal; akun pengimpor perlu izin CREATE DATABASE.
-- Impor ulang mempertahankan data yang sudah ada (tanpa DROP / TRUNCATE).

SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE DATABASE IF NOT EXISTS `riamart` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `riamart`;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  category ENUM('Sembako','Makanan','Minuman','Rumah Tangga','Perawatan Diri','Ibu & Anak') NOT NULL,
  unit VARCHAR(60) NOT NULL,
  price INT UNSIGNED NOT NULL,
  old_price INT UNSIGNED NOT NULL DEFAULT 0,
  brand VARCHAR(80) NOT NULL DEFAULT '',
  image MEDIUMTEXT NOT NULL,
  badge VARCHAR(20) NOT NULL DEFAULT '',
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  description TEXT NOT NULL,
  active TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_products_catalog (active,category),
  CONSTRAINT chk_product_price CHECK (price > 0),
  CONSTRAINT chk_product_discount CHECK (old_price = 0 OR old_price > price),
  CONSTRAINT chk_product_active CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  user_id CHAR(36) NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id,product_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  phone VARCHAR(24) NOT NULL,
  note TEXT NOT NULL,
  method ENUM('Tunai','QRIS') NOT NULL,
  total BIGINT UNSIGNED NOT NULL,
  status ENUM('Menunggu Pembayaran','Menunggu Verifikasi','Diproses','Siap Diambil','Selesai','Dibatalkan') NOT NULL,
  proof MEDIUMTEXT NOT NULL,
  request_key VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_code (code),
  UNIQUE KEY uq_order_request (user_id,request_key),
  KEY idx_orders_date (created_at),
  KEY idx_orders_status (status),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id CHAR(36) NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  unit VARCHAR(60) NOT NULL,
  price INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_order_product (order_id,product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_order_item_qty CHECK (qty > 0),
  CONSTRAINT chk_order_item_price CHECK (price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
  setting_value MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_key CHAR(64) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at BIGINT UNSIGNED NOT NULL,
  KEY idx_attempt_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
('admin_setup','0'),('qris','');

-- Harga dan stok berikut merupakan contoh awal; sesuaikan dari halaman Kelola toko.
INSERT IGNORE INTO products (id,name,category,unit,price,old_price,brand,image,badge,stock,description,active) VALUES
(1,'Minyak Goreng Sania','Sembako','1 liter',18500,21500,'Sania','assets/product-1.svg','Terlaris',60,'Minyak Goreng Sania, kemasan 1 liter. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(2,'Beras Pandan Wangi','Sembako','5 kg',72500,79000,'PANDAN WANGI','assets/product-2.svg','Hemat',35,'Beras Pandan Wangi, kemasan 5 kg. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(3,'Gula Pasir Gulaku','Sembako','1 kg',17500,19000,'Gulaku','assets/product-3.svg','Promo',48,'Gula Pasir Gulaku, kemasan 1 kg. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(4,'Indomie Mi Goreng','Makanan','85 gram',3200,3500,'Indomie','assets/product-4.svg','Terlaris',120,'Indomie Mi Goreng, kemasan 85 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(5,'Susu Ultra Milk Full Cream','Minuman','1 liter',19500,23000,'ULTRA MILK','assets/product-5.svg','Promo',42,'Susu Ultra Milk Full Cream, kemasan 1 liter. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(6,'Telur Ayam Negeri','Sembako','10 butir',27000,30000,'TELUR SEGAR','assets/product-6.svg','Segar',24,'Telur Ayam Negeri, kemasan 10 butir. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(7,'Oreo Original','Makanan','133 gram',9500,11500,'OREO','assets/product-7.svg','Promo',50,'Oreo Original, kemasan 133 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(8,'Aqua Air Mineral','Minuman','600 ml',3500,0,'AQUA','assets/product-8.svg','',100,'Aqua Air Mineral, kemasan 600 ml. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(9,'Rinso Matic Cair','Rumah Tangga','750 ml',24500,29000,'Rinso','assets/product-9.svg','Hemat',30,'Rinso Matic Cair, kemasan 750 ml. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(10,'Lifebuoy Total 10','Perawatan Diri','100 gram',4500,5500,'Lifebuoy','assets/product-10.svg','Promo',50,'Lifebuoy Total 10, kemasan 100 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(11,'Tepung Terigu Segitiga Biru','Sembako','1 kg',13500,15000,'SEGITIGA BIRU','assets/product-11.svg','',32,'Tepung Terigu Segitiga Biru, kemasan 1 kg. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(12,'Kecap Manis Bango','Sembako','275 ml',18500,21000,'BANGO','assets/product-12.svg','',28,'Kecap Manis Bango, kemasan 275 ml. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(13,'Chitato Sapi Panggang','Makanan','68 gram',11500,13500,'Chitato','assets/product-13.svg','Promo',45,'Chitato Sapi Panggang, kemasan 68 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(14,'Teh Pucuk Harum','Minuman','350 ml',4000,0,'PUCUK HARUM','assets/product-14.svg','',72,'Teh Pucuk Harum, kemasan 350 ml. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(15,'Sunlight Jeruk Nipis','Rumah Tangga','600 ml',14500,17000,'Sunlight','assets/product-15.svg','Hemat',35,'Sunlight Jeruk Nipis, kemasan 600 ml. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(16,'Pepsodent Pencegah Gigi Berlubang','Perawatan Diri','190 gram',12500,15000,'Pepsodent','assets/product-16.svg','',36,'Pepsodent Pencegah Gigi Berlubang, kemasan 190 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(17,'Kopi Kapal Api Special','Minuman','165 gram',18500,21000,'KAPAL API','assets/product-17.svg','',40,'Kopi Kapal Api Special, kemasan 165 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(18,'Tisu Paseo Smart','Rumah Tangga','250 lembar',11500,13500,'Paseo','assets/product-18.svg','Promo',40,'Tisu Paseo Smart, kemasan 250 lembar. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(19,'Sarden ABC Saus Tomat','Makanan','155 gram',12500,14500,'ABC','assets/product-19.svg','',35,'Sarden ABC Saus Tomat, kemasan 155 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(20,'Shampo Pantene Anti Ketombe','Perawatan Diri','160 ml',28500,32000,'PANTENE','assets/product-20.svg','',24,'Shampo Pantene Anti Ketombe, kemasan 160 ml. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(21,'Susu Dancow FortiGro','Ibu & Anak','400 gram',46500,52000,'DANCOW','assets/product-21.svg','Hemat',20,'Susu Dancow FortiGro, kemasan 400 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(22,'MamyPoko Pants','Ibu & Anak','M • 20 pcs',43500,49000,'MamyPoko','assets/product-22.svg','Promo',18,'MamyPoko Pants, kemasan M • 20 pcs. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(23,'Biskuit Roma Kelapa','Makanan','300 gram',11500,13000,'ROMA','assets/product-23.svg','',36,'Biskuit Roma Kelapa, kemasan 300 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1),
(24,'Garam Cap Kapal','Sembako','500 gram',4500,0,'CAP KAPAL','assets/product-24.svg','',60,'Garam Cap Kapal, kemasan 500 gram. Pilihan praktis untuk melengkapi kebutuhan sehari-hari keluarga Anda.',1);

-- Akun admin pertama dibuat dari footer website: Kelola toko → Siapkan Toko.
