<?php
session_start();
include "config.php"; // pastikan config.php sudah konek ke database monitoring_db
include "util_audit.php";

if (isset($_POST['login'])) {
    // Amankan input dari SQL injection
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Query ke tabel users
    $query = mysqli_query($conn, "
        SELECT * FROM users 
        WHERE username = '$username' 
        AND password = MD5('$password')
        LIMIT 1
    ");

    // Cek hasil query
    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);

        // Set session user
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];

        // Log audit
        logAudit($conn, $row['user_id'], 'login', 'users', $row['user_id'], null, [
            'username' => $row['username'],
            'role' => $row['role']
        ]);

        // Arahkan ke dashboard sesuai role
        if ($row['role'] === 'admin') {
            header("Location: ADMIN/dashboardAdmin.php");
        } elseif ($row['role'] === 'pemilik') {
            header("Location: OWNER/dashboardOwner.php");
        } else {
            header("Location: login.php");
        }
        exit;
    } else {
        $error = "❌ Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem Monitoring Keuangan</title>
    <link href="startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        body { background-color: #f8f9fa; }
        .login-box {
            max-width: 400px;
            margin: 100px auto;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .login-header {
            background: #555;
            color: #fff;
            padding: 12px;
            text-align: center;
            margin: -25px -25px 20px -25px;
            border-radius: 8px 8px 0 0;
        }
        .btn-login {
            background: #555;
            color: #fff;
            font-weight: bold;
            width: 100%;
        }
        .btn-login:hover {
            background: #333;
        }
    </style>
</head>
<body>

<div class="login-box">
    <div class="login-header">
        <h5>SISTEM MONITORING KEUANGAN</h5>
        <small>Toko Kelontong</small>
    </div>
    <h6 class="mb-3">Login ke sistem</h6>

    <?php if (!empty($error)) { ?>
        <div class="alert alert-danger"><?= $error; ?></div>
    <?php } ?>

    <form method="post">
        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" name="username" placeholder="Masukkan username" required autofocus>
        </div>
        <div class="form-group mt-2">
            <label>Password</label>
            <input type="password" class="form-control" name="password" placeholder="Masukkan password" required>
        </div>
        <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember">Ingat saya</label>
        </div>
        <button type="submit" name="login" class="btn btn-login mt-3">LOGIN</button>
    </form>

    <div class="mt-3 text-center">
        <a href="lupa_password.php">Lupa Password?</a>
    </div>
</div>

</body>
</html>
