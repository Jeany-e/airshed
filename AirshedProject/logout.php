<?php
require __DIR__ . '/config.php';
if (isset($_SESSION['user_id'])) {
	logActivity('logout', $_SESSION['user_id'], $_SESSION['username'] ?? null, $_SESSION['role'] ?? null);
}
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>