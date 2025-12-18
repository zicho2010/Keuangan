<?php
session_start();
include "config.php";
include "util_audit.php";

// Log audit before destroying session
if (isset($_SESSION['user_id'])) {
	logAudit($conn, $_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], null, [
		'username' => $_SESSION['username'] ?? '',
		'role' => $_SESSION['role'] ?? ''
	]);
}

session_destroy();
header("Location: login.php");
exit;
?>