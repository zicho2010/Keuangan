-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 22, 2025 at 12:56 PM
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
-- Stand-in structure for view `activity_log`
-- (See below for the actual view)
--
CREATE TABLE `activity_log` (
`log_id` bigint(20)
,`user_id` int(11)
,`action` enum('create','update','delete','approve','reject','login','logout')
,`table_name` varchar(100)
,`record_id` varchar(64)
,`old_values` longtext
,`new_values` longtext
,`created_at` datetime
,`ip_address` varchar(45)
,`user_agent` varchar(255)
,`username` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` enum('create','update','delete','approve','reject','login','logout') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` varchar(64) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `user_id`, `username`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `created_at`, `ip_address`, `user_agent`) VALUES
(1, 2, NULL, 'logout', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 03:08:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(2, 1, NULL, 'login', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-18 03:08:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(3, 1, NULL, 'logout', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-18 03:08:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(4, 2, NULL, 'login', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 03:08:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(5, 2, NULL, 'logout', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 03:08:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(6, 1, NULL, 'login', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-18 03:09:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(7, 1, NULL, 'logout', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-18 03:10:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(8, 2, NULL, 'login', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 03:10:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(9, 2, NULL, 'logout', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 03:31:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(10, 2, NULL, 'login', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 13:39:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(11, 2, NULL, 'logout', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 13:43:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(12, 1, NULL, 'login', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-18 19:34:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(13, 2, NULL, 'login', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 23:22:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(14, 2, NULL, 'logout', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-18 23:27:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(15, 1, NULL, 'login', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-22 18:29:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(16, 1, NULL, 'create', 'transaksi_file', '2', NULL, '{\"tipe\":\"pengeluaran\",\"ref_id\":3,\"filename\":\"pengeluaran_3_1766403015_69492bc73b4c3.jpg\"}', '2025-12-22 18:30:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(17, 1, NULL, 'create', 'transaksi_file', '3', NULL, '{\"tipe\":\"pemasukan\",\"ref_id\":3,\"filename\":\"pemasukan_3_1766403034_69492bdaab4a7.jpg\"}', '2025-12-22 18:30:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(18, 1, NULL, 'logout', 'users', '1', NULL, '{\"username\":\"admin\",\"role\":\"admin\"}', '2025-12-22 18:30:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(19, 2, NULL, 'login', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-22 18:30:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(20, 2, NULL, 'create', 'kas_shift', '3', NULL, '{\"tanggal\":\"2025-12-22\",\"kasir_user_id\":1,\"opening_cash\":330000}', '2025-12-22 18:31:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(21, 2, NULL, 'update', 'kas_shift', '3', '{\"tanggal\":\"2025-12-22\",\"kasir_user_id\":\"1\",\"opening_cash\":\"330000.00\",\"actual_closing_cash\":\"0.00\",\"notes\":null}', '{\"tanggal\":\"2025-12-22\",\"kasir_user_id\":1,\"opening_cash\":330000,\"actual_closing_cash\":null,\"notes\":null}', '2025-12-22 18:31:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(22, 2, NULL, 'update', 'kas_shift', '3', '{\"status\":\"open\",\"opening_cash\":330000}', '{\"status\":\"closed\",\"expected_closing_cash\":330000,\"actual_closing_cash\":340000,\"variance\":10000}', '2025-12-22 18:33:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(23, 2, NULL, 'approve', 'transaksi_pemasukan', '3', '{\"status\":\"draft\",\"jumlah\":\"15000.00\"}', '{\"status\":\"approved\",\"jumlah\":\"15000.00\"}', '2025-12-22 18:33:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(24, 2, NULL, 'approve', 'transaksi_pengeluaran', '3', '{\"status\":\"draft\",\"jumlah\":\"5000.00\"}', '{\"status\":\"approved\",\"jumlah\":\"5000.00\"}', '2025-12-22 18:33:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(25, 2, NULL, 'approve', 'transaksi_file', '3', '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '2025-12-22 18:33:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(26, 2, NULL, 'approve', 'transaksi_file', '2', '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '2025-12-22 18:33:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(27, 2, NULL, 'create', 'kas_shift', '3', NULL, '{\"tanggal\":\"2025-12-22\",\"kasir_user_id\":1,\"opening_cash\":330000}', '2025-12-22 18:44:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(28, 2, NULL, 'create', 'kategori_pemasukan', '4', NULL, '{\"nama_kategori\":\"Makanan\"}', '2025-12-22 18:45:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(29, 2, NULL, 'create', 'transaksi_pemasukan', '3', NULL, '{\"kat_pemasukan_id\":4,\"tanggal_masuk\":\"2025-12-22\",\"jumlah_pemasukan\":140000,\"deskripsi\":\"Penjualan 1 dus indomie goreng\"}', '2025-12-22 18:46:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(30, 2, NULL, 'create', 'transaksi_pengeluaran', '3', NULL, '{\"kat_pengeluaran_id\":1,\"tanggal_keluar\":\"2025-12-22\",\"jumlah_pengeluaran\":100000,\"deskripsi\":\"Pembelian voucher listrik toko\"}', '2025-12-22 18:46:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(31, 2, NULL, 'update', 'kas_shift', '3', '{\"status\":\"open\",\"opening_cash\":330000}', '{\"status\":\"closed\",\"expected_closing_cash\":370000,\"actual_closing_cash\":370000,\"variance\":0}', '2025-12-22 18:48:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'),
(32, 2, NULL, 'logout', 'users', '2', NULL, '{\"username\":\"owner\",\"role\":\"pemilik\"}', '2025-12-22 18:55:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36');

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

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`budget_id`, `kat_pengeluaran_id`, `bulan`, `tahun`, `jumlah_budget`, `jumlah_terpakai`, `sisa_budget`, `status`) VALUES
(1, 2, 1, 2025, 1000000.00, 0.00, 1000000.00, 'aman');

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

--
-- Dumping data for table `cash_flow`
--

INSERT INTO `cash_flow` (`kas_id`, `transaksi_masuk_id`, `transaksi_keluar_id`, `tipe`, `saldo_awal`, `saldo_akhir`, `created_at`) VALUES
(1, 1, NULL, 'pemasukan', 0.00, 295000.00, '2025-12-04 23:53:18'),
(2, NULL, 1, 'pengeluaran', 295000.00, 280000.00, '2025-12-04 23:53:21'),
(3, 2, NULL, 'pemasukan', 280000.00, 430000.00, '2025-12-04 23:55:59'),
(4, NULL, 2, 'pengeluaran', 430000.00, 330000.00, '2025-12-04 23:56:01'),
(5, 3, NULL, 'pemasukan', 330000.00, 470000.00, '2025-12-22 18:46:25'),
(6, NULL, 3, 'pengeluaran', 470000.00, 370000.00, '2025-12-22 18:46:53');

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

--
-- Dumping data for table `catatan`
--

INSERT INTO `catatan` (`catatan_id`, `tanggal`, `judul`, `isi_catatan`, `tipe`) VALUES
(1, '2025-12-18', 'Listrik Naik', 'Listrik boros', 'Operasional');

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

--
-- Dumping data for table `kas_shift`
--

INSERT INTO `kas_shift` (`shift_id`, `tanggal`, `kasir_user_id`, `opened_at`, `closed_at`, `opening_cash`, `expected_closing_cash`, `actual_closing_cash`, `variance`, `notes`, `status`) VALUES
(1, '2025-12-04', 1, '2025-12-04 23:51:52', '2025-12-04 23:53:34', 0.00, 280000.00, 280000.00, 0.00, NULL, 'closed'),
(2, '2025-12-05', 1, '2025-12-04 23:53:48', '2025-12-04 23:56:28', 280000.00, 330000.00, 330000.00, 0.00, NULL, 'closed'),
(3, '2025-12-22', 1, '2025-12-22 18:44:21', '2025-12-22 18:48:23', 330000.00, 370000.00, 370000.00, 0.00, NULL, 'closed');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_pemasukan`
--

CREATE TABLE `kategori_pemasukan` (
  `kat_pemasukan_id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori_pemasukan`
--

INSERT INTO `kategori_pemasukan` (`kat_pemasukan_id`, `nama_kategori`) VALUES
(1, 'Minuman'),
(2, 'Rokok'),
(3, 'Sembako'),
(4, 'Makanan');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_pengeluaran`
--

CREATE TABLE `kategori_pengeluaran` (
  `kat_pengeluaran_id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori_pengeluaran`
--

INSERT INTO `kategori_pengeluaran` (`kat_pengeluaran_id`, `nama_kategori`) VALUES
(1, 'Operasional'),
(2, 'Stock');

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
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaksi_file`
--

INSERT INTO `transaksi_file` (`file_id`, `tipe`, `ref_id`, `filename`, `filepath`, `mime`, `size_bytes`, `uploaded_by`, `uploaded_at`, `status`) VALUES
(1, 'pemasukan', 1, 'pemasukan_1_1764922099_693292f377835.jpg', '../uploads/bukti/pemasukan_1_1764922099_693292f377835.jpg', 'image/jpeg', 58860, 1, '2025-12-05 15:08:19', 'pending');

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

--
-- Dumping data for table `transaksi_pemasukan`
--

INSERT INTO `transaksi_pemasukan` (`transaksi_masuk_id`, `user_id`, `kat_pemasukan_id`, `tanggal_masuk`, `jumlah_pemasukan`, `deskripsi`, `status`, `shift_id`) VALUES
(1, 1, 2, '2025-12-04', 295000.00, 'Penjualan 5 bungkus rokok jarum coklat, magnum 4, bintang 1, jazy kretek 2, magnum kretek 1, magnum', 'approved', NULL),
(2, 1, 3, '2025-12-05', 150000.00, 'Penjualan beras 10kg', 'approved', NULL),
(3, 2, 4, '2025-12-22', 140000.00, 'Penjualan 1 dus indomie goreng', 'approved', NULL);

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

--
-- Dumping data for table `transaksi_pengeluaran`
--

INSERT INTO `transaksi_pengeluaran` (`transaksi_keluar_id`, `user_id`, `kat_pengeluaran_id`, `tanggal_keluar`, `jumlah_pengeluaran`, `deskripsi`, `status`, `shift_id`) VALUES
(1, 1, 2, '2025-12-04', 15000.00, 'Pembelian 2 lusin plastik belanja', 'approved', NULL),
(2, 1, 1, '2025-12-05', 100000.00, 'Pembelian voucher listrik', 'approved', NULL),
(3, 2, 1, '2025-12-22', 100000.00, 'Pembelian voucher listrik toko', 'approved', NULL);

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
(2, 'owner', '5be057accb25758101fa5eadbbd79503', 'pemilik'),
(3, 'admin1', 'e714f5e09b26f37bb36f63f24789a3b5', 'admin'),
(4, 'admin2', 'e714f5e09b26f37bb36f63f24789a3b5', 'admin');

-- --------------------------------------------------------

--
-- Structure for view `activity_log`
--
DROP TABLE IF EXISTS `activity_log`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `activity_log`  AS SELECT `al`.`log_id` AS `log_id`, `al`.`user_id` AS `user_id`, `al`.`action` AS `action`, `al`.`table_name` AS `table_name`, `al`.`record_id` AS `record_id`, `al`.`old_values` AS `old_values`, `al`.`new_values` AS `new_values`, `al`.`created_at` AS `created_at`, `al`.`ip_address` AS `ip_address`, `al`.`user_agent` AS `user_agent`, `u`.`username` AS `username` FROM (`audit_log` `al` left join `users` `u` on(`al`.`user_id` = `u`.`user_id`)) ;

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
-- Indexes for table `transaksi_file`
--
ALTER TABLE `transaksi_file`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `idx_tf_ref` (`tipe`,`ref_id`),
  ADD KEY `tf_user_fk` (`uploaded_by`),
  ADD KEY `idx_tf_status` (`status`);

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
