(function (global) {
    'use strict';

    const categoryRules = [
        { max: 12, level: 'GOOD', label: 'Good', color: '#168a68' },
        { max: 35, level: 'MODERATE', label: 'Moderate', color: '#d88a18' },
        { max: 150, level: 'UNHEALTHY', label: 'Unhealthy', color: '#ed6c02' },
        { max: 1000, level: 'HAZARDOUS', label: 'Hazardous', color: '#c43d4b' }
    ];
    const unknown = { level: 'UNKNOWN', label: 'Unknown', color: '#64748b' };
    const invalid = { level: 'INVALID', label: 'Invalid', color: '#64748b' };

    function classifyPm25(rawValue) {
        if (rawValue === null || rawValue === undefined || rawValue === '') return unknown;
        const value = Number(rawValue);
        if (!Number.isFinite(value)) return unknown;
        if (value < 0 || value > 1000) return invalid;
        return categoryRules.find(rule => value <= rule.max);
    }

    function isAlertLevel(level) {
        return level === 'UNHEALTHY' || level === 'HAZARDOUS';
    }

    function forecastSummary(metrics, basis) {
        if (!metrics || metrics.available !== true) {
            return `No estimate yet. ${(metrics && metrics.reason) || 'More recent readings are needed.'}`;
        }

        const backtests = Number(metrics.backtest_samples);
        const mae = Number(metrics.mae);
        const unit = metrics.unit || 'units';
        const errorText = Number.isFinite(backtests) && Number.isFinite(mae)
            ? ` In ${backtests} past checks, it was off by about ${mae.toFixed(1)} ${unit} on average.`
            : '';

        if (metrics.method === 'baseline') {
            return `Recent readings did not show a reliable trend, so this estimate uses the latest reading.${errorText}`;
        }

        const samples = Number(basis && basis.sample_count);
        const spanMinutes = Number(basis && basis.span_minutes);
        const basisText = Number.isFinite(samples) && Number.isFinite(spanMinutes)
            ? `Estimate uses ${samples} recent readings from the past ${spanMinutes.toFixed(0)} minutes.`
            : 'Estimate uses recent readings.';

        if (!Number.isFinite(backtests) || !Number.isFinite(mae)) {
            return `${basisText} Past accuracy information is not available yet.`;
        }

        const accuracy = Number(metrics.accuracy_pct);
        const comparisonText = Number.isFinite(accuracy)
            ? accuracy > 0
                ? ` It was ${accuracy.toFixed(1)}% more accurate than using the latest reading alone.`
                : ' It was about as accurate as using the latest reading alone.'
            : '';
        return `${basisText}${errorText}${comparisonText}`;
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[character]);
    }

    global.AirshedAirQuality = {
        classifyPm25,
        isAlertLevel,
        forecastSummary,
        escapeHtml
    };
})(window);
