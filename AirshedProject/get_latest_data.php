<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $records = array_values(array_filter(firebaseRows('sensor_logs'), function ($record) {
        return ($record['source'] ?? '') === 'device'
            && (isset($record['pm25']) || isset($record['pm2_5']) || isset($record['pm2.5']));
    }));
    foreach ($records as &$record) {
        $record['pm25'] = $record['pm25'] ?? $record['pm2_5'] ?? $record['pm2.5'];
    }
    unset($record);
    usort($records, function ($a, $b) {
        return strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? '');
    });
    $history = array_slice($records, -15);
    $latestLocation = $history ? end($history) : [];
    $latest = $history ? end($history) : null;
    $latestTimestamp = strtotime($latest['timestamp'] ?? '');
    $latestIsFresh = $latestTimestamp && (time() - $latestTimestamp <= 45);
    $predictionHistory = array_map(function ($record) {
        return [
            'temp' => (float) ($record['temp'] ?? $record['temperature'] ?? 0),
            'hum' => (float) ($record['hum'] ?? $record['humidity'] ?? 0),
            'pm25' => (float) ($record['pm25'] ?? 0),
            'co' => (float) ($record['co'] ?? $record['co_ppm'] ?? 0)
        ];
    }, $history);

    echo json_encode([
        'live' => (bool) $latestIsFresh,
        'temp' => $latestIsFresh ? (float) ($latest['temp'] ?? $latest['temperature'] ?? 0) : null,
        'hum' => $latestIsFresh ? (float) ($latest['hum'] ?? $latest['humidity'] ?? 0) : null,
        'pm25' => $latestIsFresh ? (float) ($latest['pm25'] ?? 0) : null,
        'co' => $latestIsFresh ? (float) ($latest['co'] ?? $latest['co_ppm'] ?? 0) : null,
        'status' => $latestIsFresh ? ($latest['status'] ?? 'UNKNOWN') : 'OFFLINE',
        'latitude' => isset($latestLocation['latitude']) ? (float) $latestLocation['latitude'] : null,
        'longitude' => isset($latestLocation['longitude']) ? (float) $latestLocation['longitude'] : null,
        'prediction_history' => $predictionHistory
    ]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Firebase sensor data is unavailable']);
}
?>