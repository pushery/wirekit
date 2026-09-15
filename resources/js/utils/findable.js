/**
 * `x-wk-findable`: a panel the browser's find in page can open.
 *
 * It takes the place of `x-show` on a disclosure panel. Where the engine supports it, a closed
 * panel carries `hidden="until-found"`: its content stays out of sight, out of the tab order and
 * out of the accessibility tree, but a find-in-page match fires `beforematch` on it and then
 * removes the attribute. The component listens for `beforematch` itself (`x-on:beforematch`) and
 * opens its own state, so the next render does not close the panel again.
 *
 * WHERE IT IS NOT SUPPORTED, IT IS `x-show`. Support is decided by `'onbeforematch' in
 * document.body`, and without it a closed panel gets the inline `display: none` that `x-show`
 * sets. Nothing is left to how an older engine parses an attribute value it does not know, so the
 * experience below the enhancement is today's by construction rather than by assumption.
 *
 * ⚠️ WHAT THE PANEL MUST NOT CARRY, measured in Chromium 151 and WebKit 26.5:
 * - padding, a border or a background of its own: a closed until-found element keeps its box, so
 *   those paint as an empty strip (26 px for a panel with padding and a border). They belong on
 *   an element inside it;
 * - `tabindex`: an element that is itself until-found still takes focus, by script and by Tab.
 *   A panel that needs a tab stop binds it to its open state;
 * - `x-cloak`: `display: none !important` would stop the browser from revealing it at all.
 *
 * `.collapse` animates the height on open and close, and not under reduced motion. On close the
 * attribute is set when the animation ends, so the content does not vanish mid-slide.
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

        const settle = () => {
            if (animation === null && open !== null) {
                applyFindableState(el, open, supported);
            }
        };

        const slide = (opening) => {
            animation?.cancel();

            if (opening) {
                applyFindableState(el, true, supported);
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
        const foundBeforeInit = supported && ! el.hasAttribute('hidden');

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
        // nothing about that change reaches Alpine's reactivity. The observer puts the state back.
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => settle());
        observer.observe(el, { attributes: true, attributeFilter: ['hidden', 'style'] });
        cleanup(() => observer.disconnect());
    });

    // The panels sit inside an `x-data` of their component, so Alpine reaches them already; the
    // selector is for a panel moved out of that tree by a morph that has not re-initialized it.
    if (typeof Alpine.addInitSelector === 'function') {
        Alpine.addInitSelector(() => '[x-wk-findable]');
    }
}
