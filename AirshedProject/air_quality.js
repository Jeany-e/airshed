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

    function forecastSummary() {
        return '';
    }

    function formatManilaDateTime(timestamp) {
        const date = timestamp instanceof Date ? timestamp : new Date(timestamp);
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

    function forecastDetails(timestamp, horizonMinutes, metrics) {
        const baseTime = timestamp ? new Date(timestamp) : null;
        if (!baseTime || !Number.isFinite(baseTime.getTime())) {
            return 'Waiting for a fresh sensor reading.';
        }

        const targetTime = new Date(baseTime.getTime() + horizonMinutes * 60 * 1000);
        const targetText = targetTime.toLocaleString('en-GB', {
            timeZone: 'Asia/Manila',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        const details = [`Target: ${targetText}`];
        if (metrics && metrics.mae !== null && metrics.mae !== undefined
            && Number.isFinite(Number(metrics.mae)) && Number(metrics.backtest_samples) >= 10) {
            const unit = metrics.unit || '';
            details.push(`Past MAE ${Number(metrics.mae).toFixed(1)} ${unit}`.trim());
            if (metrics.baseline_mae !== null && metrics.baseline_mae !== undefined
                && Number.isFinite(Number(metrics.baseline_mae))) {
                details.push(`last-reading baseline ${Number(metrics.baseline_mae).toFixed(1)} ${unit}`.trim());
            }
            details.push(`n=${Number(metrics.backtest_samples)} backtests`);
        } else {
            details.push('Historical error check is not available yet.');
        }
        return details.join(' · ');
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
        formatManilaDateTime,
        forecastDetails,
        escapeHtml
    };
})(window);
