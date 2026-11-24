/**
 * Where an overlay's focus trap should start when its scroll wrapper is itself a tab stop.
 *
 * `modal.body` and `drawer.body` scroll, and a scroll region is keyboard-reachable the house way:
 * an unconditional `tabindex="0"`. focus-trap starts on the first tabbable node, and the body
 * wraps everything inside the overlay — so a dialog or a drawer without a header would open with
 * focus on the wrapper instead of its first field. Both overlays hand focus-trap this instead.
 *
 * When the overlay holds nothing but text, the answer is `undefined`, which leaves focus-trap's
 * own default in place — and that default is then the body itself: the right first stop for a
 * document the reader is about to scroll.
 */
import { controlIsDisabled } from './fieldset-disabled.js';

/** What counts as something focus can land on — the selector alert-dialog uses, for the same reason. */
export const FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * The mark a page sets on the control an overlay opens on: the field a dialog was opened for, ahead
 * of a close button in its header. `autofocus` is not read for it, since a browser acts on that at
 * page load, not when a dialog opens.
 */
export const INITIAL_FOCUS = 'data-wk-initial-focus';

/**
 * Where the overlay's focus starts: the control the page marked for it, else the first control in
 * the panel that is not the scroll wrapper.
 *
 * The dialog pattern lets the focus start on the element the reader is most likely to begin with.
 * A mark on a wrapper, such as the field around an input, names the first control inside it.
 *
 * Visible and enabled only: a header's close button is hidden with `x-show` on a non-dismissible
 * overlay, and handing focus-trap a node it cannot focus makes it throw. A marked control that is
 * hidden or disabled gives way to the first control.
 *
 * @param {Element|null|undefined} panelEl
 * @param {string} wrapperAttribute  the attribute marking the scroll wrapper, e.g. `data-wk-modal-body`
 * @returns {Element|undefined}
 */
export function firstControl(panelEl, wrapperAttribute) {
    if (! panelEl) return undefined;

    const usable = (el) => Boolean(el)
        && ! el.hasAttribute(wrapperAttribute)
        // A control inside a disabled fieldset is disabled without saying so itself.
        && ! controlIsDisabled(el)
        && el.getClientRects().length > 0;

    const named = [...panelEl.querySelectorAll(`[${INITIAL_FOCUS}]`)]
        .map((el) => (el.matches?.(FOCUSABLE) ? el : el.querySelector?.(FOCUSABLE)))
        .find(usable);

    if (named) return named;

    const control = [...panelEl.querySelectorAll(FOCUSABLE)].find(usable);

    return control ?? undefined;
}

export default firstControl;
