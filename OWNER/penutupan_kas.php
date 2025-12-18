<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
	header("Location: ../login.php");
	exit;
}

include '../config.php';
include '../util_id.php';
include '../util_audit.php';

$success = '';
$error = '';

// Fungsi untuk mendapatkan saldo akhir hari sebelumnya
function getSaldoAkhirHariSebelumnya(mysqli $conn, string $tanggal): float {
	$tglEsc = mysqli_real_escape_string($conn, $tanggal);
	// Ambil saldo akhir dari cash_flow untuk hari sebelum tanggal yang dipilih
	$q = mysqli_query($conn, "
		SELECT saldo_akhir
		FROM cash_flow
		WHERE DATE(created_at) < '$tglEsc'
		ORDER BY created_at DESC, kas_id DESC
		LIMIT 1
	");
	if ($q && mysqli_num_rows($q) > 0) {
		$r = mysqli_fetch_assoc($q);
		return floatval($r['saldo_akhir']);
	}
	return 0.0;
}

// Buka shift baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buka_shift'])) {
	$shift_id = intval($_POST['shift_id']);
	$tanggal = $_POST['tanggal'];
	$kasir_user_id = intval($_POST['kasir_user_id']);
	$opening_cash = floatval($_POST['opening_cash']);
	$userId = intval($_SESSION['user_id']);
	
	// Cek apakah shift_id sudah ada
	$cekId = mysqli_query($conn, "SELECT shift_id FROM kas_shift WHERE shift_id = $shift_id");
	if ($cekId && mysqli_num_rows($cekId) > 0) {
		$error = "ID shift $shift_id sudah digunakan.";
	} else {
		// Cek apakah sudah ada shift terbuka untuk tanggal dan kasir yang sama
		$cekOpen = mysqli_query($conn, "SELECT shift_id FROM kas_shift WHERE tanggal = '$tanggal' AND kasir_user_id = $kasir_user_id AND status = 'open'");
		if ($cekOpen && mysqli_num_rows($cekOpen) > 0) {
			$error = "Shift untuk tanggal dan kasir ini masih terbuka. Tutup shift sebelumnya terlebih dahulu.";
		} else {
			$sql = "INSERT INTO kas_shift (shift_id, tanggal, kasir_user_id, opening_cash, status)
					VALUES ($shift_id, '$tanggal', $kasir_user_id, $opening_cash, 'open')";
			
			if (mysqli_query($conn, $sql)) {
				logAudit($conn, $userId, 'create', 'kas_shift', $shift_id, null, [
					'tanggal' => $tanggal,
					'kasir_user_id' => $kasir_user_id,
					'opening_cash' => $opening_cash
				]);
				$success = "Shift berhasil dibuka.";
			} else {
				$error = "Gagal membuka shift: " . mysqli_error($conn);
			}
		}
	}
}

