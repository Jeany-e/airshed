<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('POST required.');
}

$sessionToken = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($sessionToken) || $sessionToken === '' || !is_string($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    exit('Invalid request token.');
}

$userIdInput = $_POST['id'] ?? null;
$usernameInput = $_POST['username'] ?? null;
if (!is_string($userIdInput) || !is_string($usernameInput)) {
    http_response_code(400);
    exit('Enter a valid account ID and username.');
}
$userId = trim($userIdInput);
$username = trim($usernameInput);
if (!preg_match('/^[A-Za-z0-9_-]+$/D', $userId)
    || preg_match('/^.{1,50}$/us', $username) !== 1
    || preg_match('/[\x00-\x1F\x7F]/u', $username) === 1) {
    http_response_code(400);
    exit('Enter a valid account ID and a username of up to 50 characters.');
}

try {
    $targetUser = null;
    foreach (firebaseRows('users') as $user) {
        if ((string) ($user['id'] ?? '') === $userId) {
            $targetUser = $user;
            continue;
        }
        if (strcasecmp(trim((string) ($user['username'] ?? '')), $username) === 0) {
            http_response_code(409);
            exit('That username is already in use.');
        }
    }

    if ($targetUser === null) {
        http_response_code(404);
        exit('Account not found.');
    }

    firebaseUpdate('users/' . $userId, ['username' => $username]);
    if ((string) ($_SESSION['user_id'] ?? '') === $userId) {
        $_SESSION['username'] = $username;
    }
    logActivity('Updated account name', $userId, $username, $targetUser['role'] ?? 'user');
    header('Location: admin_dashboard.php?account_name_updated=1#v-users');
    exit();
} catch (Throwable $error) {
    error_log('Unable to update account name: ' . $error->getMessage());
    http_response_code(503);
    exit('Account name could not be updated. Please try again later.');
}
