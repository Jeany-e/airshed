<?php
include "config.php";

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// get data
$temp   = $_POST['temp'] ?? null;
$hum    = $_POST['hum'] ?? null;
$co     = $_POST['co'] ?? null;
$pm25   = $_POST['pm25'] ?? null;
$status = $_POST['status'] ?? null;

if ($temp === null || $hum === null || $co === null || $pm25 === null) {
    http_response_code(400);
    echo "Missing sensor data";
    exit();
}

$sensorData = firebasePush('sensor_data', [
    'source' => 'dashboard',
    'temperature' => (float) $temp,
    'humidity' => (float) $hum,
    'co_ppm' => (float) $co,
    'temp_status' => 'OK',
    'hum_status' => 'OK',
    'co_status' => 'OK',
    'timestamp' => gmdate('c')
]);
$sensorLog = firebasePush('sensor_logs', [
    'source' => 'dashboard',
    'temp' => (float) $temp,
    'hum' => (float) $hum,
    'co' => (float) $co,
    'pm25' => (float) $pm25,
    'status' => $status,
    'timestamp' => gmdate('c')
]);

if ($sensorData && $sensorLog) {
    echo "Both data saved!";
} else {
    echo "Error saving sensor data";
}
?>
