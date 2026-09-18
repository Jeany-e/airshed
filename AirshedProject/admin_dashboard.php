<?php
/** * STEP 1: Include config. 
 * Ensures database connection for User Monitoring.
 */
include "config.php"; 

// STEP 2: Role Check (Security)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Load Firebase collections and recreate the display fields used by the page.
$users = firebaseRows('users');
$userNames = [];
foreach ($users as $user) {
    $userNames[(string) $user['id']] = $user['username'] ?? 'Anonymous';
}
$user_result = $users;
$report_result = firebaseRows('pollution_reports');
foreach ($report_result as &$report) {
    $report['username'] = $userNames[(string) ($report['user_id'] ?? 0)] ?? 'Anonymous';
}
unset($report);
usort($report_result, function ($a, $b) {
    return strcmp($b['reported_at'] ?? '', $a['reported_at'] ?? '');
});
$unread_count = count(array_filter($report_result, function ($report) {
    return ($report['status'] ?? 'Pending') === 'Pending';
}));
$audit_logs = firebaseRows('audit_logs');
usort($audit_logs, function ($a, $b) {
    return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
});
$audit_logs = array_slice($audit_logs, 0, 10);
$activity_logs = firebaseRows('activity_logs');
usort($activity_logs, function ($a, $b) {
    return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
});
$activity_logs = array_slice($activity_logs, 0, 20);
$feedback_rows = firebaseRows('feedback');
usort($feedback_rows, function ($a, $b) {
    return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
});
$feedback_count = count($feedback_rows);
$feedback_average = $feedback_count
    ? round(array_sum(array_map(function ($feedback) { return (int) ($feedback['rating'] ?? 0); }, $feedback_rows)) / $feedback_count, 1)
    : 0;
