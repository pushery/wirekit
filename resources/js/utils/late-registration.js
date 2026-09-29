/**
 * The diagnostic for a bundle that loaded after Alpine had already walked the page.
 *
 * Every WireKit bundle registers its components twice over: on `alpine:init` (the
 * intended path) and again immediately if `window.Alpine` is already present. The
 * second path cannot rescue the DOM that is already on the page.
 *
 * Registering an `Alpine.data()` name after `Alpine.start()` leaves an element that
 * already named it permanently dead — the binding never resolves, the element keeps
 * `display: none`. And `Alpine.initTree(document.body)` does not revive it: Alpine
 * skips a node it has already marked, so the re-walk is a no-op on exactly the
 * elements that need one. The obvious repair does not work, which is why this
 * reports rather than retries.
 *
 * The registration itself is still worth doing: Alpine walks DOM added later, so a
 * Livewire morph or anything appended afterwards does get its component. The report
 * is for everything already rendered, which would otherwise fail in silence.
 *
 * And that silence is expensive, because the error the developer actually sees names
 * neither WireKit nor the real cause. Alpine reports the dead element's CHILD
 * bindings, so the console says `startShadow is not defined` — a property of a
 * component the developer never wrote, out of a bundle they did not author. On the
 * table `responsive` defaults to true, so one ordinary `<x-wirekit::table>` is enough
 * to produce it, on a page whose author has never heard of a sticky panel.
 */

/**
 * Run a bundle's registration and return the component names it registered.
 *
 * The guard below asks whether a component of this bundle is dead on the page, so it needs
 * the names, and the registration function is the one place that already lists them. The
 * `Alpine.data` of the running Alpine is wrapped for the duration of the call and restored
 * afterwards, whatever the call does.
 *
 * @param {{data: Function}} Alpine  The running Alpine, whose `data` the bundle calls.
 * @param {() => void} register      The bundle's registration function.
 * @returns {string[]}
 */
export function registeredNames(Alpine, register) {
    const names = [];
    const original = Alpine.data;

    Alpine.data = function (name, ...rest) {
        names.push(name);

        return original.call(this, name, ...rest);
    };

    try {
        register();
    } finally {
        Alpine.data = original;
    }

    return names;
}

/**
 * Whether Alpine has walked an element that names one of `names` and had no component for it.
 *
 * An x-data expression that names an unregistered component fails to evaluate, and Alpine
 * gives the element an empty scope: `_x_dataStack` is set, and its first entry has no keys of
 * its own (Alpine's magics are not enumerable). A live component always has keys. An element
 * Alpine has not walked yet has no `_x_dataStack` and is not dead: it gets its component when
 * the walk reaches it. That is the state of the incoming page during a `wire:navigate`, whose
 * new head scripts run after the body is swapped in and before Alpine walks it.
 *
 * @param {string[]|null} names  The bundle's component names; null matches every WireKit one.
 */
function hasDeadComponent(names) {
    for (const el of document.querySelectorAll('[x-data^="wirekit"]')) {
        const name = /^\s*([\w$]+)/.exec(el.getAttribute('x-data') || '')?.[1];

        if (Array.isArray(names) && ! names.includes(name)) {
            continue;
        }

        const scope = el._x_dataStack?.[0];

        if (scope && Object.keys(scope).length === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Report, once and only when a component of this bundle is dead on the page, that this
 * bundle missed Alpine's walk.
 *
 * @param {string} bundle       Bundle filename, so the message names the file to move.
 * @param {() => boolean} wasEarly  Reads the caller's `alpine:init` flag AT CHECK TIME.
 * @param {string[]|null} [names]   The component names the bundle registered (see
 *   `registeredNames`); null checks every WireKit component.
 */
export function reportLateRegistration(bundle, wasEarly, names = null) {
    // No document to read means nothing to report on. The test is for the method the check
    // below calls, `querySelectorAll`.
    if (typeof document === 'undefined' || typeof document.querySelectorAll !== 'function') {
        return;
    }

    /*
     * A second evaluation of the same bundle can never be the thing this guard reports.
     *
     * The flag `wasEarly()` reads lives in the bundle's module scope. Evaluate the file again
     * while Alpine is already running — which a Livewire redirect-navigate does, by executing the
     * replaced head script — and that flag is a fresh `false` in a fresh closure. `alpine:init`
     * has long since fired and does not fire again, so it can never become true; `readyState` is
     * already "complete", so the check would run immediately rather than on `load`; and the theme
     * controller every page carries satisfies the markup test. Every gate would pass, and the
     * console would get an error about a working page, naming `async`, a runtime injection and a
     * bundler that dropped `defer`, where the tag is present, it is `defer`, and registration
     * just happened one expression earlier.
     *
     * Keyed per bundle rather than globally: `wirekit.js` and `wirekit.core.js` can both be on a
     * page, and one having been seen says nothing about the other. Held on `window` because that
     * is the only scope a second evaluation shares with the first.
     *
     * The real case still reports. A bundle genuinely loaded late — `async` instead of `defer` —
     * is a first evaluation, so nothing is recorded yet and the guard arms.
     */
    if (typeof window !== 'undefined') {
        const seen = window.__wirekitBundlesEvaluated || (window.__wirekitBundlesEvaluated = {});

        if (seen[bundle]) {
            return;
        }

        seen[bundle] = true;
    }

    const check = () => {
        /*
         * `alpine:init` having reached us means we were early after all, and the
         * synchronous `window.Alpine?.version` test that got us here simply could not
         * tell the two cases apart: Livewire assigns `window.Alpine` well before it
         * calls `start()`, so "Alpine is on the page" and "Alpine has walked the page"
         * look identical at that moment. Deferring the question to `load` separates
         * them — by then Alpine has started, so an `alpine:init` that never arrived
         * means the walk genuinely happened without us. Checking any earlier reports a
         * healthy head-of-document load as broken.
         */
        if (wasEarly()) {
            return;
        }

        /*
         * Late is only a problem where it killed something. A page with no component of
         * this bundle, or with components Alpine has not walked yet, loses nothing, and
         * warning there would train developers to ignore the message on the pages where
         * it is true. The page `wire:navigate` brings in is the common case: the bundle
         * first runs there while Alpine is running, and its components start afterwards.
         */
        if (! hasDeadComponent(names)) {
            return;
        }

        /*
         * Kept deliberately short. It ships in five bundles, so every character is paid
         * for five times over — and on the core bundle the message was a measurable
         * fraction of the whole file. What it may not lose is the part a developer can
         * SEARCH for: the `[wirekit]` tag, the bundle name, the shape of the error they
         * are actually staring at, and the one directive that fixes it.
         */
        console.error(
            `[wirekit] ${bundle} loaded after Alpine started — components already rendered `
            + 'never got their data and now report "<name> is not defined" (a table alone '
            + 'gives "startShadow is not defined"). Add @wirekitScripts if it is missing. If it '
            + 'is there, the tag order is not the lever — it emits `defer`, which already runs '
            + 'before Alpine.start() — so look for what ran the bundle late: an `async` attribute, '
            + 'a runtime injection, a bundler that dropped the defer.'
        );
    };

    if (document.readyState === 'complete') {
        check();

        return;
    }

    if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
        window.addEventListener('load', check, { once: true });
    }
}
