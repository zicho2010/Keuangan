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
$error = isset($_GET['error']) ? $_GET['error'] : '';

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
	$res = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
	return $res && $res->num_rows > 0;
}

// Approve bukti
if (isset($_GET['approve'])) {
	$fileId = intval($_GET['approve']);
	$userId = intval($_SESSION['user_id']);
	
	$hasStatus = tableHasColumn($conn, 'transaksi_file', 'status');
	if ($hasStatus) {
		$file = mysqli_query($conn, "SELECT * FROM transaksi_file WHERE file_id = $fileId");
		if ($file && mysqli_num_rows($file) > 0) {
			$fileData = mysqli_fetch_assoc($file);
			mysqli_query($conn, "UPDATE transaksi_file SET status = 'approved' WHERE file_id = $fileId");
			logAudit($conn, $userId, 'approve', 'transaksi_file', $fileId, [
				'status' => $fileData['status'] ?? 'pending'
			], [
				'status' => 'approved'
			]);
			$success = "Bukti transaksi berhasil disetujui.";
		} else {
			$error = "Bukti tidak ditemukan.";
		}
	} else {
		$error = "Fitur approval belum tersedia. Jalankan migration untuk menambahkan kolom status.";
	}
}

// Reject bukti
if (isset($_GET['reject'])) {
	$fileId = intval($_GET['reject']);
	$userId = intval($_SESSION['user_id']);
	
	$hasStatus = tableHasColumn($conn, 'transaksi_file', 'status');
	if ($hasStatus) {
		$file = mysqli_query($conn, "SELECT * FROM transaksi_file WHERE file_id = $fileId");
		if ($file && mysqli_num_rows($file) > 0) {
			$fileData = mysqli_fetch_assoc($file);
			mysqli_query($conn, "UPDATE transaksi_file SET status = 'rejected' WHERE file_id = $fileId");
			logAudit($conn, $userId, 'reject', 'transaksi_file', $fileId, [
				'status' => $fileData['status'] ?? 'pending'
			], [
				'status' => 'rejected'
			]);
			$success = "Bukti transaksi ditolak.";
		} else {
			$error = "Bukti tidak ditemukan.";
		}
	} else {
		$error = "Fitur approval belum tersedia. Jalankan migration untuk menambahkan kolom status.";
	}
}

// Hapus bukti
if (isset($_GET['hapus'])) {
	$fileId = intval($_GET['hapus']);
	$userId = intval($_SESSION['user_id']);
	
	$file = mysqli_query($conn, "SELECT * FROM transaksi_file WHERE file_id = $fileId");
	if ($file && mysqli_num_rows($file) > 0) {
		$fileData = mysqli_fetch_assoc($file);
		$dbPath = $fileData['filepath'];
		
		// Normalize path - remove leading ../ if exists
		if (strpos($dbPath, '../') === 0) {
			$dbPath = substr($dbPath, 3);
		}
		$filepath = '../' . $dbPath;
		
		// Delete file from disk
		if (file_exists($filepath)) {
			unlink($filepath);
		} else {
			// Try alternative path
			$altPath = '../uploads/bukti/' . basename($dbPath);
			if (file_exists($altPath)) {
				unlink($altPath);
			}
		}
		
		// Delete from database
		mysqli_query($conn, "DELETE FROM transaksi_file WHERE file_id = $fileId");
		logAudit($conn, $userId, 'delete', 'transaksi_file', $fileId, [
			'filename' => $fileData['filename'],
			'tipe' => $fileData['tipe'],
			'ref_id' => $fileData['ref_id']
		], null);
		
		$success = "Bukti transaksi berhasil dihapus.";
	}
}

// Filter status
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$hasStatus = tableHasColumn($conn, 'transaksi_file', 'status');

// Get all files
$statusFilter = '';
if ($hasStatus && $filterStatus != 'all') {
	$statusFilter = " AND tf.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'";
}

