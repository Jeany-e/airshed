<?php
/**
 * fetch_reports.php
 * Para sa AJAX Real-time update ng Pollution Reports table.
 */

include "config.php";

$users = firebaseRows('users');
$userNames = [];
foreach ($users as $user) {
    $userNames[(string) $user['id']] = $user['username'] ?? 'Anonymous';
}
$report_result = firebaseRows('pollution_reports');
foreach ($report_result as &$report) {
    $report['display_name'] = $userNames[(string) ($report['user_id'] ?? 0)] ?? 'Anonymous';
}
unset($report);
usort($report_result, function ($a, $b) {
    return strcmp($b['reported_at'] ?? '', $a['reported_at'] ?? '');
});

if (count($report_result) > 0) {
    foreach($report_result as $report) {
        // Dynamic styling base sa status
        $isPending = ($report['status'] == 'Pending');
        $statusBg = $isPending ? '#fff3e0' : '#e8f5e9';
        $statusColor = $isPending ? '#ef6c00' : '#2e7d32';
        $formattedTime = date('M d, g:i A', strtotime($report['reported_at']));
        
        echo "<tr>";
        echo "<td><small>{$formattedTime}</small></td>";
        echo "<td><b>" . htmlspecialchars($report['display_name']) . "</b></td>";
        echo "<td style='color: #1a237e; font-weight: bold;'>" . htmlspecialchars($report['incident_type']) . "</td>";
        echo "<td><small>" . htmlspecialchars($report['location']) . "</small></td>";
        echo "<td style='text-align: left; font-size: 13px; max-width: 250px;'>" . htmlspecialchars($report['description']) . "</td>";
        echo "<td>";
        echo "<span style='padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: bold; background: {$statusBg}; color: {$statusColor};'>";
        echo strtoupper($report['status']);
        echo "</span>";

        // Lalabas lang ang Resolve button kung Pending pa ang report
        if($isPending) {
            echo "<br>";
            echo "<button onclick='confirmResolve(" . htmlspecialchars(json_encode($report['id']), ENT_QUOTES) . ")' class='resolve-btn'>";
            echo "RESOLVE NOW";
            echo "</button>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' style='padding: 20px; color: #95a5a6;'>No incident reports found.</td></tr>";
}
?>