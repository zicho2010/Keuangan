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

// Tambah kategori
if (isset($_POST['tambah'])) {
	$id = intval($_POST['kat_pemasukan_id']);
	$nama = trim($_POST['nama_kategori']);
	$userId = intval($_SESSION['user_id']);
	
	if ($id > 0 && $nama !== '') {
		// Cek apakah ID sudah ada
		$cekId = mysqli_query($conn, "SELECT kat_pemasukan_id FROM kategori_pemasukan WHERE kat_pemasukan_id = $id");
		if ($cekId && mysqli_num_rows($cekId) > 0) {
			$error = "ID kategori $id sudah digunakan. Gunakan ID lain.";
		} else {
			$namaEsc = mysqli_real_escape_string($conn, $nama);
			$sql = "INSERT INTO kategori_pemasukan (kat_pemasukan_id, nama_kategori) VALUES ($id, '$namaEsc')";
			
			if (mysqli_query($conn, $sql)) {
				logAudit($conn, $userId, 'create', 'kategori_pemasukan', $id, null, [
					'nama_kategori' => $nama
				]);
				$success = "Kategori berhasil ditambahkan.";
			} else {
				$error = "Gagal menambahkan kategori: " . mysqli_error($conn);
			}
		}
	} else {
		$error = "ID dan nama kategori harus diisi.";
	}
}

// Edit kategori
if (isset($_POST['edit'])) {
	$id = intval($_POST['kat_pemasukan_id']);
	$nama = trim($_POST['nama_kategori']);
	$userId = intval($_SESSION['user_id']);
	
	if ($id > 0 && $nama !== '') {
		// Ambil data lama untuk audit
		$oldData = mysqli_query($conn, "SELECT * FROM kategori_pemasukan WHERE kat_pemasukan_id = $id");
		$oldRow = $oldData ? mysqli_fetch_assoc($oldData) : null;
		
		$namaEsc = mysqli_real_escape_string($conn, $nama);
		$sql = "UPDATE kategori_pemasukan SET nama_kategori = '$namaEsc' WHERE kat_pemasukan_id = $id";
		
		if (mysqli_query($conn, $sql)) {
			logAudit($conn, $userId, 'update', 'kategori_pemasukan', $id, 
				$oldRow ? ['nama_kategori' => $oldRow['nama_kategori']] : null,
				['nama_kategori' => $nama]
			);
			$success = "Kategori berhasil diupdate.";
		} else {
			$error = "Gagal mengupdate kategori: " . mysqli_error($conn);
		}
	} else {
		$error = "ID dan nama kategori harus diisi.";
	}
}

// Hapus kategori
if (isset($_GET['hapus'])) {
	$id = intval($_GET['hapus']);
	$userId = intval($_SESSION['user_id']);
	
	// Cek apakah kategori digunakan di transaksi
	$cekUse = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi_pemasukan WHERE kat_pemasukan_id = $id");
	$useData = mysqli_fetch_assoc($cekUse);
	
	if ($useData['total'] > 0) {
		$error = "Kategori tidak dapat dihapus karena masih digunakan di " . $useData['total'] . " transaksi.";
	} else {
		// Ambil data untuk audit
		$oldData = mysqli_query($conn, "SELECT * FROM kategori_pemasukan WHERE kat_pemasukan_id = $id");
		$oldRow = $oldData ? mysqli_fetch_assoc($oldData) : null;
		
		$sql = "DELETE FROM kategori_pemasukan WHERE kat_pemasukan_id = $id";
		
		if (mysqli_query($conn, $sql)) {
			logAudit($conn, $userId, 'delete', 'kategori_pemasukan', $id, 
				$oldRow ? ['nama_kategori' => $oldRow['nama_kategori']] : null,
				null
			);
			$success = "Kategori berhasil dihapus.";
		} else {
			$error = "Gagal menghapus kategori: " . mysqli_error($conn);
		}
	}
}

$data = mysqli_query($conn, "SELECT * FROM kategori_pemasukan ORDER BY kat_pemasukan_id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Kategori Pemasukan</title>
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
	<?php $currentPage = 'kategori_pemasukan'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-list"></i> Kategori Pemasukan</h3>

			<?php if ($success): ?>
				<div class="alert alert-success alert-dismissible fade show" role="alert">
					<?= $success; ?>
					<button type="button" class="close" data-dismiss="alert">&times;</button>
				</div>
			<?php endif; ?>
			<?php if ($error): ?>
				<div class="alert alert-danger alert-dismissible fade show" role="alert">
					<?= $error; ?>
					<button type="button" class="close" data-dismiss="alert">&times;</button>
				</div>
			<?php endif; ?>

			<div class="card mb-4">
				<div class="card-header bg-success text-white">
					<i class="fas fa-plus"></i> Tambah Kategori
				</div>
				<div class="card-body">
					<form method="POST">
						<div class="form-row">
							<div class="form-group col-md-3">
								<label>ID Kategori <small class="text-muted">(wajib)</small></label>
								<input type="number" name="kat_pemasukan_id" class="form-control" min="1" required>
							</div>
							<div class="form-group col-md-7">
								<label>Nama Kategori</label>
								<input type="text" name="nama_kategori" class="form-control" placeholder="Nama kategori..." required>
							</div>
							<div class="form-group col-md-2">
								<label>&nbsp;</label>
								<button type="submit" name="tambah" class="btn btn-success btn-block"><i class="fas fa-save"></i> Simpan</button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<div class="card">
				<div class="card-header bg-primary text-white">
					<i class="fas fa-table"></i> Daftar Kategori
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th width="10%">ID</th>
								<th>Nama Kategori</th>
								<th width="25%">Aksi</th>
							</tr>
						</thead>
						<tbody>
						<?php if (mysqli_num_rows($data) > 0): ?>
							<?php while ($r = mysqli_fetch_assoc($data)): ?>
								<tr>
									<td class="text-center"><?= $r['kat_pemasukan_id']; ?></td>
									<td><?= htmlspecialchars($r['nama_kategori']); ?></td>
									<td class="text-center">
										<button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#edit<?= $r['kat_pemasukan_id']; ?>">
											<i class="fas fa-edit"></i> Edit
										</button>
										<a href="?hapus=<?= $r['kat_pemasukan_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kategori ini?')">
											<i class="fas fa-trash"></i> Hapus
										</a>
									</td>
								</tr>
								<div class="modal fade" id="edit<?= $r['kat_pemasukan_id']; ?>" tabindex="-1" role="dialog">
									<div class="modal-dialog" role="document">
										<div class="modal-content">
											<form method="POST">
												<div class="modal-header bg-warning text-dark">
													<h5 class="modal-title">Edit Kategori</h5>
													<button type="button" class="close" data-dismiss="modal">&times;</button>
												</div>
												<div class="modal-body">
													<input type="hidden" name="kat_pemasukan_id" value="<?= $r['kat_pemasukan_id']; ?>">
													<div class="form-group">
														<label>Nama Kategori</label>
														<input type="text" name="nama_kategori" class="form-control" value="<?= htmlspecialchars($r['nama_kategori']); ?>" required>
													</div>
												</div>
												<div class="modal-footer">
													<button type="submit" name="edit" class="btn btn-warning"><i class="fas fa-save"></i> Simpan</button>
													<button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
												</div>
											</form>
										</div>
									</div>
								</div>
							<?php endwhile; ?>
						<?php else: ?>
							<tr><td colspan="3" class="text-center text-muted">Belum ada kategori.</td></tr>
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
