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

// Fungsi validasi password
function validatePassword($password) {
	$errors = [];
//strlen untuk menghitung jumlah karakter yang ada dalam string
//preg_macth untuk cek sudah sesuai apa belum

	if (strlen($password) < 6) {
		$errors[] = "Password minimal 6 karakter";
	}
	if (!preg_match('/[A-Z]/', $password)) {
		$errors[] = "Password harus mengandung huruf besar (A-Z)";
	}
	if (!preg_match('/[a-z]/', $password)) {
		$errors[] = "Password harus mengandung huruf kecil (a-z)";
	}
	if (!preg_match('/[0-9]/', $password)) {
		$errors[] = "Password harus mengandung angka (0-9)";
	}
	if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
		$errors[] = "Password harus mengandung tanda baca (!@#$%^&*()_+-=[]{}|;':\",./<>?)";
	}
	
	return $errors;
}

// Tambah user
if (isset($_POST['tambah'])) {
	$user_id = intval($_POST['user_id']);
	$username = trim($_POST['username']);
	$password = $_POST['password'];
	$role = $_POST['role'];
	$userId = intval($_SESSION['user_id']);
	
	if ($user_id > 0 && $username !== '' && $password !== '') {
		// Validasi password
		$passwordErrors = validatePassword($password);
		if (!empty($passwordErrors)) {
			$error = "Password tidak valid:<br>• " . implode("<br>• ", $passwordErrors);
		} else {
			// Cek apakah ID sudah ada
			$cekId = mysqli_query($conn, "SELECT user_id FROM users WHERE user_id = $user_id");
			if ($cekId && mysqli_num_rows($cekId) > 0) {
				$error = "ID user $user_id sudah digunakan. Gunakan ID lain.";
			} else {
				// Cek apakah username sudah ada
				$cekUsername = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'");
				if ($cekUsername && mysqli_num_rows($cekUsername) > 0) {
					$error = "Username '$username' sudah digunakan. Gunakan username lain.";
				} else {
					$usernameEsc = mysqli_real_escape_string($conn, $username);
					$passwordHash = md5($password); // MD5 sesuai dengan sistem
					$roleEsc = mysqli_real_escape_string($conn, $role);
					
					$sql = "INSERT INTO users (user_id, username, password, role) VALUES ($user_id, '$usernameEsc', '$passwordHash', '$roleEsc')";
					
					if (mysqli_query($conn, $sql)) {
						logAudit($conn, $userId, 'create', 'users', $user_id, null, [
							'username' => $username,
							'role' => $role
						]);
						$success = "User berhasil ditambahkan.";
					} else {
						$error = "Gagal menambahkan user: " . mysqli_error($conn);
					}
				}
			}
		}
	} else {
		$error = "ID, username, dan password harus diisi.";
	}
}

// Edit user
if (isset($_POST['edit'])) {
	$user_id = intval($_POST['user_id']);
	$username = trim($_POST['username']);
	$password = $_POST['password'];
	$role = $_POST['role'];
	$userId = intval($_SESSION['user_id']);
	
	if ($user_id > 0 && $username !== '') {
		// Ambil data lama untuk audit
		$oldData = mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id");
		$oldRow = $oldData ? mysqli_fetch_assoc($oldData) : null;
		
		// Cek apakah username sudah digunakan oleh user lain
		$cekUsername = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "' AND user_id != $user_id");
		if ($cekUsername && mysqli_num_rows($cekUsername) > 0) {
			$error = "Username '$username' sudah digunakan oleh user lain.";
		} else {
			$usernameEsc = mysqli_real_escape_string($conn, $username);
			$roleEsc = mysqli_real_escape_string($conn, $role);
			
			// Update password hanya jika diisi
			if (!empty($password)) {
				// Validasi password jika diisi
				$passwordErrors = validatePassword($password);
				if (!empty($passwordErrors)) {
					$error = "Password tidak valid:<br>• " . implode("<br>• ", $passwordErrors);
				} else {
					$passwordHash = md5($password);
					$sql = "UPDATE users SET username = '$usernameEsc', password = '$passwordHash', role = '$roleEsc' WHERE user_id = $user_id";
					
					if (mysqli_query($conn, $sql)) {
						$newData = [
							'username' => $username,
							'role' => $role
						];
						if (!empty($password)) {
							$newData['password'] = '***';
						}
						
						logAudit($conn, $userId, 'update', 'users', $user_id, 
							$oldRow ? [
								'username' => $oldRow['username'],
								'role' => $oldRow['role']
							] : null,
							$newData
						);
						$success = "User berhasil diupdate.";
					} else {
						$error = "Gagal mengupdate user: " . mysqli_error($conn);
					}
				}
			} else {
				$sql = "UPDATE users SET username = '$usernameEsc', role = '$roleEsc' WHERE user_id = $user_id";
				
				if (mysqli_query($conn, $sql)) {
					$newData = [
						'username' => $username,
						'role' => $role
					];
					
					logAudit($conn, $userId, 'update', 'users', $user_id, 
						$oldRow ? [
							'username' => $oldRow['username'],
							'role' => $oldRow['role']
						] : null,
						$newData
					);
					$success = "User berhasil diupdate.";
				} else {
					$error = "Gagal mengupdate user: " . mysqli_error($conn);
				}
			}
		}
	} else {
		$error = "ID dan username harus diisi.";
	}
}

