<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

$userId = (string) $_POST['id'];

try {
    firebaseUpdate('users/' . rawurlencode($userId), ['role' => 'admin']);
    firebasePush('audit_logs', [
        'action' => 'promote_user_to_admin',
        'actor_user_id' => (string) $_SESSION['user_id'],
        'target_user_id' => $userId,
        'timestamp' => gmdate('c')
    ]);
    header('Location: admin_dashboard.php?role_updated=1');
    exit();
} catch (Throwable $error) {
    http_response_code(500);
    exit('Unable to update user role.');
}
?>
