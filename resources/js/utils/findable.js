/**
 * `x-wk-findable`: a panel the browser's find in page can open.
 *
 * It takes the place of `x-show` on a disclosure panel. Where the engine supports it, a closed
 * panel carries `hidden="until-found"`: its content stays out of sight, out of the tab order and
 * out of the accessibility tree, but a find-in-page match fires `beforematch` on it and then
 * removes the attribute. The component listens for `beforematch` itself (`x-on:beforematch`) and
 * opens its own state, so the next render does not close the panel again.
 *
 * Where it is not supported, it is `x-show`. Support is decided by `'onbeforematch' in
 * document.body`, and without it a closed panel gets the inline `display: none` that `x-show`
 * sets. Nothing is left to how an older engine parses an attribute value it does not know, so the
 * experience below the enhancement is `x-show`'s by construction rather than by assumption.
 *
 * What the panel must not carry:
 * - padding, a border or a background of its own: a closed until-found element keeps its box, so
 *   those paint as an empty strip. They belong on an element inside it;
 * - `tabindex`: an element that is itself until-found still takes focus, by script and by Tab.
 *   A panel that needs a tab stop binds it to its open state;
 * - `x-cloak`: `display: none !important` would stop the browser from revealing it at all.
 *
 * A panel the server writes as a named region (`role="region"` with `aria-labelledby`) is one only
 * while it is open: closed, the role and its name come off, so the page lists no landmark that
 * holds nothing to read (applyRegionState()).
 *
 * `.collapse` animates the height on open and close, and not under reduced motion. On close the
 * attribute is set when the animation ends, so the content does not vanish mid-slide.
 *
 * A panel the server draws open carries `data-wk-rendered-open` where the component's start can
 * close it: the second of two accordion items asking to start open in single mode, or the panel of
 * the default tab when the server names another one. Without `hidden` it would read like a panel
 * the reader found before the script started (foundBeforeStart()), and opening it again would undo
 * the start.
 *
 * Cleanup contract: the attribute observer is released through Alpine's `cleanup()`, and an
 * animation still running when the element goes is canceled with it.
 */
import { prefersReducedMotion } from './motion.js';

/** Whether this engine reveals `hidden="until-found"` content on a find-in-page match. */
export function supportsUntilFound(doc = typeof document === 'undefined' ? null : document) {
    return Boolean(doc && doc.body && 'onbeforematch' in doc.body);
}

/**
 * Put a panel into its open or closed state, writing only what differs, so a call that changes
 * nothing touches nothing and an observer reacting to it does not loop.
 */
export function applyFindableState(el, open, supported) {
    if (supported) {
        if (el.style.display === 'none') {
            el.style.removeProperty('display');
        }

        if (open) {
            if (el.hasAttribute('hidden')) {
                el.removeAttribute('hidden');
            }
        } else if (el.getAttribute('hidden') !== 'until-found') {
            el.setAttribute('hidden', 'until-found');
        }

        return;
    }

    // Below support the server-rendered attribute comes off and x-show's own mechanism takes over.
    if (el.hasAttribute('hidden')) {
        el.removeAttribute('hidden');
    }

    if (open) {
        if (el.style.display === 'none') {
            el.style.removeProperty('display');
        }
    } else if (el.style.display !== 'none') {
        el.style.setProperty('display', 'none');
    }
}

/**
 * A closed panel is no landmark. A panel the server wrote as a named region carries the role and
 * its name only while it is open: a closed until-found panel keeps its box, and an engine that
 * counts it among the page's landmarks lists a region with nothing in it, under its trigger's
 * name, beside any other region of that name. Writes only what differs, as applyFindableState().
 */
export function applyRegionState(el, open, labelledby) {
    if (open) {
        if (el.getAttribute('role') !== 'region') {
            el.setAttribute('role', 'region');
        }

        if (labelledby !== null && el.getAttribute('aria-labelledby') !== labelledby) {
            el.setAttribute('aria-labelledby', labelledby);
        }

        return;
    }

    if (el.hasAttribute('role')) {
        el.removeAttribute('role');
    }

    if (el.hasAttribute('aria-labelledby')) {
        el.removeAttribute('aria-labelledby');
    }
}

/** Whether a panel is showing: neither the attribute nor `x-show`'s inline `display: none` hides it. */
export function isShown(el) {
    return ! el.hasAttribute('hidden') && el.style.display !== 'none';
}

