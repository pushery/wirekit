/**
 * WireKit Liquid Glass Extension
 *
 * No version in this banner, deliberately. The file is PUBLISHED into the developer's
 * own application by `wirekit:glass install` and never rebuilt there, so a number here
 * freezes at whatever it said the day it was written — it read v1.0.0 through a rewrite
 * of the whole detection strategy. The package's version is the one that moves.
 * Detects Tier 2 support (an SVG `filter:` reference the browser keeps) and sets a class
 * on <html> for progressive enhancement.
 *
 * The header said "SVG filter in backdrop-filter", which is what the detector used to test
 * and stopped testing — the block below records why. It is the one line of this file a
 * reader sees first, and it described the very property the fix moved away from.
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
