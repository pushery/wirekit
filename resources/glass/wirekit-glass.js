/**
 * WireKit Liquid Glass Extension v1.0.0
 * Detects Tier 2 support (SVG filter in backdrop-filter) and sets a class
 * on <html> for progressive enhancement.
 */
(function () {
    'use strict';

    // What this used to test — `backdropFilter = 'url(#x)'` sticking — answered
    // the wrong question. Chromium accepts that assignment, so the class was set
    // and the stylesheet's matching @supports gate opened, while the filter
    // painted nothing: measured at 0 differing pixels against the same
    // declaration without the url(). Parsing was never the thing to detect.
    //
    // Tier 2 now rides on `filter:` applied to a pseudo-element, which is what
    // actually paints, so this tests THAT instead. The class stays because it is
    // documented and applications may style on it; the stylesheet no longer
    // depends on it, because the enhancement is additive — where the
    // displacement does not apply, what remains is Tier 1.
    var el = document.createElement('div');
    el.style.filter = 'url(#x)';
    var supportsRefract = el.style.filter !== '';

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
