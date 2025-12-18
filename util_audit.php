<?php
// util_audit.php - Helper functions for audit logging

/**
 * Log an action to audit_log table
 * @param mysqli $conn Database connection
 * @param int|null $userId User ID (null if system action)
 * @param string $action Action type: 'create', 'update', 'delete', 'approve', 'reject', 'login', 'logout'
 * @param string $tableName Table name
 * @param string|int $recordId Record ID
 * @param array|null $oldValues Old values (for update/delete)
 * @param array|null $newValues New values (for create/update)
 * @return bool Success status
 */
if (!function_exists('logAudit')) {
function logAudit(mysqli $conn, ?int $userId, string $action, string $tableName, $recordId, ?array $oldValues = null, ?array $newValues = null): bool {
	include_once 'util_id.php';
	
	$logId = generateNextId($conn, 'audit_log', 'log_id');
	$userId = $userId ?? null;
	$action = mysqli_real_escape_string($conn, $action);
	$tableName = mysqli_real_escape_string($conn, $tableName);
	$recordId = mysqli_real_escape_string($conn, (string)$recordId);
	
	$oldJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
	$newJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
	
	$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
	$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
	
	if ($oldJson) $oldJson = "'" . mysqli_real_escape_string($conn, $oldJson) . "'";
	else $oldJson = "NULL";
	
	if ($newJson) $newJson = "'" . mysqli_real_escape_string($conn, $newJson) . "'";
	else $newJson = "NULL";
	
	$ipAddress = $ipAddress ? "'" . mysqli_real_escape_string($conn, $ipAddress) . "'" : "NULL";
	$userAgent = $userAgent ? "'" . mysqli_real_escape_string($conn, $userAgent) . "'" : "NULL";
	
	$userIdSql = $userId ? $userId : "NULL";
	
	$sql = "INSERT INTO audit_log (log_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
			VALUES ($logId, $userIdSql, '$action', '$tableName', '$recordId', $oldJson, $newJson, $ipAddress, $userAgent)";
	
	return mysqli_query($conn, $sql);
}
}

/**
 * Get audit logs with filters
 * @param mysqli $conn Database connection
 * @param array $filters Filters: table_name, action, user_id, date_from, date_to
 * @param int $limit Limit results
 * @param int $offset Offset for pagination
 * @return mysqli_result|false Query result
 */
if (!function_exists('getAuditLogs')) {
function getAuditLogs(mysqli $conn, array $filters = [], int $limit = 100, int $offset = 0) {
	$sql = "SELECT al.*, u.username 
			FROM audit_log al
			LEFT JOIN users u ON al.user_id = u.user_id
			WHERE 1=1";
	
	if (!empty($filters['table_name'])) {
		$tableName = mysqli_real_escape_string($conn, $filters['table_name']);
		$sql .= " AND al.table_name = '$tableName'";
	}
	
	if (!empty($filters['action'])) {
		$action = mysqli_real_escape_string($conn, $filters['action']);
		$sql .= " AND al.action = '$action'";
	}
	
	if (!empty($filters['user_id'])) {
		$userId = intval($filters['user_id']);
		$sql .= " AND al.user_id = $userId";
	}
	
	if (!empty($filters['date_from'])) {
		$dateFrom = mysqli_real_escape_string($conn, $filters['date_from']);
		$sql .= " AND DATE(al.created_at) >= '$dateFrom'";
	}
	
	if (!empty($filters['date_to'])) {
		$dateTo = mysqli_real_escape_string($conn, $filters['date_to']);
		$sql .= " AND DATE(al.created_at) <= '$dateTo'";
	}
	
	$sql .= " ORDER BY al.log_id ASC, al.created_at ASC LIMIT $limit OFFSET $offset";
	
	return mysqli_query($conn, $sql);
}
}
?>