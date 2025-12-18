<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
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
$filterApprovedIn = $hasStatus ? "WHERE status='approved'" : "";
$filterApprovedOut = $hasStatus ? "WHERE status='approved'" : "";

// === Total Pemasukan (hanya approved jika kolom status ada) ===
$sqlPemasukan = mysqli_query($conn, "
	SELECT 
		COUNT(*) AS jumlah,
		COALESCE(SUM(jumlah_pemasukan),0) AS total 
	FROM transaksi_pemasukan
	$filterApprovedIn
");
$pemasukan = $sqlPemasukan ? mysqli_fetch_assoc($sqlPemasukan) : ['jumlah' => 0, 'total' => 0];

// === Total Pengeluaran (hanya approved jika kolom status ada) ===
$sqlPengeluaran = mysqli_query($conn, "
	SELECT 
		COUNT(*) AS jumlah,
		COALESCE(SUM(jumlah_pengeluaran),0) AS total 
	FROM transaksi_pengeluaran
	$filterApprovedOut
");
$pengeluaran = $sqlPengeluaran ? mysqli_fetch_assoc($sqlPengeluaran) : ['jumlah' => 0, 'total' => 0];

// === Ambil total budget dari tabel budgets ===
$sqlBudget = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(jumlah_budget),0) AS total_budget,
        COALESCE(SUM(jumlah_terpakai),0) AS total_terpakai,
        COALESCE(SUM(sisa_budget),0) AS total_sisa
    FROM budgets
");
$budget = mysqli_fetch_assoc($sqlBudget);

// === Ambil Saldo Akhir dari cash_flow ===
// Saldo akhir adalah saldo kumulatif terakhir dari cash_flow
$sqlCashFlow = mysqli_query($conn, "
	SELECT saldo_akhir 
	FROM cash_flow 
	ORDER BY created_at DESC, kas_id DESC 
	LIMIT 1
");
$saldoAkhir = 0.0;
if ($sqlCashFlow && mysqli_num_rows($sqlCashFlow) > 0) {
	$saldoAkhir = floatval(mysqli_fetch_assoc($sqlCashFlow)['saldo_akhir']);
} else {
	// Fallback: hitung dari selisih pemasukan - pengeluaran jika belum ada data cash_flow
	$saldoAkhir = $pemasukan['total'] - $pengeluaran['total'];
}
$saldo = $saldoAkhir;

// === Persentase penggunaan budget ===
$persenBudget = ($budget['total_budget'] > 0) 
    ? number_format(($budget['total_terpakai'] / $budget['total_budget']) * 100, 2) . '%'
    : 'Belum diset';

// === Transaksi Draft (menunggu approval) ===
$sqlDraft = $hasStatus ? mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM transaksi_pemasukan WHERE status='draft') +
        (SELECT COUNT(*) FROM transaksi_pengeluaran WHERE status='draft') AS total_draft
") : null;
$draft = $sqlDraft ? mysqli_fetch_assoc($sqlDraft) : ['total_draft' => 0];

// === Bukti Pending Review ===
$sqlBuktiPending = mysqli_query($conn, "
    SELECT COUNT(*) AS total_pending
    FROM transaksi_file
    WHERE status = 'pending'
");
$buktiPending = mysqli_fetch_assoc($sqlBuktiPending);

// === Statistik Bulan Ini ===
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

// === Statistik Bulan Lalu ===
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

// === Statistik Hari Ini ===
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

// === Transaksi Terbaru (5 transaksi) ===
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

// === Top 5 Kategori Pemasukan ===
$topKategoriMasuk = mysqli_query($conn, "
    SELECT 
        kp.nama_kategori,
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(tp.jumlah_pemasukan), 0) AS total
    FROM kategori_pemasukan kp
    LEFT JOIN transaksi_pemasukan tp ON kp.kat_pemasukan_id = tp.kat_pemasukan_id
    " . ($hasStatus ? "AND tp.status='approved'" : "") . "
    GROUP BY kp.kat_pemasukan_id, kp.nama_kategori
    ORDER BY total DESC
    LIMIT 5
");

// === Top 5 Kategori Pengeluaran ===
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

// === Transaksi Draft Detail ===
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

// === Catatan Operasional Terbaru ===
$catatan = mysqli_query($conn, "SELECT * FROM catatan ORDER BY catatan_id DESC LIMIT 5");

// === Ringkasan Harian & Bulanan ===
// Tabel daily_summary dan monthly_summary sudah tidak digunakan
$daily = null;
$monthly = null;

// === Activity Log Terbaru (5 log) ===
$activityLog = mysqli_query($conn, "
    SELECT al.*, u.username 
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.log_id DESC, al.created_at DESC 
    LIMIT 5
");

// === Shift/Kas Status Terbaru ===
$shiftTerbaru = mysqli_query($conn, "
    SELECT * FROM kas_shift 
    ORDER BY shift_id DESC 
    LIMIT 1
");

// Hitung persentase perubahan bulan ini vs bulan lalu
$selisihPemasukan = $bulanLaluData['pemasukan_bulan_lalu'] > 0 
    ? (($bulanIniData['pemasukan_bulan_ini'] - $bulanLaluData['pemasukan_bulan_lalu']) / $bulanLaluData['pemasukan_bulan_lalu']) * 100 
    : 0;
$selisihPengeluaran = $bulanLaluData['pengeluaran_bulan_lalu'] > 0 
    ? (($bulanIniData['pengeluaran_bulan_ini'] - $bulanLaluData['pengeluaran_bulan_lalu']) / $bulanLaluData['pengeluaran_bulan_lalu']) * 100 
    : 0;

// Progress bar untuk budget
$progressBudget = ($budget['total_budget'] > 0) 
    ? min(100, ($budget['total_terpakai'] / $budget['total_budget']) * 100) 
    : 0;
$progressColor = $progressBudget > 80 ? 'danger' : ($progressBudget > 60 ? 'warning' : 'success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pemilik</title>
    <link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #555 !important; }
        .sidebar .nav-link { color: #fff !important; }
        .sidebar .nav-link:hover { background: #444; }
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
    <?php $currentPage = 'dashboard'; include 'sidebar_owner.php'; ?>

    <!-- Konten -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4"><i class="fas fa-chart-line"></i> Dashboard Pemilik</h3>

            <!-- Ringkasan Keuangan -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card border-left-success shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Pemasukan</h6>
                                    <h4 class="mb-0">Rp <?= number_format($pemasukan['total'],0,',','.'); ?></h4>
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
                                    <h4 class="mb-0">Rp <?= number_format($pengeluaran['total'],0,',','.'); ?></h4>
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
                                    <h4 class="mb-0">Rp <?= number_format($saldo,0,',','.'); ?></h4>
                                    <small class="text-muted">Total kas tersedia</small>
                                </div>
                                <div class="text-primary">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card border-left-warning shadow h-100 stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Status Budget</h6>
                                    <h4 class="mb-0"><?= $persenBudget; ?></h4>
                                    <small class="text-muted">Sisa: Rp <?= number_format($budget['total_sisa'],0,',','.'); ?></small>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-chart-pie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Budget -->
            <?php if ($budget['total_budget'] > 0): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-chart-bar"></i> Progress Penggunaan Budget
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span><strong>Terpakai:</strong> Rp <?= number_format($budget['total_terpakai'],0,',','.'); ?> / Rp <?= number_format($budget['total_budget'],0,',','.'); ?></span>
                                <span><strong><?= number_format($progressBudget, 1); ?>%</strong></span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-<?= $progressColor; ?>" role="progressbar" 
                                     style="width: <?= $progressBudget; ?>%" 
                                     aria-valuenow="<?= $progressBudget; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?= number_format($progressBudget, 1); ?>%
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                Sisa Budget: <strong>Rp <?= number_format($budget['total_sisa'],0,',','.'); ?></strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
                                <div class="col-md-4">
                                    <p class="mb-2">
                                        <span class="text-success"><i class="fas fa-arrow-up"></i> Pemasukan:</span><br>
                                        <strong>Rp <?= number_format($hariIniData['pemasukan_hari_ini'], 0, ',', '.'); ?></strong>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-2">
                                        <span class="text-danger"><i class="fas fa-arrow-down"></i> Pengeluaran:</span><br>
                                        <strong>Rp <?= number_format($hariIniData['pengeluaran_hari_ini'], 0, ',', '.'); ?></strong>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-0">
                                        <span class="text-primary"><i class="fas fa-wallet"></i> Saldo Hari Ini:</span><br>
                                        <strong>Rp <?= number_format($hariIniData['pemasukan_hari_ini'] - $hariIniData['pengeluaran_hari_ini'], 0, ',', '.'); ?></strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaksi Terbaru & Top Kategori -->
            <div class="row mt-4">
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

            <!-- Transaksi Draft & Bukti Pending -->
            <?php if (($hasStatus && $draft['total_draft'] > 0) || $buktiPending['total_pending'] > 0): ?>
            <div class="row mt-4">
                <?php if ($hasStatus && $draft['total_draft'] > 0): ?>
                <div class="col-md-6 mb-3">
                    <div class="card shadow border-warning">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-exclamation-triangle"></i> Transaksi Draft (<?= $draft['total_draft']; ?>)
                        </div>
                        <div class="card-body">
                            <?php if ($draftDetail && mysqli_num_rows($draftDetail) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tanggal</th>
                                                <th>Tipe</th>
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
                                                    <td class="text-right">
                                                        <strong>Rp <?= number_format($d['jumlah'], 0, ',', '.'); ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-2">
                                    <a href="approval_transaksi.php" class="btn btn-warning btn-sm">
                                        <i class="fas fa-check-circle"></i> Lihat Semua Draft
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($buktiPending['total_pending'] > 0): ?>
                <div class="col-md-6 mb-3">
                    <div class="card shadow border-info">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-file-upload"></i> Bukti Pending Review (<?= $buktiPending['total_pending']; ?>)
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Ada <strong><?= $buktiPending['total_pending']; ?></strong> bukti transaksi yang menunggu review dan approval.
                            </div>
                            <div class="text-center">
                                <a href="upload_bukti.php?status=pending" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Review Bukti
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Catatan Terbaru & Activity Log -->
            <div class="row mt-4">
                <div class="col-md-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-secondary text-white">
                            <i class="fas fa-sticky-note"></i> Catatan Operasional Terbaru
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php if (mysqli_num_rows($catatan) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($catatan)) : ?>
                                        <li class="list-group-item">
                                            <strong><?= htmlspecialchars($row['tanggal']); ?></strong> 
                                            <span class="badge badge-info ml-2"><?= htmlspecialchars($row['tipe']); ?></span><br>
                                            <strong><?= htmlspecialchars($row['judul']); ?></strong><br>
                                            <small><?= nl2br(htmlspecialchars(substr($row['isi_catatan'], 0, 100))); ?><?= strlen($row['isi_catatan']) > 100 ? '...' : ''; ?></small>
                                        </li>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-muted text-center">Belum ada catatan operasional.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <i class="fas fa-history"></i> Activity Log Terbaru
                        </div>
                        <div class="card-body">
                            <?php if ($activityLog && mysqli_num_rows($activityLog) > 0): ?>
                                <ul class="list-group">
                                    <?php while ($log = mysqli_fetch_assoc($activityLog)): ?>
                                        <li class="list-group-item">
                                            <small>
                                                <strong><?= htmlspecialchars($log['action']); ?></strong> 
                                                pada <strong><?= htmlspecialchars($log['table_name']); ?></strong><br>
                                                <span class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($log['created_at'])); ?> 
                                                    oleh <?= htmlspecialchars($log['username'] ?? 'System'); ?>
                                                </span>
                                            </small>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                                <div class="text-center mt-2">
                                    <a href="activity_log.php" class="btn btn-dark btn-sm">
                                        <i class="fas fa-list"></i> Lihat Semua Log
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">Belum ada activity log.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

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
                                    <a href="approval_transaksi.php" class="btn btn-warning btn-block">
                                        <i class="fas fa-check-circle"></i> Approval Transaksi
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="upload_bukti.php" class="btn btn-info btn-block">
                                        <i class="fas fa-file-upload"></i> Review Bukti
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="penutupan_kas.php" class="btn btn-success btn-block">
                                        <i class="fas fa-cash-register"></i> Penutupan Kas
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="laporan_keuangan.php" class="btn btn-secondary btn-block">
                                        <i class="fas fa-chart-bar"></i> Laporan
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
