<?php
require __DIR__ . '/config.php';
require __DIR__ . '/forecast_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $cutoff = time() - 30 * 24 * 60 * 60;
    $records = [];
    foreach (firebaseRows('sensor_logs') as $record) {
        if (($record['source'] ?? '') !== 'device') {
            continue;
        }

        $timestamp = strtotime($record['timestamp'] ?? '');
        if (!$timestamp || $timestamp < $cutoff) {
            continue;
        }

        $normalize = function ($value, $minimum, $maximum) {
            if (!is_numeric($value)) {
                return null;
            }
            $number = (float) $value;
            return is_finite($number) && $number >= $minimum && $number <= $maximum ? $number : null;
        };
        $normalized = [
            'timestamp' => $record['timestamp'],
            'timestamp_unix' => $timestamp,
            'temp' => $normalize($record['temp'] ?? $record['temperature'] ?? null, -40, 85),
            'hum' => $normalize($record['hum'] ?? $record['humidity'] ?? null, 0, 100),
            'pm25' => $normalize($record['pm25'] ?? $record['pm2_5'] ?? $record['pm2.5'] ?? null, 0, 1000),
            'co' => $normalize($record['co'] ?? $record['mq_adc'] ?? $record['co_ppm'] ?? null, 0, 4095),
            'battery' => $record['battery'] ?? null,
            'latitude' => $normalize($record['latitude'] ?? null, -90, 90),
            'longitude' => $normalize($record['longitude'] ?? null, -180, 180),
            'status' => $record['status'] ?? null
        ];
        if ($normalized['temp'] !== null || $normalized['hum'] !== null || $normalized['pm25'] !== null) {
            $records[] = $normalized;
        }
    }

    usort($records, function ($a, $b) {
        return $a['timestamp_unix'] <=> $b['timestamp_unix'];
    });
    $records = array_slice($records, -5000);
    $latest = $records ? $records[count($records) - 1] : null;
    $latestAge = $latest ? time() - $latest['timestamp_unix'] : null;
    $live = $latestAge !== null && $latestAge >= -30 && $latestAge <= 45;
    $history = array_map(function ($record) {
        return [
            'timestamp' => $record['timestamp'],
            'temp' => $record['temp'],
            'hum' => $record['hum'],
            'pm25' => $record['pm25'],
            'co' => $record['co']
        ];
    }, array_slice($records, -15));

    $forecastFields = [
        'pm25' => ['minimum' => 0, 'maximum' => 1000],
        'temp' => ['minimum' => -40, 'maximum' => 85],
        'hum' => ['minimum' => 0, 'maximum' => 100]
    ];
    $fieldHistory = [];
    foreach ($forecastFields as $field => $limits) {
        $fieldHistory[$field] = array_values(array_filter($records, function ($record) use ($field) {
            return $record[$field] !== null;
        }));
    }

    $forecast30 = calculateValidatedForecast($fieldHistory['pm25'], 'pm25', 30 * 60, 0, 1000);
    $forecast2h = calculateValidatedForecast($fieldHistory['pm25'], 'pm25', 2 * 60 * 60, 0, 1000);
    $forecastTemp = calculateValidatedForecast($fieldHistory['temp'], 'temp', 30 * 60, -40, 85);
    $forecastHum = calculateValidatedForecast($fieldHistory['hum'], 'hum', 30 * 60, 0, 100);
    $forecastResults = [
        'forecast30' => $forecast30,
        'forecast2h' => $forecast2h,
        'forecast_temp' => $forecastTemp,
        'forecast_hum' => $forecastHum
    ];

    $forecastFieldKeys = [
        'forecast30' => 'pm25',
        'forecast2h' => 'pm25',
        'forecast_temp' => 'temp',
        'forecast_hum' => 'hum'
    ];
    foreach ($forecastFieldKeys as $forecastKey => $field) {
        if (!$live || ($latest[$field] ?? null) === null) {
            $forecastResults[$forecastKey]['value'] = null;
            $forecastResults[$forecastKey]['metrics']['available'] = false;
            $forecastResults[$forecastKey]['metrics']['reason'] = !$live
                ? 'The sensor is offline; estimates are paused.'
                : 'No current valid ' . strtoupper($field) . ' reading; estimates are paused.';
        }
    }
    $forecast30 = $forecastResults['forecast30'];
    $forecast2h = $forecastResults['forecast2h'];
    $forecastTemp = $forecastResults['forecast_temp'];
    $forecastHum = $forecastResults['forecast_hum'];

    $pmClassification = classifyPm25($latest['pm25'] ?? null);
    echo json_encode([
        'live' => $live,
        'temp' => $live ? $latest['temp'] : null,
        'hum' => $live ? $latest['hum'] : null,
        'pm25' => $live ? $latest['pm25'] : null,
        'co' => $live ? $latest['co'] : null,
        'battery' => $live ? $latest['battery'] : null,
        'status' => $live ? $pmClassification['label'] : 'OFFLINE',
        'pm25_status' => $live ? $pmClassification['level'] : 'UNKNOWN',
        'latitude' => $latest['latitude'] ?? null,
        'longitude' => $latest['longitude'] ?? null,
        'prediction_history' => $history,
        'forecast30' => $forecast30['value'] ?? null,
        'forecast2h' => $forecast2h['value'] ?? null,
        'forecast_temp' => $forecastTemp['value'] ?? null,
        'forecast_hum' => $forecastHum['value'] ?? null,
        'forecast_metrics' => [
            'forecast30' => $forecast30['metrics'],
            'forecast2h' => $forecast2h['metrics'],
            'forecast_temp' => $forecastTemp['metrics'],
            'forecast_hum' => $forecastHum['metrics']
        ],
        'forecast_basis' => [
            'pm25_30m' => $forecast30['basis'],
            'pm25_2h' => $forecast2h['basis'],
            'temp' => $forecastTemp['basis'],
            'hum' => $forecastHum['basis']
        ]
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Firebase sensor data is unavailable']);
}
?>
