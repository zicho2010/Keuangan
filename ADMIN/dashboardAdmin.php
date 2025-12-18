<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

include '../config.php';

// Helper: cek apakah kolom ada (untuk kompatibilitas migrasi)
function tableHasColumn(mysqli $conn, string $table, string $column): bool {
	$res = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
	return $res && mysqli_num_rows($res) > 0;
}

$hasStatus = tableHasColumn($conn, 'transaksi_pemasukan', 'status');
$filterApproved = $hasStatus ? "WHERE status='approved'" : "";

// === 1. Total Pemasukan (hanya approved) ===
$sqlPemasukan = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS jumlah,
        COALESCE(SUM(jumlah_pemasukan), 0) AS total
    FROM transaksi_pemasukan
    $filterApproved
");
$pemasukan = $sqlPemasukan ? mysqli_fetch_assoc($sqlPemasukan) : ['jumlah' => 0, 'total' => 0];

// === 2. Total Pengeluaran (hanya approved) ===
$filterApprovedOut = $hasStatus ? "WHERE status='approved'" : "";
$sqlPengeluaran = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS jumlah,
        COALESCE(SUM(jumlah_pengeluaran), 0) AS total
    FROM transaksi_pengeluaran
    $filterApprovedOut
");
$pengeluaran = $sqlPengeluaran ? mysqli_fetch_assoc($sqlPengeluaran) : ['jumlah' => 0, 'total' => 0];

// === 3. Transaksi Draft (menunggu approval) ===
$sqlDraft = $hasStatus ? mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM transaksi_pemasukan WHERE status='draft') +
        (SELECT COUNT(*) FROM transaksi_pengeluaran WHERE status='draft') AS total_draft
") : null;
$draft = $sqlDraft ? mysqli_fetch_assoc($sqlDraft) : ['total_draft' => 0];

// === 4. Saldo akhir ===
$saldo_akhir = $pemasukan['total'] - $pengeluaran['total'];

// === 5. Statistik Bulan Ini ===
$bulanIni = date('Y-m');
$sqlBulanIni = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN amount ELSE 0 END), 0) AS pemasukan_bulan_ini,
        COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN amount ELSE 0 END), 0) AS pengeluaran_bulan_ini
    FROM (
        SELECT 'pemasukan' AS tipe, jumlah_pemasukan AS amount, tanggal_masuk AS tanggal
        FROM transaksi_pemasukan
        " . ($hasStatus ? "WHERE status='approved' AND" : "WHERE") . " DATE_FORMAT(tanggal_masuk, '%Y-%m') = '$bulanIni'
        UNION ALL
        SELECT 'pengeluaran' AS tipe, jumlah_pengeluaran AS amount, tanggal_keluar AS tanggal
        FROM transaksi_pengeluaran
        " . ($hasStatus ? "WHERE status='approved' AND" : "WHERE") . " DATE_FORMAT(tanggal_keluar, '%Y-%m') = '$bulanIni'
    ) AS transaksi
");
$bulanIniData = mysqli_fetch_assoc($sqlBulanIni);

// === 6. Statistik Bulan Lalu ===
$bulanLalu = date('Y-m', strtotime('-1 month'));
$sqlBulanLalu = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN amount ELSE 0 END), 0) AS pemasukan_bulan_lalu,
        COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN amount ELSE 0 END), 0) AS pengeluaran_bulan_lalu
    FROM (
        SELECT 'pemasukan' AS tipe, jumlah_pemasukan AS amount, tanggal_masuk AS tanggal
        FROM transaksi_pemasukan
        " . ($hasStatus ? "WHERE status='approved' AND" : "WHERE") . " DATE_FORMAT(tanggal_masuk, '%Y-%m') = '$bulanLalu'
        UNION ALL
        SELECT 'pengeluaran' AS tipe, jumlah_pengeluaran AS amount, tanggal_keluar AS tanggal
        FROM transaksi_pengeluaran
        " . ($hasStatus ? "WHERE status='approved' AND" : "WHERE") . " DATE_FORMAT(tanggal_keluar, '%Y-%m') = '$bulanLalu'
    ) AS transaksi
");
$bulanLaluData = mysqli_fetch_assoc($sqlBulanLalu);

