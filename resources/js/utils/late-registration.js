/**
 * The diagnostic for a bundle that loaded after Alpine had already walked the page.
 *
 * Every WireKit bundle registers its components twice over: on `alpine:init` (the
 * intended path) and again immediately if `window.Alpine` is already present. That
 * second path was documented as the fallback for "Alpine was already started before
 * this script loaded", and for the DOM that is already on the page it does not do
 * that at all.
 *
 * Measured in chromium, both halves. Registering an `Alpine.data()` name after
 * `Alpine.start()` leaves an element that already named it permanently dead — the
 * binding never resolves, the element keeps `display: none`. And
 * `Alpine.initTree(document.body)` does NOT revive it: Alpine skips a node it has
 * already marked, so the re-walk is a no-op on exactly the elements that need one.
 * The obvious repair does not work, which is why this reports rather than retries.
 *
 * The registration itself is still worth doing, so it stays: Alpine walks DOM added
 * LATER, so a Livewire morph or anything appended afterwards does get its component.
 * What was wrong was the silence about everything already rendered.
 *
 * And that silence is expensive, because the error the developer actually sees names
 * neither WireKit nor the real cause. Alpine reports the dead element's CHILD
 * bindings, so the console says `startShadow is not defined` — a property of a
 * component the developer never wrote, out of a bundle they did not author. On the
 * table `responsive` defaults to true, so one ordinary `<x-wirekit::table>` is enough
 * to produce it, on a page whose author has never heard of a sticky panel.
 */

/**
 * Report — once, and only when there is dead markup to explain — that this bundle
 * missed Alpine's walk.
 *
 * @param {string} bundle       Bundle filename, so the message names the file to move.
 * @param {() => boolean} wasEarly  Reads the caller's `alpine:init` flag AT CHECK TIME.
 */
export function reportLateRegistration(bundle, wasEarly) {
    if (typeof document === 'undefined' || typeof document.querySelector !== 'function') {
        return;
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
         * No WireKit markup means nothing died, even though we were late. A bundle
         * loaded late onto a page that renders no WireKit component is a real and
         * harmless arrangement, and warning there would train developers to ignore
         * the message on the pages where it is true.
         */
        if (! document.querySelector('[x-data^="wirekit"]')) {
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
            + 'gives "startShadow is not defined"). Load @wirekitScripts before Alpine/Livewire.'
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
