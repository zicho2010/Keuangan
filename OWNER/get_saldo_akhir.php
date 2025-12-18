<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'Unauthorized']);
	exit;
}

include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tanggal'])) {
	$tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
	
	// Ambil saldo akhir dari cash_flow untuk hari sebelumnya
	$q = mysqli_query($conn, "
		SELECT saldo_akhir
		FROM cash_flow
		WHERE DATE(created_at) < '$tanggal'
		ORDER BY created_at DESC, kas_id DESC
		LIMIT 1
	");
	
	$saldoAkhir = 0.0;
	if ($q && mysqli_num_rows($q) > 0) {
		$r = mysqli_fetch_assoc($q);
		$saldoAkhir = floatval($r['saldo_akhir']);
	}
	
	header('Content-Type: application/json');
	echo json_encode([
		'success' => true,
		'saldo_akhir' => number_format($saldoAkhir, 0, '.', '')
	]);
} else {
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>






