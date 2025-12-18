-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2025 at 03:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_monitoring`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('create','update','delete','approve','reject','login','logout') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` varchar(64) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `budget_id` int(11) NOT NULL,
  `kat_pengeluaran_id` int(11) DEFAULT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah_budget` decimal(15,2) NOT NULL,
  `jumlah_terpakai` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sisa_budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('aman','warning','overlimit') NOT NULL DEFAULT 'aman'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_flow`
--

CREATE TABLE `cash_flow` (
  `kas_id` int(11) NOT NULL,
  `transaksi_masuk_id` int(11) DEFAULT NULL,
  `transaksi_keluar_id` int(11) DEFAULT NULL,
  `tipe` enum('pemasukan','pengeluaran') NOT NULL,
  `saldo_awal` decimal(15,2) NOT NULL,
  `saldo_akhir` decimal(15,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `catatan`
--

CREATE TABLE `catatan` (
  `catatan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `judul` varchar(100) NOT NULL,
  `isi_catatan` text NOT NULL,
  `tipe` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_summary`
--

CREATE TABLE `daily_summary` (
  `tanggal` date NOT NULL,
  `saldo_awal` decimal(15,2) NOT NULL,
  `total_pemasukan` decimal(15,2) NOT NULL,
  `total_pengeluaran` decimal(15,2) NOT NULL,
  `saldo_akhir` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kas_shift`
--

CREATE TABLE `kas_shift` (
  `shift_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kasir_user_id` int(11) NOT NULL,
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `opening_cash` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expected_closing_cash` decimal(15,2) DEFAULT 0.00,
  `actual_closing_cash` decimal(15,2) DEFAULT 0.00,
  `variance` decimal(15,2) DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori_pemasukan`
--

CREATE TABLE `kategori_pemasukan` (
  `kat_pemasukan_id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori_pengeluaran`
--

CREATE TABLE `kategori_pengeluaran` (
  `kat_pengeluaran_id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_summary`
--

CREATE TABLE `monthly_summary` (
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `total_pemasukan` decimal(15,2) NOT NULL,
  `total_pengeluaran` decimal(15,2) NOT NULL,
  `rata_rata_harian` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_file`
--

CREATE TABLE `transaksi_file` (
  `file_id` int(11) NOT NULL,
  `tipe` enum('pemasukan','pengeluaran') NOT NULL,
  `ref_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `size_bytes` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_pemasukan`
--

CREATE TABLE `transaksi_pemasukan` (
  `transaksi_masuk_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kat_pemasukan_id` int(11) NOT NULL,
  `tanggal_masuk` date NOT NULL,
  `jumlah_pemasukan` decimal(15,2) NOT NULL,
  `deskripsi` varchar(100) DEFAULT NULL,
  `status` enum('draft','approved','rejected') NOT NULL DEFAULT 'draft',
  `shift_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_pengeluaran`
--

CREATE TABLE `transaksi_pengeluaran` (
  `transaksi_keluar_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kat_pengeluaran_id` int(11) NOT NULL,
  `tanggal_keluar` date NOT NULL,
  `jumlah_pengeluaran` decimal(15,2) NOT NULL,
  `deskripsi` varchar(100) DEFAULT NULL,
  `status` enum('draft','approved','rejected') NOT NULL DEFAULT 'draft',
  `shift_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pemilik') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'admin'),
(2, 'owner', '5be057accb25758101fa5eadbbd79503', 'pemilik');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_when` (`created_at`),
  ADD KEY `idx_audit_where` (`table_name`,`record_id`),
  ADD KEY `audit_user_fk` (`user_id`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `idx_bgt_byt` (`bulan`,`tahun`),
  ADD KEY `idx_bgt_kat` (`kat_pengeluaran_id`);

--
-- Indexes for table `cash_flow`
--
ALTER TABLE `cash_flow`
  ADD PRIMARY KEY (`kas_id`),
  ADD KEY `idx_cf_in` (`transaksi_masuk_id`),
  ADD KEY `idx_cf_out` (`transaksi_keluar_id`);

--
-- Indexes for table `catatan`
--
ALTER TABLE `catatan`
  ADD PRIMARY KEY (`catatan_id`);

--
-- Indexes for table `daily_summary`
--
ALTER TABLE `daily_summary`
  ADD PRIMARY KEY (`tanggal`);

--
-- Indexes for table `kas_shift`
--
ALTER TABLE `kas_shift`
  ADD PRIMARY KEY (`shift_id`),
  ADD KEY `idx_shift_tgl` (`tanggal`),
  ADD KEY `shift_user_fk` (`kasir_user_id`);

--
-- Indexes for table `kategori_pemasukan`
--
ALTER TABLE `kategori_pemasukan`
  ADD PRIMARY KEY (`kat_pemasukan_id`);

--
-- Indexes for table `kategori_pengeluaran`
--
ALTER TABLE `kategori_pengeluaran`
  ADD PRIMARY KEY (`kat_pengeluaran_id`);

--
-- Indexes for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  ADD PRIMARY KEY (`bulan`,`tahun`);

--
-- Indexes for table `transaksi_file`
--
ALTER TABLE `transaksi_file`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `idx_tf_ref` (`tipe`,`ref_id`),
  ADD KEY `tf_user_fk` (`uploaded_by`);

--
-- Indexes for table `transaksi_pemasukan`
--
ALTER TABLE `transaksi_pemasukan`
  ADD PRIMARY KEY (`transaksi_masuk_id`),
  ADD KEY `idx_in_tanggal` (`tanggal_masuk`),
  ADD KEY `idx_in_status` (`status`),
  ADD KEY `idx_in_kat` (`kat_pemasukan_id`),
  ADD KEY `in_shift_fk` (`shift_id`),
  ADD KEY `idx_in_user` (`user_id`);

--
-- Indexes for table `transaksi_pengeluaran`
--
ALTER TABLE `transaksi_pengeluaran`
  ADD PRIMARY KEY (`transaksi_keluar_id`),
  ADD KEY `idx_out_tanggal` (`tanggal_keluar`),
  ADD KEY `idx_out_status` (`status`),
  ADD KEY `idx_out_kat` (`kat_pengeluaran_id`),
  ADD KEY `out_shift_fk` (`shift_id`),
  ADD KEY `idx_out_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_username` (`username`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `bgt_kat_fk` FOREIGN KEY (`kat_pengeluaran_id`) REFERENCES `kategori_pengeluaran` (`kat_pengeluaran_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cash_flow`
--
ALTER TABLE `cash_flow`
  ADD CONSTRAINT `cf_in_fk` FOREIGN KEY (`transaksi_masuk_id`) REFERENCES `transaksi_pemasukan` (`transaksi_masuk_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `cf_out_fk` FOREIGN KEY (`transaksi_keluar_id`) REFERENCES `transaksi_pengeluaran` (`transaksi_keluar_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `kas_shift`
--
ALTER TABLE `kas_shift`
  ADD CONSTRAINT `shift_user_fk` FOREIGN KEY (`kasir_user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `transaksi_file`
--
ALTER TABLE `transaksi_file`
  ADD CONSTRAINT `tf_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transaksi_pemasukan`
--
ALTER TABLE `transaksi_pemasukan`
  ADD CONSTRAINT `fk_in_kat` FOREIGN KEY (`kat_pemasukan_id`) REFERENCES `kategori_pemasukan` (`kat_pemasukan_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_in_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `in_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `kas_shift` (`shift_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `transaksi_pengeluaran`
--
ALTER TABLE `transaksi_pengeluaran`
  ADD CONSTRAINT `fk_out_kat` FOREIGN KEY (`kat_pengeluaran_id`) REFERENCES `kategori_pengeluaran` (`kat_pengeluaran_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_out_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `out_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `kas_shift` (`shift_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
