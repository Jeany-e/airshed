<?php

function calculateValidatedForecast(array $rows, $field, $horizonSeconds, $minimum, $maximum) {
    $minimumSamples = 4;
    $minimumSpanSeconds = 300;
    $historyWindowSeconds = 3600;
    $maximumSamples = 100;
    $toleranceSeconds = max(300, (int) round($horizonSeconds * 0.1));
    $anchorSpacingSeconds = max(300, (int) ($horizonSeconds / 4));

    $fit = function ($endIndex) use ($rows, $field, $horizonSeconds, $minimum, $maximum, $minimumSamples, $minimumSpanSeconds, $historyWindowSeconds, $maximumSamples) {
        if (!isset($rows[$endIndex]['timestamp_unix'])) {
            return null;
        }

        $anchorTime = (int) $rows[$endIndex]['timestamp_unix'];
        $samples = [];
        for ($index = $endIndex; $index >= 0 && count($samples) < $maximumSamples; $index--) {
            $timestamp = (int) ($rows[$index]['timestamp_unix'] ?? 0);
            if ($anchorTime - $timestamp > $historyWindowSeconds) {
                break;
            }

            $value = $rows[$index][$field] ?? null;
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < $minimum || (float) $value > $maximum) {
                continue;
            }
            $samples[] = [
                'x' => ($timestamp - $anchorTime) / 60,
                'y' => (float) $value
            ];
        }

        if (count($samples) < $minimumSamples) {
            return null;
        }

        $spanMinutes = -$samples[count($samples) - 1]['x'];
        if ($spanMinutes * 60 < $minimumSpanSeconds) {
            return null;
        }

        $meanX = array_sum(array_column($samples, 'x')) / count($samples);
        $meanY = array_sum(array_column($samples, 'y')) / count($samples);
        $covariance = 0;
        $variance = 0;
        foreach ($samples as $sample) {
            $differenceX = $sample['x'] - $meanX;
            $covariance += $differenceX * ($sample['y'] - $meanY);
            $variance += $differenceX * $differenceX;
        }
        if ($variance <= 0) {
            return null;
        }

        $slope = $covariance / $variance;
        $prediction = $meanY + $slope * (($horizonSeconds / 60) - $meanX);
        if (!is_finite($prediction)) {
            return null;
        }

        return [
            'value' => min($maximum, max($minimum, $prediction)),
            'sample_count' => count($samples),
            'span_minutes' => $spanMinutes
        ];
    };

    $predictionErrors = [];
    $baselineErrors = [];
    $nextAnchorTime = null;
    foreach ($rows as $index => $row) {
        $anchorTime = (int) ($row['timestamp_unix'] ?? 0);
        if ($anchorTime <= 0 || ($nextAnchorTime !== null && $anchorTime < $nextAnchorTime)) {
            continue;
        }

        $prediction = $fit($index);
        if ($prediction === null) {
            continue;
        }
        $nextAnchorTime = $anchorTime + $anchorSpacingSeconds;

        $targetTime = $anchorTime + $horizonSeconds;
        $targetIndex = null;
        $targetDistance = PHP_INT_MAX;
        for ($futureIndex = $index + 1; $futureIndex < count($rows); $futureIndex++) {
            $futureTime = (int) ($rows[$futureIndex]['timestamp_unix'] ?? 0);
            if ($futureTime > $targetTime + $toleranceSeconds) {
                break;
            }
            $distance = abs($futureTime - $targetTime);
            if ($distance < $targetDistance) {
                $targetIndex = $futureIndex;
                $targetDistance = $distance;
            }
        }

        if ($targetIndex === null || $targetDistance > $toleranceSeconds) {
            continue;
        }

        $actual = (float) ($rows[$targetIndex][$field] ?? 0);
        $anchorValue = (float) ($row[$field] ?? 0);
        $predictionErrors[] = abs($prediction['value'] - $actual);
        $baselineErrors[] = abs($anchorValue - $actual);
    }

    $latestFit = $rows ? $fit(count($rows) - 1) : null;
    $latestValue = $rows ? ($rows[count($rows) - 1][$field] ?? null) : null;
    $latestValueIsValid = is_numeric($latestValue)
        && is_finite((float) $latestValue)
        && (float) $latestValue >= $minimum
        && (float) $latestValue <= $maximum;
    $forecastValue = null;
    $backtestCount = count($predictionErrors);
    $metrics = [
        'available' => false,
        'unit' => $field === 'pm25' ? 'µg/m³' : ($field === 'temp' ? '°C' : '%'),
        'backtest_samples' => $backtestCount,
        'mae' => null,
        'baseline_mae' => null,
        'accuracy_pct' => null,
        'reason' => ''
    ];
    $basis = [
        'sample_count' => $latestFit['sample_count'] ?? 0,
        'span_minutes' => isset($latestFit['span_minutes']) ? round($latestFit['span_minutes'], 1) : 0
    ];

    if ($backtestCount < 10) {
        $metrics['reason'] = 'More past readings are needed to check this estimate.';
    } else {
        $mae = array_sum($predictionErrors) / $backtestCount;
        $baselineMae = array_sum($baselineErrors) / $backtestCount;
        $metrics['mae'] = round($mae, 2);
        $metrics['baseline_mae'] = round($baselineMae, 2);
        $metrics['accuracy_pct'] = $baselineMae > 0
            ? round(max(0, (1 - ($mae / $baselineMae)) * 100), 1)
            : null;

        $trendPassesValidation = $baselineMae > 0 && $mae <= $baselineMae * 0.97;
        if ($trendPassesValidation && $latestFit !== null) {
            $metrics['available'] = true;
            $metrics['method'] = 'trend';
            $forecastValue = $latestFit['value'];
        } elseif ($latestValueIsValid) {
            $metrics['available'] = true;
            $metrics['method'] = 'baseline';
            $metrics['mae'] = round($baselineMae, 2);
            $metrics['accuracy_pct'] = 0;
            $metrics['reason'] = $latestFit === null
                ? 'Not enough recent readings for a stable trend; using the latest reading as a baseline estimate.'
                : 'The trend model did not beat the last-reading baseline by at least 3%; using the baseline estimate.';
            $forecastValue = (float) $latestValue;
        } else {
            $metrics['reason'] = $latestFit === null
                ? 'Not enough recent readings to make a reliable estimate yet.'
                : 'Recent patterns were not accurate enough to forecast a change.';
        }
    }

    return [
        'value' => $metrics['available'] ? round($forecastValue, 2) : null,
        'metrics' => $metrics,
        'basis' => $basis
    ];
}
