/**
 * WireKit Core Bundle (IIFE).
 *
 * Contains the chart and image-compare Alpine components plus the one
 * directive the zero-JS form primitives need — no overlay dependencies. For
 * projects that only use the core form components, charts and the
 * before/after slider.
 *
 * Keep this list in step with what registerCoreComponents() below actually
 * registers: this docblock and the bundle table in dist/README.md are the two
 * places a developer looks to find out what "the smallest bundle" contains,
 * and image-compare was in the bundle for several releases while both still
 * said "chart only".
 */
import wirekitChartJs from './components/chart.js';
import wirekitImageCompare from './components/image-compare.js';
import { registerIndeterminateDirective } from './utils/indeterminate.js';
import { reportLateRegistration } from './utils/late-registration.js';

// Image compare has no Floating UI / focus-trap deps so it ships in the
// lighter "core" bundle too — a landing-page staple that form-heavy apps
// on the core bundle shouldn't have to upgrade to the full bundle for.
function registerCoreComponents() {
    // `indeterminate` is a DOM property with no HTML attribute, so something has
    // to apply it after EVERY render — not only the first. See
    // utils/indeterminate.js.
    //
    // It belongs in THIS bundle and not only the full one. Checkbox is a form
    // primitive with no overlay dependency, and the docblock above sells this
    // bundle to projects that use the form components; without the directive a
    // checkbox rendered with `indeterminate` reads as "none selected" while
    // something is selected. Alpine treats an unregistered directive as a no-op
    // and says nothing, so the failure is silent on exactly the bundle chosen
    // for being small. It costs one directive and no dependency.
    registerIndeterminateDirective(Alpine);

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
