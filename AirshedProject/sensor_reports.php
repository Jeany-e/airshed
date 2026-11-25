<?php
require __DIR__ . '/config.php';

date_default_timezone_set('Asia/Manila');
unset($_SESSION['sensor_reports_logs_cache']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$error = '';
$filterWarning = '';
$reportPageSize = 5000;
$displayPageSize = 200;
$requestedReportPage = max(1, (int) ($_GET['report_page'] ?? 1));
$period = strtolower(trim($_GET['period'] ?? 'daily'));
if (!in_array($period, ['daily', 'weekly', 'monthly', 'custom'], true)) {
    $period = 'daily';
}
$requestedDate = trim($_GET['date'] ?? $_GET['day'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) || !checkdate((int) substr($requestedDate, 5, 2), (int) substr($requestedDate, 8, 2), (int) substr($requestedDate, 0, 4))) {
    $requestedDate = '';
}
$recordFileSearch = trim($_GET['file_search'] ?? '');
$rawFromInput = trim($_GET['from'] ?? '');
$rawToInput = trim($_GET['to'] ?? '');
$normalizeDateInput = function ($value) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
        && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
        return $value;
    }
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
        $date = DateTime::createFromFormat('!d/m/Y', $value);
        $errors = DateTime::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return '';
};
$fromInput = $normalizeDateInput($rawFromInput);
$toInput = $normalizeDateInput($rawToInput);
$invalidDateInput = ($rawFromInput !== '' && $fromInput === '') || ($rawToInput !== '' && $toInput === '');
if ($invalidDateInput) {
    $filterWarning = 'Enter valid dates using DD/MM/YYYY.';
}
$selectedSensor = trim($_GET['sensor'] ?? '');
$days = [];
$sensors = [];
$totalDeviceReadings = 0;
$selectedLogs = [];

$fetchRows = function (array $query) {
    try {
        return firebaseRows('sensor_logs', $query);
    } catch (Throwable $exception) {
        usleep(150000);
        return firebaseRows('sensor_logs', $query);
    }
};

$normalizeDeviceLog = function (array $log) {
    $timestamp = strtotime($log['timestamp'] ?? '');
    if (($log['source'] ?? '') !== 'device' || !$timestamp) {
        return null;
    }
    $log['temp'] = $log['temp'] ?? $log['temperature'] ?? null;
    $log['hum'] = $log['hum'] ?? $log['humidity'] ?? null;
    $log['co'] = $log['co'] ?? $log['co_ppm'] ?? null;
    $log['pm25'] = $log['pm25'] ?? $log['pm2_5'] ?? $log['pm2.5'] ?? null;
    $sensorId = $log['sensor_id'] ?? '';
    if (!is_scalar($sensorId) || trim((string) $sensorId) === '') {
        $sensorId = $log['device_id'] ?? '';
    }
    $log['sensor_id'] = is_scalar($sensorId) ? trim((string) $sensorId) : '';
    if ($log['sensor_id'] === '') {
        $log['sensor_id'] = 'Unidentified sensor';
    }
    return $log;
};

$loadRangeLogs = function ($startTimestamp, $endTimestamp) use ($fetchRows, $normalizeDeviceLog) {
    $rows = $fetchRows([
        'orderBy' => '"timestamp"',
        'startAt' => json_encode(gmdate('c', $startTimestamp)),
        'endAt' => json_encode(gmdate('c', $endTimestamp))
    ]);
    $rangeLogs = [];
    foreach ($rows as $row) {
        $log = $normalizeDeviceLog($row);
        $timestamp = $log === null ? false : strtotime($log['timestamp']);
        if ($log !== null && $timestamp >= $startTimestamp && $timestamp <= $endTimestamp) {
            $rangeLogs[] = $log;
        }
    }
    usort($rangeLogs, function ($a, $b) {
        return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
    });
    return $rangeLogs;
};

