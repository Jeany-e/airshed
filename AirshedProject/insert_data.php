<?php
include "config.php";

$deviceToken = getenv('AIRSHED_DEVICE_TOKEN');
$providedToken = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
if (!$deviceToken) {
    http_response_code(503);
    exit('Device ingestion is not configured.');
}
if (!is_string($providedToken) || !hash_equals($deviceToken, $providedToken)) {
    http_response_code(401);
    exit('Unauthorized device.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('POST required.');
}

$input = $_POST;
$parseReading = function ($value, $minimum, $maximum) {
    if (!is_scalar($value) || !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= $minimum && $number <= $maximum ? $number : null;
};
$temp = $parseReading($input['temp'] ?? $input['temperature'] ?? null, -40, 85);
$hum = $parseReading($input['hum'] ?? $input['humidity'] ?? null, 0, 100);
$co = $parseReading($input['co'] ?? $input['mq_adc'] ?? null, 0, 4095);
$pm25 = $parseReading($input['pm25'] ?? null, 0, 1000);
if ($temp === null || $hum === null || $co === null || $pm25 === null) {
    http_response_code(400);
    exit('Provide valid temperature, humidity, raw MQ ADC, and PM2.5 readings.');
}

$readStatus = function ($key, $default = '') use ($input) {
    $value = $input[$key] ?? $default;
    return is_scalar($value) ? substr((string) $value, 0, 32) : $default;
};
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
    firebasePush('sensor_logs', [
        'source' => 'device',
        'temp' => $temp,
        'hum' => $hum,
        'co' => $co,
        'pm25' => $pm25,
        'status' => $readStatus('status', 'UNKNOWN'),
        'timestamp' => $timestamp
    ]);
    echo 'Data inserted successfully';
} catch (Throwable $error) {
    error_log('Device ingestion failed: ' . $error->getMessage());
    http_response_code(502);
    echo 'Sensor data could not be saved.';
}
?>
