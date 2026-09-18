<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $deviceUrl = 'http://10.198.229.175/data';
    $deviceRequest = curl_init($deviceUrl);
    curl_setopt_array($deviceRequest, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2
    ]);
    $deviceResponse = curl_exec($deviceRequest);
    $deviceStatus = curl_getinfo($deviceRequest, CURLINFO_HTTP_CODE);
    curl_close($deviceRequest);

    if ($deviceResponse !== false && $deviceStatus >= 200 && $deviceStatus < 300) {
        $deviceData = json_decode($deviceResponse, true);
        if (is_array($deviceData)) {
            $reading = [
                'temp' => (float) ($deviceData['temp'] ?? $deviceData['temperature'] ?? 0),
                'hum' => (float) ($deviceData['hum'] ?? $deviceData['humidity'] ?? 0),
                'pm25' => (float) ($deviceData['pm25'] ?? 0),
                'co' => (float) ($deviceData['co'] ?? $deviceData['co_ppm'] ?? 0),
                'latitude' => isset($deviceData['latitude']) ? (float) $deviceData['latitude'] : (isset($deviceData['lat']) ? (float) $deviceData['lat'] : null),
                'longitude' => isset($deviceData['longitude']) ? (float) $deviceData['longitude'] : (isset($deviceData['lng']) ? (float) $deviceData['lng'] : (isset($deviceData['lon']) ? (float) $deviceData['lon'] : null)),
                'status' => $deviceData['status'] ?? 'UNKNOWN'
            ];
            $timestamp = gmdate('c');

            // Dashboards poll frequently, so save at most one identical reading every 30 seconds.
            $recentLogs = firebaseRows('sensor_logs');
            $latestLog = $recentLogs ? end($recentLogs) : null;
            $latestTimestamp = strtotime($latestLog['timestamp'] ?? '');
            $isDuplicate = $latestLog
                && $latestTimestamp
                && (time() - $latestTimestamp < 30)
                && (float) ($latestLog['temp'] ?? 0) === $reading['temp']
                && (float) ($latestLog['hum'] ?? 0) === $reading['hum']
                && (float) ($latestLog['pm25'] ?? 0) === $reading['pm25']
                && (float) ($latestLog['co'] ?? 0) === $reading['co'];

            if (!$isDuplicate) {
                firebasePush('sensor_logs', $reading + [
                    'source' => 'device',
                    'timestamp' => $timestamp
                ]);
            }

            echo json_encode([
                'live' => true,
                'temp' => $reading['temp'],
                'hum' => $reading['hum'],
                'pm25' => $reading['pm25'],
                'co' => $reading['co'],
                'status' => $reading['status'],
                'battery' => $deviceData['battery'] ?? null,
                'latitude' => $reading['latitude'],
                'longitude' => $reading['longitude'],
                'timestamp' => $timestamp
            ]);
            exit();
        }
    }

    $records = array_values(array_filter(firebaseRows('sensor_logs'), function ($record) {
        return ($record['source'] ?? '') === 'device';
    }));
    if (!$records) {
        $records = array_values(array_filter(firebaseRows('sensor_data'), function ($record) {
            return ($record['source'] ?? '') === 'device';
        }));
    }
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