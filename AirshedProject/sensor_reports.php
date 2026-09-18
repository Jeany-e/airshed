<?php
require __DIR__ . '/config.php';

date_default_timezone_set('Asia/Manila');
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$logs = [];
$error = '';

try {
    $logs = firebaseRows('sensor_logs');
} catch (Throwable $exception) {
    $error = 'Sensor history is temporarily unavailable.';
}

usort($logs, function ($a, $b) {
    return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
});

$days = [];
foreach ($logs as $log) {
    $timestamp = strtotime($log['timestamp'] ?? '');
    if (!$timestamp) {
        continue;
    }
    $day = date('Y-m-d', $timestamp);
    $days[$day][] = $log;
}

$selectedDay = $_GET['day'] ?? (array_key_first($days) ?: date('Y-m-d'));
$selectedLogs = $days[$selectedDay] ?? [];
$searchTerm = trim($_GET['search'] ?? '');
$fromInput = trim($_GET['from'] ?? '');
$toInput = trim($_GET['to'] ?? '');
$fromTimestamp = $fromInput ? strtotime($fromInput) : null;
$toTimestamp = $toInput ? strtotime($toInput) : null;

if ($searchTerm !== '' || $fromTimestamp || $toTimestamp) {
    $selectedLogs = array_values(array_filter($selectedLogs, function ($log) use ($searchTerm, $fromTimestamp, $toTimestamp) {
        $timestamp = strtotime($log['timestamp'] ?? '');
        $haystack = strtolower(implode(' ', [
            $log['timestamp'] ?? '',
            $timestamp ? date('M d Y h:i A', $timestamp) : '',
            $log['temp'] ?? '',
            $log['hum'] ?? '',
            $log['pm25'] ?? '',
            $log['co'] ?? '',
            $log['status'] ?? ''
        ]));

        return $timestamp
            && (!$fromTimestamp || $timestamp >= $fromTimestamp)
            && (!$toTimestamp || $timestamp <= $toTimestamp)
            && ($searchTerm === '' || strpos($haystack, strtolower($searchTerm)) !== false);
    }));
}

$average = function ($field) use ($selectedLogs) {
    $values = array_map(function ($log) use ($field) {
        return (float) ($log[$field] ?? 0);
    }, $selectedLogs);
    return count($values) ? array_sum($values) / count($values) : 0;
};

