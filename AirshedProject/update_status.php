<?php
include "config.php";

if(isset($_GET['id'])) {
    $id = $_GET['id'];
    
    if(firebaseUpdate('pollution_reports/' . rawurlencode($id), ['status' => 'Resolved'])) {
        // Balik sa admin dashboard pagkatapos ng update
        header("Location: admin_dashboard.php");
        exit();
    } else {
        echo "Database Error";
    }
} else {
    header("Location: admin_dashboard.php");
}
?>