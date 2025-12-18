<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
    header("Location: ../login.php");
    exit;
}

include '../config.php';
include '../util_id.php';

// === Tambah Catatan ===
if (isset($_POST['tambah'])) {
    $catatan_id = intval($_POST['catatan_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $isi = mysqli_real_escape_string($conn, $_POST['isi_catatan']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']); // langsung dari input

	// Cek apakah ID sudah ada
	$cekId = mysqli_query($conn, "SELECT catatan_id FROM catatan WHERE catatan_id = $catatan_id");
	if ($cekId && mysqli_num_rows($cekId) > 0) {
		$_SESSION['error'] = "ID catatan $catatan_id sudah digunakan. Gunakan ID lain.";
	} else {
    mysqli_query($conn, "
			INSERT INTO catatan (catatan_id, tanggal, judul, isi_catatan, tipe) 
			VALUES ($catatan_id, '$tanggal', '$judul', '$isi', '$tipe')
    ");
	}
    header("Location: catatan_operasional.php");
    exit;
}

// === Hapus Catatan ===
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM catatan WHERE catatan_id = $id");
    header("Location: catatan_operasional.php");
    exit;
}

// === Edit Catatan ===
if (isset($_POST['edit'])) {
    $id = intval($_POST['catatan_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $isi = mysqli_real_escape_string($conn, $_POST['isi_catatan']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe']);

    mysqli_query($conn, "
        UPDATE catatan 
        SET tanggal='$tanggal', judul='$judul', isi_catatan='$isi', tipe='$tipe'
        WHERE catatan_id=$id
    ");
    header("Location: catatan_operasional.php");
    exit;
}

// === Ambil semua data catatan ===
$catatan = mysqli_query($conn, "SELECT * FROM catatan ORDER BY catatan_id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Catatan Operasional</title>

    <!-- SB Admin 2 -->
    <link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        .sidebar { background-color: #555 !important; }
        .sidebar .nav-link { color: #fff !important; }
        .sidebar .nav-link:hover { background-color: #444; }
        .card-header { background-color: #6c757d; color: #fff; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body id="page-top">
<div id="wrapper">

    <!-- Sidebar -->
    <?php $currentPage = 'catatan_operasional'; include 'sidebar_owner.php'; ?>

    <!-- Content -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content" class="p-4">
            <h3 class="mb-4"><i class="fas fa-sticky-note"></i> Catatan Operasional</h3>

            <!-- Form Tambah Catatan -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-plus"></i> Tambah Catatan Baru
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label>ID Catatan <small class="text-muted">(wajib)</small></label>
                                <input type="number" name="catatan_id" class="form-control" min="1" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Tipe</label>
                                <input type="text" name="tipe" class="form-control" placeholder="Masukkan tipe catatan" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Judul</label>
                                <input type="text" name="judul" class="form-control" placeholder="Masukkan judul catatan" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Isi Catatan</label>
                            <textarea name="isi_catatan" class="form-control" rows="3" placeholder="Tuliskan isi catatan..." required></textarea>
                        </div>
                        <button type="submit" name="tambah" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
                    </form>
                </div>
            </div>

            <!-- Daftar Catatan -->
            <div class="card shadow">
                <div class="card-header">
                    <i class="fas fa-list"></i> Daftar Catatan
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-dark text-center">
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Tanggal</th>
                                <th width="20%">Judul</th>
                                <th>Isi Catatan</th>
                                <th width="10%">Tipe</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($catatan)): ?>
                            <tr>
                                <td class="text-center"><?= $no++; ?></td>
                                <td class="text-center"><?= $row['tanggal']; ?></td>
                                <td><?= htmlspecialchars($row['judul']); ?></td>
                                <td><?= nl2br(htmlspecialchars($row['isi_catatan'])); ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['tipe']); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?= $row['catatan_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="catatan_operasional.php?hapus=<?= $row['catatan_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus catatan ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>

                            <!-- Modal Edit -->
                            <div class="modal fade" id="editModal<?= $row['catatan_id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header bg-secondary text-white">
                                                <h5 class="modal-title">Edit Catatan</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="catatan_id" value="<?= $row['catatan_id']; ?>">
                                                <div class="form-row">
                                                    <div class="form-group col-md-4">
                                                        <label>Tanggal</label>
                                                        <input type="date" name="tanggal" value="<?= $row['tanggal']; ?>" class="form-control" required>
                                                    </div>
                                                    <div class="form-group col-md-4">
                                                        <label>Tipe</label>
                                                        <input type="text" name="tipe" value="<?= htmlspecialchars($row['tipe']); ?>" class="form-control" required>
                                                    </div>
                                                    <div class="form-group col-md-4">
                                                        <label>Judul</label>
                                                        <input type="text" name="judul" value="<?= htmlspecialchars($row['judul']); ?>" class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Isi Catatan</label>
                                                    <textarea name="isi_catatan" class="form-control" rows="4" required><?= htmlspecialchars($row['isi_catatan']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="edit" class="btn btn-warning"><i class="fas fa-save"></i> Simpan Perubahan</button>
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Script -->
<script src="../startbootstrap-sb-admin-2-master/vendor/jquery/jquery.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="../startbootstrap-sb-admin-2-master/js/sb-admin-2.min.js"></script>
</body>
</html>