try {
    $cursorKey = null;
    do {
        $query = [
            'orderBy' => '"$key"',
            'limitToFirst' => $cursorKey === null ? $reportPageSize : $reportPageSize + 1
        ];
        if ($cursorKey !== null) {
            $query['startAt'] = json_encode($cursorKey);
        }
        $page = $fetchRows($query);
        usort($page, function ($a, $b) {
            return strcmp($a['id'] ?? '', $b['id'] ?? '');
        });
        if ($cursorKey !== null) {
            $page = array_values(array_filter($page, function ($row) use ($cursorKey) {
                return strcmp($row['id'] ?? '', $cursorKey) > 0;
            }));
        }
        $pageCount = count($page);
        foreach ($page as $record) {
            $cursorKey = $record['id'] ?? $cursorKey;
            $log = $normalizeDeviceLog($record);
            if ($log === null) {
                continue;
            }
            $day = date('Y-m-d', strtotime($log['timestamp']));
            $days[$day] = ($days[$day] ?? 0) + 1;
            $sensors[$log['sensor_id']] = true;
            $totalDeviceReadings++;
        }
    } while ($pageCount >= $reportPageSize);

    krsort($days, SORT_STRING);
    $selectedDate = $requestedDate ?: ($fromInput ?: (array_key_first($days) ?: date('Y-m-d')));
    $rangeStartDate = $selectedDate;
    $rangeEndDate = $selectedDate;
    if ($period === 'weekly') {
        $weekStart = strtotime($selectedDate . ' -' . ((int) date('N', strtotime($selectedDate)) - 1) . ' days');
        $rangeStartDate = date('Y-m-d', $weekStart);
        $rangeEndDate = date('Y-m-d', strtotime($rangeStartDate . ' +6 days'));
    } elseif ($period === 'monthly') {
        $rangeStartDate = date('Y-m-01', strtotime($selectedDate));
        $rangeEndDate = date('Y-m-t', strtotime($selectedDate));
    } elseif ($period === 'custom') {
        if ($fromInput && $toInput && $fromInput <= $toInput) {
            $rangeStartDate = $fromInput;
            $rangeEndDate = $toInput;
        } elseif (!$filterWarning) {
            $filterWarning = 'Choose valid start and end dates, with the start date on or before the end date.';
        }
    }
    $rangeStart = strtotime($rangeStartDate . ' 00:00:00');
    $rangeEnd = strtotime($rangeEndDate . ' 23:59:59');
    $rangeSeconds = $rangeEnd - $rangeStart + 1;
    $previousStart = $rangeStart - $rangeSeconds;
    $previousEnd = $rangeStart - 1;
    $selectedLogs = $loadRangeLogs($rangeStart, $rangeEnd);
    $previousLogs = $loadRangeLogs($previousStart, $previousEnd);
    if ($selectedSensor !== '' && !isset($sensors[$selectedSensor])) {
        $selectedSensor = '';
    }
    $filterReportLogs = function (array $logs) use ($selectedSensor) {
        return array_values(array_filter($logs, function ($log) use ($selectedSensor) {
            if ($selectedSensor !== '' && $log['sensor_id'] !== $selectedSensor) {
                return false;
            }
            return true;
        }));
    };
    $selectedLogs = $filterReportLogs($selectedLogs);
    $previousLogs = $filterReportLogs($previousLogs);
} catch (Throwable $exception) {
    error_log('Sensor report history unavailable: ' . $exception->getMessage());
    $error = 'Sensor history is temporarily unavailable.';
    $days = [];
    $selectedLogs = [];
    $previousLogs = [];
    $selectedDate = $requestedDate ?: date('Y-m-d');
    $rangeStartDate = $selectedDate;
    $rangeEndDate = $selectedDate;
    $rangeStart = strtotime($rangeStartDate . ' 00:00:00');
    $rangeEnd = strtotime($rangeEndDate . ' 23:59:59');
}

$validValues = function (array $rows, $field, $minimum, $maximum) {
    $values = [];
    foreach ($rows as $row) {
        $value = $row[$field] ?? null;
        if (is_numeric($value) && is_finite((float) $value) && (float) $value >= $minimum && (float) $value <= $maximum) {
            $values[] = (float) $value;
        }
    }
    return $values;
};

$pm25Values = $validValues($selectedLogs, 'pm25', 0, 1000);
$previousPm25Values = $validValues($previousLogs, 'pm25', 0, 1000);
$averagePm25 = $pm25Values ? array_sum($pm25Values) / count($pm25Values) : null;
$peakPm25 = $pm25Values ? max($pm25Values) : null;
$previousAveragePm25 = $previousPm25Values ? array_sum($previousPm25Values) / count($previousPm25Values) : null;
$averageDelta = $averagePm25 !== null && $previousAveragePm25 !== null
    ? $averagePm25 - $previousAveragePm25
    : null;