/** The mark of a panel the server drew open, which no find in page has revealed. */
export const RENDERED_OPEN_ATTRIBUTE = 'data-wk-rendered-open';

/**
 * Whether the reader found this panel before the script started: the server drew it closed, and a
 * find-in-page match took the attribute off before anything listened for `beforematch`. A panel
 * the server drew open has no attribute either, and says so with its mark.
 */
export function foundBeforeStart(el, supported) {
    return Boolean(supported) && ! el.hasAttribute('hidden') && ! el.hasAttribute(RENDERED_OPEN_ATTRIBUTE);
}

/** The house transition duration in milliseconds, read from the element's own token. */
function durationOf(el) {
    const raw = typeof getComputedStyle === 'function'
        ? getComputedStyle(el).getPropertyValue('--transition-wk-duration').trim()
        : '';
    const value = parseFloat(raw);

    if (Number.isNaN(value)) {
        return 150;
    }

    return raw.endsWith('ms') ? value : value * 1000;
}

export function registerFindableDirective(Alpine) {
    Alpine.directive('wk-findable', (el, { expression, modifiers }, { evaluateLater, effect, cleanup }) => {
        const supported = supportsUntilFound(el.ownerDocument);
        const read = evaluateLater(expression);
        const collapse = modifiers.includes('collapse');
        let open = null;
        let animation = null;
        // A panel the server wrote as a region, and the name it was given (applyRegionState()).
        const region = el.getAttribute('role') === 'region';
        let labelledby = region ? el.getAttribute('aria-labelledby') : null;

        const place = (state) => {
            applyFindableState(el, state, supported);

            if (region) {
                applyRegionState(el, state, labelledby);
            }
        };

        const settle = () => {
            if (animation === null && open !== null) {
                place(open);
            }
        };

        const slide = (opening) => {
            animation?.cancel();

            // A panel something already showed, the browser on a find-in-page match or a component
            // for a field the browser is about to focus, is not slid open again from nothing: the
            // match or the field would drop out of sight and come back.
            if (opening && isShown(el)) {
                settle();

                return;
            }

            if (opening) {
                place(true);
            }

            const from = opening ? 0 : el.getBoundingClientRect().height;
            const to = opening ? el.scrollHeight : 0;

            if (typeof el.animate !== 'function') {
                settle();

                return;
            }

            el.style.setProperty('overflow', 'hidden');
            animation = el.animate([{ height: `${from}px` }, { height: `${to}px` }], {
                duration: durationOf(el),
                easing: 'ease-in-out',
            });

            const finish = () => {
                animation = null;
                el.style.removeProperty('overflow');
                settle();
            };

            animation.onfinish = finish;
            animation.oncancel = () => {
                animation = null;
                el.style.removeProperty('overflow');
            };
        };

        // A panel the reader found before Alpine started: the server rendered it closed, the
        // browser already took the attribute off, and the state has not heard. The component's
        // own `beforematch` listener is what opens the state, so it gets the event it missed.
        const foundBeforeInit = foundBeforeStart(el, supported);

        effect(() => {
            read((value) => {
                const next = Boolean(value);

                if (next === open) {
                    return;
                }

                const first = open === null;
                open = next;

                if (first && ! next && foundBeforeInit) {
                    open = null;
                    queueMicrotask(() => el.dispatchEvent(new Event('beforematch')));

                    return;
                }

                if (collapse && ! first && ! prefersReducedMotion()) {
                    slide(next);
                } else {
                    settle();
                }
            });
        });

        cleanup(() => animation?.cancel());

        // A Livewire morph writes the server's `hidden` back onto a panel the reader opened, and
        // the server's role and name back onto a region that is closed. Nothing about either change
        // reaches Alpine's reactivity, so the observer puts the state back, with the name the
        // server wrote last.
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            if (region && el.hasAttribute('aria-labelledby')) {
                labelledby = el.getAttribute('aria-labelledby');
            }

            settle();
        });
        observer.observe(el, { attributes: true, attributeFilter: ['hidden', 'style', 'role', 'aria-labelledby'] });
        cleanup(() => observer.disconnect());
    });

    // The panels sit inside an `x-data` of their component, so Alpine reaches them already; the
    // selector is for a panel moved out of that tree by a morph that has not re-initialized it.
    if (typeof Alpine.addInitSelector === 'function') {
        Alpine.addInitSelector(() => '[x-wk-findable]');
    }
}
