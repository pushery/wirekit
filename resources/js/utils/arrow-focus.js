/**
 * The page carries `data-wk-arrow-focus` on its root element while the focus stands on a radio the
 * arrow keys moved it to.
 *
 * In a group of native radios the arrow keys move the focus and the choice together. Blink and
 * Gecko count that focus as `:focus-visible`. WebKit counts the focus Tab brings and not this one,
 * so a ring drawn only for `:focus-visible` goes away at the first arrow key in Safari and in every
 * browser on iOS, while the focus walks on. `radio` and `swatch-group` also draw their ring for a
 * focused radio on a page that carries the attribute, which keeps the ring with the keyboard in all
 * three engines.
 *
 * The attribute sits on the root element, not on the radio. A render that patches the radio from
 * the server, as the round trip of a `wire:model.live` binding does, removes an attribute it did not
 * write, and the ring would go with it after every arrow key; nothing patches the root element.
 *
 * Installed once per page. An arrow key on a radio arms it, and the radio that takes the focus while
 * the key's default action runs sets it. A focus that comes back to that same radio, as when the
 * window regains the focus, keeps it; any other focus and any pointer press take it away.
 */
export const WK_ARROW_FOCUS_ATTRIBUTE = 'data-wk-arrow-focus';

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a
 * second evaluation of this one, shares with the first. It holds the handle that takes the
 * installation down again.
 */
const INSTALLED_FLAG = '__wirekitArrowFocus';

const ARROW_KEYS = new Set(['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight']);

/**
 * Whether a node is a native radio.
 *
 * @param {EventTarget|null} node
 * @returns {boolean}
 */
export function isNativeRadio(node) {
    return node !== null
        && typeof node === 'object'
        && node.localName === 'input'
        && node.type === 'radio';
}

/**
 * Install the page-wide listeners. Idempotent.
 *
 * @param {Document} [doc]
 */
export function installArrowFocus(doc = typeof document !== 'undefined' ? document : undefined) {
    if (typeof window === 'undefined' || ! doc || ! doc.documentElement || window[INSTALLED_FLAG]) {
        return;
    }

    const root = doc.documentElement;
    let armed = false;
    let reached = null;

    const clear = () => {
        if (root.hasAttribute(WK_ARROW_FOCUS_ATTRIBUTE)) {
            root.removeAttribute(WK_ARROW_FOCUS_ATTRIBUTE);
        }
    };

    const onKeydown = (event) => {
        if (! ARROW_KEYS.has(event.key) || event.altKey || event.ctrlKey || event.metaKey || ! isNativeRadio(event.target)) {
            return;
        }

        armed = true;
        // The default action moves the focus before the next task runs. A key whose default a
        // handler prevented moves nothing, and a later focus must not take a mark it did not earn.
        setTimeout(() => {
            armed = false;
        }, 0);
    };

    const onFocusin = (event) => {
        if (armed && isNativeRadio(event.target)) {
            reached = event.target;
            root.setAttribute(WK_ARROW_FOCUS_ATTRIBUTE, '');

            return;
        }

        if (event.target !== reached) {
            clear();
        }
    };

    const onPointerdown = () => {
        armed = false;
        clear();
    };

    // Capture, so a handler that stops the event on its way down cannot keep the mark from
    // following the focus.
    doc.addEventListener('keydown', onKeydown, true);
    doc.addEventListener('focusin', onFocusin, true);
    doc.addEventListener('pointerdown', onPointerdown, true);

    window[INSTALLED_FLAG] = {
        uninstall() {
            doc.removeEventListener('keydown', onKeydown, true);
            doc.removeEventListener('focusin', onFocusin, true);
            doc.removeEventListener('pointerdown', onPointerdown, true);
            clear();
            delete window[INSTALLED_FLAG];
        },
    };
}

/**
 * Take the listeners down again and clear the mark. A page keeps them for its whole life; this is
 * for a host that removes WireKit from a page that goes on running.
 */
export function uninstallArrowFocus() {
    if (typeof window !== 'undefined' && window[INSTALLED_FLAG] && typeof window[INSTALLED_FLAG].uninstall === 'function') {
        window[INSTALLED_FLAG].uninstall();
    }
}
