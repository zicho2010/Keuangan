-- =====================================================
-- MIGRATION SQL - Semua Perubahan Database
-- =====================================================
-- File ini berisi semua perubahan database yang diperlukan
-- untuk fitur: Upload Bukti, Penutupan Kas, Activity Log
-- 
-- CARA MENGGUNAKAN:
-- 1. Buka phpMyAdmin
-- 2. Pilih database: db_monitoring
-- 3. Klik tab "SQL"
-- 4. Copy-paste script ini
-- 5. Klik "Go" atau tekan Ctrl+Enter
-- =====================================================

-- =====================================================
-- 1. Tambah kolom STATUS ke tabel transaksi_file
-- =====================================================
-- Kolom ini untuk approval workflow bukti transaksi
-- Status: pending, approved, rejected
-- =====================================================

-- Cek apakah kolom sudah ada, jika belum tambahkan
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transaksi_file' 
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `transaksi_file` 
     ADD COLUMN `status` ENUM(''pending'', ''approved'', ''rejected'') NOT NULL DEFAULT ''pending'' AFTER `uploaded_at`',
    'SELECT ''Kolom status sudah ada di tabel transaksi_file'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. Pastikan kolom STATUS ada di transaksi_pemasukan
-- =====================================================
-- Kolom ini untuk approval workflow transaksi pemasukan
-- Status: draft, approved, rejected
-- =====================================================

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transaksi_pemasukan' 
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `transaksi_pemasukan` 
     ADD COLUMN `status` ENUM(''draft'', ''approved'', ''rejected'') NOT NULL DEFAULT ''draft''',
    'SELECT ''Kolom status sudah ada di tabel transaksi_pemasukan'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. Pastikan kolom STATUS ada di transaksi_pengeluaran
-- =====================================================
-- Kolom ini untuk approval workflow transaksi pengeluaran
-- Status: draft, approved, rejected
-- =====================================================

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transaksi_pengeluaran' 
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `transaksi_pengeluaran` 
     ADD COLUMN `status` ENUM(''draft'', ''approved'', ''rejected'') NOT NULL DEFAULT ''draft''',
    'SELECT ''Kolom status sudah ada di tabel transaksi_pengeluaran'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. Pastikan tabel kas_shift ada
-- =====================================================
-- Tabel ini untuk penutupan kas harian/shift
-- =====================================================

CREATE TABLE IF NOT EXISTS `kas_shift` (
  `shift_id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kasir_user_id` int(11) NOT NULL,
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `opening_cash` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expected_closing_cash` decimal(15,2) DEFAULT 0.00,
  `actual_closing_cash` decimal(15,2) DEFAULT 0.00,
  `variance` decimal(15,2) DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  PRIMARY KEY (`shift_id`),
  KEY `idx_shift_tgl` (`tanggal`),
  KEY `shift_user_fk` (`kasir_user_id`),
  CONSTRAINT `shift_user_fk` FOREIGN KEY (`kasir_user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 5. Buat VIEW activity_log (alias untuk audit_log)
-- =====================================================
-- Tabel ini untuk audit trail / activity log
-- Catatan: Di kode dashboard menggunakan activity_log, 
-- tapi di database menggunakan audit_log
-- =====================================================

-- Hapus view jika sudah ada
DROP VIEW IF EXISTS `activity_log`;

-- Buat view activity_log yang mengarah ke audit_log
CREATE VIEW `activity_log` AS 
SELECT 
    al.log_id,
    al.user_id,
    al.action,
    al.table_name,
    al.record_id,
    al.old_values,
    al.new_values,
    al.created_at,
    al.ip_address,
    al.user_agent,
    u.username
FROM `audit_log` al
LEFT JOIN `users` u ON al.user_id = u.user_id;

-- =====================================================
-- 6. Tambah kolom username ke audit_log (jika belum ada)
-- =====================================================
-- Untuk memudahkan query di dashboard
-- =====================================================

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_log' 
    AND COLUMN_NAME = 'username'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `audit_log` 
     ADD COLUMN `username` varchar(100) DEFAULT NULL AFTER `user_id`',
    'SELECT ''Kolom username sudah ada di tabel audit_log'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 7. Pastikan kolom shift_id ada di transaksi_pemasukan
-- =====================================================
-- Untuk linking transaksi dengan shift
-- =====================================================

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transaksi_pemasukan' 
    AND COLUMN_NAME = 'shift_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `transaksi_pemasukan` 
     ADD COLUMN `shift_id` int(11) DEFAULT NULL AFTER `status`,
     ADD KEY `in_shift_fk` (`shift_id`),
     ADD CONSTRAINT `in_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `kas_shift` (`shift_id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''Kolom shift_id sudah ada di tabel transaksi_pemasukan'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 8. Pastikan kolom shift_id ada di transaksi_pengeluaran
-- =====================================================
-- Untuk linking transaksi dengan shift
-- =====================================================

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transaksi_pengeluaran' 
    AND COLUMN_NAME = 'shift_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `transaksi_pengeluaran` 
     ADD COLUMN `shift_id` int(11) DEFAULT NULL AFTER `status`,
     ADD KEY `out_shift_fk` (`shift_id`),
     ADD CONSTRAINT `out_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `kas_shift` (`shift_id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''Kolom shift_id sudah ada di tabel transaksi_pengeluaran'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 9. Update data existing: Set status approved untuk transaksi lama
-- =====================================================
-- Jika ada transaksi lama yang belum punya status, set sebagai approved
-- =====================================================

-- Update transaksi_pemasukan yang statusnya NULL
UPDATE `transaksi_pemasukan` 
SET `status` = 'approved' 
WHERE `status` IS NULL OR `status` = '';

-- Update transaksi_pengeluaran yang statusnya NULL
UPDATE `transaksi_pengeluaran` 
SET `status` = 'approved' 
WHERE `status` IS NULL OR `status` = '';

-- Update transaksi_file yang statusnya NULL
UPDATE `transaksi_file` 
SET `status` = 'approved' 
WHERE `status` IS NULL OR `status` = '';

-- =====================================================
-- 10. Buat index untuk performa query
-- =====================================================

-- Index untuk transaksi_file.status (cek dulu apakah sudah ada)
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transaksi_file' 
    AND INDEX_NAME = 'idx_tf_status'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX `idx_tf_status` ON `transaksi_file` (`status`)',
    'SELECT ''Index idx_tf_status sudah ada'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- SELESAI
-- =====================================================
-- Semua perubahan database sudah diterapkan
-- Silakan refresh halaman aplikasi
-- =====================================================

SELECT 'Migration selesai! Semua perubahan database sudah diterapkan.' AS message;

