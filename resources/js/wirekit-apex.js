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
import { reportLateRegistration } from './utils/late-registration.js';

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
let reachedByInitEvent = false;
document.addEventListener('alpine:init', () => {
    reachedByInitEvent = true;
    registerApexChartComponent();
});

// Fallback: if Alpine was already started before this script loaded, register now, so
// charts in DOM Alpine has not walked yet still work. It does NOT rescue the charts
// already on the page — measured: an element whose x-data named a component that did
// not exist at walk time stays dead, and a re-walk does not revive it. The sentence
// this comment used to carry ("otherwise the component never registers at all") was
// true about the registration and wrong about the charts.
if (window.Alpine?.version) {
    registerApexChartComponent();
    reportLateRegistration('wirekit-apex.js', () => reachedByInitEvent);
}
