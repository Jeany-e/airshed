<?php
// Firebase Realtime Database configuration. Set FIREBASE_DATABASE_SECRET in the
// web server environment; never expose it in browser JavaScript.
date_default_timezone_set('Asia/Manila');

const FIREBASE_DATABASE_URL = 'https://airshed-4ec87-default-rtdb.asia-southeast1.firebasedatabase.app';
$firebaseSecret = getenv('FIREBASE_DATABASE_SECRET');

if (!$firebaseSecret) {
    die('Firebase is not configured. Set FIREBASE_DATABASE_SECRET in the server environment.');
}

function firebaseRequest($path, $method = 'GET', $data = null) {
    global $firebaseSecret;
    $url = rtrim(FIREBASE_DATABASE_URL, '/') . '/' . trim($path, '/') . '.json?auth=' . rawurlencode($firebaseSecret);
    $ch = curl_init($url);
    $caCandidates = [
        'C:/wamp/bin/php/php8.2.0/extras/ssl/cacert.pem',
        'C:/wamp/bin/php/php8.1.13/extras/ssl/cacert.pem',
        'C:/wamp64/bin/php/php8.2.26/extras/ssl/cacert.pem',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem'
    ];
    $caFiles = array_values(array_filter($caCandidates, 'is_file'));
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15
    ];
    if ($caFiles) {
        $curlOptions[CURLOPT_CAINFO] = $caFiles[0];
    }
    curl_setopt_array($ch, $curlOptions);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        throw new RuntimeException('Firebase request failed: ' . ($error ?: $response));
    }
    return $response === '' ? null : json_decode($response, true);
}

function firebasePush($path, array $data) {
    return firebaseRequest($path, 'POST', $data);
}

function firebaseSet($path, $data) {
    return firebaseRequest($path, 'PUT', $data);
}

function firebaseUpdate($path, array $data) {
    return firebaseRequest($path, 'PATCH', $data);
}

function firebaseRows($path) {
    $items = firebaseRequest($path) ?: [];
    $rows = [];
    foreach ($items as $key => $item) {
        if (is_array($item)) {
            $item['id'] = $item['id'] ?? $key;
            $rows[] = $item;
        }
    }
    return $rows;
}

function logActivity($action, $userId = null, $username = null, $role = null) {
    try {
        firebasePush('activity_logs', [
            'action' => $action,
            'user_id' => $userId === null ? null : (string) $userId,
            'username' => $username,
            'role' => $role,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'timestamp' => gmdate('c')
        ]);
    } catch (Throwable $error) {
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>