$maxPm25 = 0;
foreach ($selectedLogs as $log) {
    $maxPm25 = max($maxPm25, (float) ($log['pm25'] ?? 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensor Reports | City Airshed</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #14213d;
            --muted: #667085;
            --blue: #174ea6;
            --blue-soft: #edf4ff;
            --green: #16845b;
            --line: #e5eaf1;
            --surface: #fff;
            --background: #f5f7fb;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: linear-gradient(120deg, #d5efff, #f8fcff, #c6e9fb); background-size: 300% 300%; animation: pageBlueFlow 11s ease-in-out infinite; color: var(--ink); font-family: 'DM Sans', sans-serif; }
        @keyframes pageBlueFlow { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--line); padding: 18px 5%; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .brand { color: var(--ink); font-family: 'Space Grotesk', sans-serif; font-size: 21px; font-weight: 700; text-decoration: none; }
        .topnav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .topnav a { color: var(--blue); text-decoration: none; font-size: 14px; font-weight: 700; padding: 10px 14px; border-radius: 9px; }
        .topnav a:hover, .topnav a.active { background: var(--blue-soft); }
        main { width: min(1180px, 92%); margin: 42px auto 64px; }
        .intro { display: flex; justify-content: space-between; align-items: end; gap: 24px; margin-bottom: 28px; }
        h1, h2 { font-family: 'Space Grotesk', sans-serif; margin: 0; }
        h1 { font-size: clamp(28px, 4vw, 46px); letter-spacing: -1px; }
        .intro p { color: var(--muted); margin: 10px 0 0; max-width: 620px; line-height: 1.55; }
        .date-form { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .date-form label { color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        select, input { border: 1px solid var(--line); border-radius: 8px; background: white; color: var(--ink); padding: 9px 10px; font: inherit; }
        .search-btn { border: 0; border-radius: 8px; padding: 10px 14px; background: var(--blue); color: #fff; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
        .clear-search { color: var(--muted); font-size: 12px; font-weight: 700; text-decoration: none; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
        .metric, .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 14px 30px rgba(36,104,157,.14), 0 3px 0 rgba(255,255,255,.9) inset; animation: cardRise .6s ease both, cardFloat 5.5s ease-in-out .7s infinite; }
        .metric { padding: 20px; transition: transform .25s ease, box-shadow .25s ease; }
        .metric:hover, .panel:hover { transform: translateY(-6px); box-shadow: 0 18px 30px rgba(45,112,164,.22); }
        .metric:nth-child(2) { animation-delay: .08s; }
        .metric:nth-child(3) { animation-delay: .16s; }
        .metric:nth-child(4) { animation-delay: .24s; }
        @keyframes cardRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes cardFloat { 0%, 100% { translate: 0 0; } 50% { translate: 0 -4px; } }
        @media (prefers-reduced-motion: reduce) { .metric, .panel { animation: none; } }
        .metric-label { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .metric-value { display: block; color: var(--blue); font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700; margin-top: 9px; }
        .panel { overflow: hidden; }
        .panel-head { padding: 20px 22px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .panel-head h2 { font-size: 19px; }
        .panel-head span { color: var(--muted); font-size: 13px; }
        .panel-actions { display: flex; align-items: center; gap: 10px; }
        .export-btn { border: 0; border-radius: 8px; padding: 9px 13px; background: var(--blue); color: #fff; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
        .export-btn:hover { opacity: .88; }
        .chart-panel { padding: 22px; margin-bottom: 24px; }
        .chart-scroll { width: 100%; overflow-x: auto; overscroll-behavior: contain; }
        .chart-wrap { height: 300px; min-width: 1200px; }
        .chart-wrap canvas { width: 1200px !important; max-width: none; }
        .table-wrap { max-height: min(58vh, 560px); overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        table { width: 100%; min-width: 650px; border-collapse: collapse; }
        th { position: sticky; top: 0; z-index: 1; background: #f9fbfe; color: var(--muted); font-size: 11px; letter-spacing: .05em; text-transform: uppercase; text-align: left; }
        th, td { padding: 15px 22px; border-bottom: 1px solid var(--line); }
        td { font-size: 14px; font-weight: 500; }
        tr:last-child td { border-bottom: 0; }
        .status { display: inline-block; border-radius: 999px; padding: 5px 10px; background: #eaf8f1; color: var(--green); font-size: 11px; font-weight: 700; }
        .empty, .error { padding: 42px 22px; color: var(--muted); text-align: center; }
        .error { color: #b42318; background: #fff5f4; }
        .app-shell { min-height: 100vh; display: flex; }
        .sidebar { width: 280px; flex: 0 0 280px; background: linear-gradient(135deg, #78c5ef, #bce8fa, #4f9bd3); background-size: 240% 240%; animation: sidebarGradient 8s ease-in-out infinite; color: #fff; padding: 30px 20px; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(36,104,157,.18); }
        @keyframes sidebarGradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .sidebar h2 { font-family: 'DM Sans', sans-serif; font-size: 20px; text-align: center; color: #174ea6; border-bottom: 2px solid rgba(255,255,255,.1); padding-bottom: 20px; margin: 0 0 20px; }
        .sidebar-link { color: #17466f; text-decoration: none; padding: 15px 20px; border-radius: 10px; margin: 8px 0; display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600; transition: all .3s ease; }
        .sidebar-link:hover, .sidebar-link.active { background: #fff; color: #174ea6; }
        .sidebar-link.active { font-weight: 700; box-shadow: 0 5px 15px rgba(0,0,0,.18); }
        .sidebar-logout { margin-top: 20px; color: #17466f; text-decoration: none; text-align: center; padding: 15px; border: 1px solid rgba(23,78,166,.18); background: rgba(255,255,255,.28); border-radius: 10px; font-weight: 700; }
        .sidebar-note { color: #38566e; font-size: 12px; line-height: 1.5; margin: 0 8px 18px; }
        .main { min-width: 0; flex: 1; }
        .header { background: #fff; border-bottom: 1px solid var(--line); padding: 20px 40px; display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .header-title { color: #1a237e; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700; }
        .main main { width: min(1180px, 92%); margin: 42px auto 64px; }
        @media (max-width: 760px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .app-shell { display: block; }
            .sidebar { width: 100%; padding: 16px 12px 12px; display: block; }
            .sidebar h2 { margin-bottom: 12px; padding-bottom: 12px; font-size: 18px; }
            .sidebar-link { display: flex; justify-content: center; padding: 10px 12px; margin: 6px 0; font-size: 12px; background: transparent; color: #17466f; box-shadow: none; }
            .sidebar-link:hover { background: #fff; color: #174ea6; box-shadow: 0 6px 14px rgba(36,104,157,.10); }
            .sidebar-link.active { color: #174ea6; background: rgba(255,255,255,.36); box-shadow: none; }
            .sidebar-logout { display: block; margin-top: 12px; padding: 11px; background: transparent; color: #17466f; box-shadow: none; }
            .sidebar-logout:hover { background: #fff; color: #174ea6; box-shadow: 0 6px 14px rgba(36,104,157,.10); }
            .header { padding: 16px 18px; }
            .header-title { font-size: 16px; }
            .main main { width: calc(100% - 24px); margin-top: 28px; }
            .intro { align-items: stretch; flex-direction: column; }
            .date-form { justify-content: space-between; }
            .date-form select, .date-form input { flex: 1; min-width: 0; }
            .date-form .search-btn { width: 100%; }
            .summary { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .metric { padding: 16px; }
            .metric-value { font-size: 25px; }
            .panel-head { align-items: flex-start; flex-direction: column; }
            .panel-actions { width: 100%; flex-wrap: wrap; }
            .panel-actions .export-btn {
                width: auto;
                align-self: flex-end;
                padding: 7px 10px;
                font-size: 11px;
            }
            .table-wrap { max-height: 52vh; }
            th, td { padding: 13px 15px; }
            .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { min-width: 720px; }
        }

        @media (max-width: 760px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .header .brand {
                font-size: 13px;
            }

            .main main {
                width: calc(100% - 20px);
                margin-top: 22px;
            }

            .summary {
                grid-template-columns: 1fr 1fr;
            }

            .metric {
                padding: 14px 10px;
            }

            .metric-value {
                font-size: 23px;
            }

            .panel-head,
            .chart-panel {
                padding: 16px 14px;
            }

            .date-form {
                display: grid;
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .date-form select,
            .date-form input,
            .date-form .search-btn {
                width: 100%;
            }

            .table-wrap {
                overflow-x: hidden;
            }

            .table-wrap table {
                min-width: 0;
                width: 100%;
                table-layout: fixed;
            }

            .table-wrap th,
            .table-wrap td {
                min-width: 0;
                padding: 9px 4px;
                font-size: 10px;
                line-height: 1.35;
                white-space: normal;
                overflow-wrap: anywhere;
                vertical-align: middle;
            }

            .table-wrap th {
                font-size: 8px;
                letter-spacing: 0;
            }

            .table-wrap .status {
                padding: 4px 5px;
                font-size: 9px;
            }
        }
        .mobile-menu-toggle, .sidebar-backdrop { display: none; }

        @media (max-width: 760px) {
            .mobile-menu-toggle {
                display: inline-flex; align-items: center; justify-content: center;
                position: fixed; top: 12px; left: 12px; z-index: 1002;
                width: 42px; height: 42px; border: 0; border-radius: 12px;
                background: #1a237e; color: #fff; font-size: 21px; cursor: pointer;
                box-shadow: 0 8px 20px rgba(26,35,126,.25);
            }
            .sidebar {
                position: fixed; inset: 0 auto 0 0; z-index: 1000;
                width: min(290px, 86vw); height: 100vh; overflow-y: auto;
                padding: 72px 20px 20px;
                transform: translateX(-105%); transition: transform .25s ease;
                box-shadow: 12px 0 30px rgba(10,18,60,.25);
            }
            .sidebar h2 { color: #174ea6; border-bottom-color: rgba(23,78,166,.16); }
            .sidebar .sidebar-link { display: flex; width: 100%; flex: 0 0 auto; margin: 6px 0; justify-content: flex-start; color: #17466f; }
            .sidebar .sidebar-link:hover { background: rgba(255,255,255,.58); color: #174ea6; transform: none; }
            .sidebar .sidebar-link.active { color: #174ea6; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,.18); }
            .sidebar .sidebar-logout { color: #17466f; border-color: rgba(23,78,166,.18); background: rgba(255,255,255,.28); padding: 9px 12px; font-size: 13px; border-radius: 9px; }
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
    <div class="app-shell">
        <aside class="sidebar">
            <h2>CITY AIRSHED MS</h2>
            <?php if ($isAdmin): ?>
                <a class="sidebar-link" href="admin_dashboard.php">📊 Dashboard</a>
                <a class="sidebar-link" href="admin_dashboard.php#v-reports">📢 User Reports</a>
                <a class="sidebar-link" href="admin_dashboard.php#v-users">👥 User &amp; Admin Management</a>
                <a class="sidebar-link" href="admin_dashboard.php#v-net">⚙️ Device Network</a>
                <a class="sidebar-link" href="admin_dashboard.php#v-feedback">⭐ User Feedback</a>
                <a class="sidebar-link" href="admin_dashboard.php#v-alerts">🔔 Alert History</a>
            <?php else: ?>
                <a class="sidebar-link" href="user_dashboard.php">📡 Real-time Monitoring</a>
            <?php endif; ?>
            <a class="sidebar-link active" href="sensor_reports.php">📈 Sensor Reports</a>
            <?php if (!$isAdmin): ?><p class="sidebar-note">View your air-quality readings and daily sensor history.</p><?php endif; ?>
            <a class="sidebar-logout" href="logout.php">🚪 Sign Out</a>
        </aside>

        <div class="main">
            <header class="header">
                <div class="header-title">IoT Airshed Monitoring - Calapan City</div>
                <a class="brand" href="public_portal.php">Live Monitoring</a>
            </header>

    <main>
        <section class="intro">
            <div>
                <h1>Sensor Reports</h1>
            </div>
            <form class="date-form" method="get">
                <label for="day">Report date</label>
                <select id="day" name="day" onchange="this.form.submit()">
                    <?php if (!$days): ?><option value="<?php echo htmlspecialchars($selectedDay); ?>">No readings yet</option><?php endif; ?>
                    <?php foreach (array_keys($days) as $day): ?>
                        <option value="<?php echo htmlspecialchars($day); ?>" <?php echo $day === $selectedDay ? 'selected' : ''; ?>>
                            <?php echo date('M d, Y', strtotime($day)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="from">From</label>
                <input id="from" name="from" type="datetime-local" value="<?php echo htmlspecialchars($fromInput); ?>" aria-label="Starting date and time">
                <label for="to">To</label>
                <input id="to" name="to" type="datetime-local" value="<?php echo htmlspecialchars($toInput); ?>" aria-label="Ending date and time">
                <input name="search" type="search" placeholder="Search time or value" value="<?php echo htmlspecialchars($searchTerm); ?>" aria-label="Search sensor records">
                <button class="search-btn" type="submit">Search</button>
                <?php if ($searchTerm || $fromInput || $toInput): ?><a class="clear-search" href="sensor_reports.php?day=<?php echo urlencode($selectedDay); ?>">Clear</a><?php endif; ?>
            </form>
        </section>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <section class="summary">
                <div class="metric"><span class="metric-label">Readings</span><strong class="metric-value"><?php echo count($selectedLogs); ?></strong></div>
                <div class="metric"><span class="metric-label">Average Temp</span><strong class="metric-value"><?php echo number_format($average('temp'), 1); ?> C</strong></div>
                <div class="metric"><span class="metric-label">Average Humidity</span><strong class="metric-value"><?php echo number_format($average('hum'), 1); ?>%</strong></div>
                <div class="metric"><span class="metric-label">Peak PM2.5</span><strong class="metric-value"><?php echo number_format($maxPm25, 1); ?></strong></div>
            </section>

            <section class="panel chart-panel">
                <div class="panel-head" style="padding: 0 0 18px; border-bottom: 0;">
                    <div>
                        <h2>Daily Air Quality Trend</h2>
                        <span>Temperature, humidity, PM2.5, and CO readings</span>
                    </div>
                </div>
                <div class="chart-scroll"><div class="chart-wrap"><canvas id="dailyChart"></canvas></div></div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2><?php echo date('l, F j, Y', strtotime($selectedDay)); ?></h2>
                    <div class="panel-actions">
                        <span><?php echo count($selectedLogs); ?> matching readings</span>
                        <?php if ($selectedLogs): ?><button type="button" class="export-btn" onclick="exportReport()">Export CSV</button><?php endif; ?>
                    </div>
                </div>
                <?php if (!$selectedLogs): ?>
                    <div class="empty">No sensor readings are available for this date.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Time</th><th>Temperature</th><th>Humidity</th><th>PM2.5</th><th>CO</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($selectedLogs as $log): ?>
                                <tr>
                                    <td><?php echo date('h:i:s A', strtotime($log['timestamp'] ?? '')); ?></td>
                                    <td><?php echo number_format((float) ($log['temp'] ?? 0), 1); ?> C</td>
                                    <td><?php echo number_format((float) ($log['hum'] ?? 0), 1); ?>%</td>
                                    <td><?php echo number_format((float) ($log['pm25'] ?? 0), 1); ?> ug/m3</td>
                                    <td><?php echo number_format((float) ($log['co'] ?? 0), 1); ?> ppm</td>
                                    <td><span class="status"><?php echo htmlspecialchars($log['status'] ?? 'UNKNOWN'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
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

        const reportRows = <?php echo json_encode(array_values($selectedLogs), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const chartLabels = reportRows.map(row => new Date(row.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
        const chartCanvas = document.getElementById('dailyChart');
        if (chartCanvas) new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    { label: 'PM2.5', data: reportRows.map(row => Number(row.pm25 || 0)), borderColor: '#174ea6', backgroundColor: 'rgba(23,78,166,.12)', fill: true, tension: .35, yAxisID: 'air' },
                    { label: 'Temperature', data: reportRows.map(row => Number(row.temp || 0)), borderColor: '#e76f51', tension: .35, yAxisID: 'weather' },
                    { label: 'Humidity', data: reportRows.map(row => Number(row.hum || 0)), borderColor: '#00a896', tension: .35, yAxisID: 'humidity' },
                    { label: 'Gas/MQ', data: reportRows.map(row => Number(row.co || 0)), borderColor: '#8e44ad', tension: .35, yAxisID: 'gas' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { air: { beginAtZero: true, position: 'left' }, weather: { beginAtZero: false, position: 'right', grid: { drawOnChartArea: false } }, humidity: { beginAtZero: true, position: 'left', display: false }, gas: { beginAtZero: true, position: 'right', display: false } } }
        });

        function exportReport() {
            const header = ['Timestamp', 'Temperature', 'Humidity', 'PM2.5', 'CO', 'Status'];
            const rows = reportRows.map(row => [row.timestamp, row.temp, row.hum, row.pm25, row.co, row.status]);
            const csv = [header, ...rows].map(row => row.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Airshed_Report_<?php echo htmlspecialchars($selectedDay, ENT_QUOTES); ?>.csv`;
            link.click();
            URL.revokeObjectURL(link.href);
        }
    </script>
</body>
</html>
