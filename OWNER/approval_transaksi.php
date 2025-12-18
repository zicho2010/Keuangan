<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
	header("Location: ../login.php");
	exit;
}

include '../config.php';
include '../util_id.php';
include '../util_audit.php';

function getSaldoTerakhir(mysqli $conn) {
	$q = mysqli_query($conn, "SELECT saldo_akhir FROM cash_flow ORDER BY created_at DESC, kas_id DESC LIMIT 1");
	if ($q && mysqli_num_rows($q) > 0) {
		$r = mysqli_fetch_assoc($q);
		return (float)$r['saldo_akhir'];
	}
	return 0.0;
}

// Fungsi recalcDaily dihapus karena tabel daily_summary sudah tidak digunakan
// function recalcDaily(mysqli $conn, string $tanggal) {
// 	...
// }

// Actions
$userId = intval($_SESSION['user_id']);

if (isset($_GET['approve_in'])) {
	$id = intval($_GET['approve_in']);
	$t = mysqli_query($conn, "SELECT * FROM transaksi_pemasukan WHERE transaksi_masuk_id=$id AND status='draft'");
	if ($t && mysqli_num_rows($t) > 0) {
		$row = mysqli_fetch_assoc($t);
		$oldData = $row;
		$saldoAwal = getSaldoTerakhir($conn);
		$saldoAkhir = $saldoAwal + (float)$row['jumlah_pemasukan'];
		$kasId = generateNextId($conn, 'cash_flow', 'kas_id');
		mysqli_query($conn, "INSERT INTO cash_flow (kas_id, transaksi_masuk_id, tipe, saldo_awal, saldo_akhir) VALUES ($kasId, $id, 'pemasukan', '$saldoAwal', '$saldoAkhir')");
		mysqli_query($conn, "UPDATE transaksi_pemasukan SET status='approved' WHERE transaksi_masuk_id=$id");
		// recalcDaily($conn, $row['tanggal_masuk']); // Dihapus karena daily_summary sudah tidak digunakan
		
		// Log audit
		logAudit($conn, $userId, 'approve', 'transaksi_pemasukan', $id, [
			'status' => 'draft',
			'jumlah' => $row['jumlah_pemasukan']
		], [
			'status' => 'approved',
			'jumlah' => $row['jumlah_pemasukan']
		]);
	}
	header("Location: approval_transaksi.php");
	exit;
}
if (isset($_GET['reject_in'])) {
	$id = intval($_GET['reject_in']);
	$t = mysqli_query($conn, "SELECT * FROM transaksi_pemasukan WHERE transaksi_masuk_id=$id AND status='draft'");
	if ($t && mysqli_num_rows($t) > 0) {
		$row = mysqli_fetch_assoc($t);
		mysqli_query($conn, "UPDATE transaksi_pemasukan SET status='rejected' WHERE transaksi_masuk_id=$id AND status='draft'");
		
		// Log audit
		logAudit($conn, $userId, 'reject', 'transaksi_pemasukan', $id, [
			'status' => 'draft',
			'jumlah' => $row['jumlah_pemasukan']
		], [
			'status' => 'rejected',
			'jumlah' => $row['jumlah_pemasukan']
		]);
	}
	header("Location: approval_transaksi.php");
	exit;
}
if (isset($_GET['approve_out'])) {
	$id = intval($_GET['approve_out']);
	$t = mysqli_query($conn, "SELECT * FROM transaksi_pengeluaran WHERE transaksi_keluar_id=$id AND status='draft'");
	if ($t && mysqli_num_rows($t) > 0) {
		$row = mysqli_fetch_assoc($t);
		$saldoAwal = getSaldoTerakhir($conn);
		$saldoAkhir = $saldoAwal - (float)$row['jumlah_pengeluaran'];
		$kasId = generateNextId($conn, 'cash_flow', 'kas_id');
		mysqli_query($conn, "INSERT INTO cash_flow (kas_id, transaksi_keluar_id, tipe, saldo_awal, saldo_akhir) VALUES ($kasId, $id, 'pengeluaran', '$saldoAwal', '$saldoAkhir')");
		mysqli_query($conn, "UPDATE transaksi_pengeluaran SET status='approved' WHERE transaksi_keluar_id=$id");
		// recalcDaily($conn, $row['tanggal_keluar']); // Dihapus karena daily_summary sudah tidak digunakan
		
		// Log audit
		logAudit($conn, $userId, 'approve', 'transaksi_pengeluaran', $id, [
			'status' => 'draft',
			'jumlah' => $row['jumlah_pengeluaran']
		], [
			'status' => 'approved',
			'jumlah' => $row['jumlah_pengeluaran']
		]);
	}
	header("Location: approval_transaksi.php");
	exit;
}
if (isset($_GET['reject_out'])) {
	$id = intval($_GET['reject_out']);
	$t = mysqli_query($conn, "SELECT * FROM transaksi_pengeluaran WHERE transaksi_keluar_id=$id AND status='draft'");
	if ($t && mysqli_num_rows($t) > 0) {
		$row = mysqli_fetch_assoc($t);
		mysqli_query($conn, "UPDATE transaksi_pengeluaran SET status='rejected' WHERE transaksi_keluar_id=$id AND status='draft'");
		
		// Log audit
		logAudit($conn, $userId, 'reject', 'transaksi_pengeluaran', $id, [
			'status' => 'draft',
			'jumlah' => $row['jumlah_pengeluaran']
		], [
			'status' => 'rejected',
			'jumlah' => $row['jumlah_pengeluaran']
		]);
	}
	header("Location: approval_transaksi.php");
	exit;
}

