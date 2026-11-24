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
