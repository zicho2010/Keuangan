<?php
// Helper to generate next integer ID for tables without AUTO_INCREMENT
if (!function_exists('generateNextId')) {
	function generateNextId(mysqli $conn, string $table, string $idColumn): int {
		$table = mysqli_real_escape_string($conn, $table);
		$idColumn = mysqli_real_escape_string($conn, $idColumn);
		$sql = "SELECT IFNULL(MAX($idColumn), 0) AS max_id FROM $table";
		$res = mysqli_query($conn, $sql);
		if ($res && ($row = mysqli_fetch_assoc($res))) {
			$next = (int)$row['max_id'] + 1;
			return $next > 0 ? $next : 1;
		}
		return 1;
	}
}
?>


