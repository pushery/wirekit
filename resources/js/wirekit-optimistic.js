/**
 * WireKit Optimistic UI Bundle (IIFE).
 *
 * A separate bundle from `wirekit.js` so developers who never show a value
 * before the server has agreed to it pay zero bytes for the machinery that
 * does. Registers a single Alpine factory, `wirekitOptimistic`.
 *
 * **Opt-in is the design, not a build detail.** The factory carries an
 * accessibility contract — one announcement on the success path, arbitration
 * against a field's own error region, focus that never moves on a rollback —
 * and a developer inherits that contract by loading this file. Folding it into
 * the full bundle would hand the contract to everyone who wanted a dropdown, so
 * a test fails the build if the factory ever appears in a default bundle.
 *
 * There is no CSP twin of this file, and that is correct rather than an
 * oversight: the CSP alias exists only for `wirekit-alpine.js`, which BUNDLES
 * Alpine and therefore has to choose a distribution. This bundle registers onto
 * whichever Alpine the application already loaded, so it inherits that choice.
 * The wiring it depends on — `$wire.$intercept` — is present in Livewire's own
 * CSP build, measured rather than assumed.
 */
import wirekitOptimistic from './components/optimistic.js';

function registerOptimisticComponent() {
    Alpine.data('wirekitOptimistic', wirekitOptimistic);
}

// Primary path: register before Alpine.start() processes the DOM.
document.addEventListener('alpine:init', registerOptimisticComponent);

// Fallback: if Alpine was already started before this script loaded, register
// immediately. Alpine.data() is idempotent — double-registration is safe.
if (window.Alpine?.version) {
    registerOptimisticComponent();
}