$draftIn = mysqli_query($conn, "
	SELECT t.*, k.nama_kategori, u.username
	FROM transaksi_pemasukan t
	JOIN kategori_pemasukan k ON t.kat_pemasukan_id=k.kat_pemasukan_id
	JOIN users u ON t.user_id=u.user_id
	WHERE t.status='draft'
	ORDER BY t.transaksi_masuk_id DESC
");
$draftOut = mysqli_query($conn, "
	SELECT t.*, k.nama_kategori, u.username
	FROM transaksi_pengeluaran t
	JOIN kategori_pengeluaran k ON t.kat_pengeluaran_id=k.kat_pengeluaran_id
	JOIN users u ON t.user_id=u.user_id
	WHERE t.status='draft'
	ORDER BY t.transaksi_keluar_id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Approval Transaksi</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background:#555!important; }
		.sidebar .nav-link { color:#fff!important; }
		.sidebar .nav-link:hover { background:#444; }
		.btn-action-group { 
			display: flex; 
			flex-wrap: wrap; 
			gap: 5px; 
			justify-content: center; 
			align-items: center; 
		}
		.btn-action-group .btn {
			margin: 2px;
			white-space: nowrap;
		}
		.table td { vertical-align: middle; }
	</style>
</head>
<body id="page-top">
<div id="wrapper">
	<!-- Sidebar -->
	<?php $currentPage = 'approval_transaksi'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-check-circle"></i> Approval Transaksi</h3>

			<div class="card mb-4">
				<div class="card-header bg-success text-white">
					<i class="fas fa-arrow-down"></i> Draft Pemasukan
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th>No</th>
								<th>Tanggal</th>
								<th>Kategori</th>
								<th>Jumlah (Rp)</th>
								<th>Deskripsi</th>
								<th>User</th>
								<th width="150">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($draftIn && mysqli_num_rows($draftIn) > 0): $no=1; while ($r=mysqli_fetch_assoc($draftIn)): ?>
								<tr>
									<td class="text-center"><?= $no++; ?></td>
									<td><?= htmlspecialchars($r['tanggal_masuk']); ?></td>
									<td><?= htmlspecialchars($r['nama_kategori']); ?></td>
									<td class="text-right"><?= number_format($r['jumlah_pemasukan'],0,',','.'); ?></td>
									<td><?= htmlspecialchars($r['deskripsi']); ?></td>
									<td class="text-center"><?= htmlspecialchars($r['username']); ?></td>
									<td class="text-center">
										<div class="btn-action-group">
											<a class="btn btn-success btn-sm" href="approval_transaksi.php?approve_in=<?= $r['transaksi_masuk_id']; ?>" onclick="return confirm('Setujui pemasukan ini?')" title="Approve">
												<i class="fas fa-check"></i> <span class="d-none d-md-inline">Approve</span>
											</a>
											<a class="btn btn-danger btn-sm" href="approval_transaksi.php?reject_in=<?= $r['transaksi_masuk_id']; ?>" onclick="return confirm('Tolak pemasukan ini?')" title="Reject">
												<i class="fas fa-times"></i> <span class="d-none d-md-inline">Reject</span>
											</a>
										</div>
									</td>
								</tr>
							<?php endwhile; else: ?>
								<tr><td colspan="7" class="text-center text-muted">Tidak ada draft pemasukan.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="card">
				<div class="card-header bg-danger text-white">
					<i class="fas fa-arrow-up"></i> Draft Pengeluaran
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th>No</th>
								<th>Tanggal</th>
								<th>Kategori</th>
								<th>Jumlah (Rp)</th>
								<th>Deskripsi</th>
								<th>User</th>
								<th width="150">Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($draftOut && mysqli_num_rows($draftOut) > 0): $no=1; while ($r=mysqli_fetch_assoc($draftOut)): ?>
								<tr>
									<td class="text-center"><?= $no++; ?></td>
									<td><?= htmlspecialchars($r['tanggal_keluar']); ?></td>
									<td><?= htmlspecialchars($r['nama_kategori']); ?></td>
									<td class="text-right"><?= number_format($r['jumlah_pengeluaran'],0,',','.'); ?></td>
									<td><?= htmlspecialchars($r['deskripsi']); ?></td>
									<td class="text-center"><?= htmlspecialchars($r['username']); ?></td>
									<td class="text-center">
										<div class="btn-action-group">
											<a class="btn btn-success btn-sm" href="approval_transaksi.php?approve_out=<?= $r['transaksi_keluar_id']; ?>" onclick="return confirm('Setujui pengeluaran ini?')" title="Approve">
												<i class="fas fa-check"></i> <span class="d-none d-md-inline">Approve</span>
											</a>
											<a class="btn btn-danger btn-sm" href="approval_transaksi.php?reject_out=<?= $r['transaksi_keluar_id']; ?>" onclick="return confirm('Tolak pengeluaran ini?')" title="Reject">
												<i class="fas fa-times"></i> <span class="d-none d-md-inline">Reject</span>
											</a>
										</div>
									</td>
								</tr>
							<?php endwhile; else: ?>
								<tr><td colspan="7" class="text-center text-muted">Tidak ada draft pengeluaran.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="../startbootstrap-sb-admin-2-master/vendor/jquery/jquery.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/js/sb-admin-2.min.js"></script>
</body>
</html>