// Edit shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_shift'])) {
	$shift_id = intval($_POST['shift_id']);
	$tanggal = $_POST['tanggal'];
	$kasir_user_id = intval($_POST['kasir_user_id']);
	$opening_cash = floatval($_POST['opening_cash']);
	$actual_closing_cash = isset($_POST['actual_closing_cash']) && $_POST['actual_closing_cash'] !== '' ? floatval($_POST['actual_closing_cash']) : null;
	$notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
	$userId = intval($_SESSION['user_id']);
	
	// Get shift data lama untuk audit
	$shiftOld = mysqli_query($conn, "SELECT * FROM kas_shift WHERE shift_id = $shift_id");
	if (!$shiftOld || mysqli_num_rows($shiftOld) === 0) {
		$error = "Shift tidak ditemukan.";
	} else {
		$shiftOldData = mysqli_fetch_assoc($shiftOld);
		$oldStatus = $shiftOldData['status'];
		
		// Jika shift sudah closed, hitung ulang variance jika actual_closing_cash diubah
		if ($oldStatus == 'closed') {
			// Gunakan actual_closing_cash yang baru jika diisi, jika tidak gunakan yang lama
			$actualCash = $actual_closing_cash !== null ? $actual_closing_cash : floatval($shiftOldData['actual_closing_cash']);
			
			$tanggalEsc = mysqli_real_escape_string($conn, $tanggal);
			$qMasuk = mysqli_query($conn, "
				SELECT COALESCE(SUM(jumlah_pemasukan), 0) AS total
				FROM transaksi_pemasukan
				WHERE tanggal_masuk = '$tanggalEsc' AND status = 'approved'
			");
			$qKeluar = mysqli_query($conn, "
				SELECT COALESCE(SUM(jumlah_pengeluaran), 0) AS total
				FROM transaksi_pengeluaran
				WHERE tanggal_keluar = '$tanggalEsc' AND status = 'approved'
			");
			
			$totalMasuk = $qMasuk ? floatval(mysqli_fetch_assoc($qMasuk)['total']) : 0;
			$totalKeluar = $qKeluar ? floatval(mysqli_fetch_assoc($qKeluar)['total']) : 0;
			$expectedClosing = $opening_cash + $totalMasuk - $totalKeluar;
			$variance = $actualCash - $expectedClosing;
			
			$notesEsc = $notes ? "'$notes'" : "NULL";
			$sql = "UPDATE kas_shift 
					SET tanggal = '$tanggal',
						kasir_user_id = $kasir_user_id,
						opening_cash = $opening_cash,
						expected_closing_cash = $expectedClosing,
						actual_closing_cash = $actualCash,
						variance = $variance,
						notes = $notesEsc
					WHERE shift_id = $shift_id";
		} else {
			// Jika shift masih open, hanya update data dasar
			$notesEsc = $notes ? "'$notes'" : "NULL";
			$sql = "UPDATE kas_shift 
					SET tanggal = '$tanggal',
						kasir_user_id = $kasir_user_id,
						opening_cash = $opening_cash,
						notes = $notesEsc
					WHERE shift_id = $shift_id";
		}
		
		if (mysqli_query($conn, $sql)) {
			logAudit($conn, $userId, 'update', 'kas_shift', $shift_id, [
				'tanggal' => $shiftOldData['tanggal'],
				'kasir_user_id' => $shiftOldData['kasir_user_id'],
				'opening_cash' => $shiftOldData['opening_cash'],
				'actual_closing_cash' => $shiftOldData['actual_closing_cash'] ?? null,
				'notes' => $shiftOldData['notes'] ?? null
			], [
				'tanggal' => $tanggal,
				'kasir_user_id' => $kasir_user_id,
				'opening_cash' => $opening_cash,
				'actual_closing_cash' => $actual_closing_cash ?? null,
				'notes' => $notes ?: null
			]);
			$success = "Shift berhasil diupdate.";
			header("Location: penutupan_kas.php");
			exit;
		} else {
			$error = "Gagal mengupdate shift: " . mysqli_error($conn);
		}
	}
}

// Tutup shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tutup_shift'])) {
	$shift_id = intval($_POST['shift_id']);
	$actual_closing_cash = floatval($_POST['actual_closing_cash']);
	$notes = mysqli_real_escape_string($conn, trim($_POST['notes']));
	$userId = intval($_SESSION['user_id']);
	
	// Get shift data
	$shift = mysqli_query($conn, "SELECT * FROM kas_shift WHERE shift_id = $shift_id AND status = 'open'");
	if (!$shift || mysqli_num_rows($shift) === 0) {
		$error = "Shift tidak ditemukan atau sudah ditutup.";
	} else {
		$shiftData = mysqli_fetch_assoc($shift);
		$openingCash = floatval($shiftData['opening_cash']);
		$kasirId = (int)$shiftData['kasir_user_id'];
		$tanggal = mysqli_real_escape_string($conn, $shiftData['tanggal']);
		
		// Hitung kas seharusnya:
		// Kas Awal (saldo akhir hari sebelumnya) + Pemasukan Approved - Pengeluaran Approved
		// Hanya hitung transaksi yang sudah approved (sesuai dengan perhitungan dashboard)
		$qMasuk = mysqli_query($conn, "
			SELECT COALESCE(SUM(jumlah_pemasukan), 0) AS total
			FROM transaksi_pemasukan
			WHERE tanggal_masuk = '$tanggal' AND status = 'approved'
		");
		$qKeluar = mysqli_query($conn, "
			SELECT COALESCE(SUM(jumlah_pengeluaran), 0) AS total
			FROM transaksi_pengeluaran
			WHERE tanggal_keluar = '$tanggal' AND status = 'approved'
		");
		
		$totalMasuk = $qMasuk ? floatval(mysqli_fetch_assoc($qMasuk)['total']) : 0;
		$totalKeluar = $qKeluar ? floatval(mysqli_fetch_assoc($qKeluar)['total']) : 0;
		$expectedClosing = $openingCash + $totalMasuk - $totalKeluar;
		$variance = $actual_closing_cash - $expectedClosing;
		
		$notesEsc = $notes ? "'$notes'" : "NULL";
		
		$sql = "UPDATE kas_shift 
				SET closed_at = NOW(),
					expected_closing_cash = $expectedClosing,
					actual_closing_cash = $actual_closing_cash,
					variance = $variance,
					notes = $notesEsc,
					status = 'closed'
				WHERE shift_id = $shift_id";
		
		if (mysqli_query($conn, $sql)) {
			logAudit($conn, $userId, 'update', 'kas_shift', $shift_id, [
				'status' => 'open',
				'opening_cash' => $openingCash
			], [
				'status' => 'closed',
				'expected_closing_cash' => $expectedClosing,
				'actual_closing_cash' => $actual_closing_cash,
				'variance' => $variance
			]);
			$success = "Shift berhasil ditutup. Selisih: Rp " . number_format($variance, 0, ',', '.');
		} else {
			$error = "Gagal menutup shift: " . mysqli_error($conn);
		}
	}
}

// Get all shifts
$shifts = mysqli_query($conn, "
	SELECT 
		ks.*, 
		u.username,
		(
			SELECT COUNT(*) 
			FROM transaksi_pemasukan 
			WHERE tanggal_masuk = ks.tanggal 
			  AND status = 'approved'
		) +
		(
			SELECT COUNT(*) 
			FROM transaksi_pengeluaran 
			WHERE tanggal_keluar = ks.tanggal 
			  AND status = 'approved'
		) AS total_transaksi
	FROM kas_shift ks
	LEFT JOIN users u ON ks.kasir_user_id = u.user_id
	ORDER BY ks.shift_id DESC
");

// Get users for dropdown
$users = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin' ORDER BY user_id ASC");

// Get default saldo akhir untuk form (hari sebelumnya dari tanggal hari ini)
$defaultSaldoAkhir = getSaldoAkhirHariSebelumnya($conn, date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Penutupan Kas</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background: #555 !important; }
		.sidebar .nav-link { color: #fff !important; }
		.sidebar .nav-link:hover { background: #444; }
		.variance-positive { color: green; font-weight: bold; }
		.variance-negative { color: red; font-weight: bold; }
		.variance-zero { color: blue; font-weight: bold; }
	</style>
</head>
<body id="page-top">
<div id="wrapper">
	<!-- Sidebar -->
	<?php $currentPage = 'penutupan_kas'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-cash-register"></i> Penutupan Kas Harian/Shift</h3>

			<?php if ($success): ?>
				<div class="alert alert-success"><?= $success; ?></div>
			<?php endif; ?>
			<?php if ($error): ?>
				<div class="alert alert-danger"><?= $error; ?></div>
			<?php endif; ?>

			<!-- Form Buka Shift -->
			<div class="card mb-4">
				<div class="card-header bg-success text-white">
					<i class="fas fa-door-open"></i> Buka Shift Baru
				</div>
				<div class="card-body">
					<form method="POST">
						<div class="form-row">
							<div class="form-group col-md-3">
								<label>ID Shift <small class="text-muted">(wajib)</small></label>
								<input type="number" name="shift_id" class="form-control" min="1" required>
							</div>
							<div class="form-group col-md-3">
								<label>Tanggal</label>
								<input type="date" name="tanggal" id="tanggal_shift" class="form-control" value="<?= date('Y-m-d'); ?>" required>
							</div>
							<div class="form-group col-md-3">
								<label>Kasir</label>
								<select name="kasir_user_id" class="form-control" required>
									<option value="">-- Pilih Kasir --</option>
									<?php 
									mysqli_data_seek($users, 0);
									while ($u = mysqli_fetch_assoc($users)): ?>
										<option value="<?= $u['user_id']; ?>"><?= htmlspecialchars($u['username']); ?></option>
									<?php endwhile; ?>
								</select>
							</div>
							<div class="form-group col-md-3">
								<label>Kas Awal (Rp) <small class="text-muted">(Saldo Akhir Hari Sebelumnya)</small></label>
								<input type="number" name="opening_cash" id="opening_cash" class="form-control" min="0" step="100" value="<?= number_format($defaultSaldoAkhir, 0, '.', ''); ?>" required>
								<small class="text-muted">Otomatis diisi dengan saldo akhir hari sebelumnya. Bisa diubah jika perlu.</small>
							</div>
						</div>
						<button type="submit" name="buka_shift" class="btn btn-success">
							<i class="fas fa-door-open"></i> Buka Shift
						</button>
					</form>
				</div>
			</div>

			<!-- Daftar Shift -->
			<div class="card">
				<div class="card-header bg-primary text-white">
					<i class="fas fa-list"></i> Daftar Shift
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th>ID</th>
								<th>Tanggal</th>
								<th>Kasir</th>
								<th>Kas Awal</th>
								<th>Kas Harusnya</th>
								<th>Kas Aktual</th>
								<th>Selisih</th>
								<th>Total Transaksi</th>
								<th>Buka</th>
								<th>Tutup</th>
								<th>Status</th>
								<th>Catatan</th>
								<th>Aksi</th>
							</tr>
						</thead>
						<tbody>
						<?php if (mysqli_num_rows($shifts) > 0): ?>
							<?php while ($s = mysqli_fetch_assoc($shifts)): ?>
								<tr>
									<td class="text-center"><?= $s['shift_id']; ?></td>
									<td class="text-center"><?= date('d/m/Y', strtotime($s['tanggal'])); ?></td>
									<td><?= htmlspecialchars($s['username']); ?></td>
									<td class="text-right">Rp <?= number_format($s['opening_cash'], 0, ',', '.'); ?></td>
									<td class="text-right">
										<?php if ($s['expected_closing_cash']): ?>
											Rp <?= number_format($s['expected_closing_cash'], 0, ',', '.'); ?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td class="text-right">
										<?php if ($s['actual_closing_cash']): ?>
											Rp <?= number_format($s['actual_closing_cash'], 0, ',', '.'); ?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td class="text-right">
										<?php if ($s['variance'] !== null): ?>
											<?php
											$variance = floatval($s['variance']);
											$varianceClass = $variance > 0 ? 'variance-positive' : ($variance < 0 ? 'variance-negative' : 'variance-zero');
											?>
											<span class="<?= $varianceClass; ?>">
												<?= $variance >= 0 ? '+' : ''; ?>Rp <?= number_format($variance, 0, ',', '.'); ?>
											</span>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td class="text-center"><?= $s['total_transaksi']; ?></td>
									<td class="text-center"><?= date('d/m/Y H:i', strtotime($s['opened_at'])); ?></td>
									<td class="text-center">
										<?php if ($s['closed_at']): ?>
											<?= date('d/m/Y H:i', strtotime($s['closed_at'])); ?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td class="text-center">
										<span class="badge badge-<?= $s['status'] == 'open' ? 'success' : 'secondary'; ?>">
											<?= ucfirst($s['status']); ?>
										</span>
									</td>
									<td><small><?= htmlspecialchars($s['notes'] ?? ''); ?></small></td>
									<td class="text-center">
										<button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editModal<?= $s['shift_id']; ?>">
											<i class="fas fa-edit"></i> Edit
										</button>
										<?php if ($s['status'] == 'open'): ?>
											<button class="btn btn-sm btn-danger" data-toggle="modal" data-target="#tutupModal<?= $s['shift_id']; ?>">
												<i class="fas fa-door-closed"></i> Tutup
											</button>
										<?php endif; ?>
									</td>
								</tr>

								<!-- Modal Edit Shift -->
								<div class="modal fade" id="editModal<?= $s['shift_id']; ?>" tabindex="-1">
									<div class="modal-dialog modal-lg">
										<div class="modal-content">
											<form method="POST">
												<div class="modal-header bg-warning text-dark">
													<h5 class="modal-title">Edit Shift #<?= $s['shift_id']; ?></h5>
													<button type="button" class="close" data-dismiss="modal">&times;</button>
												</div>
												<div class="modal-body">
													<input type="hidden" name="shift_id" value="<?= $s['shift_id']; ?>">
													<div class="form-row">
														<div class="form-group col-md-6">
															<label>Tanggal</label>
															<input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($s['tanggal']); ?>" required>
														</div>
														<div class="form-group col-md-6">
															<label>Kasir</label>
															<select name="kasir_user_id" class="form-control" required>
																<option value="">-- Pilih Kasir --</option>
																<?php 
																mysqli_data_seek($users, 0);
																while ($u = mysqli_fetch_assoc($users)): ?>
																	<option value="<?= $u['user_id']; ?>" <?= $s['kasir_user_id'] == $u['user_id'] ? 'selected' : ''; ?>>
																		<?= htmlspecialchars($u['username']); ?>
																	</option>
																<?php endwhile; ?>
															</select>
														</div>
													</div>
													<div class="form-row">
														<div class="form-group col-md-6">
															<label>Kas Awal (Rp)</label>
															<input type="number" name="opening_cash" class="form-control" min="0" step="100" value="<?= number_format($s['opening_cash'], 0, '.', ''); ?>" required>
														</div>
														<?php if ($s['status'] == 'closed'): ?>
														<div class="form-group col-md-6">
															<label>Kas Aktual (Rp)</label>
															<input type="number" name="actual_closing_cash" class="form-control" min="0" step="100" value="<?= $s['actual_closing_cash'] ? number_format($s['actual_closing_cash'], 0, '.', '') : ''; ?>">
														</div>
														<?php endif; ?>
													</div>
													<div class="form-group">
														<label>Catatan</label>
														<textarea name="notes" class="form-control" rows="3" placeholder="Catatan shift..."><?= htmlspecialchars($s['notes'] ?? ''); ?></textarea>
													</div>
													<?php if ($s['status'] == 'closed'): ?>
													<div class="alert alert-info">
														<small>
															<strong>Info:</strong><br>
															Kas Harusnya: Rp <?= number_format($s['expected_closing_cash'] ?? 0, 0, ',', '.'); ?><br>
															Selisih: <span class="<?= floatval($s['variance'] ?? 0) > 0 ? 'text-success' : (floatval($s['variance'] ?? 0) < 0 ? 'text-danger' : 'text-primary'); ?>">
																<?= floatval($s['variance'] ?? 0) >= 0 ? '+' : ''; ?>Rp <?= number_format($s['variance'] ?? 0, 0, ',', '.'); ?>
															</span>
														</small>
													</div>
													<?php endif; ?>
												</div>
												<div class="modal-footer">
													<button type="submit" name="edit_shift" class="btn btn-warning">
														<i class="fas fa-save"></i> Simpan Perubahan
													</button>
													<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
												</div>
											</form>
										</div>
									</div>
								</div>

								<!-- Modal Tutup Shift -->
								<?php if ($s['status'] == 'open'): ?>
								<div class="modal fade" id="tutupModal<?= $s['shift_id']; ?>" tabindex="-1">
									<div class="modal-dialog">
										<div class="modal-content">
											<form method="POST">
												<div class="modal-header bg-warning text-dark">
													<h5 class="modal-title">Tutup Shift #<?= $s['shift_id']; ?></h5>
													<button type="button" class="close" data-dismiss="modal">&times;</button>
												</div>
												<div class="modal-body">
													<input type="hidden" name="shift_id" value="<?= $s['shift_id']; ?>">
													<div class="form-group">
														<label>Kas Awal</label>
														<input type="text" class="form-control" value="Rp <?= number_format($s['opening_cash'], 0, ',', '.'); ?>" readonly>
													</div>
													<div class="form-group">
														<label>Kas Aktual (Rp) <small class="text-muted">*wajib</small></label>
														<input type="number" name="actual_closing_cash" class="form-control" min="0" step="100" required>
													</div>
													<div class="form-group">
														<label>Catatan (opsional)</label>
														<textarea name="notes" class="form-control" rows="3" placeholder="Catatan penutupan shift..."></textarea>
													</div>
												</div>
												<div class="modal-footer">
													<button type="submit" name="tutup_shift" class="btn btn-warning">
														<i class="fas fa-door-closed"></i> Tutup Shift
													</button>
													<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
												</div>
											</form>
										</div>
									</div>
								</div>
								<?php endif; ?>
							<?php endwhile; ?>
						<?php else: ?>
							<tr><td colspan="13" class="text-center text-muted">Belum ada shift.</td></tr>
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
<script>
// Update Kas Awal otomatis saat tanggal berubah
$(document).ready(function() {
	$('#tanggal_shift').on('change', function() {
		var tanggal = $(this).val();
		if (tanggal) {
			// AJAX request untuk mendapatkan saldo akhir hari sebelumnya
			$.ajax({
				url: 'get_saldo_akhir.php',
				method: 'POST',
				data: { tanggal: tanggal },
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						$('#opening_cash').val(response.saldo_akhir);
					} else {
						$('#opening_cash').val(0);
					}
				},
				error: function() {
					// Fallback: hitung manual via JavaScript jika AJAX gagal
					// Untuk sekarang, biarkan user input manual
				}
			});
		}
	});
});
</script>
</body>
</html>

