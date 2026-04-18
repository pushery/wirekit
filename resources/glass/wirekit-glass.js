/**
 * WireKit Liquid Glass Extension v1.0.0
 * Detects Tier 2 support (SVG filter in backdrop-filter) and sets a class
 * on <html> for progressive enhancement.
 */
(function () {
    'use strict';

    var el = document.createElement('div');
    el.style.backdropFilter = 'url(#x)';
    var supportsRefract = el.style.backdropFilter !== '';

    if (supportsRefract) {
        document.documentElement.classList.add('wk-glass-tier2');
    }

    if (window.location.search.indexOf('wk-glass-debug') !== -1) {
        console.info(
            '[WireKit Glass] Tier:', supportsRefract ? '2 (Refraction)' : '1 (Frosted)',
            '| UA:', navigator.userAgent.split(' ').pop()
        );
    }
})();