// Hapus user
if (isset($_GET['hapus'])) {
	$id = intval($_GET['hapus']);
	$userId = intval($_SESSION['user_id']);
	
	// Cek apakah user sedang login
	if ($id == $userId) {
		$error = "Tidak dapat menghapus user yang sedang login.";
	} else {
		// Cek apakah user digunakan di transaksi atau shift
		$cekTransaksi = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi_pemasukan WHERE user_id = $id");
		$cekTransaksi2 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi_pengeluaran WHERE user_id = $id");
		$cekShift = mysqli_query($conn, "SELECT COUNT(*) AS total FROM kas_shift WHERE kasir_user_id = $id");
		
		$totalUse = 0;
		if ($cekTransaksi) {
			$row = mysqli_fetch_assoc($cekTransaksi);
			$totalUse += $row['total'];
		}
		if ($cekTransaksi2) {
			$row = mysqli_fetch_assoc($cekTransaksi2);
			$totalUse += $row['total'];
		}
		if ($cekShift) {
			$row = mysqli_fetch_assoc($cekShift);
			$totalUse += $row['total'];
		}
		
		if ($totalUse > 0) {
			$error = "User tidak dapat dihapus karena masih digunakan di $totalUse record.";
		} else {
			// Ambil data untuk audit
			$oldData = mysqli_query($conn, "SELECT * FROM users WHERE user_id = $id");
			$oldRow = $oldData ? mysqli_fetch_assoc($oldData) : null;
			
			$sql = "DELETE FROM users WHERE user_id = $id";
			
			if (mysqli_query($conn, $sql)) {
				logAudit($conn, $userId, 'delete', 'users', $id, 
					$oldRow ? [
						'username' => $oldRow['username'],
						'role' => $oldRow['role']
					] : null,
					null
				);
				$success = "User berhasil dihapus.";
			} else {
				$error = "Gagal menghapus user: " . mysqli_error($conn);
			}
		}
	}
}

