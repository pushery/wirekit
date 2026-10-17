/**
 * Put the popup ARIA on the trigger's interactive CHILD, not on the wrapper.
 *
 * `aria-haspopup` and `aria-expanded` have to sit on an element with an
 * interactive role — on a generic `<div>` they fail axe-core's
 * aria-allowed-attr rule — and the wrapper both popover and hover-card render
 * is a generic element. So the attributes are applied to the first focusable
 * descendant, and kept in step with `isOpen`.
 *
 * Both components did this with an inline IIFE in `x-init`. That shape cannot
 * be parsed by Alpine's CSP build (an arrow function, a `const`, a `return`, and
 * several statements — none of them in its grammar), so both were unavailable
 * under a strict Content-Security-Policy. Written once here, it is also the same
 * behavior in both places rather than two copies that can drift.
 *
 * The dropdown trigger was a THIRD copy, and it had already drifted: its popup
 * is a menu rather than a dialog, it points at the panel with `aria-controls`,
 * and it names an icon-only trigger from a fallback label. Those are real
 * differences, so they became options here rather than a reason to keep a
 * separate implementation.
 *
 * @param {Element}  el       the wrapper whose descendant carries the ARIA
 * @param {Function} watch    Alpine's $watch, bound to the component scope
 * @param {Object}   options
 * @param {string}   [options.missingTriggerWarning]  logged when no focusable
 *        child exists. Only components where that is a genuine dead end pass it:
 *        a popover opens on click, so no focusable child means keyboard users
 *        cannot open it at all. A hover-card also opens on hover, so the same
 *        markup is merely suboptimal there and stays quiet.
 * @param {string}   [options.haspopup]  the popup's role — 'dialog' by default,
 *        'menu' for a dropdown. It describes what OPENS, so it is not cosmetic:
 *        a screen reader announces the wrong kind of thing when it is wrong.
 * @param {?string}  [options.controls]  id of the panel the trigger opens, set
 *        as `aria-controls`. Omitted when there is no id to point at — an
 *        aria-controls naming nothing is worse than none.
 * @param {?string}  [options.labelFallback]  accessible name to apply ONLY when
 *        the trigger has none of its own. An icon-only trigger is otherwise
 *        announced as an unnamed button.
 * @returns {Function} stops putting the attributes back; the caller keeps it under a `_` name and
 *        calls it from `destroy()`
 */
export function applyTriggerAria(el, watch, options = {}) {
    const interactive = el ? el.querySelector('button, [role=button], a') : null;

    if (! interactive) {
        if (options.missingTriggerWarning) {
             
            console.warn(options.missingTriggerWarning);
        }

        return () => {};
    }

    // Only when the trigger has NO name of its own. `textContent` is the right
    // test rather than visible text: it includes sr-only nodes, which are
    // exactly how a well-built icon-only control is usually named.
    const unnamed = () => ! interactive.hasAttribute('aria-labelledby') && (interactive.textContent || '').trim().length === 0;
    const needsLabel = Boolean(options.labelFallback) && ! interactive.hasAttribute('aria-label') && unnamed();
    let expanded = 'false';

    // What the trigger carries, written again whenever something took it off.
    const wanted = () => {
        const attributes = { 'aria-haspopup': options.haspopup || 'dialog', 'aria-expanded': expanded };

        if (options.controls) {
            attributes['aria-controls'] = options.controls;
        }

        if (needsLabel && unnamed()) {
            attributes['aria-label'] = options.labelFallback;
        }

        return attributes;
    };

    const apply = () => {
        for (const [name, value] of Object.entries(wanted())) {
            if (interactive.getAttribute(name) !== value) {
                interactive.setAttribute(name, value);
            }
        }
    };

    apply();

    if (typeof watch === 'function') {
        watch('isOpen', (value) => {
            expanded = value ? 'true' : 'false';
            apply();
        });
    }

    // The trigger is the caller's markup, so no binding can hold these: a Livewire morph compares
    // the page with a clone of the new markup that Alpine builds without this script, and takes
    // off every attribute the clone lacks. The observer puts them back in the same task.
    if (typeof MutationObserver === 'undefined') {
        return () => {};
    }

    const observer = new MutationObserver(apply);
    observer.observe(interactive, { attributes: true, attributeFilter: ['aria-haspopup', 'aria-expanded', 'aria-controls', 'aria-label'] });

    return () => observer.disconnect();
}

export default applyTriggerAria;
