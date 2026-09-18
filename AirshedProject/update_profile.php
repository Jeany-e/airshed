<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user_dashboard.php');
    exit();
}

$userId = (string) $_SESSION['user_id'];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$newPassword = $_POST['password'] ?? '';

if ($username === '' || $email === '' || $phone === '') {
    http_response_code(400);
    exit('Username, email, and phone are required.');
}

try {
    foreach (firebaseRows('users') as $user) {
        $sameUser = (string) ($user['id'] ?? '') === $userId;
        $duplicate = ($user['username'] ?? '') === $username || ($user['email'] ?? '') === $email;
        if (!$sameUser && $duplicate) {
            http_response_code(409);
            exit('Username or email already exists.');
        }
    }

    $updates = [
        'username' => $username,
        'email' => $email,
        'phone' => $phone
    ];
    if ($newPassword !== '') {
        $updates['password'] = hash('sha256', $newPassword);
    }

    firebaseUpdate('users/' . rawurlencode($userId), $updates);
    logActivity('profile_updated', $userId, $username, $_SESSION['role'] ?? 'user');
    header('Location: user_dashboard.php?profile_updated=1');
    exit();
} catch (Throwable $error) {
    http_response_code(500);
    exit('Unable to update profile.');
}
?>
