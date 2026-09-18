<?php
include "config.php";

// Accept GET or POST so the device can send readings to the hosted endpoint.
$input = array_merge($_GET, $_POST);
$temp = $input['temp'] ?? $input['temperature'] ?? null;
$hum = $input['hum'] ?? $input['humidity'] ?? null;
$co = $input['co'] ?? $input['co_ppm'] ?? null;
$pm25 = $input['pm25'] ?? null;
$tempStatus = $input['tempStatus'] ?? '';
$humStatus = $input['humStatus'] ?? '';
$coStatus = $input['coStatus'] ?? '';

if ($temp === null || $hum === null || $co === null || $pm25 === null) {
    http_response_code(400);
    echo 'Missing temp, hum, co, or pm25 sensor data';
    exit();
}

$timestamp = gmdate('c');

if (firebasePush('sensor_data', [
    'source' => 'device',
    'temperature' => (float) $temp,
    'humidity' => (float) $hum,
    'co_ppm' => (float) $co,
    'temp_status' => $tempStatus,
    'hum_status' => $humStatus,
    'co_status' => $coStatus,
    'timestamp' => $timestamp
]) && firebasePush('sensor_logs', [
    'source' => 'device',
    'temp' => (float) $temp,
    'hum' => (float) $hum,
    'co' => (float) $co,
    'pm25' => (float) $pm25,
    'status' => $input['status'] ?? 'UNKNOWN',
    'timestamp' => $timestamp
])){
    echo 'Data inserted successfully';
} else {
    echo "Error inserting data";
}
?>
