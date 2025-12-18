<?php
include '../config.php';
include '../util_id.php';
include '../util_audit.php';
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
	$res = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
	return $res && $res->num_rows > 0;
}

// Fungsi recalcDaily dihapus karena tabel daily_summary sudah tidak digunakan
// function recalcDaily(mysqli $conn, string $tanggal) {
// 	...
// }

// =====================
// === TAMBAH PENGELUARAN ===
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengeluaran'])) {
    $transaksi_keluar_id = intval($_POST['transaksi_keluar_id']);
    $kat_pengeluaran_id = intval($_POST['kat_pengeluaran_id']);
    $tanggal_keluar = $_POST['tanggal_keluar'];
    $jumlah_pengeluaran = floatval($_POST['jumlah_pengeluaran']);
    $deskripsi = trim($_POST['deskripsi'] ?? '');

	// Validasi ID > 0
	if ($transaksi_keluar_id <= 0) {
		$error = "❌ ID transaksi harus lebih besar dari 0.";
	} elseif ($kat_pengeluaran_id <= 0) {
		$error = "❌ Kategori harus dipilih.";
	} elseif ($jumlah_pengeluaran <= 0) {
		$error = "❌ Jumlah pengeluaran harus lebih besar dari 0.";
	} else {
		// Cek apakah ID sudah ada
		$cekId = $conn->query("SELECT transaksi_keluar_id FROM transaksi_pengeluaran WHERE transaksi_keluar_id = '$transaksi_keluar_id'");
		if ($cekId && $cekId->num_rows > 0) {
			$error = "❌ ID transaksi $transaksi_keluar_id sudah digunakan. Gunakan ID lain.";
		} else {
			// Simpan sebagai DRAFT (menunggu approval)
			$deskripsiEsc = mysqli_real_escape_string($conn, $deskripsi);
			$tanggalEsc = mysqli_real_escape_string($conn, $tanggal_keluar);
			$query = "
				INSERT INTO transaksi_pengeluaran (transaksi_keluar_id, kat_pengeluaran_id, tanggal_keluar, jumlah_pengeluaran, deskripsi, user_id, status)
				VALUES ('$transaksi_keluar_id', '$kat_pengeluaran_id', '$tanggalEsc', '$jumlah_pengeluaran', '$deskripsiEsc', '$user_id', 'draft')
			";

			if ($conn->query($query)) {
				$success = "✅ Data pengeluaran disimpan sebagai DRAFT. Menunggu persetujuan pemilik.";
			} else {
				$error = "❌ Gagal menyimpan data: " . $conn->error;
			}
		}
	}
}

// =====================
// === EDIT PENGELUARAN ===
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pengeluaran'])) {
	$id = intval($_POST['transaksi_keluar_id']);
    $kat_pengeluaran_id = $_POST['kat_pengeluaran_id'];
    $tanggal_keluar = $_POST['tanggal_keluar'];
    $jumlah_pengeluaran = $_POST['jumlah_pengeluaran'];
    $deskripsi = $_POST['deskripsi'];

	$conn->query("UPDATE transaksi_pengeluaran SET kat_pengeluaran_id='$kat_pengeluaran_id', tanggal_keluar='$tanggal_keluar', jumlah_pengeluaran='$jumlah_pengeluaran', deskripsi='$deskripsi' WHERE transaksi_keluar_id='$id' AND user_id='$user_id' AND status!='approved'");
	$success = "✅ Data pengeluaran (draft) berhasil diperbarui. Data approved hanya bisa diubah oleh pemilik.";
}

        // =====================