$feedback_rows = array_slice($feedback_rows, 0, 20);
$alert_delivery_logs = firebaseRows('alert_delivery_logs');
usort($alert_delivery_logs, function ($a, $b) {
    return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
});
$alert_delivery_logs = array_slice($alert_delivery_logs, 0, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airshed MS | Calapan City Admin</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --primary: #174ea6; --ai-purple: #2767b5; --accent: #0f9f86; --bg: #e5f4ff; 
            --card-bg: #ffffff; --danger: #d94b4b; --warning: #d88a18; --text: #263b4d;
            --blue-anim: linear-gradient(135deg, #78c5ef, #bce8fa, #4f9bd3);
        }

        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(120deg, #d5efff, #f8fcff, #c6e9fb); background-size: 300% 300%; animation: pageBlueFlow 11s ease-in-out infinite; display: flex; height: 100vh; overflow: hidden; color: var(--text); }

        /* Sidebar */
        .sidebar { 
            width: 280px; background: var(--blue-anim); background-size: 240% 240%;
            animation: gradientBG 8s ease-in-out infinite; color: #fff; padding: 30px 20px; 
            display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(36,104,157,0.18); 
        }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes pageBlueFlow { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .sidebar h2 { font-size: 20px; text-align: center; color: #174ea6; border-bottom: 2px solid rgba(23,78,166,0.16); padding-bottom: 20px; margin-bottom: 20px; }
        .nav-link { padding: 15px 20px; border-radius: 10px; cursor: pointer; margin: 8px 0; transition: all 0.3s ease; display: flex; align-items: center; gap: 12px; color: #17466f; text-decoration: none; }
        .nav-link:hover { background: rgba(255,255,255,0.58); color: var(--primary); transform: translateX(8px); }
        .nav-link.active { background: #fff; color: var(--primary); font-weight: bold; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        
        .notif-badge {
            background: var(--danger); color: white; font-size: 10px; padding: 2px 8px;
            border-radius: 20px; font-weight: bold; margin-left: auto; border: 2px solid rgba(255,255,255,0.2);
        }

        .logout-btn { margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.28); border: 1px solid rgba(23,78,166,0.18); color: #17466f; text-align: center; border-radius: 10px; text-decoration: none; font-weight: bold; }

        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .header { background: rgba(255,255,255,0.9); padding: 20px 40px; border-bottom: 1px solid #cce5f4; display: flex; justify-content: space-between; align-items: center; }
        .page-title { width: 100%; padding: 18px 40px 0; color: #38566e; font-size: 18px; font-weight: 700; }
        .scroll-area { padding: 30px; overflow-y: auto; flex: 1; min-width: 0; }

        .view-section { display: none; animation: fadeIn 0.5s ease; width: 100%; min-width: 0; }
        .active-view { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .dash-grid { display: grid; grid-template-columns: 1fr; gap: 25px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        
        .card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; text-align: center; position: relative; border: 1px solid #c9e4f2; border-top: 5px solid var(--primary); animation: panelFloat 5.5s ease-in-out infinite; }
        .ai-card { border-top: 5px solid var(--primary); background: #fff; text-align: left; margin-bottom: 25px; }
        .range-selector { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; background: #fff; border: 1px solid #c9e4f2; border-radius: 22px; padding: 16px 18px; margin-bottom: 18px; box-shadow: 0 10px 28px rgba(36,104,157,0.10); }
        .range-selector .selector-label { color: #4e5667; font-weight: 700; font-size: 13px; white-space: nowrap; }
        .pred-range-btn { min-width: 70px; padding: 10px 18px; border-radius: 999px; border: 1px solid #c9e4f2; background: #eef8ff; color: #38566e; cursor: pointer; transition: all 0.25s ease; font-weight: 700; }
        .pred-range-btn.active, .pred-range-btn:hover { background: var(--primary); color: #fff; box-shadow: 0 12px 24px rgba(23,78,166,0.18); }
        .forecast-meta { margin-bottom: 12px; color: #5f677a; font-size: 13px; }
        .forecast-card { background: #fff; padding: 22px; border-radius: 18px; border: 1px solid #c9e4f2; box-shadow: 0 12px 26px rgba(36,104,157,0.12); animation: panelFloat 5.5s ease-in-out 0.4s infinite; }
        
        .card label { display: block; font-size: 11px; font-weight: 800; color: #7f8c8d; text-transform: uppercase; margin-bottom: 10px; }
        .val { font-size: 38px; font-weight: 900; color: var(--primary); }
        .unit { font-size: 14px; color: #95a5a6; }

        .table-container { background: #fff; padding: 20px; border-radius: 15px; margin-top: 25px; overflow: hidden; border: 1px solid #c9e4f2; box-shadow: 0 14px 30px rgba(36,104,157,0.14); animation: panelFloat 5.5s ease-in-out 0.8s infinite; }
        .table-scroll { max-width: 100%; max-height: min(55vh, 500px); overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .history-table-wrap { max-height: min(42vh, 360px); overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .chart-scroll { width: 100%; overflow-x: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .chart-track { width: 1200px; height: 260px; }
        .chart-track canvas { display: block; width: 1200px !important; height: 260px !important; max-width: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #eef8ff; padding: 15px; font-size: 12px; color: var(--primary); border-bottom: 2px solid #c9e4f2; text-transform: uppercase; text-align: center; }
        td { padding: 12px; text-align: center; border-bottom: 1px solid #e0f0f8; font-size: 14px; }

        .export-btn { float: right; background: var(--accent); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .status-badge { font-size: 32px; font-weight: 800; background: #fff; padding: 10px 25px; border-radius: 50px; display: inline-block; margin: 15px 0; }
        .advisory-card { background: #fff; padding: 15px; border-radius: 15px; border: 1px solid #c9e4f2; border-left: 8px solid var(--accent); margin-bottom: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; animation: panelFloat 5.5s ease-in-out 0.2s infinite; }
        .guide-card { background: #fff; padding: 22px; border-radius: 18px; border: 1px solid #c9e4f2; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; margin-bottom: 20px; animation: panelFloat 5.5s ease-in-out 1.1s infinite; }
        .guide-card h3 { margin: 0 0 12px 0; font-size: 16px; color: var(--primary); }
        .guide-card p { margin: 0 0 14px 0; color: #5f677a; font-size: 14px; }
        .guide-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .guide-list li { background: #eef8ff; color: #38566e; padding: 12px 14px; border-radius: 12px; font-size: 13px; line-height: 1.5; }
        .guide-list strong { color: var(--primary); }
        @keyframes panelFloat { 0%, 100% { translate: 0 0; } 50% { translate: 0 -6px; } }
        @media (prefers-reduced-motion: reduce) { body, .card, .forecast-card, .table-container, .guide-card { animation: none; } }
        
        /* Resolve Button Style */
        .resolve-btn {
            margin-top: 8px; background: var(--primary); color: white; border: none; 
            padding: 6px 12px; border-radius: 6px; font-size: 10px; cursor: pointer; 
            font-weight: bold; width: 100%; transition: opacity 0.3s;
        }
        .resolve-btn:hover { opacity: 0.8; }
        .promote-btn { background: var(--ai-purple); color: #fff; border: 0; border-radius: 6px; padding: 7px 10px; font-size: 10px; font-weight: 800; cursor: pointer; }
        .promote-btn:hover { opacity: .85; }

        @media (max-width: 900px) {
            body { display: block; height: auto; min-height: 100vh; overflow: auto; }
            .sidebar {
                width: 100%;
                min-width: 0;
                padding: 14px 10px 12px;
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .sidebar h2 { width: 100%; margin: 0 0 8px; padding-bottom: 12px; font-size: 19px; }
            .nav-link {
                display: inline-flex;
                width: auto;
                flex: 1 1 calc(50% - 8px);
                justify-content: center;
                padding: 10px 12px;
                margin: 0;
                font-size: 12px;
                border-radius: 10px;
            }
            .logout-btn {
                width: 100%;
                display: block;
                margin-top: 8px;
                padding: 12px;
            }
            .main { height: auto; min-height: calc(100vh - 155px); }
            .header { padding: 16px 18px; }
            .scroll-area { padding: 18px; }
            .dash-grid { grid-template-columns: 1fr; }
            .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .chart-track { width: 1000px; }
        }

        @media (max-width: 520px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            body {
                background: #edf2ff;
            }

            .sidebar {
                padding: 12px 10px;
                gap: 8px;
            }

            .nav-link {
                flex: 1 1 100%;
                justify-content: center;
                padding: 10px 12px;
                margin: 0;
            }

            .page-title {
                padding: 14px 18px 0;
            }

            .header {
                padding: 16px 18px;
            }

            .metrics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .card {
                padding: 18px 12px;
            }

            .range-selector {
                gap: 8px;
                padding: 12px 10px;
            }

            .pred-range-btn {
                min-width: 54px;
                padding: 9px 12px;
                font-size: 11px;
            }

            .forecast-card {
                padding: 18px 12px;
            }

            .card,
            .table-container,
            .guide-card {
                padding: 16px 12px;
            }

            .table-scroll,
            .history-table-wrap {
                overflow-x: hidden;
            }

            .chart-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-scroll table,
            .history-table-wrap table {
                min-width: 0;
                width: 100%;
                table-layout: fixed;
            }

            .chart-track {
                width: 900px;
            }

            th,
            td {
                font-size: 10px;
                line-height: 1.35;
                padding: 9px 4px;
                overflow-wrap: anywhere;
                vertical-align: middle;
            }
        }

        @media (max-width: 360px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .nav-link {
                font-size: 11px;
            }

            .header {
                padding: 14px 16px;
            }

            .scroll-area {
                padding: 14px;
                width: 100%;
                min-width: 0;
            }

            .chart-track {
                width: 820px;
            }
        }

        .mobile-menu-toggle, .sidebar-backdrop { display: none; }

        @media (max-width: 900px) {
            .mobile-menu-toggle {
                display: inline-flex; align-items: center; justify-content: center;
                position: fixed; top: 12px; left: 12px; z-index: 1002;
                width: 78px; height: 42px; gap: 7px; border: 0; border-radius: 12px;
                background: var(--primary); color: #fff; font-size: 21px; cursor: pointer;
                box-shadow: 0 8px 20px rgba(26,35,126,.25);
            }

            .mobile-menu-label {
                font-size: 10px;
                font-weight: 800;
                letter-spacing: .05em;
            }
            .sidebar {
                position: fixed; inset: 0 auto 0 0; z-index: 1000;
                width: min(290px, 86vw); min-width: 0; height: 100vh;
                display: flex; flex-direction: column; flex-wrap: nowrap; align-items: stretch;
                padding: 72px 20px 20px;
                overflow-y: auto; transform: translateX(-105%);
                transition: transform .25s ease; box-shadow: 12px 0 30px rgba(10,18,60,.25);
            }

            .sidebar h2 { color: var(--primary); border-bottom-color: rgba(23,78,166,0.16); }

            .sidebar .nav-link {
                display: flex;
                width: 100%;
                flex: 0 0 auto;
                margin: 6px 0;
                justify-content: flex-start;
                color: #17466f;
            }

            .sidebar .nav-link:hover {
                background: rgba(255,255,255,0.58);
                color: var(--primary);
            }

            .sidebar .nav-link.active {
                color: var(--primary);
                background: #fff;
            }

            .sidebar .logout-btn {
                color: #17466f;
                border-color: rgba(23,78,166,0.18);
                background: rgba(255,255,255,0.28);
                padding: 9px 12px;
                font-size: 13px;
                border-radius: 9px;
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-backdrop {
                position: fixed; inset: 0; z-index: 999; background: rgba(10,18,60,.45);
            }
            .sidebar-backdrop.open { display: block; }
            .header { padding-left: 102px; }
        }

        @media (max-width: 520px) {
            .header {
                min-height: 68px;
                padding: 14px 14px 14px 102px;
            }

            .header h1 {
                max-width: 220px;
                font-size: 15px !important;
                line-height: 1.25;
            }

            .page-title {
                padding: 14px 14px 0;
                font-size: 15px;
            }

            .scroll-area {
                padding: 14px;
            }

            .advisory-card {
                align-items: flex-start;
                padding: 14px;
                gap: 10px;
                border-radius: 16px;
            }

            .advisory-card p {
                font-size: 12px !important;
                line-height: 1.45;
            }

            .ai-card,
            .guide-card,
            .table-container,
            .card {
                border-radius: 16px;
                max-width: 100%;
                min-width: 0;
                width: 100%;
            }

            .dash-grid,
            .dash-grid > div,
            .view-section { min-width: 0; max-width: 100%; width: 100%; }

            .ai-card {
                padding: 16px 12px;
            }

            .ai-card h3 {
                font-size: 15px !important;
            }

            .range-selector {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 7px;
                padding: 10px;
                border-radius: 16px;
            }

            .range-selector .selector-label {
                grid-column: 1 / -1;
            }

            .pred-range-btn {
                min-width: 0;
                padding: 9px 6px;
                font-size: 11px;
            }

            .forecast-card {
                padding: 16px 12px;
            }

            .metrics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .metrics-grid .card {
                min-width: 0;
                padding: 17px 8px;
            }

            .metrics-grid .val {
                font-size: 29px;
            }

            .guide-card {
                padding: 16px 12px;
            }

            .guide-card h3 {
                font-size: 15px;
                line-height: 1.3;
            }

            .guide-card p,
            .guide-list li {
                font-size: 12px;
                line-height: 1.45;
                overflow-wrap: anywhere;
            }

            .chart-scroll {
                overflow: hidden;
                max-width: 100%;
                width: 100%;
            }

            .chart-track,
            .chart-track canvas {
                width: 100% !important;
                max-width: 100%;
                height: 220px !important;
            }

            .table-container {
                padding: 14px 10px;
            }

            .table-container .export-btn {
                float: none;
                display: inline-block;
                width: auto;
                margin-bottom: 10px;
                padding: 7px 10px;
                font-size: 11px;
            }

            .admin-status-strip {
                grid-template-columns: 1fr !important;
                gap: 10px;
                margin-bottom: 16px;
            }

            .admin-status-strip .card {
                margin: 0 !important;
                padding: 15px 14px;
            }

            .admin-status-strip .status-badge {
                font-size: 22px;
                padding: 7px 16px;
                margin: 10px 0;
                max-width: 100%;
                box-sizing: border-box;
            }

            .admin-status-strip .card p {
                margin-bottom: 6px;
            }
        }

    </style>
</head>
<body>

<button class="mobile-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" onclick="toggleMobileSidebar()"><span aria-hidden="true">☰</span><span class="mobile-menu-label">MENU</span></button>
<div class="sidebar-backdrop" onclick="closeMobileSidebar()"></div>
<div class="sidebar">
    <h2>CITY AIRSHED MS</h2>
    <div class="nav-link active" onclick="switchView('v-dash', this)">📊 Dashboard</div>
    
    <div class="nav-link" onclick="switchView('v-reports', this)">
        📢 User Reports 
        <?php if($unread_count > 0): ?>
            <span class="notif-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </div>

    <div class="nav-link" onclick="switchView('v-users', this)">👥 User & Admin Management</div>
    <div class="nav-link" onclick="switchView('v-net', this)">⚙️ Device Network</div>
    <div class="nav-link" onclick="switchView('v-feedback', this)">⭐ User Feedback</div>
    <div class="nav-link" onclick="switchView('v-alerts', this)">🔔 Alert History</div>
    <a class="nav-link" href="sensor_reports.php">📈 Sensor Reports</a>
    <a href="logout.php" class="logout-btn">🚪 Sign Out</a>
</div>

<div class="main">
    <div class="header">
        <h1 style="font-size: 20px; color: var(--primary); margin: 0; font-weight: 800;">IoT Airshed Monitoring - Calapan City</h1>
        <div id="clock" style="font-weight: 800; background: #eef2ff; padding: 8px 18px; border-radius: 50px; color: var(--primary);"></div>
    </div>
    <div id="pageTitle" class="page-title">Dashboard Overview</div>

    <div class="scroll-area">
        <div id="v-dash" class="view-section active-view">
            <div id="healthPanel" class="advisory-card">
                <div id="advIcon" style="font-size: 30px;">🍃</div>
                <div>
                    <h4 style="margin: 0; color: var(--primary);" id="advTitle">Air Quality Advisory</h4>
                    <p style="margin: 3px 0 0 0; font-size: 14px;" id="advText">Waiting for sensor data...</p>
                </div>
            </div>

            <div class="admin-status-strip" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                <div class="card" style="background: var(--blue-anim); background-size: 400% 400%; color: #fff; border-top: none;">
                    <h4 style="margin:0; opacity: 0.9; letter-spacing: 1px;">CURRENT STATUS</h4>
                    <div id="statusText" class="status-badge" style="color: var(--primary);">FETCHING</div>
                    <p id="advice" style="font-size: 13px; line-height: 1.5;">Establishing IoT node connection...</p>
                </div>
                <div class="card" style="text-align: left; border-top: none; border-right: 5px solid var(--accent);">
                    <h4 style="margin: 0 0 10px 0; font-weight: 800;">Hardware Health</h4>
                    <p style="font-size: 14px;">📡 <b>Link:</b> <span id="sideStatus">Checking...</span></p>
                    <p style="font-size: 14px;">🔋 <b>Battery:</b> <span id="bat">--</span>%</p>
                    <p style="font-size: 12px; color: #95a5a6; margin-top: 15px;">Node ID: CAL-STATION-01</p>
                </div>
            </div>

            <div class="card ai-card">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 18px; color: var(--ai-purple); font-weight: 800;">AI PREDICTIVE ANALYTICS</h3>
                </div>
                <div class="range-selector">
                    <span class="selector-label">Prediction Range</span>
                    <button class="pred-range-btn active" onclick="setPredictionRange(5,this)">5m</button>
                    <button class="pred-range-btn" onclick="setPredictionRange(60,this)">1h</button>
                    <button class="pred-range-btn" onclick="setPredictionRange(120,this)">2h</button>
                    <button class="pred-range-btn" onclick="setPredictionRange(360,this)">6h</button>
                    <button class="pred-range-btn" onclick="setPredictionRange(720,this)">12h</button>
                </div>
                <div id="selectedRangeLabel" class="forecast-meta">Viewing next 5 minute forecast</div>
                <div class="forecast-card">
                    <div class="forecast-meta" id="forecastRangeLabel">Next 5 minute forecast</div>
                    <div style="display: flex; align-items: baseline; justify-content: center; gap: 5px;">
                        <span id="forecastVal" style="font-size: 40px; font-weight: 900; color: var(--ai-purple);">---</span>
                        <span class="unit">µg/m³</span>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:14px;">
                    <div style="background:#f6f4ff; padding:14px; border-radius:12px; border:1px solid #e8e0ff; text-align:center;">
                        <div style="font-size:11px; font-weight:800; color:var(--ai-purple); text-transform:uppercase;">Humidity Forecast</div>
                        <div id="humidityForecast" style="font-size:24px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                    </div>
                    <div style="background:#ecfdf3; padding:14px; border-radius:12px; border:1px solid #dcfce7; text-align:center;">
                        <div style="font-size:11px; font-weight:800; color:#2e7d32; text-transform:uppercase;">Weather Outlook</div>
                        <div id="weatherForecast" style="font-size:18px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                    </div>
                </div>
                <div style="background: white; padding: 15px; border-radius: 12px; border: 1px solid #e0e0e0; display: flex; align-items: center; margin-top: 14px;">
                    <div>
                        <label>MACHINE LEARNING INSIGHT</label>
                        <div id="aiInsight" style="font-size: 16px; font-weight: 600; color: #2c3e50;">
                            Initializing pattern recognition...
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-grid">
                <div>
                    <div class="metrics-grid">
                        <div class="card"><label>Temperature</label><span class="val" id="temp">--</span><span class="unit">°C</span></div>
                        <div class="card"><label>Humidity</label><span class="val" id="hum">--</span><span class="unit">%</span></div>
                        <div class="card" style="border-top-color: #00bcd4;"><label>PM2.5 Level</label><span class="val" id="pm25">--</span><span class="unit">µg</span></div>
                        <div class="card" style="border-top-color: #009688;"><label>CO Gas</label><span class="val" id="co">--</span><span class="unit">ppm</span></div>
                    </div>

                    <div class="guide-card">
                        <h3>How to Read Your Airshed Dashboard</h3>
                        <p>These guidelines clarify what each measurement means and what action to take.</p>
                        <ul class="guide-list">
                            <li><strong>PM2.5</strong>: 0–12 = Good, 12–35 = Moderate, 35+ = Poor. High values mean unhealthy air.</li>
                            <li><strong>CO Gas</strong>: Target below 9 ppm. Elevated CO indicates combustion or poor ventilation.</li>
                            <li><strong>Temperature</strong>: Ideal comfort range is 20–28°C. Extreme heat can reduce air quality perception.</li>
                            <li><strong>Humidity</strong>: 30–60% is optimal. Below 30% feels dry; above 60% feels heavy.</li>
                            <li><strong>System status</strong>: Online means the device is connected; Offline means the dashboard cannot reach the sensor node.</li>
                        </ul>
                    </div>

                    <div class="card" style="padding: 20px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 14px; text-align: left; color: var(--primary); font-weight: 800;">LIVE POLLUTANT & TEMPERATURE TREND</h3>
                        <div class="chart-scroll"><div class="chart-track"><canvas id="pollutantChart" width="1200" height="260"></canvas></div></div>
                    </div>

                    <div class="table-container">
                        <button class="export-btn" onclick="exportToCSV()">📥 Export CSV Data</button>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800;">📊 Real-time Sensor Log History</h3>
                        <div class="history-table-wrap">
                            <table id="fullLogTable">
                                <thead>
                                    <tr><th>Timestamp</th><th>Temp</th><th>Humid</th><th>PM2.5</th><th>CO Gas</th><th>AQI Status</th><th>Device</th></tr>
                                </thead>
                                <tbody id="historyBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="v-reports" class="view-section">
            <div class="table-container" style="border-top: 5px solid var(--danger);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: var(--danger); margin:0;">📢 Public Incident Reports</h3>
                    <span style="font-size: 12px; color: #666;">Incoming messages from mobile application users</span>
                </div>
                <div class="table-scroll"><table>
                    <thead>
                        <tr><th>Time Reported</th><th>User</th><th>Incident</th><th>Location</th><th>Description</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($report_result as $report): ?>
                        <tr>
                            <td><small><?php echo date('M d, g:i A', strtotime($report['reported_at'])); ?></small></td>
                            <td><b><?php echo htmlspecialchars($report['username']); ?></b></td>
                            <td style="color: var(--primary); font-weight: bold;"><?php echo $report['incident_type']; ?></td>
                            <td><small><?php echo $report['location']; ?></small></td>
                            <td style="text-align: left; font-size: 13px; max-width: 250px;"><?php echo $report['description']; ?></td>
                            <td>
                                <span style="padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: bold; 
                                    background: <?php echo ($report['status'] == 'Pending') ? '#fff3e0' : '#e8f5e9'; ?>; 
                                    color: <?php echo ($report['status'] == 'Pending') ? '#ef6c00' : '#2e7d32'; ?>;">
                                    <?php echo strtoupper($report['status']); ?>
                                </span>
                                <?php if($report['status'] == 'Pending'): ?>
                                    <br>
                                    <button onclick="confirmResolve(<?php echo $report['id']; ?>)" class="resolve-btn">
                                        RESOLVE NOW
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>

        <div id="v-users" class="view-section">
             <div class="table-container" style="border-top: 5px solid var(--ai-purple);">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <h3 style="color: var(--ai-purple); margin:0;">👥 User & Admin Management</h3>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input id="userSearch" type="search" placeholder="Search users..." style="padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px;">
                        <select id="roleFilter" style="padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px;">
                            <option value="all">All roles</option>
                            <option value="admin">Admins</option>
                            <option value="user">Users</option>
                        </select>
                    </div>
                </div>
                <div class="table-scroll"><table>
                    <thead>
                        <tr><th>ID</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Registered</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($user_result as $user): ?>
                        <tr class="user-row" data-role="<?php echo htmlspecialchars($user['role'] ?? 'user', ENT_QUOTES); ?>">
                            <td>#<?php echo $user['id']; ?></td>
                            <td style="font-weight: bold; color: var(--primary);"><?php echo $user['username']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['phone']; ?></td>
                            <td><span style="background: #eef2ff; padding: 4px 10px; border-radius: 5px; font-size: 12px; font-weight: bold;"><?php echo strtoupper($user['role']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if (($user['role'] ?? 'user') !== 'admin'): ?>
                                    <form method="POST" action="promote_user.php" onsubmit="return confirm('Make this user an administrator?');">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>">
                                        <button type="submit" class="promote-btn">MAKE ADMIN</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #2e7d32; font-size: 11px; font-weight: 800;">ADMIN</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
            <div class="table-container" style="border-top: 5px solid var(--accent);">
                <h3 style="color: var(--primary);">Recent Admin Activity</h3>
                <?php if (!$audit_logs): ?>
                    <p style="color:#7f8c8d;">No admin activity recorded yet.</p>
                <?php else: ?>
                    <div class="table-scroll"><table>
                        <thead><tr><th>Action</th><th>Target User</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($audit_logs as $audit): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($audit['action'] ?? 'Admin action'); ?></td>
                                    <td><?php echo htmlspecialchars($audit['target_user_id'] ?? ''); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($audit['timestamp'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
            <div class="table-container" style="border-top: 5px solid #00bcd4;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <h3 style="color: var(--primary); margin:0;">Web Activity Log</h3>
                    <input id="activitySearch" type="search" placeholder="Search user, action, or date..." aria-label="Search Web Activity Log" style="padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; min-width:240px;">
                </div>
                <?php if (!$activity_logs): ?>
                    <p style="color:#7f8c8d;">No web activity recorded yet.</p>
                <?php else: ?>
                    <div class="table-scroll"><table>
                        <thead><tr><th>User</th><th>Role</th><th>Action</th><th>IP Address</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($activity_logs as $activity): ?>
                                <tr class="activity-row">
                                    <td><?php echo htmlspecialchars($activity['username'] ?? 'Unknown'); ?></td>
                                    <td><?php echo strtoupper(htmlspecialchars($activity['role'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($activity['action'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($activity['ip_address'] ?? ''); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($activity['timestamp'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>

        <div id="v-net" class="view-section">
            <div class="card" style="text-align: left; border-top: 5px solid var(--accent);">
                <h3 style="color: var(--primary);">Connected IoT Network</h3>
                <div class="table-scroll"><table style="width: 100%;">
                    <thead>
                        <tr><th>Device Name</th><th>IP Address</th><th>Status</th><th>Last Communication</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><b>CAL-NODE-01</b></td>
                            <td style="font-family: monospace;">IoT Device &rarr; Firebase</td>
                            <td id="netStatus"><span style="color:gray;">● Checking...</span></td>
                            <td id="lastSync">--</td>
                        </tr>
                    </tbody>
                </table></div>
            </div>
        </div>
        <div id="v-feedback" class="view-section">
            <div class="metrics-grid">
                <div class="card"><label>Total Ratings</label><span class="val"><?php echo $feedback_count; ?></span></div>
                <div class="card" style="border-top-color: #f9a825;"><label>Average Rating</label><span class="val" style="color:#f9a825;"><?php echo $feedback_average; ?> ★</span></div>
            </div>
            <div class="table-container" style="border-top: 5px solid #f9a825;">
                <h3 style="color: var(--primary); margin-top: 0;">Recent User Feedback</h3>
                <?php if (!$feedback_rows): ?>
                    <p style="color:#7f8c8d;">No ratings have been submitted yet.</p>
                <?php else: ?>
                    <div class="table-scroll"><table>
                        <thead><tr><th>User</th><th>Rating</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($feedback_rows as $feedback): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($feedback['username'] ?? 'Anonymous'); ?></td>
                                    <td style="color:#f9a825; font-size:18px; letter-spacing:2px;"><?php echo str_repeat('★', max(0, min(5, (int) ($feedback['rating'] ?? 0)))); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($feedback['timestamp'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
        <div id="v-alerts" class="view-section">
            <div class="table-container" style="border-top: 5px solid var(--danger);">
                <h3 style="color: var(--danger); margin-top: 0;">Critical Alert Delivery History</h3>
                <p style="color:#7f8c8d; font-size:13px;">Registered users who received critical air-quality alerts.</p>
                <?php if (!$alert_delivery_logs): ?>
                    <p style="color:#7f8c8d;">No critical alerts have been sent yet.</p>
                <?php else: ?>
                    <div class="table-scroll"><table>
                        <thead><tr><th>User</th><th>Email Address</th><th>PM2.5</th><th>Status</th><th>Date Sent</th></tr></thead>
                        <tbody>
                            <?php foreach ($alert_delivery_logs as $alert): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($alert['username'] ?? 'Unknown User'); ?></td>
                                    <td><?php echo htmlspecialchars($alert['email'] ?? ''); ?></td>
                                    <td><?php echo number_format((float) ($alert['pm25'] ?? 0), 1); ?> µg/m³</td>
                                    <td style="color:#2e7d32; font-weight:800;"><?php echo htmlspecialchars($alert['status'] ?? 'Sent'); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($alert['timestamp'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');
        const toggle = document.querySelector('.mobile-menu-toggle');
        const isOpen = sidebar.classList.toggle('open');
        backdrop.classList.toggle('open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function closeMobileSidebar() {
        document.querySelector('.sidebar').classList.remove('open');
        document.querySelector('.sidebar-backdrop').classList.remove('open');
        document.querySelector('.mobile-menu-toggle').setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMobileSidebar();
    });

    // Lahat ng dating JavaScript functions mo ay mananatili dito...
    let map;
    let lastEmailSentTime = 0;
    let selectedPredictionMinutes = 5;
    let tempHistory = [];
    let humHistory = [];
    let predictionHistory = [];
    let offlineReadingShown = false;
    let predictionLocked = false;
    let predictionSnapshot = { pm: null, hum: null, temp: null, outlook: 'Pleasant' };
    let predictionLastUpdatedAt = 0;
    let predictionWindowMs = 5 * 60 * 1000;

    function switchView(id, btn) {
        document.querySelectorAll('.view-section').forEach(v => v.classList.remove('active-view'));
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        document.getElementById(id).classList.add('active-view');
        btn.classList.add('active');
        const titles = {
            'v-dash': 'Dashboard Overview',
            'v-reports': 'Incident Reports',
            'v-users': 'User & Admin Management',
            'v-net': 'Device Network',
            'v-feedback': 'User Feedback',
            'v-alerts': 'Alert History'
        };
        document.getElementById('pageTitle').innerText = titles[id] || 'Airshed Monitor';
    }

    function confirmResolve(reportId) {
        Swal.fire({
            title: 'Resolve Incident?',
            text: "Are you sure you want to mark this report as resolved?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1a237e',
            cancelButtonColor: '#d32f2f',
            confirmButtonText: 'Yes, Resolve it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `update_status.php?id=${reportId}`;
            }
        })
    }

    const ctx = document.getElementById('pollutantChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: { 
            labels: [], 
            datasets: [
                { label: 'PM2.5 (µg/m³)', data: [], borderColor: '#1a237e', backgroundColor: 'rgba(26, 35, 126, 0.1)', fill: true, tension: 0.4, yAxisID: 'y' },
                { label: 'Temperature (°C)', data: [], borderColor: '#ff7043', backgroundColor: 'transparent', fill: false, tension: 0.4, yAxisID: 'y1' },
                { label: 'Humidity (%)', data: [], borderColor: '#00a896', backgroundColor: 'transparent', fill: false, tension: 0.4, yAxisID: 'y2' },
                { label: 'Gas/MQ', data: [], borderColor: '#8e44ad', backgroundColor: 'transparent', fill: false, tension: 0.4, yAxisID: 'y3' }
            ] 
        },
        options: { 
            responsive: false,
            maintainAspectRatio: false,
            animation: false, 
            scales: { 
                y: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'PM2.5' } },
                y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Temp (°C)' } },
                y2: { type: 'linear', position: 'left', beginAtZero: true, display: false, title: { display: true, text: 'Humidity (%)' } },
                y3: { type: 'linear', position: 'right', beginAtZero: true, display: false, title: { display: true, text: 'Gas/MQ' } }
            } 
        }
    });

    function setPredictionRange(minutes, btn) {
        selectedPredictionMinutes = minutes;
        predictionWindowMs = minutes * 60 * 1000;
        const rangeText = minutes === 720 ? '12 hours' : minutes === 360 ? '6 hours' : minutes === 120 ? '2 hours' : minutes === 60 ? '1 hour' : '5 minutes';
        document.getElementById('selectedRangeLabel').innerText = `Viewing next ${rangeText} forecast`;
        document.getElementById('forecastRangeLabel').innerText = `Next ${rangeText} forecast`;
        document.querySelectorAll('.pred-range-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        predictionLastUpdatedAt = Date.now();
        predictionLocked = false;
        updatePredictionSnapshot(true);
    }

    function runAIPrediction(dataPoints, minutes) {
        if (dataPoints.length < 3) return null;
        let n = dataPoints.length;
        let x = [], y = dataPoints;
        for (let i = 0; i < n; i++) x.push(i);
        let sumX = x.reduce((a,b) => a+b, 0), sumY = y.reduce((a,b) => a+b, 0);
        let sumXY = x.reduce((acc, curr, i) => acc + (curr * y[i]), 0);
        let sumXX = x.reduce((a,b) => a + (b*b), 0);
        let denom = (n * sumXX - sumX * sumX);
        if (denom === 0) return null;
        let slope = (n * sumXY - sumX * sumY) / denom;
        let intercept = (sumY - slope * sumX) / n;
        const sampleIntervalSeconds = 3;
        const steps = Math.max(1, Math.round((minutes * 60) / sampleIntervalSeconds));
        let targetIndex = (n - 1) + steps;
        return slope * targetIndex + intercept;
    }

    function getWeatherOutlook(temp, hum) {
        if (temp >= 33) return "Hot";
        if (temp >= 28) return "Warm";
        if (temp <= 20) return "Cool";
        return "Pleasant";
    }

    function updatePredictionSnapshot(force = false) {
        const now = Date.now();
        if (!force && predictionLocked && (now - predictionLastUpdatedAt < predictionWindowMs)) return;

        const pmHistory = predictionHistory.length ? predictionHistory.map(reading => reading.pm25) : chart.data.datasets[0].data;
        const currentTemp = tempHistory.length ? tempHistory[tempHistory.length - 1] : null;
        const currentHum = humHistory.length ? humHistory[humHistory.length - 1] : null;
        const forecast = runAIPrediction(pmHistory, selectedPredictionMinutes) ?? (pmHistory.length ? pmHistory[pmHistory.length - 1] : null);
        const forecastTemp = runAIPrediction(tempHistory, 5) ?? currentTemp;
        const forecastHum = runAIPrediction(humHistory, 5) ?? currentHum;
        const outlook = currentTemp !== null && currentHum !== null ? getWeatherOutlook(currentTemp, currentHum) : 'Pleasant';

        predictionSnapshot = { pm: forecast, hum: forecastHum, temp: forecastTemp, outlook };
        predictionLocked = true;
        predictionLastUpdatedAt = now;

        if (forecast !== null && pmHistory.length) {
            document.getElementById('forecastVal').innerText = Math.max(0, forecast).toFixed(1);
            const currentPM = pmHistory[pmHistory.length - 1];
            const insight = document.getElementById('aiInsight');
            let trendText = forecast > currentPM + 1.5 ? "📈 <span style='color:var(--danger)'>DETECTED UPWARD TREND</span>" : (forecast < currentPM - 1.5 ? "📉 <span style='color:var(--accent)'>DETECTED DOWNWARD TREND</span>" : "📊 <span style='color:var(--primary)'>STABLE PATTERN</span>");
            insight.innerHTML = `${trendText}<br><b>Weather:</b> ${outlook} with humidity forecast at ${forecastHum !== null ? `${forecastHum.toFixed(1)}%` : '--'}.`;
        }

        document.getElementById('humidityForecast').innerText = forecastHum !== null ? `${forecastHum.toFixed(1)}%` : '--';
        document.getElementById('weatherForecast').innerText = `${outlook} · ${forecastTemp !== null ? `${forecastTemp.toFixed(1)}°C` : '...'}`;
    }

    function runWatchdogAutomation(currentPM25) {
        const val = parseFloat(currentPM25);
        const now = Date.now();
        const cooldown = 30 * 60 * 1000; 

        if (val > 35 && (now - lastEmailSentTime > cooldown)) {
            let formData = new FormData();
            formData.append('pm25', val);
            fetch('automated_alerts.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(msg => { lastEmailSentTime = now; })
            .catch(err => console.error(err));
        }
    }

    function exportToCSV() {
        let csv = "Timestamp,Temperature,Humidity,PM2.5,CO Gas,AQI Status,Device Status\n";
        const rows = document.querySelectorAll("#historyBody tr");
        rows.forEach(row => {
            const cols = row.querySelectorAll("td");
            let rowData = [];
            cols.forEach(col => rowData.push(col.innerText.replace(/,/g, "")));
            csv += rowData.join(",") + "\n";
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Airshed_Data_${new Date().getTime()}.csv`;
        a.click();
    }

    function fetchData() {
        fetch('get_latest_data.php', { cache: 'no-store' })
        .then(res => res.json())
        .then(d => {
            if (d.live === false) {
                predictionHistory = d.prediction_history || [];
                tempHistory = predictionHistory.map(reading => Number(reading.temp));
                humHistory = predictionHistory.map(reading => Number(reading.hum));
                document.getElementById('temp').innerText = '--';
                document.getElementById('hum').innerText = '--';
                document.getElementById('pm25').innerText = '--';
                document.getElementById('co').innerText = '--';
                document.getElementById('bat').innerText = '--';
                document.getElementById('statusText').innerText = 'OFFLINE';
                document.getElementById('statusText').style.color = 'var(--danger)';
                document.getElementById('sideStatus').innerText = 'Offline';
                document.getElementById('sideStatus').style.color = 'var(--danger)';
                document.getElementById('netStatus').innerHTML = '<span style="color:var(--danger);">● Offline</span>';
                if (!offlineReadingShown) {
                    document.getElementById('historyBody').insertAdjacentHTML('afterbegin', `<tr><td><b>${new Date().toLocaleTimeString()}</b></td><td>--</td><td>--</td><td>--</td><td>--</td><td style="color:var(--danger); font-weight:bold;">OFFLINE</td><td style="color:var(--danger); font-weight:bold;">OFFLINE</td></tr>`);
                    offlineReadingShown = true;
                }
                chart.data.labels = [];
                chart.data.datasets.forEach(dataset => { dataset.data = []; });
                chart.update('none');
                predictionLocked = false;
                updatePredictionSnapshot(true);
                return;
            }
            predictionHistory = [];
            offlineReadingShown = false;
            const currentTemp = parseFloat(d.temp);
            const currentHum = parseFloat(d.hum);
            tempHistory.push(currentTemp);
            humHistory.push(currentHum);
            if (tempHistory.length > 10) tempHistory.shift();
            if (humHistory.length > 10) humHistory.shift();

            const outlook = getWeatherOutlook(currentTemp, currentHum);

            document.getElementById('temp').innerText = d.temp;
            document.getElementById('hum').innerText = d.hum;
            document.getElementById('pm25').innerText = d.pm25;
            document.getElementById('co').innerText = d.co;
            document.getElementById('bat').innerText = d.battery;

            runWatchdogAutomation(d.pm25);

            let status = d.pm25 > 35 ? "POOR" : (d.pm25 > 12 ? "MODERATE" : "GOOD");
            let color = d.pm25 > 35 ? "#d32f2f" : (d.pm25 > 12 ? "#fbc02d" : "#00c853");
            
            document.getElementById('statusText').innerText = status;
            document.getElementById('statusText').style.color = color;
            document.getElementById('sideStatus').innerText = "Online";
            document.getElementById('sideStatus').style.color = "var(--accent)";
            document.getElementById('netStatus').innerHTML = `<span style="color:var(--accent);">● Online</span>`;
            document.getElementById('lastSync').innerText = new Date().toLocaleTimeString();

            const advText = document.getElementById('advText');
            const advIcon = document.getElementById('advIcon');
            const adviceBox = document.getElementById('advice');
            if(d.pm25 > 35) {
                advIcon.innerText = "😷";
                advText.innerText = "Air quality is unhealthy. Masking is highly recommended.";
                adviceBox.innerText = "High PM2.5 detected. Limit outdoor exposure and avoid exercise outside.";
            } else if(d.pm25 > 12) {
                advIcon.innerText = "⚠️";
                advText.innerText = "Moderate conditions. Sensitive groups should stay alert.";
                adviceBox.innerText = "Moderate air pollution. Consider reducing prolonged outdoor activity.";
            } else {
                advIcon.innerText = "✅";
                advText.innerText = "Excellent air quality! Perfect for outdoor activities.";
                adviceBox.innerText = "Air quality is good. Routine outdoor activities are safe.";
            }

            fetch('save_all.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `temp=${encodeURIComponent(d.temp)}&hum=${encodeURIComponent(d.hum)}&co=${encodeURIComponent(d.co)}&pm25=${encodeURIComponent(d.pm25)}&status=${encodeURIComponent(status)}`
            }).catch(error => console.error('Firebase save error:', error));

            updatePredictionSnapshot();

            let t = new Date().toLocaleTimeString([], { hour12: false });
            chart.data.labels.push(t);
            chart.data.datasets[0].data.push(d.pm25);
            chart.data.datasets[1].data.push(d.temp);
            chart.data.datasets[2].data.push(d.hum);
            chart.data.datasets[3].data.push(d.co);
            if(chart.data.labels.length > 12) { chart.data.labels.shift(); chart.data.datasets.forEach(dataset => dataset.data.shift()); }
            chart.update('none');

            let row = `<tr><td><b>${t}</b></td><td>${d.temp}°C</td><td>${d.hum}%</td><td>${d.pm25}</td><td>${d.co}</td><td style="color:${color}; font-weight:bold;">${status}</td><td style="color:var(--accent); font-weight:bold;">ONLINE</td></tr>`;
            const hBody = document.getElementById('historyBody');
            hBody.insertAdjacentHTML("afterbegin", row);
            if(hBody.rows.length > 15) hBody.deleteRow(15);
        })
        .catch(err => {
            document.getElementById('statusText').innerText = "OFFLINE";
            document.getElementById('sideStatus').innerText = "Offline";
            document.getElementById('sideStatus').style.color = "var(--danger)";
        });
    }

    setInterval(fetchData, 3000);
    setInterval(() => document.getElementById('clock').innerText = new Date().toLocaleString(), 1000);

    const userSearch = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const activitySearch = document.getElementById('activitySearch');
    function filterUsers() {
        const query = (userSearch?.value || '').toLowerCase();
        const role = roleFilter?.value || 'all';
        document.querySelectorAll('.user-row').forEach(row => {
            const matchesText = row.innerText.toLowerCase().includes(query);
            const matchesRole = role === 'all' || row.dataset.role === role;
            row.style.display = matchesText && matchesRole ? '' : 'none';
        });
    }
    userSearch?.addEventListener('input', filterUsers);
    roleFilter?.addEventListener('change', filterUsers);
    activitySearch?.addEventListener('input', () => {
        const query = activitySearch.value.toLowerCase().trim();
        document.querySelectorAll('.activity-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });
</script>
</body>
</html>