$data = mysqli_query($conn, "SELECT * FROM users ORDER BY user_id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Data User</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background: #555 !important; }
		.sidebar .nav-link { color: #fff !important; }
		.sidebar .nav-link:hover { background: #444; }
		.badge-admin { background-color: #007bff; color: #fff; }
		.badge-pemilik { background-color: #28a745; color: #fff; }
	</style>
</head>
<body id="page-top">
<div id="wrapper">
	<!-- Sidebar -->
	<?php $currentPage = 'data_user'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-users"></i> Data User</h3>

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
					<i class="fas fa-user-plus"></i> Tambah User Baru
				</div>
				<div class="card-body">
					<form method="POST">
						<div class="form-row">
							<div class="form-group col-md-2">
								<label>ID User <small class="text-muted">(wajib)</small></label>
								<input type="number" name="user_id" class="form-control" min="1" required>
							</div>
							<div class="form-group col-md-3">
								<label>Username</label>
								<input type="text" name="username" class="form-control" placeholder="Username..." required>
							</div>
							<div class="form-group col-md-3">
								<label>Password</label>
								<input type="password" name="password" id="password_tambah" class="form-control" placeholder="Password..." required>
								<small class="form-text text-muted">
									Minimal 6 karakter, harus ada: huruf besar, huruf kecil, angka, dan tanda baca
								</small>
								<div id="password_feedback_tambah" class="mt-1"></div>
							</div>
							<div class="form-group col-md-2">
								<label>Role</label>
								<select name="role" class="form-control" required>
									<option value="admin">Admin</option>
									<option value="pemilik">Pemilik</option>
								</select>
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
					<i class="fas fa-table"></i> Daftar User
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover">
						<thead class="thead-dark text-center">
							<tr>
								<th width="10%">ID</th>
								<th>Username</th>
								<th width="15%">Role</th>
								<th width="25%">Aksi</th>
							</tr>
						</thead>
						<tbody>
						<?php if (mysqli_num_rows($data) > 0): ?>
							<?php while ($r = mysqli_fetch_assoc($data)): ?>
								<tr>
									<td class="text-center"><?= $r['user_id']; ?></td>
									<td><?= htmlspecialchars($r['username']); ?></td>
									<td class="text-center">
										<span class="badge badge-<?= $r['role'] == 'admin' ? 'admin' : 'pemilik'; ?>">
											<?= ucfirst($r['role']); ?>
										</span>
									</td>
									<td class="text-center">
										<button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#edit<?= $r['user_id']; ?>">
											<i class="fas fa-edit"></i> Edit
										</button>
										<?php if ($r['user_id'] != $_SESSION['user_id']): ?>
											<a href="?hapus=<?= $r['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus user ini?')">
												<i class="fas fa-trash"></i> Hapus
											</a>
										<?php else: ?>
											<button class="btn btn-danger btn-sm" disabled title="Tidak dapat menghapus user yang sedang login">
												<i class="fas fa-trash"></i> Hapus
											</button>
										<?php endif; ?>
									</td>
								</tr>
								<div class="modal fade" id="edit<?= $r['user_id']; ?>" tabindex="-1" role="dialog">
									<div class="modal-dialog" role="document">
										<div class="modal-content">
											<form method="POST">
												<div class="modal-header bg-warning text-dark">
													<h5 class="modal-title">Edit User</h5>
													<button type="button" class="close" data-dismiss="modal">&times;</button>
												</div>
												<div class="modal-body">
													<input type="hidden" name="user_id" value="<?= $r['user_id']; ?>">
													<div class="form-group">
														<label>Username</label>
														<input type="text" name="username" class="form-control" value="<?= htmlspecialchars($r['username']); ?>" required>
													</div>
													<div class="form-group">
														<label>Password Baru <small class="text-muted">(kosongkan jika tidak ingin mengubah)</small></label>
														<input type="password" name="password" id="password_edit<?= $r['user_id']; ?>" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
														<small class="form-text text-muted">
															Minimal 6 karakter, harus ada: huruf besar, huruf kecil, angka, dan tanda baca
														</small>
														<div id="password_feedback_edit<?= $r['user_id']; ?>" class="mt-1"></div>
													</div>
													<div class="form-group">
														<label>Role</label>
														<select name="role" class="form-control" required>
															<option value="admin" <?= $r['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
															<option value="pemilik" <?= $r['role'] == 'pemilik' ? 'selected' : ''; ?>>Pemilik</option>
														</select>
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
							<tr><td colspan="4" class="text-center text-muted">Belum ada user.</td></tr>
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
// Fungsi validasi password di JavaScript
function validatePasswordJS(password) {
	var errors = [];
	
	if (password.length < 6) {
		errors.push("Minimal 6 karakter");
	}
	if (!/[A-Z]/.test(password)) {
		errors.push("Harus ada huruf besar (A-Z)");
	}
	if (!/[a-z]/.test(password)) {
		errors.push("Harus ada huruf kecil (a-z)");
	}
	if (!/[0-9]/.test(password)) {
		errors.push("Harus ada angka (0-9)");
	}
	if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
		errors.push("Harus ada tanda baca");
	}
	
	return errors;
}

// Validasi password untuk form tambah
$('#password_tambah').on('input', function() {
	var password = $(this).val();
	var feedback = $('#password_feedback_tambah');
	
	if (password.length === 0) {
		feedback.html('').removeClass('text-danger text-success');
		return;
	}
	
	var errors = validatePasswordJS(password);
	if (errors.length === 0) {
		feedback.html('<small class="text-success"><i class="fas fa-check-circle"></i> Password valid</small>').removeClass('text-danger').addClass('text-success');
	} else {
		feedback.html('<small class="text-danger"><i class="fas fa-times-circle"></i> ' + errors.join(', ') + '</small>').removeClass('text-success').addClass('text-danger');
	}
});

// Validasi password untuk form edit (semua modal) - menggunakan event delegation
$(document).on('input', 'input[id^="password_edit"]', function() {
	var password = $(this).val();
	var userId = $(this).attr('id').replace('password_edit', '');
	var feedback = $('#password_feedback_edit' + userId);
	
	if (password.length === 0) {
		feedback.html('').removeClass('text-danger text-success');
		return;
	}
	
	var errors = validatePasswordJS(password);
	if (errors.length === 0) {
		feedback.html('<small class="text-success"><i class="fas fa-check-circle"></i> Password valid</small>').removeClass('text-danger').addClass('text-success');
	} else {
		feedback.html('<small class="text-danger"><i class="fas fa-times-circle"></i> ' + errors.join(', ') + '</small>').removeClass('text-success').addClass('text-danger');
	}
});
</script>
</body>
</html>
