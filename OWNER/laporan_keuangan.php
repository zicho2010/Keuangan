<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
    header("Location: ../login.php");
    exit;
}

include '../config.php';

// === Filter ===
$filter_awal = isset($_GET['awal']) ? $_GET['awal'] : '';
$filter_akhir = isset($_GET['akhir']) ? $_GET['akhir'] : '';


function tableHasColumn(mysqli $conn, string $table, string $column): bool {
	$res = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
	return $res && mysqli_num_rows($res) > 0;
}


// Build WHERE clause untuk pemasukan
$wherePemasukan = [];
if (tableHasColumn($conn, 'transaksi_pemasukan', 'status')) {
	$wherePemasukan[] = "status='approved'";
}
if (!empty($filter_awal) && !empty($filter_akhir)) {
	$filter_awalEsc = mysqli_real_escape_string($conn, $filter_awal);
	$filter_akhirEsc = mysqli_real_escape_string($conn, $filter_akhir);
	$wherePemasukan[] = "tanggal_masuk BETWEEN '$filter_awalEsc' AND '$filter_akhirEsc'";
}
$wherePemasukanStr = !empty($wherePemasukan) ? "WHERE " . implode(" AND ", $wherePemasukan) : "";

// Build WHERE clause untuk pengeluaran
$wherePengeluaran = [];
if (tableHasColumn($conn, 'transaksi_pengeluaran', 'status')) {
	$wherePengeluaran[] = "status='approved'";
}
if (!empty($filter_awal) && !empty($filter_akhir)) {
	$filter_awalEsc = mysqli_real_escape_string($conn, $filter_awal);
	$filter_akhirEsc = mysqli_real_escape_string($conn, $filter_akhir);
	$wherePengeluaran[] = "tanggal_keluar BETWEEN '$filter_awalEsc' AND '$filter_akhirEsc'";
}
$wherePengeluaranStr = !empty($wherePengeluaran) ? "WHERE " . implode(" AND ", $wherePengeluaran) : "";

//  Total Pemasukan
$sqlPemasukan = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_pemasukan),0) AS total FROM transaksi_pemasukan $wherePemasukanStr");
$pemasukan = $sqlPemasukan ? mysqli_fetch_assoc($sqlPemasukan) : ['total'=>0];

// Total Pengeluaran
$sqlPengeluaran = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_pengeluaran),0) AS total FROM transaksi_pengeluaran $wherePengeluaranStr");
$pengeluaran = $sqlPengeluaran ? mysqli_fetch_assoc($sqlPengeluaran) : ['total'=>0];

//  Hitung Saldo 
$saldo = $pemasukan['total'] - $pengeluaran['total'];

//  Data Cash Flow 
$cashFlowWhere = "";
if (!empty($filter_awal) && !empty($filter_akhir)) {
	$filter_awalEsc = mysqli_real_escape_string($conn, $filter_awal);
	$filter_akhirEsc = mysqli_real_escape_string($conn, $filter_akhir);
	$cashFlowWhere = "WHERE DATE(COALESCE(p.tanggal_masuk, q.tanggal_keluar)) BETWEEN '$filter_awalEsc' AND '$filter_akhirEsc'";
}

$sqlCashFlow = mysqli_query($conn, "
    SELECT 
        c.kas_id,
        c.tipe,
        c.saldo_awal,
        c.saldo_akhir,
        c.created_at,
        COALESCE(p.tanggal_masuk, q.tanggal_keluar) AS tanggal_transaksi,
        COALESCE(p.jumlah_pemasukan, 0) AS jumlah_masuk,
        COALESCE(q.jumlah_pengeluaran, 0) AS jumlah_keluar,
        COALESCE(kp.nama_kategori, kk.nama_kategori) AS kategori
    FROM cash_flow c
    LEFT JOIN transaksi_pemasukan p ON c.transaksi_masuk_id = p.transaksi_masuk_id
    LEFT JOIN kategori_pemasukan kp ON p.kat_pemasukan_id = kp.kat_pemasukan_id
    LEFT JOIN transaksi_pengeluaran q ON c.transaksi_keluar_id = q.transaksi_keluar_id
    LEFT JOIN kategori_pengeluaran kk ON q.kat_pengeluaran_id = kk.kat_pengeluaran_id
    $cashFlowWhere
    ORDER BY c.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan</title>
    <link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #555 !important; }
        .sidebar .nav-link { color: #fff !important; }
        .sidebar .nav-link:hover { background: #444; }
        body { background: #f8f9fc; }
        .thead-dark th { background-color: #343a40 !important; color: white !important; }
        .badge-pemasukan { background-color: #28a745; color: #fff; }
        .badge-pengeluaran { background-color: #dc3545; color: #fff; }
    </style>
</head>
<body id="page-top">

<div id="wrapper">
    <!-- Sidebar -->
    <?php $currentPage = 'laporan_keuangan'; include 'sidebar_owner.php'; ?>

    <!-- Konten -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4 text-dark"><i class="fas fa-money-check-alt"></i> Laporan Keuangan</h3>

            <!-- Ringkasan Keuangan -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card border-left-success shadow h-100">
                        <div class="card-body">
                            <h6>Total Pemasukan</h6>
                            <strong>Rp <?= number_format($pemasukan['total'],0,',','.'); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-left-danger shadow h-100">
                        <div class="card-body">
                            <h6>Total Pengeluaran</h6>
                            <strong>Rp <?= number_format($pengeluaran['total'],0,',','.'); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-left-primary shadow h-100">
                        <div class="card-body">
                            <h6>Saldo Akhir</h6>
                            <strong>Rp <?= number_format($saldo,0,',','.'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4 mt-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-filter"></i> Filter Laporan
                </div>
                <div class="card-body">
                    <form method="get" class="row">
                        <div class="form-group col-md-3">
                            <label>Tanggal Dari</label>
                            <input type="date" name="awal" class="form-control" value="<?= htmlspecialchars($filter_awal); ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Tanggal Sampai</label>
                            <input type="date" name="akhir" class="form-control" value="<?= htmlspecialchars($filter_akhir); ?>">
                        </div>
                        <div class="form-group col-md-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                            <a href="laporan_keuangan.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Cash Flow -->
            <div class="card shadow mt-4">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-exchange-alt"></i> Detail Cash Flow
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-dark text-center">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Transaksi</th>
                                    <th>Tipe</th>
                                    <th>Kategori</th>
                                    <th>Saldo Awal (Rp)</th>
                                    <th>Pemasukan (Rp)</th>
                                    <th>Pengeluaran (Rp)</th>
                                    <th>Saldo Akhir (Rp)</th>
                                    <th>Dibuat Pada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($sqlCashFlow) > 0): ?>
                                    <?php $no = 1; while ($row = mysqli_fetch_assoc($sqlCashFlow)): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($row['tanggal_transaksi']); ?></td>
                                            <td class="text-center">
                                                <?php if ($row['tipe'] == 'pemasukan'): ?>
                                                    <span class="badge badge-pemasukan">Pemasukan</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pengeluaran">Pengeluaran</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['kategori'] ?? '-'); ?></td>
                                            <td class="text-right"><?= number_format($row['saldo_awal'],0,',','.'); ?></td>
                                            <td class="text-right"><?= number_format($row['jumlah_masuk'],0,',','.'); ?></td>
                                            <td class="text-right"><?= number_format($row['jumlah_keluar'],0,',','.'); ?></td>
                                            <td class="text-right"><?= number_format($row['saldo_akhir'],0,',','.'); ?></td>
                                            <td><?= htmlspecialchars($row['created_at']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center text-muted">Belum ada data cash flow.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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