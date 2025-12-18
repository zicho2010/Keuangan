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

// Fungsi upsertDailySummary dihapus karena tabel daily_summary sudah tidak digunakan
// function upsertDailySummary(mysqli $conn, $tanggal) {
// 	...
// }

$success = '';
$error = '';

// Proses tambah pemasukan
if (isset($_POST['tambah_pemasukan'])) {
	$transaksi_masuk_id = intval($_POST['transaksi_masuk_id']);
	$tanggal = $_POST['tanggal_masuk'];
	$kategori = intval($_POST['kat_pemasukan_id']);
	$jumlah = floatval($_POST['jumlah_pemasukan']);
	$deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
	$userId = intval($_SESSION['user_id']);

	// Validasi
	if ($transaksi_masuk_id <= 0) {
		$error = "ID transaksi harus lebih besar dari 0.";
	} elseif ($kategori <= 0) {
		$error = "Kategori harus dipilih.";
	} elseif ($jumlah <= 0) {
		$error = "Jumlah pemasukan harus lebih besar dari 0.";
	} else {
		// Cek apakah ID sudah ada
		$cekId = mysqli_query($conn, "SELECT transaksi_masuk_id FROM transaksi_pemasukan WHERE transaksi_masuk_id = $transaksi_masuk_id");
		if ($cekId && mysqli_num_rows($cekId) > 0) {
			$error = "ID transaksi $transaksi_masuk_id sudah digunakan. Gunakan ID lain.";
		} else {
			// Owner input langsung approved
			$hasStatus = mysqli_query($conn, "SHOW COLUMNS FROM transaksi_pemasukan LIKE 'status'");
			$statusSql = ($hasStatus && mysqli_num_rows($hasStatus) > 0) ? ", status" : "";
			$statusVal = ($hasStatus && mysqli_num_rows($hasStatus) > 0) ? ", 'approved'" : "";

			mysqli_query($conn, "
				INSERT INTO transaksi_pemasukan (transaksi_masuk_id, user_id, kat_pemasukan_id, tanggal_masuk, jumlah_pemasukan, deskripsi$statusSql)
				VALUES ($transaksi_masuk_id, $userId, $kategori, '$tanggal', '$jumlah', '$deskripsi'$statusVal)
			");

			$saldoAwal = getSaldoTerakhir($conn);
			$saldoAkhir = $saldoAwal + $jumlah;
			$kasId = generateNextId($conn, 'cash_flow', 'kas_id');
			
			mysqli_query($conn, "
				INSERT INTO cash_flow (kas_id, transaksi_masuk_id, tipe, saldo_awal, saldo_akhir)
				VALUES ($kasId, $transaksi_masuk_id, 'pemasukan', '$saldoAwal', '$saldoAkhir')
			");

			// upsertDailySummary($conn, $tanggal); // Dihapus karena daily_summary sudah tidak digunakan
			
			// Log audit
			logAudit($conn, $userId, 'create', 'transaksi_pemasukan', $transaksi_masuk_id, null, [
				'kat_pemasukan_id' => $kategori,
				'tanggal_masuk' => $tanggal,
				'jumlah_pemasukan' => $jumlah,
				'deskripsi' => $deskripsi
			]);
			
			$success = "Pemasukan berhasil ditambahkan.";
		}
	}
}

// Proses tambah pengeluaran
if (isset($_POST['tambah_pengeluaran'])) {
	$transaksi_keluar_id = intval($_POST['transaksi_keluar_id']);
	$tanggal = $_POST['tanggal_keluar'];
	$kategori = intval($_POST['kat_pengeluaran_id']);
	$jumlah = floatval($_POST['jumlah_pengeluaran']);
	$deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
	$userId = intval($_SESSION['user_id']);

	// Validasi
	if ($transaksi_keluar_id <= 0) {
		$error = "ID transaksi harus lebih besar dari 0.";
	} elseif ($kategori <= 0) {
		$error = "Kategori harus dipilih.";
	} elseif ($jumlah <= 0) {
		$error = "Jumlah pengeluaran harus lebih besar dari 0.";
	} else {
		// Cek apakah ID sudah ada
		$cekId = mysqli_query($conn, "SELECT transaksi_keluar_id FROM transaksi_pengeluaran WHERE transaksi_keluar_id = $transaksi_keluar_id");
		if ($cekId && mysqli_num_rows($cekId) > 0) {
			$error = "ID transaksi $transaksi_keluar_id sudah digunakan. Gunakan ID lain.";
		} else {
			// Owner input langsung approved
			$hasStatus = mysqli_query($conn, "SHOW COLUMNS FROM transaksi_pengeluaran LIKE 'status'");
			$statusSql = ($hasStatus && mysqli_num_rows($hasStatus) > 0) ? ", status" : "";
			$statusVal = ($hasStatus && mysqli_num_rows($hasStatus) > 0) ? ", 'approved'" : "";

			mysqli_query($conn, "
				INSERT INTO transaksi_pengeluaran (transaksi_keluar_id, user_id, kat_pengeluaran_id, tanggal_keluar, jumlah_pengeluaran, deskripsi$statusSql)
				VALUES ($transaksi_keluar_id, $userId, $kategori, '$tanggal', '$jumlah', '$deskripsi'$statusVal)
			");

			$saldoAwal = getSaldoTerakhir($conn);
			$saldoAkhir = $saldoAwal - $jumlah;
			$kasId = generateNextId($conn, 'cash_flow', 'kas_id');
			
			mysqli_query($conn, "
				INSERT INTO cash_flow (kas_id, transaksi_keluar_id, tipe, saldo_awal, saldo_akhir)
				VALUES ($kasId, $transaksi_keluar_id, 'pengeluaran', '$saldoAwal', '$saldoAkhir')
			");

			// upsertDailySummary($conn, $tanggal); // Dihapus karena daily_summary sudah tidak digunakan
			
			// Log audit
			logAudit($conn, $userId, 'create', 'transaksi_pengeluaran', $transaksi_keluar_id, null, [
				'kat_pengeluaran_id' => $kategori,
				'tanggal_keluar' => $tanggal,
				'jumlah_pengeluaran' => $jumlah,
				'deskripsi' => $deskripsi
			]);
			
			$success = "Pengeluaran berhasil ditambahkan.";
		}
	}
}

$katMasuk = mysqli_query($conn, "SELECT * FROM kategori_pemasukan ORDER BY kat_pemasukan_id ASC");
$katKeluar = mysqli_query($conn, "SELECT * FROM kategori_pengeluaran ORDER BY kat_pengeluaran_id ASC");

$lastTransaksi = mysqli_query($conn, "
	(SELECT tanggal_masuk AS tanggal, 'Pemasukan' AS tipe, jumlah_pemasukan AS amount, deskripsi, transaksi_masuk_id AS id
	 FROM transaksi_pemasukan ORDER BY transaksi_masuk_id DESC LIMIT 10)
	UNION ALL
	(SELECT tanggal_keluar AS tanggal, 'Pengeluaran' AS tipe, jumlah_pengeluaran As amount, deskripsi, transaksi_keluar_id AS id
	 FROM transaksi_pengeluaran ORDER BY transaksi_keluar_id DESC LIMIT 10)
	ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Kelola Transaksi</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background: #555 !important; }
		.sidebar .nav-link { color: #fff !important; }
		.sidebar .nav-link:hover { background: #444; }
	</style>
</head>
<body id="page-top">
<div id="wrapper">
	<!-- Sidebar -->
	<?php $currentPage = 'kelola_transaksi'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-exchange-alt"></i> Kelola Transaksi</h3>

			<?php if ($success): ?>
				<div class="alert alert-success"><?= $success; ?></div>
			<?php endif; ?>
			<?php if ($error): ?>
				<div class="alert alert-danger"><?= $error; ?></div>
			<?php endif; ?>

			<div class="row">
				<div class="col-md-6">
					<div class="card mb-4">
						<div class="card-header bg-success text-white">
							<i class="fas fa-plus-circle"></i> Tambah Pemasukan
						</div>
						<div class="card-body">
							<form method="POST">
								<div class="form-row">
									<div class="form-group col-md-6">
										<label>ID Transaksi <small class="text-muted">(wajib)</small></label>
										<input type="number" name="transaksi_masuk_id" class="form-control" min="1" required>
									</div>
									<div class="form-group col-md-6">
										<label>Tanggal</label>
										<input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d'); ?>" required>
									</div>
								</div>
								<div class="form-row">
									<div class="form-group col-md-6">
										<label>Kategori</label>
										<select name="kat_pemasukan_id" class="form-control" required>
											<option value="" disabled selected>- Pilih -</option>
											<?php 
											mysqli_data_seek($katMasuk, 0);
											while ($km = mysqli_fetch_assoc($katMasuk)): ?>
												<option value="<?php echo $km['kat_pemasukan_id']; ?>"><?php echo htmlspecialchars($km['nama_kategori']); ?></option>
											<?php endwhile; ?>
										</select>
									</div>
									<div class="form-group col-md-6">
										<label>Jumlah (Rp)</label>
										<input type="number" name="jumlah_pemasukan" class="form-control" min="0" step="100" required>
									</div>
								</div>
								<div class="form-group">
									<label>Deskripsi</label>
									<input type="text" name="deskripsi" class="form-control" placeholder="Keterangan (opsional)">
								</div>
								<button type="submit" name="tambah_pemasukan" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
							</form>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card mb-4">
						<div class="card-header bg-danger text-white">
							<i class="fas fa-minus-circle"></i> Tambah Pengeluaran
						</div>
						<div class="card-body">
							<form method="POST">
								<div class="form-row">
									<div class="form-group col-md-6">
										<label>ID Transaksi <small class="text-muted">(wajib)</small></label>
										<input type="number" name="transaksi_keluar_id" class="form-control" min="1" required>
									</div>
									<div class="form-group col-md-6">
										<label>Tanggal</label>
										<input type="date" name="tanggal_keluar" class="form-control" value="<?= date('Y-m-d'); ?>" required>
									</div>
								</div>
								<div class="form-row">
									<div class="form-group col-md-6">
										<label>Kategori</label>
										<select name="kat_pengeluaran_id" class="form-control" required>
											<option value="" disabled selected>- Pilih -</option>
											<?php 
											mysqli_data_seek($katKeluar, 0);
											while ($kk = mysqli_fetch_assoc($katKeluar)): ?>
												<option value="<?php echo $kk['kat_pengeluaran_id']; ?>"><?php echo htmlspecialchars($kk['nama_kategori']); ?></option>
											<?php endwhile; ?>
										</select>
									</div>
									<div class="form-group col-md-6">
										<label>Jumlah (Rp)</label>
										<input type="number" name="jumlah_pengeluaran" class="form-control" min="0" step="100" required>
									</div>
								</div>
								<div class="form-group">
									<label>Deskripsi</label>
									<input type="text" name="deskripsi" class="form-control" placeholder="Keterangan (opsional)">
								</div>
								<button type="submit" name="tambah_pengeluaran" class="btn btn-danger"><i class="fas fa-save"></i> Simpan</button>
							</form>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header bg-secondary text-white">
					<i class="fas fa-history"></i> Transaksi Terbaru
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th width="10%">Tanggal</th>
								<th width="15%">Tipe</th>
								<th>Deskripsi</th>
								<th width="20%" class="text-right">Jumlah (Rp)</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($lastTransaksi && mysqli_num_rows($lastTransaksi) > 0): ?>
								<?php while ($t = mysqli_fetch_assoc($lastTransaksi)): ?>
									<tr>
										<td class="text-center"><?php echo htmlspecialchars($t['tanggal']); ?></td>
										<td class="text-center">
											<?php if ($t['tipe'] === 'Pemasukan'): ?>
												<span class="badge badge-success">Pemasukan</span>
											<?php else: ?>
												<span class="badge badge-danger">Pengeluaran</span>
											<?php endif; ?>
										</td>
										<td><?php echo htmlspecialchars($t['deskripsi'] ?? '-'); ?></td>
										<td class="text-right"><?php echo number_format($t['amount'], 0, ',', '.'); ?></td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr><td colspan="4" class="text-center text-muted">Belum ada transaksi.</td></tr>
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
