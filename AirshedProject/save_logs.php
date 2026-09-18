<?php
include "config.php";

$temp = $_POST['temp'] ?? null;
$hum  = $_POST['hum'] ?? null;
$co   = $_POST['co'] ?? null;
$pm25 = $_POST['pm25'] ?? null;
$status = $_POST['status'] ?? null;

// ✅ FIX: fallback
if(empty($status)){
    $status = "UNKNOWN";
}

if($temp !== null){
    if(firebasePush('sensor_logs', [
        'source' => 'dashboard',
        'temp' => (float) $temp,
        'hum' => (float) $hum,
        'co' => (float) $co,
        'pm25' => (float) $pm25,
        'status' => $status,
        'timestamp' => gmdate('c')
    ])){
        echo "OK";
    } else {
        echo "ERROR";
    }
} else {
    echo "NO DATA";
}
?>