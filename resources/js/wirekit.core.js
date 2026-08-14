/**
 * WireKit Core Bundle (IIFE).
 *
 * Contains only the chart Alpine component — no overlay dependencies.
 * For projects that only use the core form components + charts.
 */
import wirekitChartJs from './components/chart.js';
import wirekitImageCompare from './components/image-compare.js';
import { reportLateRegistration } from './utils/late-registration.js';

// Image compare has no Floating UI / focus-trap deps so it ships in the
// lighter "core" bundle too — a landing-page staple that form-heavy apps
// on the core bundle shouldn't have to upgrade to the full bundle for.
function registerCoreComponents() {
    Alpine.data('wirekitChartJs', wirekitChartJs);
    Alpine.data('wirekitImageCompare', wirekitImageCompare);
}

let reachedByInitEvent = false;
document.addEventListener('alpine:init', () => {
    reachedByInitEvent = true;
    registerCoreComponents();
});

// Fallback for late-loading scripts / non-Livewire setups where Alpine started
// before this module was parsed. It still reaches DOM Alpine has not walked yet, so
// it stays — but it cannot repair what Alpine already walked, and staying silent
// about that is what left developers reading "<name> is not defined" for a property
// they never wrote.
if (window.Alpine?.version) {
    registerCoreComponents();
    reportLateRegistration('wirekit.core.js', () => reachedByInitEvent);
}
