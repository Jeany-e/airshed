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
            --primary: #1a237e; --ai-purple: #673ab7; --accent: #00c853; --bg: #f0f2f5; 
            --danger: #d32f2f; --text: #1c1e21;
            --blue-gradient: linear-gradient(-45deg, #0d47a1, #1a237e, #311b92, #1a237e);
        }

        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

        /* Navbar with User Focus */
        .navbar {
            background: #fff; padding: 1.2rem 5%; display: flex;
            justify-content: space-between; align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000;
        }
        .navbar h2 { margin: 0; font-size: 28px; color: var(--primary); font-weight: 900; letter-spacing: -1.5px; }
        
        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .btn-link { text-decoration: none; color: var(--primary); font-weight: 700; font-size: 16px; padding: 10px 15px; }
        .btn-report { background: var(--danger); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 800; transition: 0.3s; }
        .btn-report:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4); }

        /* Hero */
        .hero {
            padding: 4rem 5%; background: var(--blue-gradient); background-size: 400% 400%;
            animation: gradientBG 15s ease infinite; color: white; text-align: center;
        }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        .status-display { font-size: 100px; font-weight: 900; margin: 10px 0; text-shadow: 0 10px 30px rgba(0,0,0,0.3); letter-spacing: -3px; }

        .container-xl { width: 94%; margin: -60px auto 60px auto; }

        /* AI Card Section */
        .ai-card { 
            background: #fff; padding: 45px; border-radius: 35px; 
            border-left: 20px solid var(--ai-purple); margin-bottom: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            display: flex; gap: 40px; align-items: stretch;
        }

        .ai-left { flex: 1.4; }
        .ai-right { 
            flex: 1; background: #f8f7ff; padding: 35px; border-radius: 25px; 
            border: 3px dashed var(--ai-purple); display: flex; flex-direction: column; justify-content: center;
        }

        .prediction-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .pred-box { background: #f0edff; padding: 25px; border-radius: 20px; text-align: center; border: 1px solid #e0defa; }
        .pred-label { font-size: 13px; font-weight: 800; color: var(--ai-purple); text-transform: uppercase; margin-bottom: 10px; display: block; }
        .pred-number { font-size: 50px; font-weight: 950; color: var(--ai-purple); line-height: 1; }

        /* Metrics Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .card { 
            background: #fff; padding: 35px; border-radius: 25px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.05); border-top: 10px solid var(--primary);
            text-align: center;
        }
        .val { font-size: 60px; font-weight: 900; color: var(--primary); line-height: 1; display: block; }
        .unit { font-size: 16px; color: #95a5a6; font-weight: 700; display: block; margin-top: 5px; }

        /* Layout split */
        .content-split { display: grid; grid-template-columns: 1.35fr 1fr; gap: 24px; align-items: stretch; }
        .section-box { background: #fff; padding: 40px; border-radius: 35px; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .table-scroll { max-width: 100%; max-height: min(55vh, 500px); overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .sensor-log-box { align-self: start; padding: 26px; min-width: 0; height: max-content; }
        .sensor-log-box .table-scroll { max-height: 360px; min-height: 0; overflow-x: auto; overflow-y: auto; }
        .sensor-log-box table { min-width: 420px; }
        .sensor-log-box th { position: sticky; top: 0; z-index: 1; background: #fff; }
        
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th { padding: 10px 12px; text-align: center; border-bottom: 3px solid #f0f2f5; color: var(--primary); font-size: 11px; text-transform: uppercase; }
        td { padding: 10px 12px; text-align: center; border-bottom: 1px solid #f0f2f5; font-weight: 600; }

        @media (max-width: 900px) {
            .navbar {
                flex-wrap: wrap;
                gap: 10px;
                padding: 1rem 5%;
            }

            .nav-actions {
                flex-wrap: wrap;
                justify-content: flex-start;
                width: 100%;
            }

            .ai-card {
                flex-direction: column;
                gap: 24px;
                padding: 26px 20px;
            }

            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-split {
                grid-template-columns: 1fr;
            }

            .status-display {
                font-size: clamp(64px, 15vw, 94px);
                letter-spacing: -2px;
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
                background: linear-gradient(180deg, #edf3ff 0%, #f6f7fb 18%, #eef2f7 100%);
            }

            .navbar {
                flex-direction: column;
                align-items: stretch;
                padding: 0.9rem 4%;
            }

            .navbar h2 {
                font-size: 22px;
                letter-spacing: -0.8px;
                text-align: center;
            }

            .nav-actions {
                display: grid;
                grid-template-columns: 1fr;
                width: 100%;
                gap: 8px;
            }

            .btn-link,
            .btn-report {
                width: 100%;
                text-align: center;
                padding: 10px 12px;
                font-size: 12px;
                border-radius: 12px;
            }

            .hero {
                padding: 1.8rem 1rem 2.4rem;
            }

            .hero > p {
                font-size: 18px !important;
                letter-spacing: 0.04em;
                text-align: center;
            }

            .status-display {
                font-size: clamp(52px, 22vw, 76px);
                line-height: 1;
                margin: 10px 0 12px;
                letter-spacing: -2px;
            }

            #clock {
                font-size: 18px !important;
            }

            .container-xl {
                width: calc(100% - 24px);
                max-width: calc(100% - 24px);
                margin: -22px auto 30px;
            }

            .ai-card {
                border-left-width: 10px;
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
                padding: 16px 10px;
            }

            .pred-number {
                font-size: 28px;
            }

            .card,
            .section-box,
            .sensor-log-box,
            .ai-right {
                border-radius: 18px;
                padding: 18px 12px;
            }

            .card .val {
                font-size: 32px;
            }

            .card {
                min-width: 0;
                padding: 18px 8px;
                border-top-width: 7px;
                box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
            }

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
                min-width: 360px;
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
                font-size: 18px;
            }

            .hero > p {
                font-size: 16px !important;
            }

            .status-display {
                font-size: clamp(42px, 20vw, 60px);
            }

            .prediction-grid,
            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .card,
            .section-box,
            .sensor-log-box,
            .ai-right {
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
        <a href="register.php" class="btn-link" style="border: 1px solid #eee; border-radius: 8px;">Create Account</a>
        <a href="report.php" class="btn-report">🚨 REPORT INCIDENT</a>
    </div>
</nav>

<div class="hero">
    <p style="font-size: 24px; font-weight: 700; opacity: 0.9; margin: 0;">CALAPAN CITY AIR QUALITY INDEX</p>
    <div id="statusText" class="status-display">LOADING...</div>
    <div id="clock" style="font-size: 22px; font-weight: 500; opacity: 0.8;"></div>
</div>

<div class="container-xl">
    <div class="ai-card">
        <div class="ai-left">
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 10px;">
                <span style="font-size: 50px;">🤖</span>
                <h3 style="margin: 0; color: var(--ai-purple); font-size: 28px; font-weight: 900;">AI ANALYTICS</h3>
            </div>
            <div class="prediction-grid">
                <div class="pred-box">
                    <span class="pred-label">Next 30 Minutes</span>
                    <span id="forecast30" class="pred-number">---</span>
                    <span class="unit">µg/m³</span>
                </div>
                <div class="pred-box" style="border: 2px solid var(--ai-purple); background: #fff;">
                    <span class="pred-label">Next 2 Hours</span>
                    <span id="forecastVal" class="pred-number">---</span>
                    <span class="unit">µg/m³</span>
                </div>
            </div>
        </div>

        <div class="ai-right">
            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                <div style="flex:1; background:#fff; border: 1px solid #e7e0ff; border-radius:16px; padding:14px; text-align:center;">
                    <div style="font-size:12px; font-weight:800; color:var(--ai-purple); text-transform:uppercase;">Humidity Forecast</div>
                    <div id="humForecast" style="font-size:28px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                </div>
                <div style="flex:1; background:#fff; border: 1px solid #e7e0ff; border-radius:16px; padding:14px; text-align:center;">
                    <div style="font-size:12px; font-weight:800; color:var(--ai-purple); text-transform:uppercase;">Weather Outlook</div>
                    <div id="weatherForecast" style="font-size:20px; font-weight:900; color:var(--primary); margin-top:6px;">--</div>
                </div>
            </div>
            <h4 style="margin: 0 0 10px 0; color: var(--ai-purple); font-size: 20px; font-weight: 800;">STATUS RECOMMENDATION</h4>
            <p id="aiInsight" style="font-size: 20px; font-weight: 600; line-height: 1.4; color: #333; margin: 0;">
                Initializing AI models for real-time forecasting...
            </p>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="card"><label>Temperature</label><span class="val" id="temp">--</span><span class="unit">Celsius (°C)</span></div>
        <div class="card"><label>Humidity</label><span class="val" id="hum">--</span><span class="unit">Percentage (%)</span></div>
        <div class="card" style="border-top-color: #00bcd4;"><label>PM2.5 Level</label><span class="val" id="pm25">--</span><span class="unit">µg/m³</span></div>
        <div class="card" style="border-top-color: #009688;"><label>CO Gas</label><span class="val" id="co">--</span><span class="unit">ppm</span></div>
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
                        <th>PM2.5</th>
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

// Dual-Axis Chart
const ctx = document.getElementById('dualChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: { 
        labels: [], 
        datasets: [
            { label: 'PM2.5', data: [], borderColor: '#1a237e', borderWidth: 5, fill: true, tension: 0.4, yAxisID: 'y' },
            { label: 'Temp', data: [], borderColor: '#ff7043', borderWidth: 3, borderDash: [5, 5], tension: 0.4, yAxisID: 'y1' },
            { label: 'Humidity', data: [], borderColor: '#00a896', borderWidth: 3, tension: 0.4, yAxisID: 'y1' },
            { label: 'Gas/MQ', data: [], borderColor: '#8e44ad', borderWidth: 3, tension: 0.4, yAxisID: 'y1' }
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
    return Math.max(0, (slope * (n + steps) + intercept)).toFixed(1);
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
            document.getElementById('statusText').innerText = 'OFFLINE';
            document.getElementById('statusText').style.color = '#d32f2f';
            document.getElementById('humForecast').innerText = '--';
            document.getElementById('weatherForecast').innerText = 'Waiting for live sensor data...';
            return;
        }

        const currentTemp = parseFloat(d.temp);
        const currentHum = parseFloat(d.hum);
        tempHistory.push(currentTemp);
        humHistory.push(currentHum);
        if (tempHistory.length > 12) tempHistory.shift();
        if (humHistory.length > 12) humHistory.shift();

        const forecastTemp = tempHistory.length >= 5 ? getPrediction(tempHistory, 600) : null;
        const forecastHum = humHistory.length >= 5 ? getPrediction(humHistory, 600) : null;
        const outlook = getWeatherOutlook(currentTemp, currentHum);

        document.getElementById('temp').innerText = d.temp;
        document.getElementById('hum').innerText = d.hum;
        document.getElementById('pm25').innerText = d.pm25;
        document.getElementById('co').innerText = d.co;
        document.getElementById('humForecast').innerText = forecastHum !== null ? `${Number(forecastHum).toFixed(1)}%` : '--';
        document.getElementById('weatherForecast').innerText = `${outlook} · ${forecastTemp !== null ? `${Number(forecastTemp).toFixed(1)}°C` : '...'}`;

        let status = d.pm25 > 35 ? "POOR" : (d.pm25 > 12 ? "MODERATE" : "GOOD");
        let tColor = d.pm25 > 35 ? "#ff1744" : (d.pm25 > 12 ? "#ffea00" : "#00e676");
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
            document.getElementById('aiInsight').innerHTML = `<b>Trend:</b> ${trend}<br>${advice}<br><b>Weather:</b> ${outlook} with humidity forecast at ${forecastHum !== null ? `${Number(forecastHum).toFixed(1)}%` : '--'}.`;
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

        let row = `<tr><td><b>${now}</b></td><td style="color:#ff7043">${d.temp}°C</td><td>${d.pm25}</td><td style="color:${tColor}; font-weight:900;">${status}</td></tr>`;
        document.getElementById('historyBody').insertAdjacentHTML("afterbegin", row);
        if(document.getElementById('historyBody').rows.length > 10) {
            document.getElementById('historyBody').deleteRow(10);

            let status = "UNKNOWN";

            if (d.pm25 !== undefined && d.pm25 !== null) {
            status = d.pm25 > 35 ? "POOR" : (d.pm25 > 12 ? "MODERATE" : "GOOD");
        }

            let tColor = status === "POOR" ? "#ff1744" : (status === "MODERATE" ? "#ffea00" : "#00e676");
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