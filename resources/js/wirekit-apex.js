/**
 * WireKit ApexCharts Bundle (IIFE).
 *
 * A separate bundle from `wirekit.js` (the main bundle) so developers who
 * don't use ApexCharts pay zero bytes. Imports a single Alpine factory and
 * registers it under `wirekitApexChart` — the name ApexChartsAdapter.alpineComponent()
 * returns and the chart Blade template wires into x-data.
 *
 * This bundle ships ZERO ApexCharts code. The developer installs apexcharts
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
    // Detect a second registration instead of asserting it is fine.
    //
    // The comment here used to say "Alpine.data() is idempotent — double-registration is
    // safe", and that is true of the CALL: the second one simply overwrites the first. It
    // is not true of the situation. Two entry paths registering the same name means the
    // adapter arrived twice — typically `scripts.apex` emitting it while the app also
    // imports it — and whichever runs last silently wins. That is a configuration nobody
    // chose, and it is invisible: charts render, the wrong factory is behind them, and
    // nothing anywhere says so.
    //
    // A flag on window rather than asking Alpine, because Alpine exposes no way to ask
    // whether a data name is taken. The honest limit: this sees WireKit's own two paths
    // and cannot see an app-side factory registered under the same name by other code.
    if (window.__wirekitApexRegistered) {
        console.warn(
            '[WireKit] wirekitApexChart was registered twice. The adapter is on the page more '
            + 'than once — usually `scripts.apex` in config/wirekit.php emitting it while your '
            + 'own JavaScript also imports it. The last registration wins, so pick one.'
        );
    }

    window.__wirekitApexRegistered = true;
    Alpine.data('wirekitApexChart', wirekitApexChart);
}

// Primary path: register before Alpine.start() processes the DOM.
document.addEventListener('alpine:init', registerApexChartComponent);

// Fallback: if Alpine was already started before this script loaded, register now —
// otherwise the component never registers at all and every chart stays inert.
if (window.Alpine?.version) {
    registerApexChartComponent();
}
