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

/** What counts as something focus can land on — the selector alert-dialog uses, for the same reason. */
export const FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * The first control in the panel that is not the scroll wrapper.
 *
 * Visible and enabled only: a header's close button is hidden with `x-show` on a non-dismissible
 * overlay, and handing focus-trap a node it cannot focus makes it throw.
 *
 * @param {Element|null|undefined} panelEl
 * @param {string} wrapperAttribute  the attribute marking the scroll wrapper, e.g. `data-wk-modal-body`
 * @returns {Element|undefined}
 */
export function firstControl(panelEl, wrapperAttribute) {
    if (! panelEl) return undefined;

    const control = [...panelEl.querySelectorAll(FOCUSABLE)].find((el) =>
        ! el.hasAttribute(wrapperAttribute)
        && ! el.disabled
        && el.getClientRects().length > 0,
    );

    return control ?? undefined;
}

export default firstControl;