// === 7. Transaksi Terbaru (5 transaksi) ===
$transaksiTerbaru = mysqli_query($conn, "
    (SELECT 
        transaksi_masuk_id AS id,
        tanggal_masuk AS tanggal,
        'Pemasukan' AS tipe,
        jumlah_pemasukan AS jumlah,
        deskripsi,
        kp.nama_kategori
     FROM transaksi_pemasukan tp
     JOIN kategori_pemasukan kp ON tp.kat_pemasukan_id = kp.kat_pemasukan_id
     " . ($hasStatus ? "WHERE tp.status='approved'" : "") . "
     ORDER BY tp.transaksi_masuk_id DESC LIMIT 5)
    UNION ALL
    (SELECT 
        transaksi_keluar_id AS id,
        tanggal_keluar AS tanggal,
        'Pengeluaran' AS tipe,
        jumlah_pengeluaran AS jumlah,
        deskripsi,
        kp2.nama_kategori
     FROM transaksi_pengeluaran tk
     JOIN kategori_pengeluaran kp2 ON tk.kat_pengeluaran_id = kp2.kat_pengeluaran_id
     " . ($hasStatus ? "WHERE tk.status='approved'" : "") . "
     ORDER BY tk.transaksi_keluar_id DESC LIMIT 5)
    ORDER BY id DESC LIMIT 5
");

// === 8. Top 5 Kategori Pengeluaran ===
$topKategoriKeluar = mysqli_query($conn, "
    SELECT 
        kp.nama_kategori,
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(tk.jumlah_pengeluaran), 0) AS total
    FROM kategori_pengeluaran kp
    LEFT JOIN transaksi_pengeluaran tk ON kp.kat_pengeluaran_id = tk.kat_pengeluaran_id
    " . ($hasStatus ? "AND tk.status='approved'" : "") . "
    GROUP BY kp.kat_pengeluaran_id, kp.nama_kategori
    ORDER BY total DESC
    LIMIT 5
");

// === 9. Transaksi Draft Detail ===
$draftDetail = $hasStatus ? mysqli_query($conn, "
    (SELECT 
        transaksi_masuk_id AS id,
        tanggal_masuk AS tanggal,
        'Pemasukan' AS tipe,
        jumlah_pemasukan AS jumlah,
        deskripsi
     FROM transaksi_pemasukan
     WHERE status='draft'
     ORDER BY transaksi_masuk_id DESC LIMIT 5)
    UNION ALL
    (SELECT 
        transaksi_keluar_id AS id,
        tanggal_keluar AS tanggal,
        'Pengeluaran' AS tipe,
        jumlah_pengeluaran AS jumlah,
        deskripsi
     FROM transaksi_pengeluaran
     WHERE status='draft'
     ORDER BY transaksi_keluar_id DESC LIMIT 5)
    ORDER BY id DESC LIMIT 5
") : null;

// === 10. Statistik Hari Ini ===
$hariIni = date('Y-m-d');
$sqlHariIni = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN amount ELSE 0 END), 0) AS pemasukan_hari_ini,
        COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN amount ELSE 0 END), 0) AS pengeluaran_hari_ini
    FROM (
        SELECT 'pemasukan' AS tipe, jumlah_pemasukan AS amount, tanggal_masuk AS tanggal
        FROM transaksi_pemasukan
        " . ($hasStatus ? "WHERE status='approved' AND" : "WHERE") . " tanggal_masuk = '$hariIni'
        UNION ALL
        SELECT 'pengeluaran' AS tipe, jumlah_pengeluaran AS amount, tanggal_keluar AS tanggal
        FROM transaksi_pengeluaran
        " . ($hasStatus ? "WHERE status='approved' AND" : "WHERE") . " tanggal_keluar = '$hariIni'
    ) AS transaksi
");
$hariIniData = mysqli_fetch_assoc($sqlHariIni);

