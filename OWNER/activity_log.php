<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pemilik') {
	header("Location: ../login.php");
	exit;
}

include '../config.php';
include '../util_audit.php';

$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	if (!empty($_GET['table_name'])) $filters['table_name'] = $_GET['table_name'];
	if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
	if (!empty($_GET['user_id'])) $filters['user_id'] = intval($_GET['user_id']);
	if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
	if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$logs = getAuditLogs($conn, $filters, $limit, $offset);

// Get total count for pagination
$countSql = "SELECT COUNT(*) AS total FROM audit_log WHERE 1=1";
if (!empty($filters['table_name'])) {
	$tableName = mysqli_real_escape_string($conn, $filters['table_name']);
	$countSql .= " AND table_name = '$tableName'";
}
if (!empty($filters['action'])) {
	$action = mysqli_real_escape_string($conn, $filters['action']);
	$countSql .= " AND action = '$action'";
}
if (!empty($filters['user_id'])) {
	$userId = intval($filters['user_id']);
	$countSql .= " AND user_id = $userId";
}
if (!empty($filters['date_from'])) {
	$dateFrom = mysqli_real_escape_string($conn, $filters['date_from']);
	$countSql .= " AND DATE(created_at) >= '$dateFrom'";
}
if (!empty($filters['date_to'])) {
	$dateTo = mysqli_real_escape_string($conn, $filters['date_to']);
	$countSql .= " AND DATE(created_at) <= '$dateTo'";
}
$countResult = mysqli_query($conn, $countSql);
$totalLogs = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
$totalPages = ceil($totalLogs / $limit);

// Get users for filter
$users = mysqli_query($conn, "SELECT * FROM users ORDER BY user_id ASC");