$highReadings = array_values(array_filter($selectedLogs, function ($log) {
    return in_array(classifyPm25($log['pm25'] ?? null)['level'], ['UNHEALTHY', 'HAZARDOUS'], true);
}));
usort($highReadings, function ($a, $b) {
    return strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? '');
});
$reportMetrics = [
    'pm25' => ['field' => 'pm25', 'label' => 'PM2.5', 'unit' => 'µg/m³', 'minimum' => 0, 'maximum' => 1000, 'category' => true],
    'temp' => ['field' => 'temp', 'label' => 'Temperature', 'unit' => '°C', 'minimum' => -40, 'maximum' => 85, 'category' => false],
    'hum' => ['field' => 'hum', 'label' => 'Humidity', 'unit' => '%', 'minimum' => 0, 'maximum' => 100, 'category' => false],
    'co' => ['field' => 'co', 'label' => 'MQ-7 signal (raw ADC)', 'unit' => 'raw ADC', 'minimum' => 0, 'maximum' => 4095, 'category' => false]
];
$trendBuckets = array_fill_keys(array_keys($reportMetrics), []);
foreach ($selectedLogs as $log) {
    $timestamp = strtotime($log['timestamp'] ?? '');
    if (!$timestamp) {
        continue;
    }
    $bucketKey = $period === 'daily' ? date('Y-m-d H:00', $timestamp) : date('Y-m-d', $timestamp);
    foreach ($reportMetrics as $metricKey => $metric) {
        $value = $log[$metric['field']] ?? null;
        if (is_numeric($value) && is_finite((float) $value) && (float) $value >= $metric['minimum'] && (float) $value <= $metric['maximum']) {
            $trendBuckets[$metricKey][$bucketKey][] = (float) $value;
        }
    }
}
$trendSeries = [];
foreach ($trendBuckets as $metricKey => $buckets) {
    ksort($buckets);
    $trendSeries[$metricKey] = ['labels' => [], 'values' => []];
    foreach ($buckets as $bucket => $values) {
        $trendSeries[$metricKey]['labels'][] = $period === 'daily' ? date('h A', strtotime($bucket . ':00')) : date('M j', strtotime($bucket));
        $trendSeries[$metricKey]['values'][] = array_sum($values) / count($values);
    }
}
$reportTitle = $period === 'daily'
    ? date('l, F j, Y', strtotime($rangeStartDate))
    : date('M j, Y', strtotime($rangeStartDate)) . ' – ' . date('M j, Y', strtotime($rangeEndDate));
$pm25Value = function ($value) {
    return is_numeric($value) && is_finite((float) $value)
        ? number_format((float) $value, 1) . ' µg/m³'
        : '--';
};
$formatMetricValue = function ($value, $minimum, $maximum, $unit) {
    if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < $minimum || (float) $value > $maximum) {
        return '--';
    }
    return number_format((float) $value, 1) . ' ' . $unit;
};
$totalReportRows = count($selectedLogs);
$totalReportPages = max(1, (int) ceil($totalReportRows / $displayPageSize));
$reportPage = min($requestedReportPage, $totalReportPages);
$firstReportRow = $totalReportRows ? (($reportPage - 1) * $displayPageSize) + 1 : 0;
$lastReportRow = min($reportPage * $displayPageSize, $totalReportRows);
$displayLogs = array_slice($selectedLogs, ($reportPage - 1) * $displayPageSize, $displayPageSize);
$generatorFromDate = $period === 'custom' && $filterWarning ? $fromInput : $rangeStartDate;
$generatorToDate = $period === 'custom' && $filterWarning ? $toInput : $rangeEndDate;
$formatDateInput = function ($value) {
    return $value ? date('d/m/Y', strtotime($value)) : '';
};
$displayFromDate = $invalidDateInput && $fromInput === '' && $rawFromInput !== ''
    ? $rawFromInput
    : $formatDateInput($generatorFromDate);
$displayToDate = $invalidDateInput && $toInput === '' && $rawToInput !== ''
    ? $rawToInput
    : $formatDateInput($generatorToDate);
