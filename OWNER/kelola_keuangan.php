<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
	header("Location: ../login.php");
	exit;
}

include '../config.php';
include '../util_id.php';

// Helper: hitung dan update pemakaian budget per baris
function refreshBudgetUsage(mysqli $conn, int $budgetId) {
	$b = mysqli_query($conn, "SELECT * FROM budgets WHERE budget_id = $budgetId");
	if (!$b || mysqli_num_rows($b) === 0) {
		return;
	}
	$row = mysqli_fetch_assoc($b);
	$bulan = intval($row['bulan']);
	$tahun = intval($row['tahun']);

	// gunakan kat_pengeluaran_id langsung (budget per kategori)
	$katId = isset($row['kat_pengeluaran_id']) ? intval($row['kat_pengeluaran_id']) : null;

	if ($katId === null) {
		// fallback: total pengeluaran bulan tsb jika kategori tidak teridentifikasi
		$qTerpakai = mysqli_query($conn, "
			SELECT COALESCE(SUM(jumlah_pengeluaran),0) AS total
			FROM transaksi_pengeluaran
			WHERE MONTH(tanggal_keluar) = '$bulan' AND YEAR(tanggal_keluar) = '$tahun'
		");
	} else {
		$qTerpakai = mysqli_query($conn, "
			SELECT COALESCE(SUM(jumlah_pengeluaran),0) AS total
			FROM transaksi_pengeluaran
			WHERE kat_pengeluaran_id = '$katId'
			  AND MONTH(tanggal_keluar) = '$bulan'
			  AND YEAR(tanggal_keluar) = '$tahun'
		");
	}
	$terpakai = $qTerpakai ? (float)mysqli_fetch_assoc($qTerpakai)['total'] : 0.0;

	$sisa = (float)$row['jumlah_budget'] - $terpakai;
	$ratio = $row['jumlah_budget'] > 0 ? ($terpakai / (float)$row['jumlah_budget']) : 0;
	$status = 'aman';
	if ($ratio >= 1.0) {
		$status = 'overlimit';
	} elseif ($ratio >= 0.8) {
		$status = 'warning';
	}

	mysqli_query($conn, "
		UPDATE budgets
		SET jumlah_terpakai = '$terpakai',
		    sisa_budget = '$sisa',
		    status = '$status'
		WHERE budget_id = $budgetId
	");
}

// Tambah / set budget baru
if (isset($_POST['set_budget'])) {
	$budget_id = intval($_POST['budget_id']);
	$bulan = intval($_POST['bulan']);
	$tahun = intval($_POST['tahun']);
	$katPengeluaranId = intval($_POST['kat_pengeluaran_id']);
	$jumlah = (float)$_POST['jumlah_budget'];

	// Cek apakah ID sudah ada
	$cekId = mysqli_query($conn, "SELECT budget_id FROM budgets WHERE budget_id = $budget_id");
	if ($cekId && mysqli_num_rows($cekId) > 0) {
		$_SESSION['error'] = "ID budget $budget_id sudah digunakan. Gunakan ID lain.";
	} else {
		mysqli_query($conn, "
			INSERT INTO budgets (budget_id, kat_pengeluaran_id, bulan, tahun, jumlah_budget, jumlah_terpakai, sisa_budget, status)
			VALUES ($budget_id, '$katPengeluaranId', '$bulan', '$tahun', '$jumlah', 0, '$jumlah', 'aman')
		");
		refreshBudgetUsage($conn, $budget_id);
	}
	header("Location: kelola_keuangan.php");
	exit;
}

// Update budget
if (isset($_POST['update_budget'])) {
	$budgetId = intval($_POST['budget_id']);
	$jumlah = (float)$_POST['jumlah_budget'];
	mysqli_query($conn, "UPDATE budgets SET jumlah_budget = '$jumlah' WHERE budget_id = $budgetId");
	refreshBudgetUsage($conn, $budgetId);
	header("Location: kelola_keuangan.php");
	exit;
}

// Refresh semua
if (isset($_POST['refresh_all'])) {
	$all = mysqli_query($conn, "SELECT budget_id FROM budgets");
	while ($all && $r = mysqli_fetch_assoc($all)) {
		refreshBudgetUsage($conn, intval($r['budget_id']));
	}
	header("Location: kelola_keuangan.php");
	exit;
}

$katKeluar = mysqli_query($conn, "SELECT * FROM kategori_pengeluaran ORDER BY kat_pengeluaran_id ASC");
$budgets = mysqli_query($conn, "
	SELECT b.*, k.nama_kategori
	FROM budgets b
	LEFT JOIN kategori_pengeluaran k ON b.kat_pengeluaran_id = k.kat_pengeluaran_id
	ORDER BY tahun DESC, bulan DESC, b.budget_id ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Kelola Keuangan (Budget)</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background: #555 !important; }
		.sidebar .nav-link { color: #fff !important; }
		.sidebar .nav-link:hover { background: #444; }
		.badge-aman { background:#28a745; }
		.badge-warning { background:#ffc107; color:#212529; }
		.badge-over { background:#dc3545; }
	</style>
</head>
<body id="page-top">
<div id="wrapper">
	<!-- Sidebar -->
	<?php $currentPage = 'kelola_keuangan'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-wallet"></i> Setting Budget</h3>

			<div class="card mb-4">
				<div class="card-header bg-secondary text-white">
					<i class="fas fa-sliders-h"></i> Tambah/Set Budget Bulanan per Kategori Pengeluaran
				</div>
				<div class="card-body">
					<?php if (isset($_SESSION['error'])): ?>
						<div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
					<?php endif; ?>
					<form method="POST">
						<div class="form-row">
							<div class="form-group col-md-2">
								<label>ID Budget <small class="text-muted">(wajib)</small></label>
								<input type="number" name="budget_id" class="form-control" min="1" required>
							</div>
							<div class="form-group col-md-2">
								<label>Bulan</label>
								<select name="bulan" class="form-control" required>
									<?php for ($i=1;$i<=12;$i++): ?>
										<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
									<?php endfor; ?>
								</select>
							</div>
							<div class="form-group col-md-3">
								<label>Tahun</label>
								<input type="number" name="tahun" class="form-control" value="<?php echo date('Y'); ?>" required>
							</div>
							<div class="form-group col-md-3">
								<label>Kategori Pengeluaran</label>
								<select name="kat_pengeluaran_id" class="form-control" required>
									<option value="" disabled selected>- Pilih -</option>
									<?php while ($k = mysqli_fetch_assoc($katKeluar)): ?>
										<option value="<?php echo $k['kat_pengeluaran_id']; ?>"><?php echo htmlspecialchars($k['nama_kategori']); ?></option>
									<?php endwhile; ?>
								</select>
							</div>
							<div class="form-group col-md-3">
								<label>Jumlah Budget (Rp)</label>
								<input type="number" name="jumlah_budget" class="form-control" min="0" step="1000" required>
							</div>
						</div>
						<button type="submit" name="set_budget" class="btn btn-success"><i class="fas fa-save"></i> Simpan Budget</button>
						<button type="submit" name="refresh_all" class="btn btn-outline-primary ml-2"><i class="fas fa-sync"></i> Refresh Pemakaian</button>
					</form>
				</div>
			</div>

			<div class="card">
				<div class="card-header bg-primary text-white">
					<i class="fas fa-list"></i> Daftar Budget
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th>Bulan</th>
								<th>Tahun</th>
								<th>Kategori</th>
								<th class="text-right">Jumlah Budget (Rp)</th>
								<th class="text-right">Terpakai (Rp)</th>
								<th class="text-right">Sisa (Rp)</th>
								<th>Status</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody>
							<?php if ($budgets && mysqli_num_rows($budgets) > 0): ?>
								<?php while ($b = mysqli_fetch_assoc($budgets)): ?>
									<tr>
										<td class="text-center"><?php echo $b['bulan']; ?></td>
										<td class="text-center"><?php echo $b['tahun']; ?></td>
										<td><?php echo htmlspecialchars($b['nama_kategori'] ?? '-'); ?></td>
										<td class="text-right"><?php echo number_format($b['jumlah_budget'],0,',','.'); ?></td>
										<td class="text-right"><?php echo number_format($b['jumlah_terpakai'],0,',','.'); ?></td>
										<td class="text-right"><?php echo number_format($b['sisa_budget'],0,',','.'); ?></td>
										<td class="text-center">
											<?php if ($b['status'] === 'overlimit'): ?>
												<span class="badge badge-over">Overlimit</span>
											<?php elseif ($b['status'] === 'warning'): ?>
												<span class="badge badge-warning">Warning</span>
											<?php else: ?>
												<span class="badge badge-aman">Aman</span>
											<?php endif; ?>
										</td>
										<td class="text-center">
											<button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#edit<?php echo $b['budget_id']; ?>"><i class="fas fa-edit"></i> Ubah</button>
										</td>
									</tr>
									<div class="modal fade" id="edit<?php echo $b['budget_id']; ?>" tabindex="-1" role="dialog">
										<div class="modal-dialog" role="document">
											<div class="modal-content">
												<form method="POST">
													<div class="modal-header bg-secondary text-white">
														<h5 class="modal-title">Ubah Budget</h5>
														<button type="button" class="close" data-dismiss="modal">&times;</button>
													</div>
													<div class="modal-body">
														<input type="hidden" name="budget_id" value="<?php echo $b['budget_id']; ?>">
														<div class="form-group">
															<label>Jumlah Budget (Rp)</label>
															<input type="number" name="jumlah_budget" class="form-control" min="0" step="1000" value="<?php echo $b['jumlah_budget']; ?>" required>
														</div>
													</div>
													<div class="modal-footer">
														<button type="submit" name="update_budget" class="btn btn-warning"><i class="fas fa-save"></i> Simpan</button>
														<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
													</div>
												</form>
											</div>
										</div>
									</div>
								<?php endwhile; ?>
							<?php else: ?>
								<tr><td colspan="8" class="text-center text-muted">Belum ada data budget.</td></tr>
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


