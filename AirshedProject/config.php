<?php
// Firebase Realtime Database configuration. Set FIREBASE_DATABASE_SECRET in the
// web server environment; never expose it in browser JavaScript.
date_default_timezone_set('Asia/Manila');

const FIREBASE_DATABASE_URL = 'https://airshed-4ec87-default-rtdb.asia-southeast1.firebasedatabase.app';
$firebaseSecret = getenv('FIREBASE_DATABASE_SECRET');

if (!$firebaseSecret) {
    die('Firebase is not configured. Set FIREBASE_DATABASE_SECRET in the server environment.');
}

function firebaseRequest($path, $method = 'GET', $data = null, array $query = []) {
    global $firebaseSecret;
    $query = ['auth' => $firebaseSecret] + $query;
    $url = rtrim(FIREBASE_DATABASE_URL, '/') . '/' . trim($path, '/') . '.json?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
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

function firebaseRows($path, array $query = []) {
    $items = firebaseRequest($path, 'GET', null, $query) ?: [];
    $rows = [];
    foreach ($items as $key => $item) {
        if (is_array($item)) {
            $item['id'] = $item['id'] ?? $key;
            $rows[] = $item;
        }
    }
    return $rows;
}

function classifyPm25($value) {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return [
            'level' => 'UNKNOWN',
            'label' => 'Unknown',
            'color' => '#64748b'
        ];
    }

    $value = (float) $value;
    if (!is_finite($value) || $value < 0 || $value > 1000) {
        return [
            'level' => 'INVALID',
            'label' => 'Invalid',
            'color' => '#64748b'
        ];
    }

    if ($value <= 12) {
        return ['level' => 'GOOD', 'label' => 'Good', 'color' => '#168a68'];
    }
    if ($value <= 35) {
        return ['level' => 'MODERATE', 'label' => 'Moderate', 'color' => '#d88a18'];
    }
    if ($value <= 150) {
        return ['level' => 'UNHEALTHY', 'label' => 'Unhealthy', 'color' => '#ed6c02'];
    }
    return ['level' => 'HAZARDOUS', 'label' => 'Hazardous', 'color' => '#c43d4b'];
}

function buildHistoricalPm25Events(array $readings) {
    usort($readings, function ($a, $b) {
        return (strtotime($a['timestamp'] ?? '') ?: 0) <=> (strtotime($b['timestamp'] ?? '') ?: 0);
    });

    $events = [];
    $currentEvent = null;
    foreach ($readings as $reading) {
        if (($reading['source'] ?? '') !== 'device') {
            continue;
        }

        $timestamp = strtotime($reading['timestamp'] ?? '');
        $pm25 = $reading['pm25'] ?? $reading['pm2_5'] ?? $reading['pm2.5'] ?? null;
        $classification = classifyPm25($pm25);
        $isHighReading = $timestamp
            && is_numeric($pm25)
            && in_array($classification['level'], ['UNHEALTHY', 'HAZARDOUS'], true);

        if (!$isHighReading) {
            if ($currentEvent !== null) {
                $events[] = $currentEvent;
                $currentEvent = null;
            }
            continue;
        }

        $pm25 = (float) $pm25;
        if ($currentEvent !== null && $timestamp - $currentEvent['last_timestamp_unix'] > 1800) {
            $events[] = $currentEvent;
            $currentEvent = null;
        }
        if ($currentEvent === null) {
            $currentEvent = [
                'first_timestamp' => $reading['timestamp'],
                'last_timestamp' => $reading['timestamp'],
                'last_timestamp_unix' => $timestamp,
                'peak_pm25' => $pm25,
                'peak_classification' => $classification['label'],
                'reading_count' => 1
            ];
            continue;
        }

        $currentEvent['last_timestamp'] = $reading['timestamp'];
        $currentEvent['last_timestamp_unix'] = $timestamp;
        $currentEvent['reading_count']++;
        if ($pm25 > $currentEvent['peak_pm25']) {
            $currentEvent['peak_pm25'] = $pm25;
            $currentEvent['peak_classification'] = $classification['label'];
        }
    }

    if ($currentEvent !== null) {
        $events[] = $currentEvent;
    }
    return array_slice(array_reverse($events), 0, 50);
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