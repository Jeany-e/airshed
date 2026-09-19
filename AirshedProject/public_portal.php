<?php
/**
 * PUBLIC PORTAL - CALAPAN CITY AIRSHED MS
 * Features: Balanced UI, Secret Admin, Dual-Time AI Forecasting, Dual-Axis Chart
 */
include "config.php"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIVE MONITORING | Calapan City Airshed</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary: #174ea6; --ai-purple: #2767b5; --accent: #0f9f86; --bg: #dff2ff; 
            --danger: #d94b4b; --warning: #d88a18; --good: #168a68; --text: #263b4d;
            --blue-gradient: linear-gradient(135deg, #69b9ed 0%, #bce8fa 45%, #5ca9df 100%);
        }

        body { margin: 0; font-family: 'Inter', sans-serif; background: linear-gradient(120deg, #d5efff, #f8fcff, #c6e9fb, #eaf7ff); background-size: 300% 300%; animation: pageBlueFlow 16s ease-in-out infinite; color: var(--text); }

        /* Navbar with User Focus */
        .navbar {
            background: #fff; padding: 1.2rem 5%; display: flex;
            justify-content: space-between; align-items: center;
            box-shadow: 0 8px 24px rgba(31,91,145,0.12); position: sticky; top: 0; z-index: 1000;
        }
        .navbar h2 { margin: 0; font-size: 34px; color: var(--primary); font-weight: 900; letter-spacing: -1.5px; }
        
        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .btn-link { text-decoration: none; color: var(--primary); font-weight: 700; font-size: 16px; padding: 11px 16px; border: 1px solid #e5e7ef; border-radius: 12px; white-space: nowrap; transition: 0.2s; }
        .btn-link:hover { background: #eaf6ff; border-color: #b9dff3; }
        .btn-report { background: var(--danger); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 800; white-space: nowrap; transition: 0.3s; }
        .report-icon { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; margin-right: 6px; border: 2px solid currentColor; border-radius: 50%; font-size: 12px; line-height: 1; }
        .btn-report:hover { background: #b93838; transform: scale(1.05); box-shadow: 0 5px 15px rgba(217, 75, 75, 0.3); }

        /* Hero */
        .hero {
            padding: 2.5rem 5% 3rem; background: var(--blue-gradient); background-size: 240% 240%;
            animation: gradientBG 8s ease-in-out infinite; color: #123b73; text-align: center; position: relative; overflow: hidden;
        }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes pageBlueFlow { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

        .status-display {
            display: inline-block; font-size: clamp(30px, 6vw, 54px); font-weight: 900; margin: 10px 0 8px;
            padding: 8px 18px 10px; border: 1px solid rgba(255,255,255,0.7);
            border-radius: 16px; background: rgba(255,255,255,0.48);
            text-shadow: 0 4px 14px rgba(36,92,137,0.12); letter-spacing: 0;
        }

        .hero > p { font-size: 18px !important; letter-spacing: 0.02em; }

        .hero-float {
            display: none;
            position: absolute; z-index: 0; min-width: 132px; padding: 14px 16px;
            border: 1px solid rgba(255,255,255,0.72); border-radius: 18px;
            background: rgba(255,255,255,0.62); box-shadow: 0 14px 30px rgba(34,104,153,0.16);
            color: #174f8f; text-align: left; backdrop-filter: blur(8px);
            animation: floatCard 5s ease-in-out infinite;
        }
        .hero-float strong { display: block; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; }
        .hero-float span { display: block; margin-top: 5px; font-size: 20px; font-weight: 900; }
        .hero-float.left { left: 4%; top: 26%; transform: rotate(-4deg); }
        .hero-float.right { right: 4%; bottom: 22%; transform: rotate(4deg); animation-delay: -2.5s; }
        @keyframes floatCard { 0%, 100% { translate: 0 0; } 50% { translate: 0 -10px; } }

        .hero > p,
        .hero > .status-display,
        .hero > #clock { position: relative; z-index: 1; }

        .container-xl { width: 94%; margin: 24px auto 60px auto; }

        /* AI Card Section */
        .ai-card { 
            background: #fff; padding: 32px; border-radius: 28px; 
            border-top: 6px solid var(--primary); margin-bottom: 40px;
            box-shadow: 0 20px 42px rgba(36,104,157,0.22), 0 3px 0 rgba(255,255,255,0.9) inset; border-left: 1px solid #c3e0f1;
            border-right: 1px solid #c3e0f1; border-bottom: 1px solid #c3e0f1;
            display: flex; gap: 28px; align-items: stretch; animation: surfaceFloat 7s ease-in-out infinite;
        }

        .ai-left { flex: 1.4; min-width: 0; }
        .ai-right { 
            flex: 1; background: transparent; padding: 0; border-radius: 0; 
            border: 0;
            display: flex; flex-direction: column; justify-content: center;
        }

        .ai-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 8px; }
        .ai-heading .ai-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 11px; background: var(--primary); color: #fff; font-size: 13px; font-weight: 900; letter-spacing: 0.04em; }
        .ai-heading h3 { margin: 0; color: var(--primary); font-size: 22px; font-weight: 900; }
        .forecast-disclaimer { margin: 0 0 12px; color: #718797; font-size: 11px; line-height: 1.4; }
        .prediction-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 18px; }
        .pred-box { background: #fff; padding: 20px 14px; border-radius: 16px; text-align: center; border: 1px solid #dbeaf6; }
        .pred-box.emphasis { border: 1px solid var(--primary); background: #fff; }
        .pred-label { font-size: 12px; font-weight: 800; color: #486275; text-transform: uppercase; margin-bottom: 10px; display: block; }
        .pred-number { font-size: 34px; font-weight: 950; color: var(--primary); line-height: 1; }
        .forecast-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .forecast-summary > div { min-width: 0; min-height: 104px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #fff !important; box-shadow: 0 8px 18px rgba(45,112,164,0.08); transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .pred-box,
        .forecast-summary > div,
        .guide-card,
        .card { animation: cardRise 0.6s ease both, cardFloat 5.5s ease-in-out 0.7s infinite; }
        .pred-box:hover,
        .forecast-summary > div:hover,
        .guide-card:hover,
        .card:hover { transform: translateY(-7px) scale(1.01); box-shadow: 0 18px 30px rgba(45,112,164,0.22); }
        .pred-box:nth-child(2), .forecast-summary > div:nth-child(2), .card:nth-child(2) { animation-delay: 0.08s; }
        .card:nth-child(3) { animation-delay: 0.16s; }
        .card:nth-child(4) { animation-delay: 0.24s; }
        @keyframes cardRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes guidePop { from { opacity: 0; scale: .94; } to { opacity: 1; scale: 1; } }
        @keyframes cardFloat { 0%, 100% { translate: 0 0; } 50% { translate: 0 -4px; } }
        @keyframes surfaceFloat { 0%, 100% { translate: 0 0; } 50% { translate: 0 -3px; } }
        @media (prefers-reduced-motion: reduce) {
            .ai-card, .pred-box, .forecast-summary > div, .guide-card, .card, .section-box { animation: none; }
        }
        .ai-right > h4 { text-align: left; margin-top: 4px !important; font-size: 15px !important; letter-spacing: 0.04em; color: #3f596c !important; }
        .ai-insight { text-align: left; background: #eef7fd; border: 1px solid #d7eaf5; border-radius: 12px; padding: 12px 14px; color: #294762 !important; font-size: 15px !important; line-height: 1.45 !important; }

        /* Metrics Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .card { 
            background: #fff; padding: 35px; border-radius: 25px; 
            box-shadow: 0 16px 32px rgba(47,112,163,0.18), 0 3px 0 rgba(255,255,255,0.9) inset; border: 1px solid #c3e0f1; border-top: 10px solid var(--primary);
            text-align: center;
        }
        .val { font-size: 60px; font-weight: 900; color: var(--primary); line-height: 1; display: block; }
        .unit { font-size: 16px; color: #718797; font-weight: 700; display: block; margin-top: 5px; }

        /* Layout split */
        .content-split { display: grid; grid-template-columns: 1.35fr 1fr; gap: 24px; align-items: stretch; }
        .section-box { background: #fff; padding: 40px; border-radius: 35px; border: 1px solid #c3e0f1; box-shadow: 0 18px 36px rgba(47,112,163,0.18), 0 3px 0 rgba(255,255,255,0.9) inset; animation: surfaceFloat 7s ease-in-out 0.8s infinite; }
        .guide-card { background: #fff; padding: 22px; border-radius: 18px; border: 1px solid #c3e0f1; box-shadow: 0 14px 30px rgba(36,104,157,0.16), 0 3px 0 rgba(255,255,255,0.9) inset; margin-bottom: 24px; animation: guidePop 0.75s cubic-bezier(.2,.8,.2,1) both, cardFloat 5.5s ease-in-out 1.1s infinite; }
        .guide-card h3 { margin: 0 0 12px; font-size: 16px; color: var(--primary); }
        .guide-card p { margin: 0 0 14px; color: #5f677a; font-size: 14px; }
        .guide-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .guide-list li { background: #eef8ff; color: #38566e; padding: 12px 14px; border-radius: 12px; font-size: 13px; line-height: 1.5; }
        .guide-list strong { color: var(--primary); }
        .table-scroll { max-width: 100%; max-height: min(55vh, 500px); overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .sensor-log-box { align-self: start; padding: 26px; min-width: 0; height: max-content; }
        .sensor-log-box .table-scroll { max-height: 360px; min-height: 0; overflow-x: auto; overflow-y: auto; }
        .sensor-log-box table { min-width: 420px; }
        .sensor-log-box th { position: sticky; top: 0; z-index: 1; background: #fff; }
        
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th { padding: 10px 12px; text-align: center; border-bottom: 3px solid #dceef9; color: var(--primary); font-size: 11px; text-transform: uppercase; }
        td { padding: 10px 12px; text-align: center; border-bottom: 1px solid #e5f2fa; font-weight: 600; }

        @media (max-width: 900px) {
            .navbar {
                flex-direction: column;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                padding: 1rem 5%;
            }

            .nav-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
                max-width: 520px;
                gap: 8px;
                margin: 0 auto;
                align-self: center;
            }

            .nav-actions .btn-report { grid-column: 1 / -1; }

            .ai-card {
                flex-direction: column;
                gap: 24px;
                padding: 26px 20px;
                margin-bottom: 24px;
            }

            .ai-left,
            .ai-right {
                width: 100%;
            }

            .hero {
                padding: 1.8rem 1rem 2rem;
            }

            .container-xl {
                width: calc(100% - 24px);
                max-width: calc(100% - 24px);
                margin: 12px auto 30px;
            }

            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-split {
                grid-template-columns: 1fr;
            }

            .status-display {
                font-size: clamp(30px, 6vw, 48px);
                line-height: 1.05;
                letter-spacing: -1px;
                margin: 8px 0 12px;
                padding: 6px 12px 8px;
            }
        }

        @media (max-width: 560px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            body {
                background: linear-gradient(120deg, #d5efff, #f8fcff, #c6e9fb, #eaf7ff);
                background-size: 300% 300%;
                animation: pageBlueFlow 16s ease-in-out infinite;
            }

            .navbar {
                flex-direction: column;
                align-items: center;
                padding: 0.55rem 12px 0.7rem;
                gap: 7px;
            }

            .navbar h2 {
                font-size: 20px;
                letter-spacing: -0.6px;
                text-align: center;
            }

            .nav-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
                max-width: none;
                gap: 6px;
            }

            .btn-link,
            .btn-report {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
                min-height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 6px;
                font-size: 9px;
                border-radius: 8px;
                min-width: 0;
                justify-self: stretch;
            }

            .btn-report {
                grid-column: 1 / -1;
                min-height: 34px;
                border-radius: 9px;
                background: var(--danger);
                box-shadow: 0 6px 14px rgba(217,75,75,0.18);
                min-width: 0;
            }

            .btn-link { background: #eef7ff; border-color: #c7e3f4; }
            .report-icon { width: 12px; height: 12px; font-size: 8px; }

            .hero {
                padding: 1.2rem 1rem 1.7rem;
            }

            .hero-float { display: none; }

            .hero > p {
                font-size: 15px !important;
                letter-spacing: 0.04em;
                text-align: center;
            }

            .status-display {
                font-size: clamp(28px, 9vw, 46px);
                line-height: 1;
                margin: 8px 0 10px;
                padding: 7px 12px 9px;
                letter-spacing: -1px;
            }

            #clock {
                font-size: 18px !important;
            }

            .container-xl {
                width: calc(100% - 24px);
                max-width: calc(100% - 24px);
                margin: 12px auto 30px;
            }

            .ai-card {
                border-top-width: 5px;
                border-radius: 22px;
                padding: 18px 14px;
            }

            .ai-left,
            .ai-right {
                width: 100%;
            }

            .prediction-grid,
            .metrics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .pred-box {
                padding: 14px 8px;
            }

            .pred-number {
                font-size: 28px;
            }

            .card,
            .guide-card,
            .section-box,
            .sensor-log-box {
                border-radius: 18px;
                padding: 18px 12px;
            }

            .ai-right {
                padding: 0;
                border-radius: 0;
            }

            .card .val {
                font-size: 32px;
            }

            .card {
                min-width: 0;
                padding: 16px 8px;
                border-top-width: 7px;
                box-shadow: 0 14px 28px rgba(36,104,157,0.18), 0 3px 0 rgba(255,255,255,0.9) inset;
            }

            .ai-insight { padding: 10px 12px; font-size: 14px !important; }

            .content-split {
                gap: 16px;
            }

            .sensor-log-box {
                padding: 14px;
            }

            .table-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .sensor-log-box table {
                min-width: 620px;
            }

            table {
                font-size: 12px;
            }

            th,
            td {
                padding: 8px 6px;
            }
        }

        @media (max-width: 360px) {
            .navbar h2 {
                font-size: 22px;
            }

            .hero > p {
                font-size: 16px !important;
            }

            .nav-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                max-width: none;
            }

            .btn-report {
                grid-column: 1 / -1;
            }

            .status-display {
                font-size: clamp(30px, 12vw, 44px);
                line-height: 1.05;
                margin: 8px 0 12px;
                padding: 6px 10px 8px;
            }

            #clock { display: block; margin-top: 4px; }

            .prediction-grid,
            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .card,
            .section-box,
            .sensor-log-box {
                padding: 16px 10px;
            }
        }

    </style>
</head>
<body>

<nav class="navbar">
    <h2>CITY AIRSHED MS</h2>
    <div class="nav-actions">
        <a href="login.php" class="btn-link">User Login</a>
        <a href="register.php" class="btn-link">Create Account</a>
        <a href="report.php" class="btn-report"><span class="report-icon" aria-hidden="true">!</span>REPORT INCIDENT</a>
    </div>
</nav>

<div class="hero">
    <p style="font-size: 24px; font-weight: 700; opacity: 0.9; margin: 0;">CALAPAN CITY AIR QUALITY INDEX</p>
    <div id="statusText" class="status-display">CHECKING DATA...</div>
    <div id="clock" style="font-size: 22px; font-weight: 500; opacity: 0.8;"></div>
    <div class="hero-float left" aria-hidden="true"><strong>Air Monitor</strong><span>Live Sensor</span></div>
    <div class="hero-float right" aria-hidden="true"><strong>City Status</strong><span>Calapan Air</span></div>
</div>

<div class="container-xl">
    <div class="ai-card">
        <div class="ai-left">
            <div class="ai-heading">
                <span class="ai-icon" aria-hidden="true">AI</span>
                <h3>AI ANALYTICS</h3>
            </div>
            <p class="forecast-disclaimer">Forecast values are estimates based on recent device readings and may change as new data arrives.</p>
            <div class="prediction-grid">
                <div class="pred-box">
                    <span class="pred-label">Next 30 Minutes</span>
                    <span id="forecast30" class="pred-number">---</span>
                    <span class="unit">µg/m³</span>
                </div>
                <div class="pred-box emphasis">
                    <span class="pred-label">Next 2 Hours</span>
                    <span id="forecastVal" class="pred-number">---</span>
                    <span class="unit">µg/m³</span>
                </div>
            </div>
        </div>

        <div class="ai-right">
            <div class="forecast-summary">
                <div style="background:#fff; border: 1px solid #dbe5f4; border-radius:16px; padding:14px; text-align:center;">
                    <div style="font-size:12px; font-weight:800; color:var(--primary); text-transform:uppercase;">Humidity Forecast</div>
                    <div id="humForecast" style="font-size:28px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                </div>
                <div style="background:#fff; border: 1px solid #dbe5f4; border-radius:16px; padding:14px; text-align:center;">
                    <div style="font-size:12px; font-weight:800; color:var(--primary); text-transform:uppercase;">Weather Outlook</div>
                    <div id="weatherForecast" style="font-size:20px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                </div>
            </div>
            <h4 style="margin: 0 0 10px 0; color: var(--primary); font-size: 20px; font-weight: 800;">STATUS RECOMMENDATION</h4>
            <p id="aiInsight" class="ai-insight" style="margin: 0;">
                Initializing AI models for real-time forecasting...
            </p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="card"><label>Temperature</label><span class="val" id="temp">--</span><span class="unit">Celsius (°C)</span></div>
        <div class="card"><label>Humidity</label><span class="val" id="hum">--</span><span class="unit">Percentage (%)</span></div>
        <div class="card" style="border-top-color: #2b8fc4;"><label>PM2.5 Level</label><span class="val" id="pm25">--</span><span class="unit">µg/m³</span></div>
        <div class="card" style="border-top-color: #0f9f86;"><label>CO Gas</label><span class="val" id="co">--</span><span class="unit">ppm</span></div>
    </div>

    <div class="guide-card">
        <h3>How to Read Your Airshed Dashboard</h3>
        <p>These guidelines explain what each measurement is, what it is for, and what action to take.</p>
        <ul class="guide-list">
            <li><strong>PM2.5</strong>: Tiny particles in the air from smoke, dust, and combustion. This is the main air-quality indicator: 0-12 = Good, 12-35 = Moderate, 35+ = Poor.</li>
            <li><strong>CO Gas</strong>: Carbon monoxide from burning fuel and combustion. It helps detect smoke or poor ventilation; target below 9 ppm.</li>
            <li><strong>Temperature</strong>: How hot or cool the air is, used to understand comfort and weather conditions. The ideal range is 20-28°C.</li>
            <li><strong>Humidity</strong>: The amount of moisture in the air, used to understand how dry or heavy the air feels. 30-60% is optimal.</li>
            <li><strong>System status</strong>: Shows whether the sensor is sending data. Online means connected; Offline means the portal cannot reach the sensor node.</li>
        </ul>
    </div>

    <div class="content-split">
        <div class="section-box sensor-log-box">
            <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 25px; color: var(--primary);">LIVE TREND: PM2.5 & TEMPERATURE</h3>
            <canvas id="dualChart" height="220"></canvas>
        </div>

        <div class="section-box">
            <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 25px; color: var(--primary);">DETAILED SENSOR LOGS</h3>
            <div class="table-scroll"><table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Temp</th>
                        <th>Humidity</th>
                        <th>PM2.5</th>
                        <th>CO Gas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="historyBody"></tbody>
            </table></div>
        </div>
    </div>
</div>

<script>
let tempHistory = [];
let humHistory = [];
let lastAlertRequest = 0;
let historyInitialized = false;

function requestCriticalAlert(pm25) {
    const now = Date.now();
    if (pm25 <= 35 || now - lastAlertRequest < 30 * 60 * 1000) return;
    lastAlertRequest = now;
    const formData = new FormData();
    formData.append('pm25', pm25);
    fetch('automated_alerts.php', { method: 'POST', body: formData }).catch(() => {});
}

function getWeatherOutlook(temp, hum) {
    if (temp >= 33) return "Hot";
    if (temp >= 28) return "Warm";
    if (temp <= 20) return "Cool";
    return "Pleasant";
}

function clampForecast(value, min, max) {
    return value === null ? null : Math.min(max, Math.max(min, Number(value)));
}

function renderHistoryRows(history) {
    const historyBody = document.getElementById('historyBody');
    historyBody.innerHTML = '';
    history.slice().reverse().slice(0, 10).forEach(reading => {
        const pm25 = Number(reading.pm25);
        const temp = Number(reading.temp);
        if (!Number.isFinite(pm25) || !Number.isFinite(temp) || temp <= 0 || temp > 60 || pm25 < 0) return;
        const status = pm25 > 35 ? 'POOR' : (pm25 > 12 ? 'MODERATE' : 'GOOD');
        const statusColor = status === 'POOR' ? '#c43d4b' : (status === 'MODERATE' ? '#b87512' : '#168a68');
        const readingTime = reading.timestamp ? new Date(reading.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Recent';
        const humidity = Number(reading.hum);
        const co = Number(reading.co);
        const humidityText = Number.isFinite(humidity) ? `${humidity.toFixed(1)}%` : '--';
        const coText = Number.isFinite(co) ? co.toFixed(1) : '--';
        historyBody.insertAdjacentHTML('beforeend', `<tr><td><b>${readingTime}</b></td><td style="color:#b87512">${temp.toFixed(1)}°C</td><td>${humidityText}</td><td>${pm25.toFixed(1)}</td><td>${coText}</td><td style="color:${statusColor}; font-weight:900;">${status}</td></tr>`);
    });
}

// Dual-Axis Chart
const ctx = document.getElementById('dualChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: { 
        labels: [], 
        datasets: [
            { label: 'PM2.5', data: [], borderColor: '#174ea6', borderWidth: 5, fill: true, tension: 0.4, yAxisID: 'y' },
            { label: 'Temp', data: [], borderColor: '#d88a18', borderWidth: 3, borderDash: [5, 5], tension: 0.4, yAxisID: 'y1' },
            { label: 'Humidity', data: [], borderColor: '#0f9f86', borderWidth: 3, tension: 0.4, yAxisID: 'y1' },
            { label: 'Gas/MQ', data: [], borderColor: '#2767b5', borderWidth: 3, tension: 0.4, yAxisID: 'y1' }
        ] 
    },
    options: { 
        responsive: true,
        scales: { 
            y: { type: 'linear', position: 'left' },
            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false } }
        }
    }
});

function getPrediction(history, steps) {
    if (history.length < 5) return null;
    let n = history.length, x = Array.from({length: n}, (_, i) => i);
    let sumX = x.reduce((a,b) => a+b, 0), sumY = history.reduce((a,b) => a+b, 0);
    let sumXY = x.reduce((acc, curr, i) => acc + (curr * history[i]), 0);
    let sumXX = x.reduce((a,b) => a + (b*b), 0);
    let slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
    let intercept = (sumY - slope * sumX) / n;
    let projected = Math.max(0, slope * (n + steps) + intercept);
    let observedMax = Math.max(...history);
    let observedMin = Math.min(...history);
    let reasonableCeiling = observedMax + Math.max(10, (observedMax - observedMin) * 2);
    return Math.min(projected, reasonableCeiling).toFixed(1);
}

function fetchData() {
    fetch('get_latest_data.php', { cache: 'no-store' })
    .then(res => res.json())
    .then(d => {
        if (d.live !== true) {
            document.getElementById('temp').innerText = '--';
            document.getElementById('hum').innerText = '--';
            document.getElementById('pm25').innerText = '--';
            document.getElementById('co').innerText = '--';
            document.getElementById('statusText').innerText = 'NO LIVE DATA';
            document.getElementById('statusText').style.color = '#a9404a';
                const recentHistory = Array.isArray(d.prediction_history) ? d.prediction_history : [];
            renderHistoryRows(recentHistory);
                const recentPmHistory = recentHistory
                    .map(reading => Number(reading.pm25))
                    .filter(Number.isFinite);
                const recentTempHistory = recentHistory
                    .map(reading => Number(reading.temp))
                    .filter(Number.isFinite)
                    .filter(value => value > 0);
                const recentHumHistory = recentHistory
                    .map(reading => Number(reading.hum))
                    .filter(Number.isFinite)
                    .filter(value => value > 0);
                const recentP30 = getPrediction(recentPmHistory, 600);
                const recentP2h = getPrediction(recentPmHistory, 2400);
                const recentForecastTemp = getPrediction(recentTempHistory, 600);
                const recentForecastHum = getPrediction(recentHumHistory, 600);
                const recentTempValue = clampForecast(recentForecastTemp, 0, 60);
                const recentHumValue = clampForecast(recentForecastHum, 0, 100);

                document.getElementById('forecast30').innerText = recentP30 || '---';
                document.getElementById('forecastVal').innerText = recentP2h || '---';
                document.getElementById('humForecast').innerText = recentHumValue !== null ? `${recentHumValue.toFixed(1)}%` : '--';
                document.getElementById('weatherForecast').innerText = recentTempValue !== null
                    ? `${getWeatherOutlook(recentTempValue, recentHumValue)} · ${recentTempValue.toFixed(1)}°C`
                    : 'Waiting for live sensor data...';
                document.getElementById('aiInsight').innerHTML = recentP2h
                    ? 'Forecast based on the latest available sensor readings. Live sensor data is currently offline.'
                    : 'Waiting for enough sensor readings to calculate a forecast.';
            return;
        }

        const currentTemp = parseFloat(d.temp);
        const currentHum = parseFloat(d.hum);
        if (!historyInitialized && Array.isArray(d.prediction_history)) {
            d.prediction_history.forEach(reading => {
                const historyTemp = Number(reading.temp);
                const historyHum = Number(reading.hum);
                const historyPm25 = Number(reading.pm25);
                if (Number.isFinite(historyTemp) && historyTemp > 0 && historyTemp <= 60) tempHistory.push(historyTemp);
                if (Number.isFinite(historyHum) && historyHum >= 0 && historyHum <= 100) humHistory.push(historyHum);
                if (Number.isFinite(historyPm25) && historyPm25 >= 0) chart.data.datasets[0].data.push(historyPm25);
            });
            historyInitialized = true;
        }
        tempHistory.push(currentTemp);
        humHistory.push(currentHum);
        if (tempHistory.length > 12) tempHistory.shift();
        if (humHistory.length > 12) humHistory.shift();

        const forecastTemp = tempHistory.length >= 5 ? getPrediction(tempHistory, 600) : null;
        const forecastHum = humHistory.length >= 5 ? getPrediction(humHistory, 600) : null;
        const forecastTempValue = clampForecast(forecastTemp, 0, 60);
        const forecastHumValue = clampForecast(forecastHum, 0, 100);
        const outlook = getWeatherOutlook(currentTemp, currentHum);

        document.getElementById('temp').innerText = d.temp;
        document.getElementById('hum').innerText = d.hum;
        document.getElementById('pm25').innerText = d.pm25;
        document.getElementById('co').innerText = d.co;
        document.getElementById('humForecast').innerText = forecastHumValue !== null ? `${forecastHumValue.toFixed(1)}%` : '--';
        document.getElementById('weatherForecast').innerText = `${outlook} · ${forecastTempValue !== null ? `${forecastTempValue.toFixed(1)}°C` : '...'}`;

        let status = d.pm25 > 35 ? "POOR" : (d.pm25 > 12 ? "MODERATE" : "GOOD");
        let tColor = d.pm25 > 35 ? "#c43d4b" : (d.pm25 > 12 ? "#b87512" : "#168a68");
        document.getElementById('statusText').innerText = status;
        document.getElementById('statusText').style.color = tColor;
        requestCriticalAlert(Number(d.pm25));

        const pmHistory = chart.data.datasets[0].data;
        const p30 = getPrediction(pmHistory, 600); 
        const p2h = getPrediction(pmHistory, 2400); 

        if(p30 && p2h) {
            document.getElementById('forecast30').innerText = p30;
            document.getElementById('forecastVal').innerText = p2h;

            let f2 = parseFloat(p2h);
            let trend = f2 > d.pm25 ? "📈 Rising" : "📉 Falling";
            let advice = "";
            if(f2 > 35) advice = "🚨 <b>Alert:</b> Dangerous levels predicted. Prepare N95 masks.";
            else if(f2 > d.pm25 + 2) advice = "😷 <b>Warning:</b> Air is deteriorating. Close windows later.";
            else if(f2 < d.pm25 - 2) advice = "✅ <b>Good:</b> Air is clearing up. Safe for outdoor tasks soon.";
            else advice = "📊 <b>Stable:</b> Conditions are consistent. Stay safe!";
            document.getElementById('aiInsight').innerHTML = `<b>Trend:</b> ${trend}<br>${advice}<br><b>Weather:</b> ${outlook} with humidity forecast at ${forecastHumValue !== null ? `${forecastHumValue.toFixed(1)}%` : '--'}.`;
        }

        let now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        chart.data.labels.push(now);
        chart.data.datasets[0].data.push(d.pm25);
        chart.data.datasets[1].data.push(d.temp);
        chart.data.datasets[2].data.push(d.hum);
        chart.data.datasets[3].data.push(d.co);
        if(chart.data.labels.length > 15) { 
            chart.data.labels.shift(); 
            chart.data.datasets.forEach(dataset => dataset.data.shift());
        }
        chart.update('none');

        let row = `<tr><td><b>${now}</b></td><td style="color:#b87512">${d.temp}°C</td><td>${d.hum}%</td><td>${d.pm25}</td><td>${d.co}</td><td style="color:${tColor}; font-weight:900;">${status}</td></tr>`;
        document.getElementById('historyBody').insertAdjacentHTML("afterbegin", row);
        if(document.getElementById('historyBody').rows.length > 10) {
            document.getElementById('historyBody').deleteRow(10);

            let status = "UNKNOWN";

            if (d.pm25 !== undefined && d.pm25 !== null) {
            status = d.pm25 > 35 ? "POOR" : (d.pm25 > 12 ? "MODERATE" : "GOOD");
        }

            let tColor = status === "POOR" ? "#c43d4b" : (status === "MODERATE" ? "#b87512" : "#168a68");
            document.getElementById('statusText').innerText = status;
            document.getElementById('statusText').style.color = tColor;
        }

        fetch("save_all.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `temp=${encodeURIComponent(d.temp)}&hum=${encodeURIComponent(d.hum)}&co=${encodeURIComponent(d.co)}&pm25=${encodeURIComponent(d.pm25)}&status=${encodeURIComponent(status)}`
        }).catch(err => console.error("Firebase save error:", err));

    })
    .catch(err => console.log("Fetch error:", err));
}

setInterval(fetchData, 3000);
setInterval(() => document.getElementById('clock').innerText = new Date().toLocaleString(), 1000);
fetchData();
</script>

</body>
</html>