$files = mysqli_query($conn, "
	SELECT tf.*, u.username,
		   CASE 
			   WHEN tf.tipe = 'pemasukan' THEN tp.jumlah_pemasukan
			   ELSE tk.jumlah_pengeluaran
		   END AS jumlah,
		   CASE 
			   WHEN tf.tipe = 'pemasukan' THEN tp.tanggal_masuk
			   ELSE tk.tanggal_keluar
		   END AS tanggal
	FROM transaksi_file tf
	LEFT JOIN users u ON tf.uploaded_by = u.user_id
	LEFT JOIN transaksi_pemasukan tp ON tf.tipe = 'pemasukan' AND tf.ref_id = tp.transaksi_masuk_id
	LEFT JOIN transaksi_pengeluaran tk ON tf.tipe = 'pengeluaran' AND tf.ref_id = tk.transaksi_keluar_id
	WHERE 1=1 $statusFilter
	ORDER BY tf.file_id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Review & Approve Bukti Transaksi</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background: #555 !important; }
		.sidebar .nav-link { color: #fff !important; }
		.sidebar .nav-link:hover { background: #444; }
		.file-preview { max-width: 200px; max-height: 200px; }
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
	<?php $currentPage = 'upload_bukti'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-check-circle"></i> Review & Approve Bukti Transaksi</h3>

			<?php if ($success): ?>
				<div class="alert alert-success"><?= $success; ?></div>
			<?php endif; ?>
			<?php if ($error): ?>
				<div class="alert alert-danger"><?= $error; ?></div>
			<?php endif; ?>

			<!-- Filter -->
			<div class="card mb-4">
				<div class="card-header bg-info text-white">
					<i class="fas fa-filter"></i> Filter
				</div>
				<div class="card-body">
					<form method="GET" class="form-inline">
						<div class="form-group mr-3">
							<label class="mr-2">Status:</label>
							<select name="status" class="form-control" onchange="this.form.submit()">
								<option value="all" <?= $filterStatus == 'all' ? 'selected' : ''; ?>>Semua</option>
								<?php if ($hasStatus): ?>
									<option value="pending" <?= $filterStatus == 'pending' ? 'selected' : ''; ?>>Pending</option>
									<option value="approved" <?= $filterStatus == 'approved' ? 'selected' : ''; ?>>Approved</option>
									<option value="rejected" <?= $filterStatus == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
								<?php endif; ?>
							</select>
						</div>
					</form>
				</div>
			</div>

			<!-- Daftar File -->
			<div class="card">
				<div class="card-header bg-secondary text-white">
					<i class="fas fa-list"></i> Daftar Bukti Transaksi
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th>ID</th>
								<th>Tipe</th>
								<th>ID Transaksi</th>
								<th>Jumlah</th>
								<th>Tanggal</th>
								<th>File</th>
								<th>Ukuran</th>
								<th>Uploaded By</th>
								<th>Uploaded At</th>
								<?php if ($hasStatus): ?>
									<th>Status</th>
								<?php endif; ?>
								<th width="200">Aksi</th>
							</tr>
						</thead>
						<tbody>
						<?php if (mysqli_num_rows($files) > 0): ?>
							<?php while ($f = mysqli_fetch_assoc($files)): 
								$fileStatus = $hasStatus && isset($f['status']) ? $f['status'] : null;
							?>
								<tr>
									<td class="text-center"><?= $f['file_id']; ?></td>
									<td>
										<span class="badge badge-<?= $f['tipe'] == 'pemasukan' ? 'success' : 'danger'; ?>">
											<?= ucfirst($f['tipe']); ?>
										</span>
									</td>
									<td class="text-center"><?= $f['ref_id']; ?></td>
									<td class="text-right">Rp <?= number_format($f['jumlah'], 0, ',', '.'); ?></td>
									<td class="text-center"><?= $f['tanggal']; ?></td>
									<td>
										<?php 
										$previewPath = $f['filepath'];
										// Normalize path for preview
										if (strpos($previewPath, '../') === 0) {
											$previewPath = substr($previewPath, 3);
										}
										$previewPath = '../' . $previewPath;
										?>
										<?php if (strpos($f['mime'], 'image/') === 0): ?>
											<img src="<?= htmlspecialchars($previewPath); ?>" class="file-preview img-thumbnail" alt="Preview" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EGambar tidak ditemukan%3C/text%3E%3C/svg%3E';">
										<?php else: ?>
											<i class="fas fa-file-pdf fa-2x text-danger"></i>
										<?php endif; ?>
										<br><small><?= htmlspecialchars($f['filename']); ?></small>
									</td>
									<td class="text-center"><?= number_format($f['size_bytes'] / 1024, 2); ?> KB</td>
									<td><?= htmlspecialchars($f['username']); ?></td>
									<td><?= date('d/m/Y H:i', strtotime($f['uploaded_at'])); ?></td>
									<?php if ($hasStatus): ?>
										<td class="text-center">
											<?php if ($fileStatus == 'pending'): ?>
												<span class="badge badge-warning">Pending</span>
											<?php elseif ($fileStatus == 'approved'): ?>
												<span class="badge badge-success">Approved</span>
											<?php elseif ($fileStatus == 'rejected'): ?>
												<span class="badge badge-danger">Rejected</span>
											<?php else: ?>
												<span class="badge badge-secondary">-</span>
											<?php endif; ?>
										</td>
									<?php endif; ?>
									<td class="text-center">
										<div class="btn-action-group">
											<a href="lihat_bukti.php?id=<?= $f['file_id']; ?>" class="btn btn-sm btn-info" title="Lihat Detail">
												<i class="fas fa-eye"></i> <span class="d-none d-md-inline">Lihat</span>
											</a>
											<?php if ($hasStatus && $fileStatus == 'pending'): ?>
												<a href="?approve=<?= $f['file_id']; ?>&status=<?= $filterStatus; ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve bukti ini?')" title="Approve">
													<i class="fas fa-check"></i> <span class="d-none d-md-inline">Approve</span>
												</a>
												<a href="?reject=<?= $f['file_id']; ?>&status=<?= $filterStatus; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Tolak bukti ini?')" title="Reject">
													<i class="fas fa-times"></i> <span class="d-none d-md-inline">Reject</span>
												</a>
											<?php endif; ?>
											<a href="?hapus=<?= $f['file_id']; ?>&status=<?= $filterStatus; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus bukti ini?')" title="Hapus">
												<i class="fas fa-trash"></i> <span class="d-none d-md-inline">Hapus</span>
											</a>
										</div>
									</td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?>
							<tr><td colspan="<?= $hasStatus ? '11' : '10'; ?>" class="text-center text-muted">Belum ada bukti transaksi.</td></tr>
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
