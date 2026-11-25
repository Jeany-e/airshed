<?php
include "config.php";

$deviceToken = getenv('AIRSHED_DEVICE_TOKEN');
if (!$deviceToken) {
    http_response_code(503);
    exit('Device ingestion is not configured.');
}
$providedToken = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
if ((!is_string($providedToken) || $providedToken === '') && isset($_POST['device_token'])) {
    $providedToken = $_POST['device_token'];
}
if (!is_string($providedToken) || !hash_equals($deviceToken, $providedToken)) {
    http_response_code(401);
    exit('Unauthorized device.');
}
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('GET or POST required.');
}

$input = array_merge($_GET, $_POST);
$parseReading = function ($value, $minimum, $maximum) {
    if (!is_scalar($value) || !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= $minimum && $number <= $maximum ? $number : null;
};
$temp = $parseReading($input['temp'] ?? $input['temperature'] ?? null, -40, 85);
$hum = $parseReading($input['hum'] ?? $input['humidity'] ?? null, 0, 100);
$co = $parseReading($input['co'] ?? $input['mq_adc'] ?? $input['co_ppm'] ?? null, 0, 4095);
$pm25 = $parseReading($input['pm25'] ?? null, 0, 1000);
if ($temp === null || $hum === null || $co === null || $pm25 === null) {
    http_response_code(400);
    exit('Provide valid temp/temperature, hum/humidity, co/mq_adc, and pm25 readings.');
}

$readStatus = function ($key, $default = '') use ($input) {
    $value = $input[$key] ?? $default;
    return is_scalar($value) ? substr((string) $value, 0, 32) : $default;
};
$readMetadata = function (array $keys, $environmentName) use ($input) {
    $value = null;
    foreach ($keys as $key) {
        $candidate = $input[$key] ?? null;
        if (is_scalar($candidate) && trim((string) $candidate) !== '') {
            $value = $candidate;
            break;
        }
    }
    if ($value === null) {
        $value = getenv($environmentName);
    }
    if (!is_scalar($value)) {
        return null;
    }
    $value = trim((string) $value);
    return $value === '' ? null : substr($value, 0, 100);
};
$sensorId = $readMetadata(['sensor_id', 'device_id'], 'AIRSHED_DEVICE_ID');
$timestamp = gmdate('c');
try {
    firebasePush('sensor_data', [
        'source' => 'device',
        'temperature' => $temp,
        'humidity' => $hum,
        'mq_adc' => $co,
        'temp_status' => $readStatus('tempStatus'),
        'hum_status' => $readStatus('humStatus'),
        'co_status' => $readStatus('coStatus'),
        'timestamp' => $timestamp
    ]);
    $sensorLog = [
        'source' => 'device',
        'temp' => $temp,
        'hum' => $hum,
        'co' => $co,
        'pm25' => $pm25,
        'status' => $readStatus('status', 'UNKNOWN'),
        'timestamp' => $timestamp
    ];
    if ($sensorId !== null) {
        $sensorLog['sensor_id'] = $sensorId;
    }
    firebasePush('sensor_logs', $sensorLog);
    echo 'Data inserted successfully';
} catch (Throwable $error) {
    error_log('Device ingestion failed: ' . $error->getMessage());
    http_response_code(502);
    echo 'Sensor data could not be saved.';
}
?>
