<?php /*
include "config.php"; // DB connection

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get POST data
$temp = isset($_POST['temp']) ? floatval($_POST['temp']) : null;
$hum  = isset($_POST['hum']) ? floatval($_POST['hum']) : null;
$co   = isset($_POST['co']) ? floatval($_POST['co']) : null;

// Compute status
$temp_status = ($temp !== null) ? ($temp > 30 ? "HIGH" : "NORMAL") : "UNKNOWN";
$hum_status  = ($hum !== null) ? ($hum < 40 ? "LOW" : "NORMAL") : "UNKNOWN";
$co_status   = ($co !== null) ? ($co > 10 ? "HIGH" : "NORMAL") : "UNKNOWN";

// Insert only if all values exist
if($temp !== null && $hum !== null && $co !== null) {
    $stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity, co_ppm, temp_status, hum_status, co_status, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("dddsds", $temp, $hum, $co, $temp_status, $hum_status, $co_status);

    if($stmt->execute()) {
        echo "Sensor data saved successfully";
    } else {
        echo "Error saving sensor data: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Missing sensor data";
}

$conn->close();
?> */