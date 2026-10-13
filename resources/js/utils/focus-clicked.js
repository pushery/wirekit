/**
 * Gives a clicked control the focus where the engine did not.
 *
 * WebKit does not focus a button it clicks, a convention of its platform, so the focus stays where
 * it was or falls to the page. A component that reads its keys from the focused control, or from a
 * container the focus has to stand in, then hears nothing after a click: the arrows of an
 * accordion, a carousel, a branch switcher or a navigation menu do nothing until Tab brings the
 * focus back. Called from a click handler with the clicked element. Focus a script moves after a
 * pointer press does not match `:focus-visible`, so no ring appears until a key is pressed.
 *
 * @param {HTMLElement|null|undefined} el
 */
export function focusClicked(el) {
    if (! el || typeof el.focus !== 'function' || el.ownerDocument?.activeElement === el) {
        return;
    }

    el.focus({ preventScroll: true });
}