// Get unique table names
$tables = mysqli_query($conn, "SELECT DISTINCT table_name FROM audit_log ORDER BY table_name");
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<title>Activity Log</title>
	<link href="../startbootstrap-sb-admin-2-master/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
	<link href="../startbootstrap-sb-admin-2-master/css/sb-admin-2.min.css" rel="stylesheet">
	<style>
		.sidebar { background: #555 !important; }
		.sidebar .nav-link { color: #fff !important; }
		.sidebar .nav-link:hover { background: #444; }
		.json-data { font-size: 0.85em; max-width: 300px; word-break: break-all; }
		.badge-action { font-size: 0.9em; }
	</style>
</head>
<body id="page-top">
<div id="wrapper">
	<!-- Sidebar -->
	<?php $currentPage = 'activity_log'; include 'sidebar_owner.php'; ?>

	<div id="content-wrapper" class="d-flex flex-column">
		<div id="content" class="p-4">
			<h3 class="mb-4"><i class="fas fa-history"></i> Activity Log (Audit Trail)</h3>

			<!-- Filter -->
			<div class="card mb-4">
				<div class="card-header bg-secondary text-white">
					<i class="fas fa-filter"></i> Filter
				</div>
				<div class="card-body">
					<form method="GET" class="form-row">
						<div class="form-group col-md-2">
							<label>Table</label>
							<select name="table_name" class="form-control">
								<option value="">Semua</option>
								<?php 
								mysqli_data_seek($tables, 0);
								while ($t = mysqli_fetch_assoc($tables)): ?>
									<option value="<?= htmlspecialchars($t['table_name']); ?>" <?= (!empty($filters['table_name']) && $filters['table_name'] == $t['table_name']) ? 'selected' : ''; ?>>
										<?= htmlspecialchars($t['table_name']); ?>
									</option>
								<?php endwhile; ?>
							</select>
						</div>
						<div class="form-group col-md-2">
							<label>Action</label>
							<select name="action" class="form-control">
								<option value="">Semua</option>
								<option value="create" <?= (!empty($filters['action']) && $filters['action'] == 'create') ? 'selected' : ''; ?>>Create</option>
								<option value="update" <?= (!empty($filters['action']) && $filters['action'] == 'update') ? 'selected' : ''; ?>>Update</option>
								<option value="delete" <?= (!empty($filters['action']) && $filters['action'] == 'delete') ? 'selected' : ''; ?>>Delete</option>
								<option value="approve" <?= (!empty($filters['action']) && $filters['action'] == 'approve') ? 'selected' : ''; ?>>Approve</option>
								<option value="reject" <?= (!empty($filters['action']) && $filters['action'] == 'reject') ? 'selected' : ''; ?>>Reject</option>
								<option value="login" <?= (!empty($filters['action']) && $filters['action'] == 'login') ? 'selected' : ''; ?>>Login</option>
								<option value="logout" <?= (!empty($filters['action']) && $filters['action'] == 'logout') ? 'selected' : ''; ?>>Logout</option>
							</select>
						</div>
						<div class="form-group col-md-2">
							<label>User</label>
							<select name="user_id" class="form-control">
								<option value="">Semua</option>
								<?php 
								mysqli_data_seek($users, 0);
								while ($u = mysqli_fetch_assoc($users)): ?>
									<option value="<?= $u['user_id']; ?>" <?= (!empty($filters['user_id']) && $filters['user_id'] == $u['user_id']) ? 'selected' : ''; ?>>
										<?= htmlspecialchars($u['username']); ?>
									</option>
								<?php endwhile; ?>
							</select>
						</div>
						<div class="form-group col-md-2">
							<label>Dari Tanggal</label>
							<input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? ''; ?>">
						</div>
						<div class="form-group col-md-2">
							<label>Sampai Tanggal</label>
							<input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? ''; ?>">
						</div>
						<div class="form-group col-md-2">
							<label>&nbsp;</label>
							<button type="submit" class="btn btn-primary btn-block">
								<i class="fas fa-search"></i> Filter
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- Daftar Log -->
			<div class="card">
				<div class="card-header bg-primary text-white">
					<i class="fas fa-list"></i> Daftar Activity Log (Total: <?= number_format($totalLogs); ?>)
				</div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover table-sm">
						<thead class="thead-dark text-center">
							<tr>
								<th>ID <i class="fas fa-sort-up"></i></th>
								<th>Waktu</th>
								<th>User</th>
								<th>Action</th>
								<th>Table</th>
								<th>Record ID</th>
								<th>Old Values</th>
								<th>New Values</th>
								<th>IP Address</th>
							</tr>
						</thead>
						<tbody>
						<?php if ($logs && mysqli_num_rows($logs) > 0): ?>
							<?php while ($log = mysqli_fetch_assoc($logs)): ?>
								<tr>
									<td class="text-center"><strong><?= $log['log_id']; ?></strong></td>
									<td class="text-center"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
									<td><?= htmlspecialchars($log['username'] ?? 'System'); ?></td>
									<td class="text-center">
										<?php
										$actionColors = [
											'create' => 'success',
											'update' => 'warning',
											'delete' => 'danger',
											'approve' => 'info',
											'reject' => 'secondary',
											'login' => 'primary',
											'logout' => 'dark'
										];
										$color = $actionColors[$log['action']] ?? 'secondary';
										?>
										<span class="badge badge-<?= $color; ?> badge-action">
											<?= strtoupper($log['action']); ?>
										</span>
									</td>
									<td><code><?= htmlspecialchars($log['table_name']); ?></code></td>
									<td class="text-center"><?= htmlspecialchars($log['record_id']); ?></td>
									<td class="json-data">
										<?php if ($log['old_values']): ?>
											<?php
											$oldData = json_decode($log['old_values'], true);
											if ($oldData) {
												echo '<pre class="mb-0" style="font-size: 0.75em;">' . htmlspecialchars(json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
											} else {
												echo '<span class="text-muted">-</span>';
											}
											?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td class="json-data">
										<?php if ($log['new_values']): ?>
											<?php
											$newData = json_decode($log['new_values'], true);
											if ($newData) {
												echo '<pre class="mb-0" style="font-size: 0.75em;">' . htmlspecialchars(json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
											} else {
												echo '<span class="text-muted">-</span>';
											}
											?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td><small><?= htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
								</tr>
							<?php endwhile; ?>
						<?php else: ?>
							<tr><td colspan="9" class="text-center text-muted">Tidak ada log ditemukan.</td></tr>
						<?php endif; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ($totalPages > 1): ?>
					<nav aria-label="Page navigation">
						<ul class="pagination justify-content-center">
							<li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
								<a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])); ?>">Previous</a>
							</li>
							<?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
								<li class="page-item <?= $i == $page ? 'active' : ''; ?>">
									<a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])); ?>"><?= $i; ?></a>
								</li>
							<?php endfor; ?>
							<li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
								<a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])); ?>">Next</a>
							</li>
						</ul>
					</nav>
					<?php endif; ?>
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