$reportQuery = $_GET;
unset($reportQuery['export'], $reportQuery['report_page'], $reportQuery['search'], $reportQuery['pollutant']);
$generatorQuery = $reportQuery;
unset($generatorQuery['file_search']);
$clearFileSearchUrl = $generatorQuery ? 'sensor_reports.php?' . http_build_query($generatorQuery) : 'sensor_reports.php';
$filteredDays = $days;
if ($recordFileSearch !== '') {
    $normalizedSearch = strtolower(preg_replace('/[^a-z0-9]/i', '', $recordFileSearch));
    if ($normalizedSearch === '') {
        $filteredDays = [];
    } else {
        $filteredDays = array_filter($days, function ($readingCount, $day) use ($normalizedSearch) {
            $timestamp = strtotime($day);
            $searchableText = implode(' ', [
                date('l, F j, Y', $timestamp),
                date('M j, Y', $timestamp),
                date('Y-m-d', $timestamp),
                date('n/j/Y', $timestamp),
                date('m/d/Y', $timestamp),
                (string) $readingCount
            ]);
            $normalizedText = strtolower(preg_replace('/[^a-z0-9]/i', '', $searchableText));
            return strpos($normalizedText, $normalizedSearch) !== false;
        }, ARRAY_FILTER_USE_BOTH);
    }
}
$exportQuery = $generatorQuery;
$exportQuery['export'] = 'csv';
$exportUrl = 'sensor_reports.php?' . http_build_query($exportQuery);
$reportPageUrl = function ($page) use ($reportQuery) {
    $query = $reportQuery;
    $query['report_page'] = $page;
    return 'sensor_reports.php?' . http_build_query($query);
};
if (($_GET['export'] ?? '') === 'csv' && !$error) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Airshed_Report_' . $period . '_' . $rangeStartDate . '_to_' . $rangeEndDate . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Timestamp', 'Sensor ID', 'Temperature (C)', 'Humidity (%)', 'PM2.5 (ug/m3)', 'MQ-7 signal (raw ADC)', 'PM2.5 category']);
    foreach ($selectedLogs as $log) {
        fputcsv($output, [
            $log['timestamp'] ?? '',
            $log['sensor_id'] ?? 'Unidentified sensor',
            $log['temp'] ?? '',
            $log['hum'] ?? '',
            $log['pm25'] ?? '',
            $log['co'] ?? '',
            classifyPm25($log['pm25'] ?? null)['label']
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensor Reports | City Airshed</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="air_quality.js"></script>
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
        .record-file-search { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 22px; border-top: 1px solid var(--line); }
        .record-file-search label { color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .record-file-search input[type="search"] { min-width: 220px; }
        select, input { border: 1px solid var(--line); border-radius: 8px; background: white; color: var(--ink); padding: 9px 10px; font: inherit; }
        .search-btn { border: 0; border-radius: 8px; padding: 10px 14px; background: var(--blue); color: #fff; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
        .clear-search { color: var(--muted); font-size: 12px; font-weight: 700; text-decoration: none; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .alert-summary { margin: -8px 0 24px; padding: 16px 20px; border: 1px solid #f0c98e; border-radius: 14px; background: #fff9ee; color: #68460d; line-height: 1.6; }
        .alert-summary.clear { border-color: #b8e4ce; background: #effaf4; color: #176345; }
        .metric, .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 14px 30px rgba(36,104,157,.14), 0 3px 0 rgba(255,255,255,.9) inset; animation: cardRise .6s ease both, cardFloat 5.5s ease-in-out .7s infinite; }
        .metric { padding: 20px; transition: transform .25s ease, box-shadow .25s ease; }
        .metric:hover, .panel:hover { transform: translateY(-6px); box-shadow: 0 18px 30px rgba(45,112,164,.22); }
        .metric:nth-child(2) { animation-delay: .08s; }
        .metric:nth-child(3) { animation-delay: .16s; }
        .metric:nth-child(4) { animation-delay: .24s; }
        @keyframes cardRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes cardFloat { 0%, 100% { translate: 0 0; } 50% { translate: 0 -4px; } }
        @keyframes tableFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
        @media (prefers-reduced-motion: reduce) { .metric, .panel, .record-files .table-wrap { animation: none; } }
        .metric-label { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .metric-value { display: block; color: var(--blue); font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700; margin-top: 9px; }
        .panel { overflow: hidden; }
        .panel-head { padding: 20px 22px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .panel-head h2 { font-size: 19px; }
        .panel-head span { color: var(--muted); font-size: 13px; }
        .panel-actions { display: flex; align-items: center; gap: 10px; }
        .export-btn { display: inline-block; border: 0; border-radius: 8px; padding: 9px 13px; background: var(--blue); color: #fff; text-decoration: none; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
        .export-btn:hover { opacity: .88; }
        .report-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 22px; border-top: 1px solid var(--line); color: var(--muted); font-size: 13px; }
        .record-files { margin-top: 24px; margin-bottom: 24px; }
        .record-files .panel-head { flex-wrap: wrap; }
        .report-generator-form { margin: 0 22px 12px; }
        .report-generator-form .search-btn { min-height: 40px; white-space: nowrap; }
        .report-period-help { margin: 0; padding: 0 22px 14px; color: var(--muted); font-size: 12px; }
        .report-metric-tabs { display: flex; gap: 8px; overflow-x: auto; padding: 0 0 14px; }
        .report-metric-tab { flex: 0 0 auto; padding: 9px 14px; border: 1px solid var(--line); border-radius: 999px; background: #f9fbfe; color: var(--muted); font: inherit; font-size: 12px; font-weight: 700; white-space: nowrap; cursor: pointer; }
        .report-metric-tab[aria-pressed="true"] { border-color: var(--blue); background: var(--blue); color: #fff; }
        .report-metric-tab:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        .record-files .table-wrap { max-height: 260px; overflow-y: auto; animation: tableFloat 7s ease-in-out 1s infinite; }
        .record-files table { min-width: 0; }
        .view-date { display: inline-block; padding: 7px 11px; border-radius: 8px; background: var(--blue-soft); color: var(--blue); text-decoration: none; font-size: 12px; font-weight: 700; }
        .view-date:hover { background: #dbeaff; }
        .overall-reading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 22px; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); background: #f9fbfe; color: var(--muted); font-size: 13px; font-weight: 700; }
        .overall-reading strong { color: var(--blue); font-family: 'Space Grotesk', sans-serif; font-size: 22px; }
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
            .record-file-search { display: grid; grid-template-columns: 1fr; gap: 8px; }
            .record-file-search input, .record-file-search .search-btn { width: 100%; min-width: 0; }
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
        @media print {
            body { background: #fff; animation: none; }
            .sidebar, .header, .mobile-menu-toggle, .sidebar-backdrop,             .date-form, .record-file-search, .panel-actions, .report-pagination, .record-files { display: none !important; }
            .app-shell, .main, .main main { display: block; width: 100%; margin: 0; }
            .main main { padding: 12px; }
            .metric, .panel { box-shadow: none; animation: none; break-inside: avoid; }
            .table-wrap { max-height: none; overflow: visible; }
            .chart-scroll { overflow: visible; }
            .chart-wrap { min-width: 0; height: 260px; }
            .chart-wrap canvas { width: 100% !important; max-width: 100%; }
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
                <p>
                    Generate daily, weekly, monthly, or custom-range reports with PM2.5 trends, alerts, and sensor selection.
                </p>
            </div>
        </section>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <?php if ($filterWarning): ?><div class="error"><?php echo htmlspecialchars($filterWarning); ?></div><?php endif; ?>
            <section class="summary">
                <div class="metric"><span class="metric-label">Device readings</span><strong class="metric-value"><?php echo number_format(count($selectedLogs)); ?></strong></div>
                <div class="metric"><span class="metric-label">Valid PM2.5 readings</span><strong class="metric-value"><?php echo number_format(count($pm25Values)); ?> / <?php echo number_format(count($selectedLogs)); ?></strong></div>
                <div class="metric"><span class="metric-label">Average PM2.5</span><strong class="metric-value"><?php echo $pm25Value($averagePm25); ?></strong></div>
                <div class="metric"><span class="metric-label">Change vs previous period</span><strong class="metric-value"><?php echo $averageDelta === null ? '--' : (($averageDelta > 0 ? '+' : '') . number_format($averageDelta, 1) . ' µg/m³'); ?></strong></div>
                <div class="metric"><span class="metric-label">Peak PM2.5</span><strong class="metric-value"><?php echo $pm25Value($peakPm25); ?></strong></div>
                <div class="metric"><span class="metric-label">PM2.5 unhealthy / hazardous</span><strong class="metric-value"><?php echo number_format(count($highReadings)); ?></strong></div>
            </section>

            <section class="alert-summary<?php echo $highReadings ? '' : ' clear'; ?>" aria-live="polite">
                <?php if ($highReadings): ?>
                    <?php $firstHighReading = $highReadings[0]; $latestHighReading = $highReadings[count($highReadings) - 1]; ?>
                    <strong><?php echo number_format(count($highReadings)); ?> unhealthy or hazardous PM2.5 readings in this report.</strong>
                    First: <?php echo date('M j, h:i A', strtotime($firstHighReading['timestamp'])); ?>
                    (<?php echo number_format((float) $firstHighReading['pm25'], 1); ?> µg/m³); latest:
                    <?php echo date('M j, h:i A', strtotime($latestHighReading['timestamp'])); ?>
                    (<?php echo number_format((float) $latestHighReading['pm25'], 1); ?> µg/m³).
                <?php else: ?>
                    No unhealthy or hazardous PM2.5 readings were recorded in this report.
                <?php endif; ?>
            </section>

            <section class="panel chart-panel">
                <div class="panel-head" style="padding: 0 0 18px; border-bottom: 0;">
                    <div>
                        <h2 id="reportChartTitle"><?php echo htmlspecialchars(ucfirst($period)); ?> PM2.5 Trend</h2>
                        <span>Daily reports show hourly averages; longer reports show daily averages.</span>
                    </div>
                </div>
                <?php if ($selectedLogs): ?>
                    <div class="report-metric-tabs" role="group" aria-label="Select measurement shown in the graph and readings table">
                        <?php foreach ($reportMetrics as $metricKey => $metric): ?>
                            <button class="report-metric-tab" type="button" data-report-metric="<?php echo htmlspecialchars($metricKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-pressed="<?php echo $metricKey === 'pm25' ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($metricKey === 'co' ? 'MQ-7 (raw ADC)' : $metric['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div id="reportChartEmpty" class="empty" hidden>No valid measurements are available for this metric in the selected report.</div>
                    <div id="reportChartScroll" class="chart-scroll"><div class="chart-wrap"><canvas id="reportChart"></canvas></div></div>
                <?php else: ?>
                    <div class="empty">No sensor readings match the selected report filters.</div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2><?php echo htmlspecialchars($reportTitle); ?></h2>
                    <div class="panel-actions">
                        <span><?php echo number_format($totalReportRows); ?> matching readings</span>
                        <?php if ($selectedLogs): ?>
                            <button type="button" class="export-btn" onclick="window.print()">Print</button>
                        <?php endif; ?>
                    </div>
                </div>
                <form class="date-form report-generator-form" method="get">
                    <label for="period">Report period</label>
                    <select id="period" name="period" onchange="updateReportDateRange()">
                        <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'custom' => 'Custom range'] as $periodValue => $periodLabel): ?>
                            <option value="<?php echo $periodValue; ?>" <?php echo $period === $periodValue ? 'selected' : ''; ?>><?php echo $periodLabel; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="from">From date</label>
                    <input id="from" name="from" type="text" inputmode="numeric" pattern="[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}" maxlength="10" placeholder="DD/MM/YYYY" value="<?php echo htmlspecialchars($displayFromDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-label="Report start date (DD/MM/YYYY)" title="Use DD/MM/YYYY" oninput="switchToCustomReportRange()" required>
                    <label for="to">To date</label>
                    <input id="to" name="to" type="text" inputmode="numeric" pattern="[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}" maxlength="10" placeholder="DD/MM/YYYY" value="<?php echo htmlspecialchars($displayToDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-label="Report end date (DD/MM/YYYY)" title="Use DD/MM/YYYY" onfocus="switchToCustomReportRange()" oninput="switchToCustomReportRange()" required <?php echo $period === 'custom' ? '' : 'readonly'; ?>>
                    <label for="sensor">Sensor</label>
                    <select id="sensor" name="sensor">
                        <option value="">All sensors</option>
                        <?php $sensorIds = array_keys($sensors); sort($sensorIds, SORT_NATURAL | SORT_FLAG_CASE); ?>
                        <?php foreach ($sensorIds as $sensorId): ?>
                            <option value="<?php echo htmlspecialchars($sensorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $selectedSensor === $sensorId ? 'selected' : ''; ?>><?php echo htmlspecialchars($sensorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="search-btn" type="submit">Generate Report</button>
                    <?php if ($selectedLogs): ?><a class="export-btn" href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Download CSV</a><?php endif; ?>
                </form>
                <p class="report-period-help">Enter dates as DD/MM/YYYY, then select Generate Report to search the selected dates and sensor. Typing will not change either date; editing a date switches to Custom range.</p>
                <?php $hasIdentifiedSensors = count(array_diff(array_keys($sensors), ['Unidentified sensor'])) > 0; ?>
                <?php if (!$hasIdentifiedSensors): ?>
                    <p class="sidebar-note" style="margin:0 22px 16px;">
                        Current readings have no sensor ID; they are grouped as “Unidentified sensor.” Configure a sensor ID to enable accurate filtering for new readings.
                    </p>
                <?php endif; ?>
                <?php if (!$selectedLogs): ?>
                    <div class="empty">No sensor readings match the selected report filters.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Date and time</th><th>Sensor</th><th id="readingMetricHeader">PM2.5 (µg/m³)</th><th id="pm25CategoryHeader">PM2.5 category</th></tr></thead>
                            <tbody>
                            <?php foreach ($displayLogs as $log): ?>
                                <tr class="report-reading-row"
                                    data-pm25="<?php echo htmlspecialchars($formatMetricValue($log['pm25'] ?? null, 0, 1000, 'µg/m³'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                    data-temp="<?php echo htmlspecialchars($formatMetricValue($log['temp'] ?? null, -40, 85, '°C'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                    data-hum="<?php echo htmlspecialchars($formatMetricValue($log['hum'] ?? null, 0, 100, '%'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                    data-co="<?php echo htmlspecialchars($formatMetricValue($log['co'] ?? null, 0, 4095, 'raw ADC'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                    <td><?php echo date('M j, Y h:i:s A', strtotime($log['timestamp'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($log['sensor_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                    <td class="reading-metric-value"><?php echo $formatMetricValue($log['pm25'] ?? null, 0, 1000, 'µg/m³'); ?></td>
                                    <?php $pmCategory = classifyPm25($log['pm25'] ?? null); ?>
                                    <td class="pm25-category"><span class="status" style="color:<?php echo htmlspecialchars($pmCategory['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;"><?php echo htmlspecialchars($pmCategory['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="report-pagination">
                        <span>Showing <?php echo number_format($firstReportRow); ?>–<?php echo number_format($lastReportRow); ?> of <?php echo number_format($totalReportRows); ?> readings · Page <?php echo number_format($reportPage); ?> of <?php echo number_format($totalReportPages); ?></span>
                        <div class="panel-actions">
                            <?php if ($reportPage > 1): ?><a class="view-date" href="<?php echo htmlspecialchars($reportPageUrl($reportPage - 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Previous</a><?php endif; ?>
                            <?php if ($reportPage < $totalReportPages): ?><a class="view-date" href="<?php echo htmlspecialchars($reportPageUrl($reportPage + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Next</a><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel record-files">
                <div class="panel-head">
                    <div>
                        <h2>Record Files</h2>
                        <span><?php echo number_format(count($days)); ?> dates and <?php echo number_format($totalDeviceReadings); ?> device readings indexed in Firebase.</span>
                    </div>
                </div>
                <form class="record-file-search" method="get">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    <input type="hidden" name="from" value="<?php echo htmlspecialchars($generatorFromDate); ?>">
                    <input type="hidden" name="to" value="<?php echo htmlspecialchars($generatorToDate); ?>">
                    <input type="hidden" name="sensor" value="<?php echo htmlspecialchars($selectedSensor); ?>">
                    <label for="file-search">Search Record Files</label>
                    <input id="file-search" name="file_search" type="search" placeholder="Search date or reading count" value="<?php echo htmlspecialchars($recordFileSearch); ?>">
                    <button class="search-btn" type="submit">Search Files</button>
                    <?php if ($recordFileSearch): ?><a class="clear-search" href="<?php echo htmlspecialchars($clearFileSearchUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Clear search</a><?php endif; ?>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Readings</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if (!$filteredDays): ?>
                            <tr><td colspan="3"><?php echo $days ? 'No record files match that search.' : 'No record files available.'; ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($filteredDays as $day => $dayReadingCount): ?>
                                <tr>
                                    <td><?php echo date('l, F j, Y', strtotime($day)); ?></td>
                                    <td><?php echo number_format($dayReadingCount); ?></td>
                                    <td><a class="view-date" href="sensor_reports.php?period=daily&amp;from=<?php echo urlencode($day); ?>&amp;to=<?php echo urlencode($day); ?>">View records</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

        function formatLocalDate(date) {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            return `${day}/${month}/${date.getFullYear()}`;
        }

        function parseLocalDate(value) {
            const match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(value.trim());
            if (!match) return null;

            const day = Number(match[1]);
            const month = Number(match[2]) - 1;
            const year = Number(match[3]);
            const date = new Date(year, month, day, 12);
            if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) return null;
            return date;
        }

        function switchToCustomReportRange() {
            const periodInput = document.getElementById('period');
            const toInput = document.getElementById('to');
            periodInput.value = 'custom';
            toInput.readOnly = false;
        }

        function updateReportDateRange() {
            const period = document.getElementById('period').value;
            const fromInput = document.getElementById('from');
            const toInput = document.getElementById('to');
            const customRange = period === 'custom';
            fromInput.required = true;
            toInput.required = true;
            fromInput.readOnly = false;
            toInput.readOnly = !customRange;
            if (customRange || !fromInput.value) return;

            const start = parseLocalDate(fromInput.value);
            if (!start) return;
            let rangeStart = new Date(start);
            let rangeEnd = new Date(start);
            if (period === 'weekly') {
                rangeStart.setDate(rangeStart.getDate() - ((rangeStart.getDay() + 6) % 7));
                rangeEnd = new Date(rangeStart);
                rangeEnd.setDate(rangeStart.getDate() + 6);
            } else if (period === 'monthly') {
                rangeStart = new Date(start.getFullYear(), start.getMonth(), 1, 12);
                rangeEnd = new Date(start.getFullYear(), start.getMonth() + 1, 0, 12);
            }
            fromInput.value = formatLocalDate(rangeStart);
            toInput.value = formatLocalDate(rangeEnd);
        }
        const reportTrendSeries = <?php echo json_encode($trendSeries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
        const reportPeriodLabel = <?php echo json_encode(ucfirst($period), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
        const reportMetricConfig = {
            pm25: { label: 'PM2.5', heading: 'PM2.5 (µg/m³)', unit: 'µg/m³', hasCategory: true },
            temp: { label: 'Temperature', heading: 'Temperature (°C)', unit: '°C', hasCategory: false },
            hum: { label: 'Humidity', heading: 'Humidity (%)', unit: '%', hasCategory: false },
            co: { label: 'MQ-7 signal (raw ADC)', heading: 'MQ-7 signal (raw ADC)', unit: 'raw ADC', hasCategory: false }
        };
        const chartCanvas = document.getElementById('reportChart');
        let reportChart = null;
        function selectReportMetric(metric, activeTab) {
            const config = reportMetricConfig[metric];
            const series = reportTrendSeries[metric];
            if (!config || !series) return;

            const hasData = series.labels.length > 0;
            document.getElementById('reportChartTitle').textContent = `${reportPeriodLabel} ${config.label} Trend`;
            document.getElementById('reportChartScroll').hidden = !hasData;
            document.getElementById('reportChartEmpty').hidden = hasData;
            document.getElementById('readingMetricHeader').textContent = config.heading;
            document.getElementById('pm25CategoryHeader').hidden = !config.hasCategory;
            document.querySelectorAll('.report-reading-row').forEach(function (row) {
                row.querySelector('.reading-metric-value').textContent = row.dataset[metric];
                row.querySelector('.pm25-category').hidden = !config.hasCategory;
            });
            document.querySelectorAll('.report-metric-tab').forEach(function (tab) {
                const isSelected = tab === activeTab;
                tab.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            });
            if (!hasData) return;

            if (!reportChart) {
                reportChart = new Chart(chartCanvas, {
                    type: 'line',
                    data: {
                        labels: series.labels,
                        datasets: [{
                            label: config.label,
                            data: series.values,
                            borderColor: '#174ea6',
                            backgroundColor: 'rgba(23,78,166,.12)',
                            fill: false,
                            tension: .35
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { title: { display: true, text: config.unit } } }
                    }
                });
            } else {
                reportChart.data.labels = series.labels;
                reportChart.data.datasets[0].label = config.label;
                reportChart.data.datasets[0].data = series.values;
                reportChart.options.scales.y.title.text = config.unit;
                reportChart.update();
                reportChart.resize();
            }
        }
        document.querySelectorAll('.report-metric-tab').forEach(function (button) {
            button.addEventListener('click', function () {
                selectReportMetric(button.dataset.reportMetric, button);
            });
        });
        const initialMetricTab = document.querySelector('.report-metric-tab[aria-pressed="true"]');
        if (initialMetricTab && chartCanvas) {
            selectReportMetric(initialMetricTab.dataset.reportMetric, initialMetricTab);
        }

    </script>
</body>
</html>
