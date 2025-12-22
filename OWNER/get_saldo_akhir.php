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
	
	// Prioritas 1: Ambil dari shift terakhir yang ditutup
	// Ambil shift terakhir yang sudah ditutup berdasarkan closed_at dan shift_id (yang paling baru)
	$qShift = mysqli_query($conn, "
		SELECT actual_closing_cash
		FROM kas_shift
		WHERE status = 'closed' AND actual_closing_cash IS NOT NULL AND actual_closing_cash > 0
		ORDER BY closed_at DESC, shift_id DESC
		LIMIT 1
	");
	
	$saldoAkhir = 0.0;
	if ($qShift && mysqli_num_rows($qShift) > 0) {
		$rShift = mysqli_fetch_assoc($qShift);
		$saldoAkhir = floatval($rShift['actual_closing_cash']);
	}
	
	if ($saldoAkhir <= 0) {
		// Prioritas 2: Ambil saldo akhir terakhir dari cash_flow (sama seperti di dashboard)
		// Ini mengambil saldo akhir kumulatif terakhir, yang sudah termasuk semua transaksi yang sudah di-approve
		$qFallback = mysqli_query($conn, "
			SELECT saldo_akhir
			FROM cash_flow
			ORDER BY created_at DESC, kas_id DESC
			LIMIT 1
		");
		if ($qFallback && mysqli_num_rows($qFallback) > 0) {
			$rFallback = mysqli_fetch_assoc($qFallback);
			$saldoAkhir = floatval($rFallback['saldo_akhir']);
		} else {
			// Prioritas 3: Hitung saldo akhir secara manual jika belum ada data cash_flow
			$hasStatus = mysqli_query($conn, "SHOW COLUMNS FROM transaksi_pemasukan LIKE 'status'");
			$hasStatus = $hasStatus && mysqli_num_rows($hasStatus) > 0;
			
			$whereMasuk = "tanggal_masuk < '$tanggal'";
			if ($hasStatus) {
				$whereMasuk .= " AND status='approved'";
			}
			$qMasuk = mysqli_query($conn, "
				SELECT COALESCE(SUM(jumlah_pemasukan), 0) AS total
				FROM transaksi_pemasukan
				WHERE $whereMasuk
			");
			
			$whereKeluar = "tanggal_keluar < '$tanggal'";
			if ($hasStatus) {
				$whereKeluar .= " AND status='approved'";
			}
			$qKeluar = mysqli_query($conn, "
				SELECT COALESCE(SUM(jumlah_pengeluaran), 0) AS total
				FROM transaksi_pengeluaran
				WHERE $whereKeluar
			");
			
			$totalMasuk = $qMasuk ? floatval(mysqli_fetch_assoc($qMasuk)['total']) : 0.0;
			$totalKeluar = $qKeluar ? floatval(mysqli_fetch_assoc($qKeluar)['total']) : 0.0;
			
			$saldoAkhir = $totalMasuk - $totalKeluar;
		}
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






