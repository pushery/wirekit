/**
 * WireKit ApexCharts Bundle (IIFE).
 *
 * A separate bundle from `wirekit.js` (the main bundle) so consumers who
 * don't use ApexCharts pay zero bytes. Imports a single Alpine factory and
 * registers it under `wirekitApexChart` — the name ApexChartsAdapter.alpineComponent()
 * returns and the chart Blade template wires into x-data.
 *
 * This bundle ships ZERO ApexCharts code. The consumer installs apexcharts
 * via npm and exposes it on window before this script loads:
 *
 *   import ApexCharts from 'apexcharts';
 *   window.ApexCharts = ApexCharts;
 *
 * License: ApexCharts is non-MIT. See https://apexcharts.com/license/.
 * WireKit ships only this Alpine glue (MIT).
 */
import wirekitApexChart from './components/chart-apex.js';

function registerApexChartComponent() {
    Alpine.data('wirekitApexChart', wirekitApexChart);
}

// Primary path: register before Alpine.start() processes the DOM.
document.addEventListener('alpine:init', registerApexChartComponent);

// Fallback: if Alpine was already started before this script loaded,
// register immediately. Alpine.data() is idempotent — double-registration
// is safe.
if (window.Alpine?.version) {
    registerApexChartComponent();
}
