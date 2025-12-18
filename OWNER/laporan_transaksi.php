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
$filter_kategori_masuk = isset($_GET['kat_masuk']) ? intval($_GET['kat_masuk']) : 0;
$filter_kategori_keluar = isset($_GET['kat_keluar']) ? intval($_GET['kat_keluar']) : 0;

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
	$res = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
	return $res && mysqli_num_rows($res) > 0;
}

$filterApprovedIn = tableHasColumn($conn, 'transaksi_pemasukan', 'status') ? "t.status='approved' AND " : "";
$filterApprovedOut = tableHasColumn($conn, 'transaksi_pengeluaran', 'status') ? "t.status='approved' AND " : "";

// Build WHERE clause untuk pemasukan
$wherePemasukan = [];
if ($filterApprovedIn !== "") $wherePemasukan[] = "t.status='approved'";
if (!empty($filter_awal) && !empty($filter_akhir)) {
	$wherePemasukan[] = "t.tanggal_masuk BETWEEN '$filter_awal' AND '$filter_akhir'";
}
if ($filter_kategori_masuk > 0) {
	$wherePemasukan[] = "t.kat_pemasukan_id = $filter_kategori_masuk";
}
$wherePemasukan = !empty($wherePemasukan) ? "WHERE " . implode(" AND ", $wherePemasukan) : "";

// Build WHERE clause untuk pengeluaran
$wherePengeluaran = [];
if ($filterApprovedOut !== "") $wherePengeluaran[] = "t.status='approved'";
if (!empty($filter_awal) && !empty($filter_akhir)) {
	$wherePengeluaran[] = "t.tanggal_keluar BETWEEN '$filter_awal' AND '$filter_akhir'";
}
if ($filter_kategori_keluar > 0) {
	$wherePengeluaran[] = "t.kat_pengeluaran_id = $filter_kategori_keluar";
}
$wherePengeluaran = !empty($wherePengeluaran) ? "WHERE " . implode(" AND ", $wherePengeluaran) : "";

// Get kategori untuk dropdown
$katMasuk = mysqli_query($conn, "SELECT * FROM kategori_pemasukan ORDER BY kat_pemasukan_id ASC");
$katKeluar = mysqli_query($conn, "SELECT * FROM kategori_pengeluaran ORDER BY kat_pengeluaran_id ASC");

// === Query gabungan transaksi ===
$query = "
(SELECT 
    t.tanggal_masuk AS tanggal,
    'Pemasukan' AS tipe,
    k.nama_kategori AS kategori,
    t.deskripsi,
    t.jumlah_pemasukan AS masuk,
    0 AS keluar,
    u.username AS user_input
 FROM transaksi_pemasukan t
 JOIN kategori_pemasukan k ON t.kat_pemasukan_id = k.kat_pemasukan_id
 JOIN users u ON t.user_id = u.user_id
 $wherePemasukan)

UNION ALL

(SELECT 
    t.tanggal_keluar AS tanggal,
    'Pengeluaran' AS tipe,
    k.nama_kategori AS kategori,
    t.deskripsi,
    0 AS masuk,
    t.jumlah_pengeluaran AS keluar,
    u.username AS user_input
 FROM transaksi_pengeluaran t
 JOIN kategori_pengeluaran k ON t.kat_pengeluaran_id = k.kat_pengeluaran_id
 JOIN users u ON t.user_id = u.user_id
 $wherePengeluaran)

ORDER BY tanggal DESC
";

$result = mysqli_query($conn, $query);
$totalMasuk = 0;
$totalKeluar = 0;
$data = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($r = mysqli_fetch_assoc($result)) {
        $totalMasuk += $r['masuk'];
        $totalKeluar += $r['keluar'];
        $data[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi</title>
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
    <?php $currentPage = 'laporan_transaksi'; include 'sidebar_owner.php'; ?>

    <!-- Konten -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4 text-dark"><i class="fas fa-file-alt"></i> Laporan Transaksi</h3>

            <!-- Filter -->
            <div class="card mb-4">
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
                        <div class="form-group col-md-3">
                            <label>Kategori Pemasukan</label>
                            <select name="kat_masuk" class="form-control">
                                <option value="0">-- Semua Kategori --</option>
                                <?php 
                                mysqli_data_seek($katMasuk, 0);
                                while ($km = mysqli_fetch_assoc($katMasuk)): ?>
                                    <option value="<?= $km['kat_pemasukan_id']; ?>" <?= $filter_kategori_masuk == $km['kat_pemasukan_id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($km['nama_kategori']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Kategori Pengeluaran</label>
                            <select name="kat_keluar" class="form-control">
                                <option value="0">-- Semua Kategori --</option>
                                <?php 
                                mysqli_data_seek($katKeluar, 0);
                                while ($kk = mysqli_fetch_assoc($katKeluar)): ?>
                                    <option value="<?= $kk['kat_pengeluaran_id']; ?>" <?= $filter_kategori_keluar == $kk['kat_pengeluaran_id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($kk['nama_kategori']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                            <a href="laporan_transaksi.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Reset</a>
                            <button type="button" class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                        </div>
            </form>
                </div>
            </div>

            <!-- Tabel -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-dark text-center">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Tipe</th>
                                    <th>Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Pemasukan (Rp)</th>
                                    <th>Pengeluaran (Rp)</th>
                                    <th>User Input</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($data)): ?>
                                    <?php $no = 1; foreach ($data as $row): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                            <td class="text-center">
                                                <?php if ($row['tipe'] == 'Pemasukan'): ?>
                                                    <span class="badge badge-pemasukan">Pemasukan</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pengeluaran">Pengeluaran</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['kategori']); ?></td>
                                            <td><?= htmlspecialchars($row['deskripsi']); ?></td>
                                            <td class="text-right"><?= number_format($row['masuk'], 0, ',', '.'); ?></td>
                                            <td class="text-right"><?= number_format($row['keluar'], 0, ',', '.'); ?></td>
                                            <td class="text-center"><?= htmlspecialchars($row['user_input']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center text-muted">Tidak ada transaksi ditemukan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="font-weight-bold text-right bg-light">
                                <tr>
                                    <td colspan="5">Total</td>
                                    <td><?= number_format($totalMasuk, 0, ',', '.'); ?></td>
                                    <td><?= number_format($totalKeluar, 0, ',', '.'); ?></td>
                                    <td>-</td>
                                </tr>
                                <tr class="table-info">
                                    <td colspan="5" class="text-right">Saldo Akhir</td>
                                    <td colspan="3"><?= number_format($totalMasuk - $totalKeluar, 0, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
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