<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

include '../config.php';

// === Hanya menampilkan catatan ===
$result = mysqli_query($conn, "SELECT * FROM catatan ORDER BY catatan_id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Catatan</title>
    <link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        .sidebar { background-color: #555 !important; }
        .sidebar .nav-link { color: #fff !important; }
        .sidebar .nav-link:hover { background-color: #444; }
    </style>
</head>
<body id="page-top">

<div id="wrapper">
    <!-- Sidebar -->
    <?php $currentPage = 'catatan'; include 'sidebar_admin.php'; ?>

    <!-- Content -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4"><i class="fas fa-sticky-note"></i> Daftar Catatan</h3>

            <div class="card shadow">
                <div class="card-header"><i class="fas fa-list"></i> Semua Catatan</div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr class="text-center">
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Judul</th>
                                <th>Isi Catatan</th>
                                <th>Tipe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $no = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td><?= date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                                        <td><?= htmlspecialchars($row['judul']); ?></td>
                                        <td><?= nl2br(htmlspecialchars($row['isi_catatan'])); ?></td>
                                        <td><?= htmlspecialchars($row['tipe']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">Belum ada catatan.</td></tr>
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
<script src="../startbootstrap-sb-admin-2-master/js/sb-admin-2.min.js"></script>
</body>
</html>