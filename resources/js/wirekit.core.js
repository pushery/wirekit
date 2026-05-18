/**
 * WireKit Core Bundle (IIFE).
 *
 * Contains only the chart Alpine component — no overlay dependencies.
 * For projects that only use the core form components + charts.
 */
import wirekitChartJs from './components/chart.js';
import wirekitImageCompare from './components/image-compare.js';

// Image compare has no Floating UI / focus-trap deps so it ships in the
// lighter "core" bundle too — a landing-page staple that form-heavy apps
// on the core bundle shouldn't have to upgrade to the full bundle for.
function registerCoreComponents() {
    Alpine.data('wirekitChartJs', wirekitChartJs);
    Alpine.data('wirekitImageCompare', wirekitImageCompare);
}

document.addEventListener('alpine:init', registerCoreComponents);

// Fallback for late-loading scripts / non-Livewire setups where Alpine
// started before this module was parsed.
if (window.Alpine?.version) {
    registerCoreComponents();
}