// === UPLOAD BUKTI ===
        // =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {
	$ref_id = intval($_POST['ref_id']);
	
	// Validasi transaksi exists dan milik user
	$cek = $conn->query("SELECT transaksi_keluar_id FROM transaksi_pengeluaran WHERE transaksi_keluar_id = $ref_id AND user_id = $user_id");
	if (!$cek || $cek->num_rows === 0) {
		$error = "Transaksi tidak ditemukan atau bukan milik Anda.";
	} elseif (!isset($_FILES['bukti_file']) || $_FILES['bukti_file']['error'] !== UPLOAD_ERR_OK) {
		$error = "File tidak valid atau terjadi error saat upload.";
        } else {
		$file = $_FILES['bukti_file'];
		$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
		$maxSize = 5 * 1024 * 1024; // 5MB
		
		if (!in_array($file['type'], $allowedTypes)) {
			$error = "Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, atau PDF.";
		} elseif ($file['size'] > $maxSize) {
			$error = "Ukuran file terlalu besar. Maksimal 5MB.";
		} else {
			// Create upload directory if not exists
			$uploadDir = '../uploads/bukti/';
			if (!is_dir($uploadDir)) {
				mkdir($uploadDir, 0755, true);
			}
			
			// Generate unique filename
			$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
			$filename = 'pengeluaran_' . $ref_id . '_' . time() . '_' . uniqid() . '.' . $ext;
			$filepath = $uploadDir . $filename;
			
			if (move_uploaded_file($file['tmp_name'], $filepath)) {
				$fileId = generateNextId($conn, 'transaksi_file', 'file_id');
				$filenameEsc = mysqli_real_escape_string($conn, $filename);
				$filepathEsc = mysqli_real_escape_string($conn, $filepath);
				$mimeEsc = mysqli_real_escape_string($conn, $file['type']);
				$sizeBytes = $file['size'];
				
				// Check if status column exists
				$hasStatus = tableHasColumn($conn, 'transaksi_file', 'status');
				$statusSql = $hasStatus ? ", status" : "";
				$statusVal = $hasStatus ? ", 'pending'" : "";
				
				$sql = "INSERT INTO transaksi_file (file_id, tipe, ref_id, filename, filepath, mime, size_bytes, uploaded_by$statusSql)
						VALUES ($fileId, 'pengeluaran', $ref_id, '$filenameEsc', '$filepathEsc', '$mimeEsc', $sizeBytes, $user_id$statusVal)";
				
				if ($conn->query($sql)) {
					logAudit($conn, $user_id, 'create', 'transaksi_file', $fileId, null, [
						'tipe' => 'pengeluaran',
						'ref_id' => $ref_id,
						'filename' => $filename
					]);
					$success = "Bukti transaksi berhasil diupload.";
				} else {
					unlink($filepath); // Delete file if DB insert fails
					$error = "Gagal menyimpan data ke database: " . $conn->error;
				}
    } else {
				$error = "Gagal memindahkan file.";
			}
		}
	}
}

// =====================
// === HAPUS PENGELUARAN ===
// =====================
if (isset($_GET['hapus'])) {
	$id = intval($_GET['hapus']);
	$tglQ = $conn->query("SELECT tanggal_keluar FROM transaksi_pengeluaran WHERE transaksi_keluar_id='$id' AND user_id='$user_id'");
	$tgl = $tglQ && $tglQ->num_rows ? $tglQ->fetch_assoc()['tanggal_keluar'] : null;
	$conn->query("DELETE FROM cash_flow WHERE transaksi_keluar_id = '$id'");
	$conn->query("DELETE FROM transaksi_pengeluaran WHERE transaksi_keluar_id = '$id' AND user_id='$user_id'");
	// if ($tgl) { recalcDaily($conn, $tgl); } // Dihapus karena daily_summary sudah tidak digunakan
	header("Location: transaksi_pengeluaran.php");
	exit;
}

