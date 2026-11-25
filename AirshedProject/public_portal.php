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
    <script src="air_quality.js"></script>
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
        .sensor-freshness { margin: 4px auto 0; color: #174f8f; font-size: 13px; font-weight: 700; }
        .sensor-freshness.is-stale { color: #9c3d36; }

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
        span.forecast-disclaimer, .forecast-summary .forecast-disclaimer { display: block; margin: 6px 0 0; font-size: 10px; overflow-wrap: anywhere; }
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
        .category-legend { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
        .category-legend span { padding: 9px 7px; border-radius: 10px; text-align: center; font-size: 12px; font-weight: 800; }
        .category-good { background: #e7f5ef; color: #168a68; }
        .category-moderate { background: #fff4dc; color: #9a6200; }
        .category-unhealthy { background: #fff0e2; color: #b35300; }
        .category-hazardous { background: #fdebed; color: #a52e3b; }
        .category-source { margin-top: 10px !important; font-size: 12px !important; }
        .pm-category { display: block; margin: 9px 0 0; font-size: 15px; font-weight: 900; }
        .pm-advice { display: block; margin-top: 5px; color: #536b7d; font-size: 12px; line-height: 1.4; }
        .chart-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        .chart-heading h3 { margin: 0; }
        .chart-ranges { display: flex; gap: 6px; flex-shrink: 0; }
        .chart-range { border: 1px solid #cbdceb; border-radius: 8px; background: #fff; color: var(--primary); cursor: pointer; font: inherit; font-size: 12px; font-weight: 800; padding: 7px 10px; }
        .chart-range[aria-pressed="true"] { background: var(--primary); border-color: var(--primary); color: #fff; }
        .chart-description { margin: 0 0 12px; color: #607789; font-size: 12px; }
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
                min-width: 0;
            }

            .chart-heading {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 12px;
            }

            .chart-heading h3 {
                font-size: 17px !important;
                margin-bottom: 0 !important;
            }

            .chart-range {
                min-height: 38px;
                padding: 8px 14px;
            }

            .category-legend {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sensor-log-box .table-scroll {
                overflow-x: visible;
            }

            .sensor-log-box table,
            .sensor-log-box tbody,
            .sensor-log-box tr,
            .sensor-log-box td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            .sensor-log-box thead {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }

            .sensor-log-box tbody tr {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0 10px;
                padding: 8px 10px;
                margin-bottom: 10px;
                border: 1px solid #dceaf4;
                border-radius: 12px;
                background: #fbfdff;
            }

            .sensor-log-box td {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 8px;
                min-width: 0;
                padding: 6px 0;
                text-align: right;
                font-size: 11px;
                overflow-wrap: anywhere;
            }

            .sensor-log-box td::before {
                content: attr(data-label);
                color: #527087;
                font-size: 10px;
                font-weight: 800;
                text-align: left;
            }

            .sensor-log-box td:first-child {
                grid-column: 1 / -1;
                justify-content: center;
                border-bottom: 1px solid #e5f2fa;
                margin-bottom: 3px;
                text-align: center;
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
    <div id="sensorFreshness" class="sensor-freshness" role="status" aria-live="polite">Checking sensor connection...</div>
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
            <p class="forecast-disclaimer">Estimates are based on the latest sensor reading. Historical MAE describes past test error, not a guarantee of future accuracy.</p>
            <div class="prediction-grid">
                <div class="pred-box">
                    <span class="pred-label">Next 30 Minutes</span>
                    <span id="forecast30" class="pred-number">---</span>
                    <span class="unit">µg/m³</span>
                    <span id="forecast30Meta" class="forecast-disclaimer"></span>
                </div>
                <div class="pred-box emphasis">
                    <span class="pred-label">Next 2 Hours</span>
                    <span id="forecastVal" class="pred-number">---</span>
                    <span class="unit">µg/m³</span>
                    <span id="forecast2hMeta" class="forecast-disclaimer"></span>
                </div>
            </div>
        </div>

        <div class="ai-right">
            <div class="forecast-summary">
                <div style="background:#fff; border: 1px solid #dbe5f4; border-radius:16px; padding:14px; text-align:center;">
                    <div style="font-size:12px; font-weight:800; color:var(--primary); text-transform:uppercase;">Humidity Forecast</div>
                    <div id="humForecast" style="font-size:28px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                    <div id="humidityForecastMeta" class="forecast-disclaimer"></div>
                </div>
                <div style="background:#fff; border: 1px solid #dbe5f4; border-radius:16px; padding:14px; text-align:center;">
                    <div style="font-size:12px; font-weight:800; color:var(--primary); text-transform:uppercase;">Weather Outlook</div>
                    <div id="weatherForecast" style="font-size:20px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                    <div id="weatherForecastMeta" class="forecast-disclaimer"></div>
                </div>
            </div>
            <h4 style="margin: 0 0 10px 0; color: var(--primary); font-size: 20px; font-weight: 800;">STATUS RECOMMENDATION</h4>
            <p id="aiInsight" class="ai-insight" style="margin: 0;">
                Checking recent trends and historical forecast accuracy...
            </p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="card"><label>Temperature</label><span class="val" id="temp">--</span><span class="unit">Celsius (°C)</span></div>
        <div class="card"><label>Humidity</label><span class="val" id="hum">--</span><span class="unit">Percentage (%)</span></div>
        <div class="card" style="border-top-color: #2b8fc4;"><label>PM2.5 Level</label><span class="val" id="pm25">--</span><span class="unit">µg/m³</span><span id="pm25Category" class="pm-category">Waiting for reading</span><span id="pm25Advice" class="pm-advice">A category will appear when a valid reading arrives.</span></div>
        <div class="card" style="border-top-color: #0f9f86;"><label>CO Sensor Signal (MQ-7)</label><span class="val" id="co">--</span><span class="unit">Raw sensor response · ADC counts</span></div>
    </div>

    <div class="guide-card">
        <h3>How to Read Your Airshed Dashboard</h3>
        <p>These guidelines explain what each measurement is, what it is for, and what action to take.</p>
        <ul class="guide-list">
            <li><strong>PM2.5</strong>: Fine particles from smoke, dust, and combustion. Missing or out-of-range readings are not classified.</li>
            <li><strong>CO sensor (MQ-7)</strong>: Displays the sensor response as raw ADC counts for observing changes in the signal over time.</li>
            <li><strong>Temperature</strong>: Current outdoor temperature from the weather service. At 33°C or higher, stay hydrated and limit heat exposure.</li>
            <li><strong>Humidity</strong>: Current outdoor relative humidity. At 85% or higher, expect very humid conditions and take extra care outdoors.</li>
            <li><strong>System status</strong>: Online means recent sensor data is available; Offline means the portal has no live reading to display.</li>
        </ul>
        <div class="category-legend" aria-label="Airshed PM2.5 display bands">
            <span class="category-good">Good<br>0–12 µg/m³</span>
            <span class="category-moderate">Moderate<br>&gt;12–35 µg/m³</span>
            <span class="category-unhealthy">Unhealthy<br>&gt;35–150 µg/m³</span>
            <span class="category-hazardous">Hazardous<br>&gt;150–1000 µg/m³</span>
        </div>
        <p class="category-source">These are the Airshed dashboard's configured display bands, not a claim of official AQI certification. Confirm applicable thresholds with the local health or environmental authority.</p>
    </div>

    <div class="content-split">
        <div class="section-box sensor-log-box">
            <div class="chart-heading">
                <h3 style="font-size: 22px; font-weight: 800; color: var(--primary);">SENSOR TREND</h3>
                <div class="chart-ranges" role="group" aria-label="Chart time range">
                    <button class="chart-range" type="button" data-window="1h" aria-pressed="true">1 hour</button>
                    <button class="chart-range" type="button" data-window="24h" aria-pressed="false">24 hours</button>
                </div>
            </div>
            <p id="chartDescription" class="chart-description">Each point is a unique sensor reading, labeled with its recorded time.</p>
            <canvas id="dualChart" height="220"></canvas>
        </div>

        <div class="section-box">
            <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 25px; color: var(--primary);">DETAILED SENSOR LOGS</h3>
            <div class="table-scroll"><table>
                <thead>
                    <tr>
                        <th>Date &amp; time</th>
                        <th>Temp</th>
                        <th>Humidity</th>
                        <th>PM2.5</th>
                        <th>MQ Gas (raw ADC)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="historyBody"></tbody>
            </table></div>
        </div>
    </div>
</div>

<script>
let lastAlertRequest = 0;

function formatAirshedDateTime(value) {
    const date = value instanceof Date ? value : new Date(value);
    if (!Number.isFinite(date.getTime())) return '--';
    return date.toLocaleString('en-GB', {
        timeZone: 'Asia/Manila',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
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

function getWeatherOutlook(temp, hum) {
    if (temp === null || temp === undefined || temp === '' || !Number.isFinite(Number(temp))) return 'Unavailable';
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
        const pm25 = reading.pm25 === null || reading.pm25 === undefined ? null : Number(reading.pm25);
        const temp = reading.temp === null || reading.temp === undefined ? NaN : Number(reading.temp);
        if (!Number.isFinite(temp) || temp < -40 || temp > 85) return;
        const classification = AirshedAirQuality.classifyPm25(pm25);
        const status = classification.label;
        const statusColor = classification.color;
        const readingTime = reading.timestamp ? formatAirshedDateTime(reading.timestamp) : 'Recent';
        const humidity = Number(reading.hum);
        const co = reading.co === null || reading.co === undefined ? NaN : Number(reading.co);
        const humidityText = Number.isFinite(humidity) ? `${humidity.toFixed(1)}%` : '--';
        const coText = Number.isFinite(co) ? co.toFixed(1) : '--';
        const pmColor = classification.color;
        historyBody.insertAdjacentHTML('beforeend', `<tr><td data-label="Date & time"><b>${readingTime}</b></td><td data-label="Temperature" style="color:#b87512">${temp.toFixed(1)}°C</td><td data-label="Humidity">${humidityText}</td><td data-label="PM2.5" style="color:${pmColor}; font-weight:900;">${pm25 !== null && Number.isFinite(pm25) ? pm25.toFixed(1) : '--'}</td><td data-label="MQ raw ADC">${coText}</td><td data-label="PM2.5 status" style="color:${statusColor}; font-weight:900;">${status}</td></tr>`);
    });
}

const chartHistory = new Map();
let chartRows = [];
let selectedChartWindow = '1h';

function mergeChartReadings(readings) {
    if (!Array.isArray(readings)) return;
    readings.forEach(reading => {
        const timestampMs = Date.parse(reading.timestamp || '');
        if (!Number.isFinite(timestampMs)) return;
        chartHistory.set(timestampMs, { ...reading, timestampMs });
    });

    const oldestKeptTime = Date.now() - 24 * 60 * 60 * 1000;
    chartHistory.forEach((reading, timestampMs) => {
        if (timestampMs < oldestKeptTime) chartHistory.delete(timestampMs);
    });
}

function formatChartLabel(timestampMs) {
    const options = selectedChartWindow === '24h'
        ? { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }
        : { hour: '2-digit', minute: '2-digit', hour12: false };
    return new Date(timestampMs).toLocaleString('en-GB', { ...options, timeZone: 'Asia/Manila' });
}

function renderSensorChart() {
    const windowMs = selectedChartWindow === '24h' ? 24 * 60 * 60 * 1000 : 60 * 60 * 1000;
    const cutoff = Date.now() - windowMs;
    chartRows = Array.from(chartHistory.values())
        .filter(reading => reading.timestampMs >= cutoff)
        .sort((left, right) => left.timestampMs - right.timestampMs);

    chart.data.labels = chartRows.map(reading => formatChartLabel(reading.timestampMs));
    chart.data.datasets[0].data = chartRows.map(reading => reading.pm25);
    chart.data.datasets[1].data = chartRows.map(reading => reading.temp);
    chart.data.datasets[2].data = chartRows.map(reading => reading.hum);
    chart.data.datasets[3].data = chartRows.map(reading => reading.co);

    const pmValues = chartRows.map(reading => Number(reading.pm25)).filter(Number.isFinite);
    const pmMaximum = pmValues.length ? Math.max(...pmValues) : 0;
    chart.options.scales.y.suggestedMax = pmMaximum > 40 ? Math.ceil(pmMaximum / 50) * 50 : 40;
    chart.update('none');

    const rangeText = selectedChartWindow === '24h' ? '24 hours' : '1 hour';
    document.getElementById('chartDescription').innerText = chartRows.length
        ? `Showing ${chartRows.length} unique sensor readings from the last ${rangeText}. Times are the device reading timestamps.`
        : `No sensor readings are available for the last ${rangeText}.`;
}

function loadChartWindow(windowName) {
    if (windowName !== '1h' && windowName !== '24h') return;
    selectedChartWindow = windowName;
    document.querySelectorAll('.chart-range').forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.window === windowName));
    });

    fetch(`get_latest_data.php?history_window=${encodeURIComponent(windowName)}`, { cache: 'no-store' })
        .then(response => {
            if (!response.ok) throw new Error(`Sensor history request failed (${response.status})`);
            return response.json();
        })
        .then(data => {
            mergeChartReadings(data.prediction_history);
            if (selectedChartWindow === windowName) renderSensorChart();
        })
        .catch(error => {
            console.error(error);
            document.getElementById('chartDescription').innerText = 'Could not load sensor history. Try selecting the range again.';
        });
}

function updateSensorFreshness(isLive, timestamp) {
    const freshness = document.getElementById('sensorFreshness');
    const date = timestamp ? new Date(timestamp) : null;
    const hasValidTime = date && Number.isFinite(date.getTime());
    freshness.classList.toggle('is-stale', !isLive);
    if (!hasValidTime) {
        freshness.innerText = isLive ? 'Sensor online · reading time unavailable' : 'Sensor offline · no recent reading';
        return;
    }

    const ageSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    const ageText = ageSeconds < 60 ? `${ageSeconds}s ago` : `${Math.floor(ageSeconds / 60)}m ago`;
    freshness.innerText = `${isLive ? 'Sensor online' : 'Sensor offline'} · Last reading ${formatAirshedDateTime(date)} · ${ageText}`;
}

function updatePm25Category(value) {
    const category = AirshedAirQuality.classifyPm25(value);
    const categoryElement = document.getElementById('pm25Category');
    const adviceElement = document.getElementById('pm25Advice');
    categoryElement.innerText = category.label === 'Unknown' || category.label === 'Invalid'
        ? 'Category unavailable'
        : `${category.label} · Airshed display band`;
    categoryElement.style.color = category.color;

    const advice = {
        GOOD: 'Within the dashboard’s Good display band.',
        MODERATE: 'Sensitive individuals may wish to limit prolonged outdoor activity.',
        UNHEALTHY: 'Consider reducing prolonged outdoor exposure.',
        HAZARDOUS: 'Follow local public-health guidance and reduce outdoor exposure.'
    };
    adviceElement.innerText = advice[category.level] || 'A category will appear when a valid reading arrives.';
}

function updateForecastContext(timestamp, horizonMinutes, metrics, elementId) {
    document.getElementById(elementId).innerText = AirshedAirQuality.forecastDetails(timestamp, horizonMinutes, metrics);
}

// Dual-Axis Chart
const ctx = document.getElementById('dualChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: { 
        labels: [], 
        datasets: [
            {
                label: 'PM2.5',
                data: [],
                borderColor: '#168a68',
                backgroundColor: 'rgba(22, 138, 104, 0.12)',
                borderWidth: 5,
                fill: true,
                tension: 0.4,
                yAxisID: 'y',
                pointRadius: 4,
                pointBackgroundColor: function(context) {
                    return AirshedAirQuality.classifyPm25(context.raw).color;
                },
                pointBorderColor: function(context) {
                    return AirshedAirQuality.classifyPm25(context.raw).color;
                },
                segment: {
                    borderColor: function(context) {
                        return AirshedAirQuality.classifyPm25(context.p1.raw).color;
                    }
                }
            },
            { label: 'Temp', data: [], borderColor: '#d88a18', borderWidth: 3, borderDash: [5, 5], tension: 0.4, yAxisID: 'y1' },
            { label: 'Humidity', data: [], borderColor: '#0f9f86', borderWidth: 3, tension: 0.4, yAxisID: 'y1' },
            { label: 'MQ Gas (raw ADC)', data: [], borderColor: '#8e44ad', borderWidth: 3, tension: 0.4, yAxisID: 'y1' }
        ] 
    },
    options: { 
        responsive: true,
        scales: { 
            y: { type: 'linear', position: 'left' },
            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false } }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    title: items => {
                        const reading = items.length ? chartRows[items[0].dataIndex] : null;
                        return reading ? formatAirshedDateTime(reading.timestampMs) : '';
                    }
                }
            }
        }
    }
});

function fetchData() {
    fetch('get_latest_data.php', { cache: 'no-store' })
    .then(res => {
        if (!res.ok) throw new Error(`Sensor data request failed (${res.status})`);
        return res.json();
    })
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
            mergeChartReadings(recentHistory);
            renderSensorChart();
            updateSensorFreshness(false, d.latest_timestamp);
            updatePm25Category(null);
            document.getElementById('forecast30').innerText = '---';
            document.getElementById('forecastVal').innerText = '---';
            updateForecastContext(null, 30, null, 'forecast30Meta');
            updateForecastContext(null, 120, null, 'forecast2hMeta');
            document.getElementById('humForecast').innerText = '--';
            updateForecastContext(null, 30, null, 'humidityForecastMeta');
            document.getElementById('weatherForecast').innerText = 'No live sensor data';
            updateForecastContext(null, 30, null, 'weatherForecastMeta');
            document.getElementById('aiInsight').innerText = 'Forecasts are paused while the sensor is offline.';
            return;
        }

        const recentHistory = Array.isArray(d.prediction_history) ? d.prediction_history : [];
        renderHistoryRows(recentHistory);
        mergeChartReadings(recentHistory);
        renderSensorChart();
        updateSensorFreshness(true, d.latest_timestamp);
        const currentTemp = parseFloat(d.temp);
        const currentHum = parseFloat(d.hum);

        const forecastTemp = d.forecast_temp !== undefined ? d.forecast_temp : null;
        const forecastHum = d.forecast_hum !== undefined ? d.forecast_hum : null;
        const forecastTempValue = clampForecast(forecastTemp, 0, 60);
        const forecastHumValue = clampForecast(forecastHum, 0, 100);
        const outlook = getWeatherOutlook(currentTemp, currentHum);

        document.getElementById('temp').innerText = d.temp === null ? '--' : d.temp;
        document.getElementById('hum').innerText = d.hum === null ? '--' : d.hum;
        const pmValue = d.pm25 === null || d.pm25 === undefined ? null : Number(d.pm25);
        document.getElementById('pm25').innerText = pmValue === null ? '--' : pmValue.toFixed(1);
        const classification = AirshedAirQuality.classifyPm25(d.pm25);
        document.getElementById('pm25').style.color = classification.color;
        updatePm25Category(d.pm25);
        document.getElementById('co').innerText = d.co;
        const forecastMetrics = d.forecast_metrics || {};
        document.getElementById('humForecast').innerText = forecastHumValue !== null ? `${forecastHumValue.toFixed(1)}%` : '--';
        updateForecastContext(d.latest_timestamp, 30, forecastMetrics.forecast_hum, 'humidityForecastMeta');
        document.getElementById('weatherForecast').innerText = `${outlook} · ${forecastTempValue !== null ? `${forecastTempValue.toFixed(1)}°C` : '...'}`;
        updateForecastContext(d.latest_timestamp, 30, forecastMetrics.forecast_temp, 'weatherForecastMeta');

        let status = classification.label;
        let tColor = classification.color;
        document.getElementById('statusText').innerText = status;
        document.getElementById('statusText').style.color = tColor;
        requestCriticalAlert(pmValue);

        const p30 = d.forecast30 !== undefined ? d.forecast30 : null;
        const p2h = d.forecast2h !== undefined ? d.forecast2h : null;
        document.getElementById('forecast30').innerText = p30 !== null && Number.isFinite(Number(p30)) ? Number(p30).toFixed(1) : '---';
        document.getElementById('forecastVal').innerText = p2h !== null && Number.isFinite(Number(p2h)) ? Number(p2h).toFixed(1) : '---';
        updateForecastContext(d.latest_timestamp, 30, forecastMetrics.forecast30, 'forecast30Meta');
        updateForecastContext(d.latest_timestamp, 120, forecastMetrics.forecast2h, 'forecast2hMeta');

        if(p2h !== null && pmValue !== null) {
            let f2 = parseFloat(p2h);
            let trend = f2 > d.pm25 ? "📈 Rising" : "📉 Falling";
            let advice = "";
            const forecastLevel = AirshedAirQuality.classifyPm25(f2).level;
            if(forecastLevel === 'HAZARDOUS') advice = "🚨 <b>Alert:</b> Hazardous levels predicted. Follow local health guidance.";
            else if(AirshedAirQuality.isAlertLevel(forecastLevel)) advice = "🚨 <b>Alert:</b> Unhealthy levels predicted. Prepare masks and limit outdoor activity.";
            else if(f2 > d.pm25 + 2) advice = "😷 <b>Warning:</b> Air is deteriorating. Close windows later.";
            else if(f2 < d.pm25 - 2) advice = "✅ <b>Good:</b> Air is clearing up. Safe for outdoor tasks soon.";
            else advice = "📊 <b>Stable:</b> Conditions are consistent. Stay safe!";
            document.getElementById('aiInsight').innerHTML = `<b>Trend:</b> ${trend}<br>${advice}<br><b>Weather:</b> ${outlook} with humidity forecast at ${forecastHumValue !== null ? `${forecastHumValue.toFixed(1)}%` : '--'}.`;
        } else {
            document.getElementById('aiInsight').innerText = AirshedAirQuality.forecastSummary(d.forecast_metrics && d.forecast_metrics.forecast2h, d.forecast_basis && d.forecast_basis.pm25_2h);
        }

    })
    .catch(err => {
        console.error(err);
        document.getElementById('statusText').innerText = 'DATA UNAVAILABLE';
        document.getElementById('temp').innerText = '--';
        document.getElementById('hum').innerText = '--';
        document.getElementById('pm25').innerText = '--';
        document.getElementById('co').innerText = '--';
        document.getElementById('forecast30').innerText = '---';
        document.getElementById('forecastVal').innerText = '---';
        document.getElementById('forecast30Meta').innerText = 'No validated forecast: sensor data could not be loaded.';
        document.getElementById('forecast2hMeta').innerText = 'No validated forecast: sensor data could not be loaded.';
        const freshness = document.getElementById('sensorFreshness');
        freshness.classList.add('is-stale');
        freshness.innerText = 'Sensor data unavailable · reconnecting';
        updatePm25Category(null);
    });
}

document.querySelectorAll('.chart-range').forEach(button => {
    button.addEventListener('click', () => loadChartWindow(button.dataset.window));
});
loadChartWindow(selectedChartWindow);
setInterval(fetchData, 3000);
setInterval(() => document.getElementById('clock').innerText = formatAirshedDateTime(new Date()), 1000);
fetchData();
</script>

</body>
</html>