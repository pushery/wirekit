/**
 * The landmark container every teleported overlay panel lives in.
 *
 * Twenty-four components teleport their panel to `<body>` — dropdowns, tooltips, popovers,
 * comboboxes, menus, the command palette. That is correct positioning (a panel inside an
 * `overflow: hidden` ancestor is clipped by it) and it puts the panel OUTSIDE every landmark
 * on the page: not in `<main>`, not in `<nav>`, not in anything. axe reports `region` for
 * each one, and a developer auditing their own app cannot fix it — the markup belongs to us.
 *
 * A screen-reader user meets the same problem the audit describes: content that exists in no
 * region at all, reachable by tabbing into it and then unmoored from the page structure.
 *
 * So the panels teleport into a named region instead. `role="region"` with a label is the
 * smallest correct answer: it is a landmark, it does not claim to be navigation or a main,
 * and it says what it holds.
 *
 * CREATED FROM JAVASCRIPT rather than emitted by a Blade directive, and that is deliberate.
 * `x-teleport` throws when its selector matches nothing, so a container that depended on a
 * directive would take the page down for anyone who loads the bundle another way — a
 * developer bundling it through Vite, a docs site embedding it, anyone not using
 * `@wirekitScripts`. Built here it cannot be missing.
 */
export const OVERLAY_ROOT_ID = 'wk-overlay-root';

/**
 * The name the landmark carries when the server did not supply one.
 *
 * English on purpose. A landmark with no accessible name is worse than one named in
 * the wrong language: a screen reader announces "region" and the listener learns
 * nothing, whereas an English word in a translated region list is merely wrong. So
 * this is the floor, not the target — `overlayRootLabel()` below prefers the
 * translated name whenever the page carries one.
 */
export const DEFAULT_OVERLAY_ROOT_LABEL = 'Overlays';

/**
 * The attribute the `@wirekitScripts` directive writes the translated name into.
 *
 * It rides the bundle's own `<script>` tag rather than an extra inline script,
 * because an inline script is a second thing a strict Content-Security-Policy has
 * to nonce and a second thing that can be blocked. The tag is already there and
 * already carries the nonce.
 */
export const OVERLAY_ROOT_LABEL_ATTRIBUTE = 'data-wk-overlay-label';

/**
 * The accessible name for the landmark, translated when the server said so.
 *
 * Read from the document on every call rather than cached: `wire:navigate` swaps
 * the body, and the arriving page carries its own script tag with its own label.
 * A value captured once at boot would pin the first page's language onto every
 * page after it, which is the same defect one level down.
 */
export function overlayRootLabel() {
    // A document that cannot be queried has no carrier to find, which is a reason to
    // use the default name rather than to throw. That sounds like belt-and-braces and
    // is not: this function runs on the way to building the landmark, so an exception
    // here costs the container — and a missing container is what `x-teleport` treats
    // as fatal. A translation is worth exactly a translation.
    if (typeof document.querySelector !== 'function') {
        return DEFAULT_OVERLAY_ROOT_LABEL;
    }

    const carrier = document.querySelector(`script[${OVERLAY_ROOT_LABEL_ATTRIBUTE}]`);
    const label = carrier?.getAttribute(OVERLAY_ROOT_LABEL_ATTRIBUTE)?.trim();

    return label || DEFAULT_OVERLAY_ROOT_LABEL;
}

/**
 * Return the overlay root, creating it on first use.
 *
 * Idempotent: repeated calls return the same element, and an element the developer put
 * there themselves is adopted rather than duplicated.
 *
 * Returns `null` when there is no `<body>` to append to. That case is not reachable
 * through `installOverlayRoot()`, which checks first — but the check belongs here as
 * well, because the caller is not the only way in and the cost of being wrong is not
 * a missing overlay. An uncaught throw ends the evaluation of the whole bundle, and a
 * page whose script died is fully rendered and completely dead: every control visible,
 * nothing bound, no error a reader would ever see. A container that could not be built
 * should cost one overlay, not the page.
 */
export function overlayRoot() {
    let root = document.getElementById(OVERLAY_ROOT_ID);

    if (root) {
        // An adopted container is finished the same way a created one is, and this
        // is not symmetry for its own sake. Writing the container yourself is the
        // MORE robust route, not a workaround: it sits in the markup Livewire
        // morphs, so it is never briefly absent and no navigation timing has to be
        // right. The reader who takes that route was getting a region a screen
        // reader announces as "region" and nothing else — precisely the state the
        // localization work was done to remove, reaching precisely the readers it
        // was meant to reach.
        //
        // Only what is MISSING is filled in. A developer who wrote their own label
        // has translated it in their own catalog, and overwriting it would take
        // their translation away in the name of localization. `aria-labelledby`
        // counts as a label too — a container pointed at a heading is named, and
        // adding `aria-label` next to it would win over the reference and quietly
        // replace the name they chose.
        if (! root.getAttribute('role')) {
            root.setAttribute('role', 'region');
        }

        if (! root.getAttribute('aria-label') && ! root.getAttribute('aria-labelledby')) {
            root.setAttribute('aria-label', overlayRootLabel());
        }

        // The check belongs on BOTH paths. An application that ships the root in its
        // own layout markup — a perfectly reasonable thing to do — would otherwise
        // never reach it, and that is precisely the kind of setup most likely to have
        // the stylesheet wrong too.
        warnIfStylesheetMissing(root);

        return root;
    }

    if (! document.body) {
        return null;
    }

    root = document.createElement('div');
    root.id = OVERLAY_ROOT_ID;
    root.setAttribute('role', 'region');

    // Named, because an unlabeled region is its own axe finding — and a landmark a screen
    // reader announces without saying what it is helps nobody.
    root.setAttribute('aria-label', overlayRootLabel());

    document.body.appendChild(root);

    warnIfStylesheetMissing(root);

    return root;
}

