<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $records = array_values(array_filter(firebaseRows('sensor_logs'), function ($record) {
        $pm25 = $record['pm25'] ?? $record['pm2_5'] ?? $record['pm2.5'] ?? null;
        return ($record['source'] ?? '') === 'device'
            && is_numeric($pm25)
            && (float) $pm25 >= 0
            && (float) $pm25 <= 1000;
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

    $calculateForecast = function (array $records, string $key, int $horizonMinutes) {
        $points = [];
        foreach ($records as $record) {
            $timestamp = strtotime($record['timestamp'] ?? '');
            $value = $key === 'temp'
                ? ($record['temp'] ?? $record['temperature'] ?? null)
                : ($key === 'hum'
                    ? ($record['hum'] ?? $record['humidity'] ?? null)
                    : ($record[$key] ?? null));
            if (!$timestamp || $value === null || !is_numeric($value)) continue;
            $value = (float) $value;
            if ($value < 0) continue;
            $points[] = ['time' => $timestamp, 'value' => $value];
        }

        if (count($points) < 5) return null;

        $origin = $points[0]['time'];
        $sumWeight = $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        $values = [];
        $count = count($points);
        foreach ($points as $index => $point) {
            $x = ($point['time'] - $origin) / 60.0;
            $y = $point['value'];
            $values[] = $y;
            $weight = 0.5 + 1.5 * ($index / max(1, $count - 1));
            $sumWeight += $weight;
            $sumX += $weight * $x;
            $sumY += $weight * $y;
            $sumXY += $weight * $x * $y;
            $sumX2 += $weight * $x * $x;
        }

        $denominator = ($sumWeight * $sumX2) - ($sumX * $sumX);
        if (abs($denominator) < 0.000001) return null;

        $slope = (($sumWeight * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = (($sumY - ($slope * $sumX)) / $sumWeight);
        $latest = $points[$count - 1];
        $latestX = ($latest['time'] - $origin) / 60.0;
        $projected = $intercept + ($slope * ($latestX + $horizonMinutes));

        $residualSquared = 0.0;
        foreach ($points as $index => $point) {
            $x = ($point['time'] - $origin) / 60.0;
            $weight = 0.5 + 1.5 * ($index / max(1, $count - 1));
            $expected = $intercept + ($slope * $x);
            $residualSquared += $weight * pow($point['value'] - $expected, 2);
        }
        $rmse = sqrt($residualSquared / $sumWeight);
        $baseline = max(10.0, abs($latest['value']) * 0.25);
        $confidence = 1.0 - min(1.0, $rmse / $baseline);

        $horizonFactor = $horizonMinutes <= 30 ? 0.85 : 0.35;
        $dampedTrend = ($projected - $latest['value']) * $horizonFactor * (0.45 + (0.55 * $confidence));
        $forecast = $latest['value'] + $dampedTrend;

        $observedMin = min($values);
        $observedMax = max($values);
        $allowedRange = max(10.0, ($observedMax - $observedMin) * 1.5);
        $forecast = max($observedMin - $allowedRange, min($observedMax + $allowedRange, $forecast));
        $forecast = max(0.0, $forecast);

        return round($forecast, 1);
    };

    $forecast30 = $calculateForecast($history, 'pm25', 30);
    $forecast2h = $calculateForecast($history, 'pm25', 120);
    $forecastTemp = $calculateForecast($history, 'temp', 30);
    $forecastHum = $calculateForecast($history, 'hum', 30);

    $predictionHistory = array_map(function ($record) {
        return [
            'temp' => (float) ($record['temp'] ?? $record['temperature'] ?? 0),
            'hum' => (float) ($record['hum'] ?? $record['humidity'] ?? 0),
            'pm25' => (float) ($record['pm25'] ?? 0),
            'co' => (float) ($record['co'] ?? $record['co_ppm'] ?? 0),
            'timestamp' => $record['timestamp'] ?? null
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
        'forecast30' => $forecast30,
        'forecast2h' => $forecast2h,
        'forecast_temp' => $forecastTemp,
        'forecast_hum' => $forecastHum,
        'prediction_history' => $predictionHistory
    ]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Firebase sensor data is unavailable']);
}
?>