// =====================
// === TAMPIL DATA ===
// =====================
$kategori = $conn->query("SELECT * FROM kategori_pengeluaran ORDER BY kat_pengeluaran_id ASC");
$data_pengeluaran = $conn->query("
    SELECT tp.*, kp.nama_kategori,
           (SELECT COUNT(*) FROM transaksi_file WHERE tipe='pengeluaran' AND ref_id=tp.transaksi_keluar_id) AS jumlah_bukti
    FROM transaksi_pengeluaran tp
    JOIN kategori_pengeluaran kp ON tp.kat_pengeluaran_id = kp.kat_pengeluaran_id
    WHERE tp.user_id = '$user_id'
    ORDER BY tp.transaksi_keluar_id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Transaksi Pengeluaran - Dashboard Admin</title>

    <link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        .sidebar { background-color: #555 !important; }
        .sidebar .nav-link { color: #fff !important; }
        .sidebar .nav-link:hover { background-color: #444; }
        .card-header { background-color: #555; color: #fff; }
        .btn-light { background-color: #eee; color: #333; }
        .btn-light:hover { background-color: #ddd; }
    </style>
</head>
<body id="page-top">

<div id="wrapper">
    <!-- Sidebar -->
    <?php $currentPage = 'transaksi_pengeluaran'; include 'sidebar_admin.php'; ?>

    <!-- Content -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4"><i class="fas fa-arrow-up"></i> Input Transaksi Pengeluaran</h3>

            <!-- Form Input -->
            <div class="card shadow mb-4">
                <div class="card-header"><i class="fas fa-plus"></i> Form Tambah Pengeluaran</div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?= $success; ?></div>
                    <?php elseif (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error; ?></div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="transaksi_keluar_id" class="form-label">ID Transaksi <small class="text-muted">(wajib diisi manual)</small></label>
                            <input type="number" name="transaksi_keluar_id" id="transaksi_keluar_id" class="form-control" required min="1">
                        </div>

                        <div class="mb-3">
                            <label for="kat_pengeluaran_id" class="form-label">Kategori Pengeluaran</label>
                            <select name="kat_pengeluaran_id" id="kat_pengeluaran_id" class="form-control" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php while ($row = $kategori->fetch_assoc()): ?>
                                    <option value="<?= $row['kat_pengeluaran_id']; ?>">
                                        <?= htmlspecialchars($row['nama_kategori']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tanggal_keluar" class="form-label">Tanggal</label>
                            <input type="date" name="tanggal_keluar" id="tanggal_keluar" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="jumlah_pengeluaran" class="form-label">Jumlah Pengeluaran (Rp)</label>
                            <input type="number" name="jumlah_pengeluaran" id="jumlah_pengeluaran" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3"></textarea>
                        </div>

                        <button type="submit" name="tambah_pengeluaran" class="btn btn-danger">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="card shadow">
                <div class="card-header"><i class="fas fa-list"></i> Data Transaksi Pengeluaran</div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr class="text-center">
                                <th>No</th>
                                <th>Kategori</th>
                                <th>Tanggal</th>
                                <th>Jumlah (Rp)</th>
                                <th>Deskripsi</th>
                                <th>Status</th>
								<th>Bukti</th>
								<th width="20%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
							<?php if ($data_pengeluaran->num_rows > 0): $no = 1; $hasStatus = tableHasColumn($conn, 'transaksi_pengeluaran', 'status'); ?>
                                <?php while ($row = $data_pengeluaran->fetch_assoc()): $st = $hasStatus && isset($row['status']) ? $row['status'] : 'draft'; ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($row['nama_kategori']); ?></td>
                                        <td><?= htmlspecialchars($row['tanggal_keluar']); ?></td>
                                        <td class="text-right"><?= number_format($row['jumlah_pengeluaran'], 0, ',', '.'); ?></td>
                                        <td><?= htmlspecialchars($row['deskripsi']); ?></td>
                                        <td class="text-center"><span class="badge <?= $st=='approved'?'badge-success':($st=='rejected'?'badge-danger':'badge-secondary'); ?>"><?= htmlspecialchars($st); ?></span></td>
										<td class="text-center">
											<?php if ($row['jumlah_bukti'] > 0): ?>
												<span class="badge badge-info"><?= $row['jumlah_bukti']; ?> file</span>
											<?php else: ?>
												<span class="text-muted">-</span>
											<?php endif; ?>
										</td>
										<td class="text-center">
											<button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#edit<?= $row['transaksi_keluar_id']; ?>"><i class="fas fa-edit"></i></button>
											<button class="btn btn-info btn-sm" data-toggle="modal" data-target="#upload<?= $row['transaksi_keluar_id']; ?>"><i class="fas fa-upload"></i></button>
											<a href="transaksi_pengeluaran.php?hapus=<?= $row['transaksi_keluar_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus transaksi ini?')"><i class="fas fa-trash"></i></a>
										</td>
                                    </tr>

									<div class="modal fade" id="edit<?= $row['transaksi_keluar_id']; ?>" tabindex="-1" role="dialog">
										<div class="modal-dialog" role="document">
											<div class="modal-content">
												<form method="POST">
													<div class="modal-header bg-secondary text-white">
														<h5 class="modal-title">Edit Pengeluaran</h5>
														<button type="button" class="close" data-dismiss="modal">&times;</button>
													</div>
													<div class="modal-body">
														<input type="hidden" name="transaksi_keluar_id" value="<?= $row['transaksi_keluar_id']; ?>">
														<div class="form-group">
															<label>Kategori</label>
															<select name="kat_pengeluaran_id" class="form-control" required>
																<?php
																$kk2 = $conn->query("SELECT * FROM kategori_pengeluaran ORDER BY kat_pengeluaran_id ASC");
																while ($k = $kk2->fetch_assoc()): ?>
																	<option value="<?= $k['kat_pengeluaran_id']; ?>" <?= $k['kat_pengeluaran_id']==$row['kat_pengeluaran_id']?'selected':''; ?>>
																		<?= htmlspecialchars($k['nama_kategori']); ?>
																	</option>
																<?php endwhile; ?>
															</select>
														</div>
														<div class="form-group">
															<label>Tanggal</label>
															<input type="date" name="tanggal_keluar" class="form-control" value="<?= $row['tanggal_keluar']; ?>" required>
														</div>
														<div class="form-group">
															<label>Jumlah (Rp)</label>
															<input type="number" name="jumlah_pengeluaran" class="form-control" value="<?= $row['jumlah_pengeluaran']; ?>" required>
														</div>
														<div class="form-group">
															<label>Deskripsi</label>
															<input type="text" name="deskripsi" class="form-control" value="<?= htmlspecialchars($row['deskripsi']); ?>">
														</div>
													</div>
													<div class="modal-footer">
														<button type="submit" name="edit_pengeluaran" class="btn btn-warning"><i class="fas fa-save"></i> Simpan</button>
														<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
													</div>
												</form>
											</div>
										</div>
									</div>

									<!-- Modal Upload Bukti -->
									<div class="modal fade" id="upload<?= $row['transaksi_keluar_id']; ?>" tabindex="-1" role="dialog">
										<div class="modal-dialog" role="document">
											<div class="modal-content">
												<form method="POST" enctype="multipart/form-data">
													<div class="modal-header bg-info text-white">
														<h5 class="modal-title">Upload Bukti Transaksi #<?= $row['transaksi_keluar_id']; ?></h5>
														<button type="button" class="close" data-dismiss="modal">&times;</button>
													</div>
													<div class="modal-body">
														<input type="hidden" name="ref_id" value="<?= $row['transaksi_keluar_id']; ?>">
														<div class="form-group">
															<label>File Bukti (JPG/PNG/PDF, max 5MB)</label>
															<input type="file" name="bukti_file" class="form-control-file" accept="image/*,application/pdf" required>
															<small class="form-text text-muted">Upload nota/foto bukti transaksi ini.</small>
														</div>
													</div>
													<div class="modal-footer">
														<button type="submit" name="upload_bukti" class="btn btn-info"><i class="fas fa-upload"></i> Upload</button>
														<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
													</div>
												</form>
											</div>
										</div>
									</div>
                                <?php endwhile; ?>
                            <?php else: ?>
								<tr><td colspan="8" class="text-center">Belum ada data pengeluaran.</td></tr>
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