/** Set once, so a page with twenty dialogs says this once rather than twenty times. */
let stylesheetWarningShown = false;

/**
 * Say so when this package's stylesheet is not loaded.
 *
 * Every overlay here positions itself with `position: fixed` — from the shipped
 * stylesheet, and from Tailwind utilities on the same elements. When NEITHER is
 * present the overlay still renders: the markup is correct, the text is there, the
 * buttons are visible and enabled. It simply sits in normal document flow at the end
 * of the page, and whether any of it lands inside the viewport depends on how long
 * the page happens to be.
 *
 * Nothing about that failure announces itself. Nothing throws, nothing logs, no test
 * of the markup can see it, and on a short page it does not even reproduce. A
 * consuming application chased it across three releases and reasonably concluded the
 * component library was broken — the trigger they had isolated was a taller form
 * control, which pushed their page past one screen and the dialog out of sight.
 *
 * One console line at the moment the first overlay is created would have ended that
 * search on day one. That is all this does.
 *
 * The probe is the geometry itself rather than a marker class, because the geometry
 * is the thing that has to work: a stylesheet that loaded but was overridden fails
 * here too, and should.
 */
function warnIfStylesheetMissing(root) {
    if (stylesheetWarningShown || typeof getComputedStyle !== 'function') {
        return;
    }

    const probe = document.createElement('div');
    probe.className = 'wk-overlay-fixed';
    probe.setAttribute('aria-hidden', 'true');

    // Inside the root, so a scoped stylesheet is measured the way a real overlay is.
    root.appendChild(probe);

    const positioned = getComputedStyle(probe).position === 'fixed';

    probe.remove();

    if (positioned) {
        return;
    }

    stylesheetWarningShown = true;

    console.error(
        '[WireKit] Overlay styles are missing, so dialogs, drawers and the command '
        + 'palette will render in normal page flow instead of over the page. They will '
        + 'look correct and be unusable — on a long page the panel can sit below the '
        + 'fold entirely.\n'
        + 'Load the package stylesheet with the @wirekitStyles directive, or import '
        + "'wirekit/dist/wirekit.css'. If you build Tailwind yourself, also point it at "
        + "this package: @source '../../vendor/pushery/wirekit/resources/views';\n"
        + 'Check the whole install with: php artisan wirekit:verify'
    );
}

/** Set once the navigation listeners are bound, so a second install does not double them. */
let navigationListenersBound = false;

/**
 * Rebuild the container across a `wire:navigate`, in the one window that is early enough.
 *
 * A navigation replaces the whole `<body>`. The container was appended to the old one and
 * leaves with it, so Alpine then walks a document in which every `x-teleport="#wk-overlay-root"`
 * points at nothing — and `x-teleport` treats that as fatal. The walk ends at the first
 * overlay it meets and nothing after it is ever initialized. Measured on a two-page fixture:
 * one uncaught `TypeError: Cannot read properties of null (reading 'appendChild')`, the
 * arriving page rendered in full, and an Alpine counter that no longer counts.
 *
 * THE OBVIOUS HOOK IS THE WRONG ONE, and this is the whole reason the comment is long.
 * `livewire:navigated` forwards Alpine's `alpine:navigated`, and in the navigate source the
 * order is:
 *
 *     swapCallbacks.forEach((callback) => callback());   // <- onSwap, after the body is in
 *     ...
 *     nowInitializeAlpineOnTheNewPage(Alpine);           // <- the walk that throws
 *     fireEventForOtherLibrariesToHookInto('alpine:navigated');
 *
 * Rebuilding on `alpine:navigated` restores the container for the NEXT overlay and leaves
 * the error that already happened exactly where it was. That is not a theory about what
 * would happen — it is what the reporting application saw: the container was back afterwards
 * and the page was dead anyway.
 *
 * So the container is rebuilt from `alpine:navigating`'s `onSwap`, which runs after the new
 * body is in place and before the walk. `alpine:navigated` is kept as a second net for a
 * navigate implementation that does not offer `onSwap` — it cannot repair a walk that
 * already ended, but it does restore the container for everything that comes after, and a
 * partially covered page beats an uncovered one.
 */
function bindNavigationListeners() {
    if (navigationListenersBound) {
        return;
    }

    navigationListenersBound = true;

    document.addEventListener('alpine:navigating', (event) => {
        const onSwap = event?.detail?.onSwap;

        if (typeof onSwap === 'function') {
            onSwap(() => overlayRoot());
        }
    });

    document.addEventListener('alpine:navigated', () => overlayRoot());
}

/**
 * Ensure the root exists before Alpine processes any `x-teleport`.
 *
 * `alpine:init` fires before the DOM walk, so a panel that teleports during that walk finds
 * the container already there. Without this the FIRST overlay on a page would teleport into
 * a selector that does not resolve yet — and `x-teleport` treats that as fatal.
 */
export function installOverlayRoot() {
    if (typeof document === 'undefined') {
        return;
    }

    // Bound before the early return below, so a bundle that loads before `<body>` exists
    // still survives a later navigation. The two concerns are independent: one is about
    // this page's first paint, the other about every page after it.
    bindNavigationListeners();

    if (document.body) {
        overlayRoot();

        return;
    }

    document.addEventListener('DOMContentLoaded', () => overlayRoot(), { once: true });
}
