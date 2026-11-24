/**
 * The focus after a cart line is taken out: the next control after the line, or the last one
 * before it when nothing follows, as `focusTargetAround()` finds it.
 *
 * A line does not remove itself. Its remove control, its − at the smallest quantity and the yes of
 * its question send `wirekit:cart-remove`, and the application's render takes the line out. The
 * control the reader pressed goes with it, and the browser drops the focus to the page, so the next
 * Tab starts at the top (WCAG 2.4.3).
 *
 * So the removal is followed until the line is out, for up to ten seconds, since a slow response is
 * still one. A render takes a line out by removing its node or by moving another line into that
 * node, which then carries the other line's id, and it may give the next line a new node as well:
 * the control that takes over is then found again at its place in the line that carries its id.
 * The control that takes over is found once the page answers again:
 * the yes of a question arrives while its dialog is still open and the page behind it is inert,
 * and the dialog gives the focus back to the line a task later. The focus moves only when it was
 * in the line on the way, as it is after a press from the keyboard, and when it has fallen to the
 * page by the time the line goes. A reader who moved it on keeps it where they put it, a press in
 * an engine that does not focus a clicked button leaves it alone, and a line the application keeps
 * leaves everything as it was.
 *
 * Installed once per page, one listener on `window` that lives as long as the page.
 */
import { focusTargetAround } from './focus-around.js';
import { controlIsDisabled } from './fieldset-disabled.js';
import { FOCUSABLE } from './first-control.js';
import { timerDelay } from './timer-delay.js';

/**
 * The installation is remembered on `window`, the one scope a second bundle on the page, or a second
 * evaluation of this one, shares with the first.
 */
const INSTALLED_FLAG = '__wirekitCartRemovalFocus';

/** The line of a cart, as cart-item renders it. */
export const CART_LINE = '[data-wk-cart-item]';

/** How often the removal is looked at, in milliseconds. */
const LOOK_EVERY_MS = 50;

/** How many looks it gets: ten seconds' worth. */
const LOOKS = 200;

/**
 * The controls of a line a reader can reach from the keyboard, in document order, as
 * `focusTargetAround()` counts them.
 *
 * @param {Element} line
 * @returns {Element[]}
 */
function reachable(line) {
    if (typeof line.querySelectorAll !== 'function') {
        return [];
    }

    return [...line.querySelectorAll(FOCUSABLE)].filter((el) => ! controlIsDisabled(el)
        && el.tabIndex >= 0
        && el.getClientRects().length > 0
        && ! el.closest('[inert]'));
}

/**
 * Follow the removal of one line, and hand the focus on once the line has left the document.
 *
 * @param {Element} line
 * @param {{ schedule: (callback: () => void, ms: number) => void, around?: (line: Element) => Element|null }} options
 */
export function followRemoval(line, { schedule, around = focusTargetAround }) {
    const doc = line.ownerDocument;
    const byId = (id) => (id && typeof doc.getElementById === 'function' ? doc.getElementById(id) : null);
    const lineId = typeof line.id === 'string' ? line.id : '';
    let successor = null;
    // The line the successor stands in, by its id, and the successor's place among its controls:
    // a render may give that line a new node, or move another line into the old one, and the same
    // control is then found again where the id now stands.
    let successorAt = null;
    let resolved = false;
    let held = false;

    // While the line stands and the page answers: the control that takes over is found once.
    const notice = () => {
        if (line.contains(doc.activeElement)) {
            held = true;
        }

        if (! resolved && line.isConnected && line.closest('[inert]') === null) {
            successor = around(line);
            const successorLine = successor && typeof successor.closest === 'function' ? successor.closest(CART_LINE) : null;
            successorAt = successorLine && successorLine.id ? { id: successorLine.id, index: reachable(successorLine).indexOf(successor) } : null;
            resolved = true;
        }
    };

    // The line is out once its node left the document, or once a render moved another line into
    // that node: then the node is connected, and its id is another's.
    const gone = () => ! line.isConnected || (lineId !== '' && byId(lineId) !== line);

    // The control that takes over, as it stands after the render: the one found before it while it
    // still stands in its line, else the control at the same place in the line that carries the id
    // now. A line whose id no element carries any more, as when the `-2` of a second cart's line
    // goes once the first cart's line of that name is out, keeps the control found before.
    const taker = () => {
        const connected = successor && successor.isConnected ? successor : null;
        const standsIn = connected && typeof connected.closest === 'function' ? connected.closest(CART_LINE) : null;

        if (connected && (! successorAt || (standsIn && standsIn.id === successorAt.id))) {
            return connected;
        }

        const fresh = successorAt ? byId(successorAt.id) : null;
        const controls = fresh ? reachable(fresh) : [];

        return controls[successorAt?.index] ?? controls[0] ?? connected;
    };

    // The dialog of a question gives the focus back to the line between two looks, and a quick
    // response takes the line out before the next one.
    const onFocusIn = (event) => {
        if (line.contains(event.target)) {
            notice();
        }
    };

    doc.addEventListener('focusin', onFocusIn, true);

    const look = (looksLeft) => {
        if (gone()) {
            doc.removeEventListener('focusin', onFocusIn, true);

            const fell = doc.activeElement === null || doc.activeElement === doc.body;
            const target = held && fell ? taker() : null;

            if (target) {
                target.focus();
            }

            return;
        }

        notice();

        if (looksLeft <= 0) {
            doc.removeEventListener('focusin', onFocusIn, true);

            return;
        }

        schedule(() => look(looksLeft - 1), LOOK_EVERY_MS);
    };

    look(LOOKS);
}

/**
 * Answer `wirekit:cart-remove` on this page.
 *
 * @param {Window} [win]
 * @param {{ schedule?: (callback: () => void, ms: number) => void, around?: (line: Element) => Element|null }} [options]
 *   how the looks are timed and how the control that takes over is found; `setTimeout` on the
 *   window and `focusTargetAround()` unless given
 */
export function installCartRemovalFocus(win = typeof window === 'undefined' ? undefined : window, options = {}) {
    if (! win || win[INSTALLED_FLAG]) {
        return;
    }

    win[INSTALLED_FLAG] = true;

    const schedule = options.schedule ?? ((callback, ms) => win.setTimeout(callback, timerDelay(ms)));
    const around = options.around ?? focusTargetAround;

    win.addEventListener('wirekit:cart-remove', (event) => {
        const origin = event ? event.target : null;
        const line = origin && typeof origin.closest === 'function' ? origin.closest(CART_LINE) : null;

        if (line) {
            followRemoval(line, { schedule, around });
        }
    });
}
