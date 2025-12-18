<?php
// sidebar_admin.php - Standard sidebar untuk semua halaman ADMIN
// Usage: include 'sidebar_admin.php';
// Set $currentPage sebelum include untuk highlight menu aktif

// Default current page jika tidak diset
if (!isset($currentPage)) {
	$currentPage = '';
}
?>
<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
	<a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboardAdmin.php">
		<div class="sidebar-brand-icon"><i class="fas fa-coins"></i></div>
		<div class="sidebar-brand-text mx-3">Sistem Keuangan</div>
	</a>

	<!-- Dashboard -->
	<li class="nav-item <?= $currentPage == 'dashboard' ? 'active' : ''; ?>">
		<a class="nav-link" href="dashboardAdmin.php">
			<i class="fas fa-fw fa-tachometer-alt"></i>
			<span>Dashboard</span>
		</a>
	</li>

	<!-- Transaksi Pemasukan -->
	<li class="nav-item <?= $currentPage == 'transaksi_pemasukan' ? 'active' : ''; ?>">
		<a class="nav-link" href="transaksi_pemasukan.php">
			<i class="fas fa-arrow-down"></i>
			<span>Input Pemasukan</span>
		</a>
	</li>

	<!-- Transaksi Pengeluaran -->
	<li class="nav-item <?= $currentPage == 'transaksi_pengeluaran' ? 'active' : ''; ?>">
		<a class="nav-link" href="transaksi_pengeluaran.php">
			<i class="fas fa-arrow-up"></i>
			<span>Input Pengeluaran</span>
		</a>
	</li>

	<!-- Catatan -->
	<li class="nav-item <?= $currentPage == 'catatan' ? 'active' : ''; ?>">
		<a class="nav-link" href="catatan.php">
			<i class="fas fa-sticky-note"></i>
			<span>Catatan</span>
		</a>
	</li>

	<!-- Logout -->
	<li class="nav-item">
		<a class="nav-link" href="../logout.php">
			<i class="fas fa-sign-out-alt"></i>
			<span>Logout</span>
		</a>
	</li>
</ul>

















