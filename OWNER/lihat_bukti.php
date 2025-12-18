<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
	header("Location: ../login.php");
	exit;
}

include '../config.php';

$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($fileId <= 0) {
	header("Location: upload_bukti.php");
	exit;
}

// Get file data
$file = mysqli_query($conn, "
	SELECT tf.*, u.username,
		   CASE 
			   WHEN tf.tipe = 'pemasukan' THEN tp.jumlah_pemasukan
			   ELSE tk.jumlah_pengeluaran
		   END AS jumlah,
		   CASE 
			   WHEN tf.tipe = 'pemasukan' THEN tp.tanggal_masuk
			   ELSE tk.tanggal_keluar
		   END AS tanggal,
		   CASE 
			   WHEN tf.tipe = 'pemasukan' THEN tp.deskripsi
			   ELSE tk.deskripsi
		   END AS deskripsi,
		   CASE 
			   WHEN tf.tipe = 'pemasukan' THEN kp.nama_kategori
			   ELSE kp2.nama_kategori
		   END AS kategori
	FROM transaksi_file tf
	LEFT JOIN users u ON tf.uploaded_by = u.user_id
	LEFT JOIN transaksi_pemasukan tp ON tf.tipe = 'pemasukan' AND tf.ref_id = tp.transaksi_masuk_id
	LEFT JOIN transaksi_pengeluaran tk ON tf.tipe = 'pengeluaran' AND tf.ref_id = tk.transaksi_keluar_id
	LEFT JOIN kategori_pemasukan kp ON tf.tipe = 'pemasukan' AND tp.kat_pemasukan_id = kp.kat_pemasukan_id
	LEFT JOIN kategori_pengeluaran kp2 ON tf.tipe = 'pengeluaran' AND tk.kat_pengeluaran_id = kp2.kat_pengeluaran_id
	WHERE tf.file_id = $fileId
");

if (!$file || mysqli_num_rows($file) == 0) {
	header("Location: upload_bukti.php?error=File tidak ditemukan");
	exit;
}

$fileData = mysqli_fetch_assoc($file);

// Normalize filepath - remove leading ../ if exists, then add relative path from OWNER folder
$dbPath = $fileData['filepath'];
// If path starts with ../, remove it (it's already relative from root)
if (strpos($dbPath, '../') === 0) {
	$dbPath = substr($dbPath, 3); // Remove '../'
}
// Path should be relative from root: uploads/bukti/filename
// From OWNER folder, we need to go up one level: ../uploads/bukti/filename
$filepath = '../' . $dbPath;

// Check if file exists
if (!file_exists($filepath)) {
	// Try alternative paths
	$altPaths = [
		'../uploads/bukti/' . basename($dbPath),
		'uploads/bukti/' . basename($dbPath),
		$fileData['filepath'], // Original path from DB
	];
	
	$found = false;
	foreach ($altPaths as $altPath) {
		if (file_exists($altPath)) {
			$filepath = $altPath;
			$found = true;
			break;
		}
	}
	
	if (!$found) {
		// Debug info
		$debugInfo = "DB Path: " . htmlspecialchars($fileData['filepath']) . " | Normalized: " . htmlspecialchars($filepath);
		header("Location: upload_bukti.php?error=File tidak ditemukan di server. " . $debugInfo);
		exit;
	}
}

$isImage = strpos($fileData['mime'], 'image/') === 0;
$isPDF = $fileData['mime'] === 'application/pdf';

// URL path for browser (relative from OWNER folder)
$urlPath = $filepath;
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Lihat Bukti Transaksi</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		body {
			background-color: #f8f9fa;
		}
		.file-container {
			background: white;
			border-radius: 8px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			padding: 20px;
			margin-bottom: 20px;
		}
		.file-preview {
			max-width: 100%;
			height: auto;
			border: 1px solid #ddd;
			border-radius: 4px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		.pdf-viewer {
			width: 100%;
			height: 80vh;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.info-card {
			background: #f8f9fa;
			border-left: 4px solid #5e72e4;
			padding: 15px;
			margin-bottom: 15px;
		}
		.info-row {
			display: flex;
			justify-content: space-between;
			padding: 8px 0;
			border-bottom: 1px solid #e9ecef;
		}
		.info-row:last-child {
			border-bottom: none;
		}
		.info-label {
			font-weight: 600;
			color: #495057;
		}
		.info-value {
			color: #212529;
		}
		.badge-custom {
			padding: 6px 12px;
			font-size: 0.875rem;
		}
	</style>
</head>
<body>
<div class="container-fluid py-4">
	<div class="row">
		<div class="col-lg-8">
			<div class="file-container">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h4 class="mb-0"><i class="fas fa-file-alt"></i> Preview Bukti</h4>
					<a href="upload_bukti.php" class="btn btn-secondary">
						<i class="fas fa-arrow-left"></i> Kembali
					</a>
				</div>
				
				<?php if ($isImage): ?>
					<div class="text-center">
						<img src="<?= htmlspecialchars($urlPath); ?>" alt="Bukti Transaksi" class="file-preview img-fluid" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EGambar tidak ditemukan%3C/text%3E%3C/svg%3E';">
					</div>
					<div class="text-center mt-3">
						<a href="<?= htmlspecialchars($urlPath); ?>" target="_blank" class="btn btn-primary">
							<i class="fas fa-external-link-alt"></i> Buka di Tab Baru
						</a>
						<a href="<?= htmlspecialchars($urlPath); ?>" download class="btn btn-success">
							<i class="fas fa-download"></i> Download
						</a>
					</div>
				<?php elseif ($isPDF): ?>
					<iframe src="<?= htmlspecialchars($urlPath); ?>#toolbar=1" class="pdf-viewer"></iframe>
					<div class="text-center mt-3">
						<a href="<?= htmlspecialchars($urlPath); ?>" target="_blank" class="btn btn-primary">
							<i class="fas fa-external-link-alt"></i> Buka di Tab Baru
						</a>
						<a href="<?= htmlspecialchars($urlPath); ?>" download class="btn btn-success">
							<i class="fas fa-download"></i> Download
						</a>
					</div>
				<?php else: ?>
					<div class="text-center py-5">
						<i class="fas fa-file fa-5x text-muted mb-3"></i>
						<p class="text-muted">Preview tidak tersedia untuk tipe file ini.</p>
						<a href="<?= htmlspecialchars($urlPath); ?>" target="_blank" class="btn btn-primary">
							<i class="fas fa-external-link-alt"></i> Buka File
						</a>
						<a href="<?= htmlspecialchars($urlPath); ?>" download class="btn btn-success">
							<i class="fas fa-download"></i> Download
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		
		<div class="col-lg-4">
			<div class="file-container">
				<h5 class="mb-3"><i class="fas fa-info-circle"></i> Informasi File</h5>
				<div class="info-card">
					<div class="info-row">
						<span class="info-label">File ID:</span>
						<span class="info-value">#<?= $fileData['file_id']; ?></span>
					</div>
					<div class="info-row">
						<span class="info-label">Nama File:</span>
						<span class="info-value"><small><?= htmlspecialchars($fileData['filename']); ?></small></span>
					</div>
					<div class="info-row">
						<span class="info-label">Tipe File:</span>
						<span class="info-value"><?= htmlspecialchars($fileData['mime']); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label">Ukuran:</span>
						<span class="info-value"><?= number_format($fileData['size_bytes'] / 1024, 2); ?> KB</span>
					</div>
					<div class="info-row">
						<span class="info-label">Uploaded By:</span>
						<span class="info-value"><?= htmlspecialchars($fileData['username']); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label">Uploaded At:</span>
						<span class="info-value"><?= date('d/m/Y H:i:s', strtotime($fileData['uploaded_at'])); ?></span>
					</div>
					<?php if (isset($fileData['status'])): ?>
						<div class="info-row">
							<span class="info-label">Status:</span>
							<span class="info-value">
								<?php 
								$status = $fileData['status'];
								$badgeClass = $status == 'approved' ? 'badge-success' : ($status == 'rejected' ? 'badge-danger' : 'badge-warning');
								?>
								<span class="badge <?= $badgeClass; ?> badge-custom"><?= ucfirst($status); ?></span>
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>
			
			<div class="file-container">
				<h5 class="mb-3"><i class="fas fa-receipt"></i> Informasi Transaksi</h5>
				<div class="info-card">
					<div class="info-row">
						<span class="info-label">Tipe:</span>
						<span class="info-value">
							<span class="badge badge-<?= $fileData['tipe'] == 'pemasukan' ? 'success' : 'danger'; ?> badge-custom">
								<?= ucfirst($fileData['tipe']); ?>
							</span>
						</span>
					</div>
					<div class="info-row">
						<span class="info-label">ID Transaksi:</span>
						<span class="info-value">#<?= $fileData['ref_id']; ?></span>
					</div>
					<div class="info-row">
						<span class="info-label">Kategori:</span>
						<span class="info-value"><?= htmlspecialchars($fileData['kategori'] ?? '-'); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label">Jumlah:</span>
						<span class="info-value"><strong>Rp <?= number_format($fileData['jumlah'], 0, ',', '.'); ?></strong></span>
					</div>
					<div class="info-row">
						<span class="info-label">Tanggal:</span>
						<span class="info-value"><?= date('d/m/Y', strtotime($fileData['tanggal'])); ?></span>
					</div>
					<?php if (!empty($fileData['deskripsi'])): ?>
						<div class="info-row">
							<span class="info-label">Deskripsi:</span>
							<span class="info-value"><small><?= htmlspecialchars($fileData['deskripsi']); ?></small></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="../startbootstrap-sb-admin-2-master/vendor/jquery/jquery.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

