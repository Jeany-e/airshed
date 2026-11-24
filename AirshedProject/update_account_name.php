<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit();
}

$userId = trim((string) ($_POST['id'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));

if ($userId === '' || $username === '') {
    http_response_code(400);
    exit('User ID and name are required.');
}

try {
    foreach (firebaseRows('users') as $user) {
        $sameUser = (string) ($user['id'] ?? '') === $userId;
        if (!$sameUser && strcasecmp((string) ($user['username'] ?? ''), $username) === 0) {
            http_response_code(409);
            exit('That username is already in use.');
        }
    }

    firebaseUpdate('users/' . rawurlencode($userId), ['username' => $username]);

    if ((string) $_SESSION['user_id'] === $userId) {
        $_SESSION['username'] = $username;
    }

    firebasePush('audit_logs', [
        'action' => 'update_account_name',
        'actor_user_id' => (string) $_SESSION['user_id'],
        'target_user_id' => $userId,
        'timestamp' => gmdate('c')
    ]);

    header('Location: admin_dashboard.php?name_updated=1');
    exit();
} catch (Throwable $error) {
    http_response_code(500);
    exit('Unable to update account name.');
}
