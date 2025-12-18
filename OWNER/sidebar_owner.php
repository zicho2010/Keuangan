<?php
// sidebar_owner.php - Standard sidebar untuk semua halaman OWNER
// Usage: include 'sidebar_owner.php';
// Set $currentPage sebelum include untuk highlight menu aktif

// Default current page jika tidak diset
if (!isset($currentPage)) {
	$currentPage = '';
}
?>
<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
	<a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboardOwner.php">
		<div class="sidebar-brand-icon"><i class="fas fa-user-tie"></i></div>
		<div class="sidebar-brand-text mx-3">Panel Pemilik</div>
	</a>

	<!-- Dashboard -->
	<li class="nav-item <?= $currentPage == 'dashboard' ? 'active' : ''; ?>">
		<a class="nav-link" href="dashboardOwner.php">
			<i class="fas fa-fw fa-tachometer-alt"></i>
			<span>Dashboard</span>
		</a>
	</li>

	<!-- Laporan -->
	<li class="nav-item <?= in_array($currentPage, ['laporan_transaksi', 'laporan_keuangan']) ? 'active' : ''; ?>">
		<a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseLaporan">
			<i class="fas fa-fw fa-file-alt"></i>
			<span>Laporan</span>
		</a>
		<div id="collapseLaporan" class="collapse <?= in_array($currentPage, ['laporan_transaksi', 'laporan_keuangan']) ? 'show' : ''; ?>" data-parent="#accordionSidebar">
			<div class="bg-white py-2 collapse-inner rounded">
				<a class="collapse-item <?= $currentPage == 'laporan_transaksi' ? 'active' : ''; ?>" href="laporan_transaksi.php">Laporan Transaksi</a>
				<a class="collapse-item <?= $currentPage == 'laporan_keuangan' ? 'active' : ''; ?>" href="laporan_keuangan.php">Laporan Keuangan</a>
			</div>
		</div>
	</li>

	<!-- Kelola Data Master -->
	<li class="nav-item <?= in_array($currentPage, ['data_user', 'kategori_pemasukan', 'kategori_pengeluaran']) ? 'active' : ''; ?>">
		<a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseMaster">
			<i class="fas fa-database"></i>
			<span>Kelola Data Master</span>
		</a>
		<div id="collapseMaster" class="collapse <?= in_array($currentPage, ['data_user', 'kategori_pemasukan', 'kategori_pengeluaran']) ? 'show' : ''; ?>" data-parent="#accordionSidebar">
			<div class="bg-white py-2 collapse-inner rounded">
				<a class="collapse-item <?= $currentPage == 'data_user' ? 'active' : ''; ?>" href="data_user.php">Data User</a>
				<a class="collapse-item <?= $currentPage == 'kategori_pemasukan' ? 'active' : ''; ?>" href="kategori_pemasukan.php">Kategori Pemasukan</a>
				<a class="collapse-item <?= $currentPage == 'kategori_pengeluaran' ? 'active' : ''; ?>" href="kategori_pengeluaran.php">Kategori Pengeluaran</a>
			</div>
		</div>
	</li>

	<!-- Kelola Keuangan & Transaksi -->
	<li class="nav-item <?= $currentPage == 'kelola_keuangan' ? 'active' : ''; ?>">
		<a class="nav-link" href="kelola_keuangan.php">
			<i class="fas fa-wallet"></i>
			<span>Kelola Keuangan (Budget)</span>
		</a>
	</li>
	<li class="nav-item <?= $currentPage == 'kelola_transaksi' ? 'active' : ''; ?>">
		<a class="nav-link" href="kelola_transaksi.php">
			<i class="fas fa-exchange-alt"></i>
			<span>Kelola Transaksi</span>
		</a>
	</li>

	<!-- Approval Transaksi -->
	<li class="nav-item <?= $currentPage == 'approval_transaksi' ? 'active' : ''; ?>">
		<a class="nav-link" href="approval_transaksi.php">
			<i class="fas fa-check-circle"></i>
			<span>Approval Transaksi</span>
		</a>
	</li>

	<!-- Upload Bukti -->
	<li class="nav-item <?= $currentPage == 'upload_bukti' ? 'active' : ''; ?>">
		<a class="nav-link" href="upload_bukti.php">
			<i class="fas fa-file-upload"></i>
			<span>Upload Bukti</span>
		</a>
	</li>

	<!-- Penutupan Kas -->
	<li class="nav-item <?= $currentPage == 'penutupan_kas' ? 'active' : ''; ?>">
		<a class="nav-link" href="penutupan_kas.php">
			<i class="fas fa-cash-register"></i>
			<span>Penutupan Kas</span>
		</a>
	</li>

	<!-- Activity Log -->
	<li class="nav-item <?= $currentPage == 'activity_log' ? 'active' : ''; ?>">
		<a class="nav-link" href="activity_log.php">
			<i class="fas fa-history"></i>
			<span>Activity Log</span>
		</a>
	</li>

	<!-- Catatan -->
	<li class="nav-item <?= $currentPage == 'catatan_operasional' ? 'active' : ''; ?>">
		<a class="nav-link" href="catatan_operasional.php">
			<i class="fas fa-sticky-note"></i>
			<span>Catatan Operasional</span>
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

















