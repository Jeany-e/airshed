<?php
/**
 * USER DASHBOARD - CALAPAN CITY AIRSHED MS (COMPLETE & ALIGNED)
 * Walang bawas, binalik lahat ng features na nawala.
 */
include "config.php"; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUser = null;
foreach (firebaseRows('users') as $user) {
    if ((string) ($user['id'] ?? '') === (string) $_SESSION['user_id']) {
        $currentUser = $user;
        break;
    }
}
$showFeedbackPrompt = empty($_SESSION['feedback_prompt_shown']);
$_SESSION['feedback_prompt_shown'] = true;
$profileMessage = isset($_GET['profile_updated']) ? 'Profile updated successfully.' : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Calapan Airshed</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="air_quality.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary: #174ea6; --ai-purple: #2767b5; --accent: #0f9f86; --bg: #e5f4ff; 
            --danger: #d94b4b; --text: #263b4d;
            --blue-anim: linear-gradient(135deg, #78c5ef, #bce8fa, #4f9bd3);
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(120deg, #d5efff, #f8fcff, #c6e9fb); background-size: 300% 300%; animation: pageBlueFlow 11s ease-in-out infinite; display: flex; height: 100vh; overflow: hidden; color: var(--text); }

        /* Sidebar */
        .sidebar { 
            width: 280px; min-width: 280px; background: linear-gradient(135deg, #78c5ef, #bce8fa, #4f9bd3); background-size: 240% 240%;
            animation: gradientBG 8s ease-in-out infinite; color: #fff; padding: 30px 20px; 
            display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(36,104,157,0.18); 
        }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes pageBlueFlow { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

        .sidebar h2 { font-size: 20px; font-weight: 800; text-align: center; color: #174ea6; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 20px; }
        .nav-link { padding: 15px 20px; border-radius: 10px; cursor: pointer; margin: 8px 0; transition: all 0.3s ease; display: flex; align-items: center; gap: 12px; color: #17466f; text-decoration: none; font-size: 14px; font-weight: 600; }
        .nav-link:hover { background: rgba(255,255,255,0.58); color: #174ea6; transform: translateX(8px); }
        .nav-link.active { background: #fff; color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .logout-btn { margin-top: 20px; padding: 15px; text-align: center; background: rgba(255,255,255,0.28); color: #17466f; text-decoration: none; border-radius: 10px; font-weight: 800; border: 1px solid rgba(23,78,166,0.18); }
        .header-signout { display: none; align-items: center; gap: 6px; padding: 9px 13px; border-radius: 9px; background: #fff1f2; color: #be123c; text-decoration: none; font-size: 12px; font-weight: 800; }
        .header-signout:hover { background: #ffe4e6; }
        .feedback-modal { display: none; position: fixed; inset: 0; z-index: 1000; align-items: center; justify-content: center; padding: 20px; background: rgba(10, 18, 60, 0.55); }
        .feedback-modal.open { display: flex; }
        .feedback-modal-card { width: min(420px, 100%); padding: 30px; border-radius: 18px; background: #fff; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
        .feedback-modal-card h2 { margin: 0 0 8px; color: var(--primary); font-size: 21px; }
        .feedback-modal-card p { margin: 0 0 20px; color: #667085; font-size: 14px; }
        .modal-star-picker { display: flex; justify-content: center; gap: 7px; }
        .modal-star-picker button { padding: 0 3px; border: 0; background: transparent; color: #d7dce5; cursor: pointer; font-size: 42px; line-height: 1; }
        .modal-star-picker button.selected, .modal-star-picker button:hover { color: #f9b51b; }
        .feedback-later { margin-top: 20px; border: 0; background: transparent; color: #667085; cursor: pointer; font-weight: 700; }

        /* Main Content Area */
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; height: 100vh; }
        .header { background: rgba(255,255,255,0.9); padding: 20px 40px; border-bottom: 1px solid #cce5f4; display: flex; justify-content: space-between; align-items: center; }

        .scroll-area { padding: 30px; overflow-y: auto; flex: 1; }
        .view-section { display: none; animation: slideUp 0.5s ease; }
        .active-view { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        /* Dashboard Layout */
        .top-row { display: flex; flex-direction: column; gap: 25px; margin-bottom: 25px; } 
        .metrics-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .card { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; border: 1px solid #c9e4f2; border-top: 5px solid var(--primary); text-align: center; position: relative; animation: panelFloat 5.5s ease-in-out infinite; }
        .val { font-size: 38px; font-weight: 800; color: var(--primary); display: block; }

        .ai-panel { 
            flex: 1; background: #fff; border-top: 5px solid var(--primary);
            padding: 25px; border-radius: 15px; box-shadow: 0 14px 30px rgba(36,104,157,0.16); order: -1; animation: panelFloat 5.5s ease-in-out .4s infinite;
        }
        .range-selector { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 20px; }
        .range-button { min-width: 70px; padding: 11px 18px; border-radius: 999px; border: 1px solid #c9e4f2; background: #eef8ff; color: #38566e; font-weight: 700; cursor: pointer; transition: all 0.25s ease; }
        .range-button.active, .range-button:hover { background: var(--primary); color: #fff; box-shadow: 0 12px 24px rgba(23,78,166,0.18); }
        .prediction-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; width: 100%; }
        .ai-insight { font-size: 18px; line-height: 1.6; min-height: 80px; font-weight: 600; color: #333; }
        .selected-forecast { display: flex; flex: 1 1 100%; width: 100%; min-width: 0; flex-direction: column; justify-content: center; align-items: center; background: #fff; border: 1px solid #c9e4f2; border-radius: 24px; padding: 22px; box-shadow: 0 12px 26px rgba(36,104,157,0.12); animation: panelFloat 5.5s ease-in-out .7s infinite; }
        .selected-forecast label { font-size: 10px; font-weight: 800; color: #7f7f9a; text-transform: uppercase; letter-spacing: 0.12em; }
        .selected-forecast-value { font-size: 32px; font-weight: 900; margin-top: 10px; color: var(--primary); }
        .selected-forecast-note { margin-top: 6px; font-size: 10px; line-height: 1.4; color: #6f708a; overflow-wrap: anywhere; }
        .forecast-disclaimer { margin: 12px 0 0; color: #7f7f9a; font-size: 11px; line-height: 1.4; text-align: center; }
        .forecast-tile { animation: forecastTileFloat 6s ease-in-out infinite; transition: transform .25s ease, box-shadow .25s ease; }
        .forecast-tile:hover { transform: translateY(-6px); box-shadow: 0 16px 26px rgba(36,104,157,.18); }
        .forecast-tile:nth-child(2) { animation-delay: -1.5s; }
        .forecast-tile:nth-child(3) { animation-delay: -3s; }
        .forecast-tile:nth-child(4) { animation-delay: -4.5s; }
        @keyframes forecastTileFloat { 0%, 100% { opacity: 1; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-4px); } }
        .status-badge { font-size: 32px; font-weight: 800; margin: 10px 0; display: block; }

        .advisory-card { background: #fff; padding: 15px; border-radius: 15px; border: 1px solid #c9e4f2; border-left: 8px solid var(--accent); margin-bottom: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; animation: panelFloat 5.5s ease-in-out .2s infinite; }
        .advisory-card h4 { margin: 0; color: var(--primary); }
        .advisory-card p { margin: 3px 0 0; font-size: 14px; }

        /* History Table */
        .history-card { background: #fff; padding: 20px; border-radius: 15px; margin-top: 25px; border: 1px solid #c9e4f2; box-shadow: 0 14px 30px rgba(36,104,157,0.14); animation: panelFloat 5.5s ease-in-out .8s infinite; }
        .history-table-wrap { max-height: min(42vh, 360px); overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .chart-scroll { width: 100%; overflow-x: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .chart-track { position: relative; width: 100%; min-width: 600px; height: 260px; }
        .chart-track canvas { display: block; width: 100% !important; height: 260px !important; max-width: none; }
        .chart-card { background: #fff; padding: 24px 28px 28px; border-radius: 30px; }
        .chart-heading { margin: 0 0 14px; color: var(--primary); font-size: 18px; font-weight: 800; }
        .chart-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
        .chart-controls { display: flex; flex-wrap: wrap; gap: 7px; }
        .chart-control-button { padding: 8px 12px; border: 1px solid #c9e4f2; border-radius: 999px; background: #eef8ff; color: #38566e; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
        .chart-control-button.active { border-color: var(--primary); background: var(--primary); color: #fff; }
        .chart-status { min-height: 16px; margin: 0 0 8px; color: #64748b; font-size: 11px; }
        .guide-card { background: #fff; padding: 22px; border-radius: 18px; border: 1px solid #c9e4f2; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; margin-bottom: 25px; animation: panelFloat 5.5s ease-in-out 1.1s infinite; }
        .guide-card h3 { margin: 0 0 12px; font-size: 16px; color: var(--primary); }
        .guide-card p { margin: 0 0 14px; color: #5f677a; font-size: 14px; }
        .guide-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .guide-list li { background: #eef8ff; color: #38566e; padding: 12px 14px; border-radius: 12px; font-size: 13px; line-height: 1.5; }
        .guide-list strong { color: var(--primary); }
        @keyframes panelFloat { 0%, 100% { translate: 0 0; } 50% { translate: 0 -6px; } }
        @media (prefers-reduced-motion: reduce) { body, .card, .ai-panel, .selected-forecast, .advisory-card, .history-card, .guide-card, .profile-card { animation: none; } }
        .profile-card { max-width: 760px; background: #fff; padding: 30px; border-radius: 15px; border: 1px solid #c9e4f2; box-shadow: 0 14px 30px rgba(36,104,157,.14); animation: panelFloat 5.5s ease-in-out 1.1s infinite; }
        .profile-card h2 { color: var(--primary); margin-top: 0; }
        .profile-note { color: #7f8c8d; font-size: 13px; }
        .profile-field { margin-bottom: 18px; }
        .profile-field label { display: block; color: #5f677a; font-size: 13px; font-weight: 800; margin-bottom: 8px; }
        .profile-field input { width: 100%; padding: 13px 15px; border: 1px solid #e2e8f0; border-radius: 9px; font: inherit; }
        .profile-submit { background: var(--primary); color: #fff; border: 0; border-radius: 9px; padding: 13px 18px; font-weight: 800; cursor: pointer; }
        .success-banner { background: var(--accent); color: #fff; padding: 16px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 16px; }
        th { text-align: left; padding: 15px; color: #888; border-bottom: 2px solid #eee; }
        td { padding: 15px; border-bottom: 1px solid #f9f9f9; }

        @media (max-width: 760px) {
            body { display: block; height: auto; min-height: 100vh; overflow: auto; }
            .sidebar { width: 100%; min-width: 0; padding: 16px; display: block; }
            .sidebar h2 { margin: 0 0 12px; padding-bottom: 12px; font-size: 19px; }
            .nav-link { display: inline-flex; width: auto; padding: 10px 12px; margin: 3px 2px; font-size: 12px; border-radius: 9px; }
            .logout-btn { display: block; margin-top: 12px; padding: 12px; }
            .main { height: auto; min-height: calc(100vh - 155px); }
            .header { padding: 16px 18px; gap: 10px; flex-wrap: wrap; }
            .header h1 { font-size: 17px !important; }
            .scroll-area { padding: 18px; }
            .metrics-container { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .card { padding: 18px 12px; }
            .val { font-size: 34px; }
            .ai-panel { padding: 22px; }
            .prediction-controls {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 6px;
            }
            .prediction-controls > span { grid-column: 1 / -1; }
            .range-button {
                min-width: 0;
                width: 100%;
                padding: 9px 4px;
                font-size: 11px;
            }
            .ai-insight { font-size: 15px; line-height: 1.45; min-height: 0; }
            .history-table-wrap { max-height: 48vh; overflow-x: hidden; overflow-y: auto; }
            .history-card table {
                min-width: 0;
                width: 100%;
                table-layout: fixed;
            }
            .history-card th,
            .history-card td {
                padding: 9px 4px;
                font-size: 10px;
                line-height: 1.35;
                overflow-wrap: anywhere;
                vertical-align: middle;
            }
            .history-card th { position: sticky; top: 0; z-index: 1; background: #fff; }
        }

        @media (max-width: 520px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .sidebar {
                padding: 12px 10px 10px;
            }

            .nav-link {
                display: flex;
                width: 100%;
                justify-content: center;
                padding: 10px;
                margin: 4px 0;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-signout {
                width: 100%;
                justify-content: center;
            }

            .scroll-area {
                padding: 14px;
            }

            .metrics-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .card {
                min-width: 0;
                padding: 18px 8px;
                border-radius: 18px;
                box-shadow: 0 10px 24px rgba(0, 0, 0, .06);
            }

            .val {
                font-size: 30px;
            }

            .ai-panel,
            .history-card,
            .guide-card,
            .profile-card {
                padding: 18px 14px;
                border-radius: 18px;
            }

            .range-selector {
                gap: 8px;
            }

            .prediction-controls {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 6px;
            }

            .prediction-controls > span {
                grid-column: 1 / -1;
            }

            .range-button {
                min-width: 0;
                width: 100%;
                padding: 9px 4px;
                font-size: 11px;
            }

            .ai-insight {
                font-size: 15px;
                line-height: 1.45;
                min-height: 0;
            }

            .selected-forecast {
                width: 100%;
                min-width: 0;
            }

            .advisory-card {
                align-items: flex-start;
                padding: 14px;
            }

            .history-table-wrap,
            .chart-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .history-card table { min-width: 0; }

            th,
            td {
                font-size: 12px;
                padding: 10px 8px;
            }
        }

        @media (max-width: 360px) {
            .metrics-container {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 15px !important;
            }

            .scroll-area {
                padding: 12px;
            }
        }

        .mobile-menu-toggle, .sidebar-backdrop { display: none; }

        @media (max-width: 760px) {
            .mobile-menu-toggle {
                display: inline-flex; align-items: center; justify-content: center;
                position: fixed; top: 12px; left: 12px; z-index: 1002;
                width: 42px; height: 42px; border: 0; border-radius: 12px;
                background: var(--primary); color: #fff; font-size: 21px; cursor: pointer;
                box-shadow: 0 8px 20px rgba(26,35,126,.25);
            }
            .sidebar {
                position: fixed; inset: 0 auto 0 0; z-index: 1000;
                width: min(290px, 86vw); min-width: 0; height: 100vh;
                overflow-y: auto; transform: translateX(-105%);
                transition: transform .25s ease; box-shadow: 12px 0 30px rgba(10,18,60,.25);
            }
            .sidebar h2 { color: #174ea6; border-bottom-color: rgba(23,78,166,.16); }
            .sidebar .nav-link { display: flex; width: 100%; flex: 0 0 auto; margin: 6px 0; justify-content: flex-start; color: #17466f; }
            .sidebar .nav-link:hover { background: rgba(255,255,255,.58); color: #174ea6; transform: none; }
            .sidebar .nav-link.active { color: #174ea6; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,.18); }
            .sidebar .logout-btn { color: #17466f; border-color: rgba(23,78,166,.18); background: rgba(255,255,255,.28); padding: 9px 12px; font-size: 13px; border-radius: 9px; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-backdrop {
                position: fixed; inset: 0; z-index: 999; background: rgba(10,18,60,.45);
            }
            .sidebar-backdrop.open { display: block; }
            .header { padding-left: 66px; }
        }

    </style>
</head>
<body>

<button class="mobile-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" onclick="toggleMobileSidebar()">☰</button>
<div class="sidebar-backdrop" onclick="closeMobileSidebar()"></div>
<div class="sidebar">
    <h2>CITY AIRSHED</h2>
    <div class="nav-link active" onclick="switchView('v-dash', this)">📊 Real-time Monitoring</div>
    <a class="nav-link" href="report.php">🚨 Report Pollution / Burning</a>
    <a class="nav-link" href="sensor_reports.php">📈 Sensor Reports</a>
    <div class="nav-link" onclick="switchView('v-profile', this)">👤 My Profile</div>
    <a href="logout.php" class="logout-btn">🚪 Sign Out</a>
</div>

<div class="main">
    <div class="header">
        <h1 style="margin:0; font-size: 20px; font-weight: 800; color: var(--primary);">IoT Airshed Monitoring - Calapan City</h1>
        <div style="text-align:right;">
            <div id="clock" style="font-weight: 800; color: var(--primary);"></div>
            <div id="syncStatus" style="font-size:11px; color:#7f8c8d; margin-top:4px;">Waiting for device...</div>
        </div>
        <a class="header-signout" href="logout.php">🚪 Sign Out</a>
    </div>

    <div class="scroll-area">
        <?php if ($profileMessage): ?><div class="success-banner"><?php echo htmlspecialchars($profileMessage); ?></div><?php endif; ?>
        <div id="v-dash" class="view-section active-view">
            <div class="advisory-card">
                <div style="font-size: 30px;">🍃</div>
                <div>
                    <h4>Air Quality Advisory</h4>
                    <p id="userAdvisory">Waiting for sensor data...</p>
                </div>
            </div>

            <div class="top-row">
                <div class="metrics-container">
                    <div class="card"><label style="font-size:12px; font-weight:800; color:#aaa;">TEMP</label><span class="val" id="temp">--</span><span>°C</span></div>
                    <div class="card"><label style="font-size:12px; font-weight:800; color:#aaa;">HUMIDITY</label><span class="val" id="hum">--</span><span>%</span></div>
                    <div class="card"><label style="font-size:12px; font-weight:800; color:#aaa;">PM2.5</label><span class="val" id="pm25" style="color:var(--ai-purple);">--</span><span>µg/m³</span></div>
                    <div class="card"><label style="font-size:12px; font-weight:800; color:#aaa;">CO SENSOR SIGNAL (MQ-7)</label><span class="val" id="co">--</span><span>Raw ADC counts</span></div>
                </div>
                
                <div class="ai-panel">
                    <div style="font-weight:800; color:var(--ai-purple); font-size:14px; margin-bottom:10px;">🤖 AI ADVISOR</div>
                    <span id="statusText" class="status-badge">...</span>
                    <div id="aiInsight" class="ai-insight">Waiting for data stream...</div>
                    <div class="range-selector">
                        <div class="prediction-controls">
                            <span style="font-size:12px; font-weight:800; color:#555;">Prediction Range:</span>
                            <button class="range-button active" onclick="setPredictionRange(30,this)">30m</button>
                            <button class="range-button" onclick="setPredictionRange(120,this)">2h</button>
                        </div>
                        <div class="selected-forecast">
                            <label>Selected PM2.5 Forecast</label>
                            <div id="selectedForecast" class="selected-forecast-value">--</div>
                            <div style="font-size:12px; font-weight:700; color:#7f7f9a;">µg/m³</div>
                            <div id="selectedForecastLabel" class="selected-forecast-note">Next 30 minutes</div>
                            <div id="selectedForecastMeta" class="selected-forecast-note"></div>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:20px;">
                        <div class="forecast-tile" style="background:#f6f4ff; padding:15px; border-radius:15px; text-align:center;">
                            <label style="font-size:10px; font-weight:800; color:var(--ai-purple);">Humidity Forecast</label>
                            <div id="humForecast" style="font-size:24px; font-weight:900;">--</div>
                            <div id="humidityForecastMeta" class="selected-forecast-note"></div>
                        </div>
                        <div class="forecast-tile" style="background:#ecfdf3; padding:15px; border-radius:15px; text-align:center;">
                            <label style="font-size:10px; font-weight:800; color:#2e7d32;">Weather Outlook</label>
                            <div id="weatherForecast" style="font-size:18px; font-weight:900;">--</div>
                            <div id="weatherForecastMeta" class="selected-forecast-note"></div>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:12px;">
                        <div class="forecast-tile" style="flex:1; background:#f0f0ff; padding:15px; border-radius:15px; text-align:center;">
                            <label style="font-size:10px; font-weight:800; color:var(--ai-purple);">PM2.5 · NEXT 30 MIN</label>
                            <div id="predict30" style="font-size:24px; font-weight:900;">--</div>
                            <div style="font-size:11px; font-weight:700; color:#7f7f9a;">µg/m³</div>
                            <div id="predict30Meta" class="selected-forecast-note"></div>
                        </div>
                        <div class="forecast-tile" style="flex:1; background:var(--ai-purple); color:#fff; padding:15px; border-radius:15px; text-align:center;">
                            <label style="font-size:10px; font-weight:800; opacity:0.8;">PM2.5 · NEXT 2 HOURS</label>
                            <div id="predict2h" style="font-size:24px; font-weight:900;">--</div>
                            <div style="font-size:11px; font-weight:700; opacity:0.8;">µg/m³</div>
                            <div id="predict2hMeta" class="selected-forecast-note" style="color:rgba(255,255,255,.82);"></div>
                        </div>
                    </div>
                    <p class="forecast-disclaimer">Forecast values are estimates based on recent device readings and may change as new data arrives.</p>
                </div>
            </div>

            <div class="guide-card">
                <h3>How to Read Your Airshed Dashboard</h3>
                <p>These guidelines explain what each measurement is, what it is for, and what action to take.</p>
                <ul class="guide-list">
                    <li><strong>PM2.5</strong>: Fine particles from smoke, dust, and combustion. The Airshed dashboard display bands are 0-12 µg/m³ = Good, above 12-35 = Moderate, above 35-150 = Unhealthy, and above 150-1000 = Hazardous. These are not a claim of official AQI certification; confirm applicable thresholds with local health or environmental authorities.</li>
                    <li><strong>CO sensor (MQ-7)</strong>: Displays the sensor response as raw ADC counts for observing changes in the signal over time.</li>
                    <li><strong>Temperature</strong>: Current outdoor temperature from the weather service. At 33°C or higher, stay hydrated and limit heat exposure.</li>
                    <li><strong>Humidity</strong>: Current outdoor relative humidity. At 85% or higher, expect very humid conditions and take extra care outdoors.</li>
                    <li><strong>System status</strong>: Online means recent sensor data is available; Offline means the dashboard has no live reading to display.</li>
                </ul>
            </div>

            <div class="chart-card">
                <h3 class="chart-heading">SENSOR TREND HISTORY</h3>
                <div class="chart-toolbar">
                    <div class="chart-controls" role="group" aria-label="Choose chart measurement">
                        <button type="button" class="chart-control-button active" data-chart-metric="pm25" aria-pressed="true">PM2.5</button>
                        <button type="button" class="chart-control-button" data-chart-metric="temp" aria-pressed="false">Temperature</button>
                        <button type="button" class="chart-control-button" data-chart-metric="hum" aria-pressed="false">Humidity</button>
                        <button type="button" class="chart-control-button" data-chart-metric="co" aria-pressed="false">MQ-7 signal</button>
                    </div>
                    <div class="chart-controls" role="group" aria-label="Choose chart time range">
                        <button type="button" class="chart-control-button active" data-chart-window="1h" aria-pressed="true">1 hour</button>
                        <button type="button" class="chart-control-button" data-chart-window="24h" aria-pressed="false">24 hours</button>
                    </div>
                </div>
                <p id="chartStatus" class="chart-status" role="status">Loading chart history...</p>
                <div class="chart-scroll"><div class="chart-track"><canvas id="pollutantChart" width="1200" height="260"></canvas></div></div>
            </div>
            
            <div class="history-card">
                <h3 style="margin:0; font-weight:800; color:var(--primary);">🕒 Recent Readings</h3>
                <div class="history-table-wrap">
                    <table>
                        <thead><tr><th>Time</th><th>Temperature</th><th>PM2.5</th><th>AQI Status</th><th>Device</th></tr></thead>
                        <tbody id="historyBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="v-profile" class="view-section">
            <div class="profile-card">
                <h2>My Profile</h2>
                <p class="profile-note">Update your own account details. Your role and access level cannot be changed here.</p>
                <form method="POST" action="update_profile.php">
                    <div class="profile-field"><label>Username</label><input type="text" name="username" required value="<?php echo htmlspecialchars($currentUser['username'] ?? '', ENT_QUOTES); ?>"></div>
                    <div class="profile-field"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES); ?>"></div>
                    <div class="profile-field"><label>Phone</label><input type="text" name="phone" required value="<?php echo htmlspecialchars($currentUser['phone'] ?? '', ENT_QUOTES); ?>"></div>
                    <div class="profile-field"><label>New Password <span style="font-weight:400;">(leave blank to keep current password)</span></label><input type="password" name="password"></div>
                    <button type="submit" class="profile-submit">Save My Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="feedbackModal" class="feedback-modal" role="dialog" aria-modal="true" aria-labelledby="feedbackModalTitle">
    <div class="feedback-modal-card">
        <h2 id="feedbackModalTitle">Is this dashboard helpful?</h2>
        <p>Does it help you understand the current air quality and conditions? Please rate its usefulness.</p>
        <div class="modal-star-picker" role="group" aria-label="Rate this dashboard from 1 to 5 stars">
            <?php for ($star = 1; $star <= 5; $star++): ?>
                <button type="button" data-rating="<?php echo $star; ?>" aria-label="<?php echo $star; ?> star">★</button>
            <?php endfor; ?>
        </div>
        <div id="modalFeedbackStatus" style="min-height:15px; margin-top:8px; color:#667085; font-size:11px;" aria-live="polite"></div>
        <button type="button" id="feedbackLater" class="feedback-later">Maybe later</button>
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

    let pmHistory = [];
    let tempHistory = [];
    let humHistory = [];
    let predictionHistory = [];
    let serverForecast30 = null;
    let serverForecast2h = null;
    let serverForecastTemp = null;
    let serverForecastHum = null;
    let forecastMetrics = {};
    let forecastBasis = {};
    let latestSensorTimestamp = null;
    let liveDataAvailable = false;
    let selectedForecastMinutes = 30;
    let predictionLocked = false;
    let lastAlertRequest = 0;
    let predictionSnapshot = { pm: null, hum: null, temp: null, outlook: 'Pleasant' };
    let predictionLastUpdatedAt = 0;
    let predictionWindowMs = 3000;

    function switchView(id, btn) {
        document.querySelectorAll('.view-section').forEach(v => v.classList.remove('active-view'));
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        document.getElementById(id).classList.add('active-view');
        btn.classList.add('active');
    }

    function submitFeedback(rating, statusElement, starSelector, closeModal = false) {
        document.querySelectorAll(starSelector).forEach(star => {
            star.classList.toggle('selected', Number(star.dataset.rating) <= rating);
        });
        statusElement.textContent = 'Saving your feedback...';
        fetch('save_feedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rating })
        }).then(response => response.json()).then(result => {
            statusElement.textContent = result.success ? 'Thank you for your feedback.' : (result.error || 'Unable to save feedback.');
            if (result.success && closeModal) {
                document.getElementById('feedbackModal').classList.remove('open');
            }
            if (!result.success) {
                document.querySelectorAll(starSelector).forEach(star => star.classList.remove('selected'));
            }
        }).catch(() => {
            statusElement.textContent = 'Unable to save feedback. Please try again.';
        });
    }

    function requestCriticalAlert(pm25) {
        const now = Date.now();
        if (!AirshedAirQuality.isAlertLevel(AirshedAirQuality.classifyPm25(pm25).level)
            || now - lastAlertRequest < 30 * 60 * 1000) return;
        lastAlertRequest = now;
        fetch('automated_alerts.php', { method: 'POST' })
            .then(response => {
                if (!response.ok) throw new Error(`Alert request failed (${response.status})`);
            })
            .catch(error => {
                lastAlertRequest = Date.now() - 25 * 60 * 1000;
                console.error(error);
            });
    }

    document.querySelectorAll('.modal-star-picker button').forEach(button => {
        button.addEventListener('click', () => {
            submitFeedback(Number(button.dataset.rating), document.getElementById('modalFeedbackStatus'), '.modal-star-picker button', true);
        });
    });

    document.getElementById('feedbackLater').addEventListener('click', () => {
        document.getElementById('feedbackModal').classList.remove('open');
    });

    <?php if ($showFeedbackPrompt): ?>
    setTimeout(() => {
        document.getElementById('feedbackModal').classList.add('open');
    }, 3 * 60 * 1000);
    <?php endif; ?>

    function getWeatherOutlook(temp, hum) {
        if (temp === null || temp === undefined || temp === '' || !Number.isFinite(Number(temp))) return 'Unavailable';
        if (temp >= 33) return "Hot";
        if (temp >= 28) return "Warm";
        if (temp <= 20) return "Cool";
        return "Pleasant";
    }

    function renderUserHistory(history, isLive) {
        const tableBody = document.getElementById('historyBody');
        tableBody.innerHTML = '';
        const readings = Array.isArray(history) ? history : [];
        readings.slice().reverse().slice(0, 5).forEach((reading, index) => {
            const temperature = reading.temp === null || reading.temp === undefined ? NaN : Number(reading.temp);
            const pm25 = reading.pm25 === null || reading.pm25 === undefined ? null : Number(reading.pm25);
            const classification = AirshedAirQuality.classifyPm25(pm25);
            const row = document.createElement('tr');
            const values = [
                AirshedAirQuality.formatManilaDateTime(reading.timestamp),
                Number.isFinite(temperature) ? `${temperature.toFixed(1)}°C` : '--',
                pm25 !== null && Number.isFinite(pm25) ? pm25.toFixed(1) : '--',
                classification.label,
                index === 0 ? (isLive ? 'ONLINE' : 'OFFLINE') : 'Recorded'
            ];
            values.forEach((value, cellIndex) => {
                const cell = row.insertCell();
                cell.textContent = value;
                if (cellIndex === 2 || cellIndex === 3) {
                    cell.style.color = classification.color;
                    cell.style.fontWeight = '800';
                }
            });
            tableBody.appendChild(row);
        });
    }

    function updatePredictionSnapshot(force = false) {
        const now = Date.now();
        if (!force && predictionLocked && (now - predictionLastUpdatedAt < predictionWindowMs)) return;

        if (!liveDataAvailable) {
            predictionSnapshot = { pm: null, hum: null, temp: null, outlook: 'Pleasant' };
            predictionLocked = false;
            predictionLastUpdatedAt = now;
            document.getElementById('selectedForecast').innerText = '--';
            document.getElementById('selectedForecastMeta').innerText = 'Forecast paused while the sensor is offline.';
            document.getElementById('humForecast').innerText = '--';
            document.getElementById('humidityForecastMeta').innerText = 'Forecast paused while the sensor is offline.';
            document.getElementById('weatherForecast').innerText = 'No live sensor data';
            document.getElementById('weatherForecastMeta').innerText = 'Forecast paused while the sensor is offline.';
            document.getElementById('predict30').innerText = '--';
            document.getElementById('predict2h').innerText = '--';
            document.getElementById('predict30Meta').innerText = 'Sensor offline.';
            document.getElementById('predict2hMeta').innerText = 'Sensor offline.';
            document.getElementById('aiInsight').innerText = 'Forecasts are paused while the sensor is offline.';
            return;
        }

        const pmHistoryData = pmHistory;
        const currentTemp = tempHistory.length ? tempHistory[tempHistory.length - 1] : null;
        const currentHum = humHistory.length ? humHistory[humHistory.length - 1] : null;
        const forecast = selectedForecastMinutes === 30 ? serverForecast30 : serverForecast2h;
        const forecastTemp = serverForecastTemp;
        const forecastHum = serverForecastHum;
        const outlook = currentTemp !== null && currentHum !== null ? getWeatherOutlook(currentTemp, currentHum) : 'Pleasant';

        predictionSnapshot = { pm: forecast, hum: forecastHum, temp: forecastTemp, outlook };
        predictionLocked = false;
        predictionLastUpdatedAt = now;

        document.getElementById('selectedForecast').innerText = forecast !== null ? Math.max(0, forecast).toFixed(1) : '--';
        document.getElementById('selectedForecastMeta').innerText = AirshedAirQuality.forecastDetails(
            latestSensorTimestamp,
            selectedForecastMinutes,
            selectedForecastMinutes === 30 ? forecastMetrics.forecast30 : forecastMetrics.forecast2h
        );
        document.getElementById('humForecast').innerText = forecastHum !== null ? `${forecastHum.toFixed(1)}%` : '--';
        document.getElementById('humidityForecastMeta').innerText = AirshedAirQuality.forecastDetails(latestSensorTimestamp, 30, forecastMetrics.forecast_hum);
        document.getElementById('weatherForecast').innerText = `${outlook} · ${forecastTemp !== null ? `${forecastTemp.toFixed(1)}°C` : '...'}`;
        document.getElementById('weatherForecastMeta').innerText = AirshedAirQuality.forecastDetails(latestSensorTimestamp, 30, forecastMetrics.forecast_temp);

        if (forecast !== null && pmHistoryData.length >= 3 && Number.isFinite(pmHistoryData[pmHistoryData.length - 1])) {
            const currentPm25 = pmHistoryData[pmHistoryData.length - 1];
            const change = forecast - currentPm25;
            const isStable = Math.abs(change) < 0.5;
            const trend = isStable ? '➖ Stable' : (change > 0 ? '📈 Rising' : '📉 Falling');
            const advice = isStable
                ? 'No meaningful change predicted. Follow current air-quality guidance.'
                : (change > 0
                    ? '🚨 Worsening. Reduce outdoor exposure and follow local guidance.'
                    : '✅ Improving. Check current air-quality guidance before outdoor activity.');
            document.getElementById('aiInsight').innerHTML = `<b>Trend:</b> ${trend}<br>${advice}<br><b>Weather:</b> ${outlook} with humidity forecast at ${forecastHum !== null ? `${forecastHum.toFixed(1)}%` : '--'}.`;
        } else {
            document.getElementById('aiInsight').innerText = AirshedAirQuality.forecastSummary(
                selectedForecastMinutes === 30 ? forecastMetrics.forecast30 : forecastMetrics.forecast2h,
                selectedForecastMinutes === 30 ? forecastBasis.pm25_30m : forecastBasis.pm25_2h
            );
        }
    }

    function setPredictionRange(minutes, btn) {
        selectedForecastMinutes = minutes;
        document.querySelectorAll('.range-button').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        const label = minutes === 120 ? '2 hours' : '30 minutes';
        document.getElementById('selectedForecastLabel').innerText = `Next ${label}`;
        predictionLastUpdatedAt = Date.now();
        predictionLocked = false;
        updatePredictionSnapshot(true);
    }

    let chartHistory = [];
    let selectedChartMetric = 'pm25';
    let selectedChartWindow = '1h';
    let chartHistoryRequestId = 0;
    const chartMetrics = {
        pm25: { field: 'pm25', label: 'PM2.5 (µg/m³)', color: '#168a68' },
        temp: { field: 'temp', label: 'Temperature (°C)', color: '#ff7043' },
        hum: { field: 'hum', label: 'Humidity (%)', color: '#00a896' },
        co: { field: 'co', label: 'MQ-7 signal (ADC counts)', color: '#8e44ad' }
    };
    const ctx = document.getElementById('pollutantChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: { labels: [], datasets: [{
            label: chartMetrics.pm25.label,
            data: [],
            borderColor: chartMetrics.pm25.color,
            borderWidth: 3,
            tension: 0.25,
            pointRadius: 2,
            pointBackgroundColor: context => AirshedAirQuality.classifyPm25(context.raw).color,
            pointBorderColor: context => AirshedAirQuality.classifyPm25(context.raw).color,
            segment: {
                borderColor: context => AirshedAirQuality.classifyPm25(context.p1.raw).color
            }
        }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxTicksLimit: 8 } },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: chartMetrics.pm25.label }
                }
            }
        }
    });

    function renderUserChart() {
        const metric = chartMetrics[selectedChartMetric];
        const dataset = chart.data.datasets[0];
        dataset.label = metric.label;
        dataset.data = chartHistory.map(reading => {
            const value = reading[metric.field];
            return value === null || value === undefined || !Number.isFinite(Number(value)) ? null : Number(value);
        });
        dataset.borderColor = metric.color;
        dataset.pointBackgroundColor = context => selectedChartMetric === 'pm25'
            ? AirshedAirQuality.classifyPm25(context.raw).color
            : metric.color;
        dataset.pointBorderColor = dataset.pointBackgroundColor;
        dataset.segment = {
            borderColor: context => selectedChartMetric === 'pm25'
                ? AirshedAirQuality.classifyPm25(context.p1.raw).color
                : metric.color
        };
        chart.data.labels = chartHistory.map(reading => {
            const timestamp = new Date(reading.timestamp);
            if (!Number.isFinite(timestamp.getTime())) return '--';
            const options = selectedChartWindow === '24h'
                ? { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }
                : { hour: '2-digit', minute: '2-digit', hour12: false };
            return timestamp.toLocaleString('en-GB', { ...options, timeZone: 'Asia/Manila' });
        });
        chart.options.scales.y.beginAtZero = selectedChartMetric !== 'temp';
        chart.options.scales.y.title.text = metric.label;
        chart.update();
    }

    function mergeUserChartHistory(readings) {
        if (!Array.isArray(readings)) return;
        const readingsByTimestamp = new Map();
        chartHistory.forEach(reading => {
            const timestamp = Date.parse(reading.timestamp);
            if (Number.isFinite(timestamp)) readingsByTimestamp.set(timestamp, reading);
        });
        readings.forEach(reading => {
            const timestamp = Date.parse(reading.timestamp);
            if (Number.isFinite(timestamp)) readingsByTimestamp.set(timestamp, reading);
        });
        const windowMs = selectedChartWindow === '24h' ? 24 * 60 * 60 * 1000 : 60 * 60 * 1000;
        const cutoff = Date.now() - windowMs;
        chartHistory = Array.from(readingsByTimestamp.entries())
            .filter(([timestamp]) => timestamp >= cutoff)
            .sort((a, b) => a[0] - b[0])
            .slice(-5000)
            .map(([, reading]) => reading);
        renderUserChart();
    }

    document.querySelectorAll('[data-chart-metric]').forEach(button => {
        button.addEventListener('click', () => {
            selectedChartMetric = button.dataset.chartMetric;
            document.querySelectorAll('[data-chart-metric]').forEach(metricButton => {
                const active = metricButton === button;
                metricButton.classList.toggle('active', active);
                metricButton.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            renderUserChart();
        });
    });

    function loadChartWindow(windowName) {
        if (windowName !== '1h' && windowName !== '24h') return;
        selectedChartWindow = windowName;
        chartHistory = [];
        renderUserChart();
        document.querySelectorAll('[data-chart-window]').forEach(button => {
            const active = button.dataset.chartWindow === windowName;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const requestId = ++chartHistoryRequestId;
        const status = document.getElementById('chartStatus');
        status.innerText = `Loading ${windowName === '1h' ? '1-hour' : '24-hour'} chart history...`;
        fetch(`get_latest_data.php?history_window=${encodeURIComponent(windowName)}`, { cache: 'no-store' })
            .then(response => {
                if (!response.ok) throw new Error(`Chart history request failed (${response.status})`);
                return response.json();
            })
            .then(data => {
                if (requestId !== chartHistoryRequestId) return;
                mergeUserChartHistory(data.prediction_history);
                status.innerText = `Showing ${windowName === '1h' ? '1-hour' : '24-hour'} sensor history.`;
            })
            .catch(error => {
                if (requestId !== chartHistoryRequestId) return;
                console.error(error);
                status.innerText = 'Could not load chart history. Recent readings may still appear.';
            });
    }

    document.querySelectorAll('[data-chart-window]').forEach(button => {
        button.addEventListener('click', () => loadChartWindow(button.dataset.chartWindow));
    });

    function fetchData() {
        fetch('get_latest_data.php', { cache: 'no-store' }).then(res => {
            if (!res.ok) throw new Error(`Sensor data request failed (${res.status})`);
            return res.json();
        }).then(d => {
            serverForecast30 = d.forecast30 !== undefined ? d.forecast30 : null;
            serverForecast2h = d.forecast2h !== undefined ? d.forecast2h : null;
            serverForecastTemp = d.forecast_temp !== undefined ? d.forecast_temp : null;
            serverForecastHum = d.forecast_hum !== undefined ? d.forecast_hum : null;
            forecastMetrics = d.forecast_metrics || {};
            forecastBasis = d.forecast_basis || {};
            latestSensorTimestamp = d.latest_timestamp || null;
            if (d.live !== true) {
                liveDataAvailable = false;
                predictionHistory = d.prediction_history || [];
                tempHistory = predictionHistory.map(reading => reading.temp === null || reading.temp === undefined ? NaN : Number(reading.temp));
                humHistory = predictionHistory.map(reading => reading.hum === null || reading.hum === undefined ? NaN : Number(reading.hum));
                pmHistory = predictionHistory.map(reading => reading.pm25 === null || reading.pm25 === undefined ? NaN : Number(reading.pm25));
                document.getElementById('temp').innerText = '--';
                document.getElementById('hum').innerText = '--';
                document.getElementById('pm25').innerText = '--';
                document.getElementById('co').innerText = '--';
                document.getElementById('statusText').innerText = 'OFFLINE';
                document.getElementById('statusText').style.color = '#ef5350';
                document.getElementById('syncStatus').innerText = 'Device offline · No live data';
                document.getElementById('syncStatus').style.color = '#d32f2f';
                renderUserHistory(predictionHistory, false);
                const lastReading = latestSensorTimestamp ? AirshedAirQuality.formatManilaDateTime(latestSensorTimestamp) : 'time unavailable';
                document.getElementById('syncStatus').innerText = `Device offline · Last sensor reading ${lastReading}`;
                document.getElementById('syncStatus').style.color = '#d32f2f';
                mergeUserChartHistory(predictionHistory);
                updatePredictionSnapshot(true);
                return;
            }
            liveDataAvailable = true;
            predictionHistory = [];
            const currentTemp = d.temp === null ? NaN : parseFloat(d.temp);
            const currentHum = d.hum === null ? NaN : parseFloat(d.hum);
            const val = d.pm25 === null ? NaN : parseFloat(d.pm25);

            tempHistory.push(currentTemp);
            humHistory.push(currentHum);
            if (tempHistory.length > 12) tempHistory.shift();
            if (humHistory.length > 12) humHistory.shift();

            const outlook = getWeatherOutlook(currentTemp, currentHum);

            document.getElementById('temp').innerText = Number.isFinite(currentTemp) ? currentTemp : '--';
            document.getElementById('hum').innerText = Number.isFinite(currentHum) ? currentHum : '--';
            document.getElementById('pm25').innerText = Number.isFinite(val) ? val.toFixed(1) : '--';
            const classification = AirshedAirQuality.classifyPm25(val);
            document.getElementById('pm25').style.color = classification.color;
            document.getElementById('co').innerText = d.co;
            let status = classification.label;
            let tColor = classification.color;
            
            document.getElementById('statusText').innerText = status;
            document.getElementById('statusText').style.color = tColor;
            requestCriticalAlert(val);
            const ageSeconds = Number(d.latest_age_seconds);
            const ageText = d.latest_age_seconds !== null && d.latest_age_seconds !== undefined && Number.isFinite(ageSeconds)
                ? `${Math.max(0, ageSeconds)}s ago`
                : 'age unavailable';
            const lastReading = latestSensorTimestamp ? AirshedAirQuality.formatManilaDateTime(latestSensorTimestamp) : 'time unavailable';
            document.getElementById('syncStatus').innerText = `Device online · Last sensor reading ${lastReading} · ${ageText}`;
            document.getElementById('syncStatus').style.color = '#16845b';
            document.getElementById('userAdvisory').innerText = classification.level === 'HAZARDOUS'
                ? 'Hazardous air detected. Avoid outdoor exposure and follow local health guidance.'
                : (AirshedAirQuality.isAlertLevel(classification.level)
                    ? 'Unhealthy air detected. Limit outdoor exposure and wear a mask.'
                    : (classification.level === 'MODERATE'
                        ? 'Moderate air quality. Sensitive groups should stay alert.'
                        : (classification.level === 'GOOD'
                            ? 'Air quality is good for normal outdoor activities.'
                            : 'PM2.5 reading unavailable or outside the valid range.')));

            // AI Trend Logic
            pmHistory.push(val);
            if(pmHistory.length > 15) pmHistory.shift();
            if(pmHistory.length >= 3) {
                updatePredictionSnapshot(true);
            }
            document.getElementById('predict30').innerText = serverForecast30 !== null ? Number(serverForecast30).toFixed(1) : '--';
            document.getElementById('predict2h').innerText = serverForecast2h !== null ? Number(serverForecast2h).toFixed(1) : '--';
            document.getElementById('predict30Meta').innerText = AirshedAirQuality.forecastDetails(latestSensorTimestamp, 30, forecastMetrics.forecast30);
            document.getElementById('predict2hMeta').innerText = AirshedAirQuality.forecastDetails(latestSensorTimestamp, 120, forecastMetrics.forecast2h);

            // Render actual device timestamps so frequent polling does not duplicate points.
            mergeUserChartHistory(d.prediction_history);
            renderUserHistory(chartHistory, true);

        }).catch(err => {
            console.error(err);
            document.getElementById('aiInsight').innerText = 'Sensor data unavailable. Reconnecting...';
            document.getElementById('syncStatus').innerText = 'Data unavailable · Reconnecting...';
            document.getElementById('syncStatus').style.color = '#d32f2f';
            ['temp', 'hum', 'pm25', 'co', 'bat', 'predict30', 'predict2h'].forEach(id => {
                const element = document.getElementById(id);
                if (element) element.innerText = '--';
            });
        });
    }

    loadChartWindow(selectedChartWindow);
    setInterval(fetchData, 3000);
    setInterval(() => document.getElementById('clock').innerText = new Date().toLocaleString(), 1000);
    fetchData();
</script>
</body>
</html>