// === 11. Jumlah Bukti yang Diupload ===
$sqlBukti = mysqli_query($conn, "
    SELECT COUNT(*) AS total_bukti
    FROM transaksi_file
");
$totalBukti = mysqli_fetch_assoc($sqlBukti);

// Hitung persentase perubahan bulan ini vs bulan lalu
$selisihPemasukan = $bulanLaluData['pemasukan_bulan_lalu'] > 0 
    ? (($bulanIniData['pemasukan_bulan_ini'] - $bulanLaluData['pemasukan_bulan_lalu']) / $bulanLaluData['pemasukan_bulan_lalu']) * 100 
    : 0;
$selisihPengeluaran = $bulanLaluData['pengeluaran_bulan_lalu'] > 0 
    ? (($bulanIniData['pengeluaran_bulan_ini'] - $bulanLaluData['pengeluaran_bulan_lalu']) / $bulanLaluData['pengeluaran_bulan_lalu']) * 100 
    : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>

    <!-- SB Admin 2 -->
    <link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .sidebar { background-color: #555 !important; }
        .sidebar .nav-link { color: #fff !important; }
        .sidebar .nav-link:hover { background-color: #444; }
        .card h6 { font-weight: 600; }
        .border-left-success { border-left: 4px solid #1cc88a !important; }
        .border-left-danger { border-left: 4px solid #e74a3b !important; }
        .border-left-primary { border-left: 4px solid #4e73df !important; }
        .border-left-warning { border-left: 4px solid #f6c23e !important; }
        .border-left-info { border-left: 4px solid #36b9cc !important; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .badge-trend-up { background-color: #1cc88a; }
        .badge-trend-down { background-color: #e74a3b; }
        .table-sm th, .table-sm td { font-size: 0.875rem; }
    </style>
</head>
<body id="page-top">

<div id="wrapper">
    <!-- Sidebar -->
    <?php $currentPage = 'dashboard'; include 'sidebar_admin.php'; ?>

    <!-- Content -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4"><i class="fas fa-tachometer-alt"></i> Dashboard Administrator</h3>

            <!-- Ringkasan Utama -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card border-left-success shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Pemasukan</h6>
                                    <h4 class="mb-0">Rp <?= number_format($pemasukan['total'], 0, ',', '.'); ?></h4>
                                    <small class="text-muted"><?= $pemasukan['jumlah']; ?> transaksi</small>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-arrow-up fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card border-left-danger shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Pengeluaran</h6>
                                    <h4 class="mb-0">Rp <?= number_format($pengeluaran['total'], 0, ',', '.'); ?></h4>
                                    <small class="text-muted"><?= $pengeluaran['jumlah']; ?> transaksi</small>
                                </div>
                                <div class="text-danger">
                                    <i class="fas fa-arrow-down fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card border-left-primary shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Saldo Akhir</h6>
                                    <h4 class="mb-0">Rp <?= number_format($saldo_akhir, 0, ',', '.'); ?></h4>
                                    <small class="text-muted">Total kas tersedia</small>
                                </div>
                                <div class="text-primary">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($hasStatus && $draft['total_draft'] > 0): ?>
                <div class="col-md-3 mb-3">
                    <div class="card border-left-warning shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Menunggu Approval</h6>
                                    <h4 class="mb-0"><?= $draft['total_draft']; ?></h4>
                                    <small class="text-muted">Transaksi draft</small>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-md-3 mb-3">
                    <div class="card border-left-info shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Bukti Diupload</h6>
                                    <h4 class="mb-0"><?= $totalBukti['total_bukti']; ?></h4>
                                    <small class="text-muted">Total file bukti</small>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-file-upload fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Statistik Bulan Ini vs Bulan Lalu -->
            <div class="row mt-4">
                <div class="col-md-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-chart-line"></i> Pemasukan Bulan Ini
                        </div>
                        <div class="card-body">
                            <h3 class="mb-2">Rp <?= number_format($bulanIniData['pemasukan_bulan_ini'], 0, ',', '.'); ?></h3>
                            <?php if ($bulanLaluData['pemasukan_bulan_lalu'] > 0): ?>
                                <p class="mb-0">
                                    <span class="badge <?= $selisihPemasukan >= 0 ? 'badge-trend-up' : 'badge-trend-down'; ?>">
                                        <?= $selisihPemasukan >= 0 ? '+' : ''; ?><?= number_format($selisihPemasukan, 1); ?>%
                                    </span>
                                    <small class="text-muted ml-2">vs bulan lalu (Rp <?= number_format($bulanLaluData['pemasukan_bulan_lalu'], 0, ',', '.'); ?>)</small>
                                </p>
                            <?php else: ?>
                                <small class="text-muted">Tidak ada data bulan lalu</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-danger text-white">
                            <i class="fas fa-chart-line"></i> Pengeluaran Bulan Ini
                        </div>
                        <div class="card-body">
                            <h3 class="mb-2">Rp <?= number_format($bulanIniData['pengeluaran_bulan_ini'], 0, ',', '.'); ?></h3>
                            <?php if ($bulanLaluData['pengeluaran_bulan_lalu'] > 0): ?>
                                <p class="mb-0">
                                    <span class="badge <?= $selisihPengeluaran >= 0 ? 'badge-trend-down' : 'badge-trend-up'; ?>">
                                        <?= $selisihPengeluaran >= 0 ? '+' : ''; ?><?= number_format($selisihPengeluaran, 1); ?>%
                                    </span>
                                    <small class="text-muted ml-2">vs bulan lalu (Rp <?= number_format($bulanLaluData['pengeluaran_bulan_lalu'], 0, ',', '.'); ?>)</small>
                                </p>
                            <?php else: ?>
                                <small class="text-muted">Tidak ada data bulan lalu</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Hari Ini -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-calendar-day"></i> Statistik Hari Ini (<?= date('d/m/Y'); ?>)
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <h5 class="text-success">Rp <?= number_format($hariIniData['pemasukan_hari_ini'], 0, ',', '.'); ?></h5>
                                    <small class="text-muted">Pemasukan Hari Ini</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h5 class="text-danger">Rp <?= number_format($hariIniData['pengeluaran_hari_ini'], 0, ',', '.'); ?></h5>
                                    <small class="text-muted">Pengeluaran Hari Ini</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h5 class="text-primary">Rp <?= number_format($hariIniData['pemasukan_hari_ini'] - $hariIniData['pengeluaran_hari_ini'], 0, ',', '.'); ?></h5>
                                    <small class="text-muted">Saldo Hari Ini</small>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

            <div class="row mt-4">
                <!-- Transaksi Terbaru -->
                <div class="col-md-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-secondary text-white">
                            <i class="fas fa-history"></i> Transaksi Terbaru
                        </div>
                        <div class="card-body">
                            <?php if ($transaksiTerbaru && mysqli_num_rows($transaksiTerbaru) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Tipe</th>
                                                <th>Kategori</th>
                                                <th class="text-right">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($t = mysqli_fetch_assoc($transaksiTerbaru)): ?>
                                                <tr>
                                                    <td><small><?= date('d/m/Y', strtotime($t['tanggal'])); ?></small></td>
                                                    <td>
                                                        <span class="badge badge-<?= $t['tipe'] == 'Pemasukan' ? 'success' : 'danger'; ?> badge-sm">
                                                            <?= $t['tipe']; ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?= htmlspecialchars($t['nama_kategori']); ?></small></td>
                                                    <td class="text-right">
                                                        <strong>Rp <?= number_format($t['jumlah'], 0, ',', '.'); ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">Belum ada transaksi.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top 5 Kategori Pengeluaran -->
                <div class="col-md-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-tags"></i> Top 5 Kategori Pengeluaran
                        </div>
                        <div class="card-body">
                            <?php if ($topKategoriKeluar && mysqli_num_rows($topKategoriKeluar) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kategori</th>
                                                <th class="text-center">Jumlah</th>
                                                <th class="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($kat = mysqli_fetch_assoc($topKategoriKeluar)): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($kat['nama_kategori']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge badge-secondary"><?= $kat['jumlah_transaksi']; ?></span>
                                                    </td>
                                                    <td class="text-right">
                                                        <strong>Rp <?= number_format($kat['total'], 0, ',', '.'); ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">Belum ada data kategori.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaksi Draft (jika ada) -->
            <?php if ($hasStatus && $draftDetail && mysqli_num_rows($draftDetail) > 0): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card shadow border-warning">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-exclamation-triangle"></i> Transaksi Draft Menunggu Approval
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Perhatian:</strong> Ada <?= $draft['total_draft']; ?> transaksi draft yang menunggu persetujuan pemilik.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tanggal</th>
                                            <th>Tipe</th>
                                            <th>Deskripsi</th>
                                            <th class="text-right">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        mysqli_data_seek($draftDetail, 0);
                                        while ($d = mysqli_fetch_assoc($draftDetail)): ?>
                                            <tr>
                                                <td>#<?= $d['id']; ?></td>
                                                <td><small><?= date('d/m/Y', strtotime($d['tanggal'])); ?></small></td>
                                                <td>
                                                    <span class="badge badge-<?= $d['tipe'] == 'Pemasukan' ? 'success' : 'danger'; ?>">
                                                        <?= $d['tipe']; ?>
                                                    </span>
                                                </td>
                                                <td><small><?= htmlspecialchars($d['deskripsi'] ?? '-'); ?></small></td>
                                                <td class="text-right">
                                                    <strong>Rp <?= number_format($d['jumlah'], 0, ',', '.'); ?></strong>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="../OWNER/approval_transaksi.php" class="btn btn-warning">
                                    <i class="fas fa-check-circle"></i> Lihat Semua Draft
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Links -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-link"></i> Quick Links
                </div>
                <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <a href="transaksi_pemasukan.php" class="btn btn-success btn-block">
                                        <i class="fas fa-plus-circle"></i> Tambah Pemasukan
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="transaksi_pengeluaran.php" class="btn btn-danger btn-block">
                                        <i class="fas fa-minus-circle"></i> Tambah Pengeluaran
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="catatan.php" class="btn btn-info btn-block">
                                        <i class="fas fa-sticky-note"></i> Lihat Catatan
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="../OWNER/upload_bukti.php" class="btn btn-warning btn-block">
                                        <i class="fas fa-file-upload"></i> Review Bukti
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script -->
<script src="../startbootstrap-sb-admin-2-master/vendor/jquery/jquery.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/js/sb-admin-2.min.js"></script>
</body>
</html>
