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
            return `No validated estimate: ${(metrics && metrics.reason) || 'not enough reliable history.'}`;
        }

        const samples = Number(basis && basis.sample_count);
        const spanMinutes = Number(basis && basis.span_minutes);
        const backtests = Number(metrics.backtest_samples);
        const mae = Number(metrics.mae);
        const baselineMae = Number(metrics.baseline_mae);
        const basisText = Number.isFinite(samples) && Number.isFinite(spanMinutes)
            ? `Estimate based on ${samples} recent readings over ${spanMinutes.toFixed(0)} minutes.`
            : 'Estimate based on recent device readings.';

        if (!Number.isFinite(backtests) || !Number.isFinite(mae) || !Number.isFinite(baselineMae)) {
            return `${basisText} Historical validation details are unavailable.`;
        }

        const unit = metrics.unit || 'units';
        const accuracy = Number(metrics.accuracy_pct);
        const accuracyText = Number.isFinite(accuracy)
            ? ` Error improved ${accuracy.toFixed(1)}% over the baseline.`
            : '';
        return `${basisText} Back-tested on ${backtests} cases: MAE ${mae.toFixed(1)} ${unit} vs ${baselineMae.toFixed(1)} ${unit} for a last-reading baseline.${accuracyText}`;
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
