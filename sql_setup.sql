-- ============================================================
-- HEXA DB SETUP — Jalankan script ini di phpMyAdmin atau MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS hexa_db;
USE hexa_db;

-- Tabel account dengan kolom role (admin / individu)
CREATE TABLE IF NOT EXISTS account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','individu') NOT NULL DEFAULT 'individu',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabel reviews
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    avatar VARCHAR(10) DEFAULT '🧑',
    name VARCHAR(100) NOT NULL,
    stars INT NOT NULL,
    text TEXT NOT NULL,
    product VARCHAR(100) DEFAULT 'Produk HEXA',
    image VARCHAR(255) DEFAULT NULL,
    date VARCHAR(30) NOT NULL
);

-- Jika tabel reviews sudah ada sebelumnya (database lama), jalankan baris ini
-- untuk menambahkan kolom gambar tanpa perlu membuat ulang tabel:
-- ALTER TABLE reviews ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER product;

-- Akun admin default (password: admin123)
INSERT INTO account (nama, email, password, role) VALUES
('Administrator', 'admin@hexa.com', MD5('admin123'), 'admin')
ON DUPLICATE KEY UPDATE role='admin';
