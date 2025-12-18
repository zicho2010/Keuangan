<?php
session_start();
include 'config.php';
include 'util_audit.php';

// Ganti kode ini secara berkala untuk keamanan tambahan
const RESET_MASTER_CODE = 'RESET123'; // TODO: ubah sesuai kebijakan internal

$success = '';
$error = '';

// Fungsi validasi password
function validatePassword($password) {
	$errors = [];
	
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $resetCode = trim($_POST['reset_code'] ?? '');

    if ($username === '' || $newPassword === '' || $confirmPassword === '' || $resetCode === '') {
        $error = 'Semua field wajib diisi.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Validasi password
        $passwordErrors = validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            $error = 'Password tidak valid:<br>• ' . implode('<br>• ', $passwordErrors);
        } elseif ($resetCode !== RESET_MASTER_CODE) {
            $error = 'Kode reset tidak valid.';
        } else {
            $usernameEsc = mysqli_real_escape_string($conn, $username);
            $userQuery = mysqli_query($conn, "SELECT * FROM users WHERE username = '$usernameEsc' LIMIT 1");

            if (!$userQuery || mysqli_num_rows($userQuery) === 0) {
                $error = 'Username tidak ditemukan.';
            } else {
                $userData = mysqli_fetch_assoc($userQuery);
                $userId = (int)$userData['user_id'];
                $oldPassword = $userData['password'];
                $newPasswordHash = md5($newPassword);

                $update = mysqli_query($conn, "UPDATE users SET password = '$newPasswordHash' WHERE user_id = $userId");
                if ($update) {
                    // Log audit tanpa menampilkan password (jika gagal, tidak menghentikan proses)
                    @logAudit($conn, $userId, 'update', 'users', $userId, [
                        'password' => '***'
                    ], [
                        'password' => '***'
                    ]);
                    $success = 'Password berhasil direset. Silakan login dengan password baru.';
                } else {
                    $error = 'Terjadi kesalahan saat menyimpan data: ' . mysqli_error($conn);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password - Sistem Monitoring Keuangan</title>
    <link href="startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .reset-box {
            max-width: 430px;
            margin: 80px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .reset-header {
            background: #555;
            color: #fff;
            padding: 14px;
            margin: -30px -30px 25px -30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .btn-reset {
            background: #555;
            color: #fff;
            width: 100%;
            font-weight: bold;
        }
        .btn-reset:hover { background: #333; }
        .form-text { font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="reset-box">
    <div class="reset-header">
        <h5>LUPA PASSWORD</h5>
        <small>Sistem Monitoring Keuangan</small>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success; ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" name="username" placeholder="Masukkan username" required>
        </div>
        <div class="form-group mt-3">
            <label>Password Baru</label>
            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Minimal 6 karakter" required>
            <small class="form-text text-muted">
                Minimal 6 karakter, harus ada: huruf besar, huruf kecil, angka, dan tanda baca
            </small>
            <div id="password_feedback" class="mt-1"></div>
        </div>
        <div class="form-group mt-3">
            <label>Konfirmasi Password Baru</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
            <div id="confirm_feedback" class="mt-1"></div>
        </div>
        <div class="form-group mt-3">
            <label>Kode Reset</label>
            <input type="text" class="form-control" name="reset_code" placeholder="Masukkan kode reset" required>
            <small class="form-text text-muted">*Minta kode reset kepada administrator sistem.</small>
        </div>
        <button type="submit" class="btn btn-reset mt-3">RESET PASSWORD</button>
    </form>

    <div class="mt-3 text-center">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
    </div>
</div>

<script src="startbootstrap-sb-admin-2-master/vendor/jquery/jquery.min.js"></script>
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

// Validasi password baru
$('#new_password').on('input', function() {
	var password = $(this).val();
	var feedback = $('#password_feedback');
	
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
	
	// Cek konfirmasi password juga
	checkConfirmPassword();
});

// Validasi konfirmasi password
$('#confirm_password').on('input', function() {
	checkConfirmPassword();
});

function checkConfirmPassword() {
	var password = $('#new_password').val();
	var confirm = $('#confirm_password').val();
	var feedback = $('#confirm_feedback');
	
	if (confirm.length === 0) {
		feedback.html('').removeClass('text-danger text-success');
		return;
	}
	
	if (password === confirm) {
		feedback.html('<small class="text-success"><i class="fas fa-check-circle"></i> Password cocok</small>').removeClass('text-danger').addClass('text-success');
	} else {
		feedback.html('<small class="text-danger"><i class="fas fa-times-circle"></i> Password tidak cocok</small>').removeClass('text-success').addClass('text-danger');
	}
}
</script>
</body>
</html>