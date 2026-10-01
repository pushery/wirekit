/**
 * Dismissible — the shared "reader closed this and it stays closed" behavior.
 *
 * Three components had their own copy: alert, badge and announcement-banner.
 * Two of them held only `{ shown: true }` and did the actual work in a handler
 * — `shown = false; $dispatch(…)` — which is two statements, and Alpine's CSP
 * build parses one expression. So the dismiss button did nothing at all under a
 * strict Content-Security-Policy: the badge stayed on screen, and nothing said
 * why.
 *
 * The three copies also did not agree. announcement-banner persisted the
 * dismissal to localStorage; the other two forgot it on reload. That difference
 * is legitimate — a promotional banner should stay gone, an inline alert
 * usually should not — so it stays a per-component decision, expressed by
 * whether a persist key is passed rather than by which copy of the code the
 * component happens to carry.
 */
import { FOCUSABLE } from '../utils/first-control.js';

// Node.DOCUMENT_POSITION_PRECEDING and _FOLLOWING, spelled out so the module reads the same
// where no `Node` global exists.
const PRECEDING = 2;
const FOLLOWING = 4;

/**
 * Where the focus goes when the element being dismissed held it: the next control after it in
 * document order, or the last one before it when nothing follows. Without this the close button
 * takes the focus with it as it hides, and the browser drops it to the page, so the next Tab
 * starts somewhere the reader never was (WCAG 2.4.3). In a row of dismissible badges the next
 * one's close button is what follows, as a removed chip hands over to its neighbor in tags-input.
 *
 * Skipped: anything inside the element itself, a disabled or invisible control, one outside the
 * tab order, and one under `inert` (a page behind an open modal).
 *
 * @param {Element} root
 * @returns {Element|null}
 */
function focusTargetAround(root) {
    if (typeof document === 'undefined' || ! root || typeof root.compareDocumentPosition !== 'function') {
        return null;
    }

    const candidates = [...document.querySelectorAll(FOCUSABLE)].filter((el) => ! root.contains(el)
        && ! el.disabled
        && el.tabIndex >= 0
        && el.getClientRects().length > 0
        && ! el.closest('[inert]'));

    const after = candidates.find((el) => root.compareDocumentPosition(el) & FOLLOWING);

    return after ?? candidates.filter((el) => root.compareDocumentPosition(el) & PRECEDING).pop() ?? null;
}

/**
 * @param {Object} config
 * @param {string} [config.persistKey]  localStorage key; absent = session-only
 * @param {string} [config.event]       DOM event dispatched on dismiss
 */
export default function wirekitDismissible(config = {}) {
    return {
        shown: true,
        _persistKey: config.persistKey || null,
        _event: config.event || null,

        init() {
            // Assign explicitly rather than relying on the initial value: init()
            // runs again on a docs replay re-mount, and a component that only
            // defaulted would come back already dismissed.
            if (! this._persistKey) {
                this.shown = true;

                return;
            }

            // Storage can throw — private mode, a full quota, a blocked origin.
            // A component that cannot READ its preference should appear, not
            // vanish.
            try {
                this.shown = window.localStorage.getItem(this._persistKey) !== '1';
            } catch {
                this.shown = true;
            }
        },

        dismiss() {
            // Resolved before the element hides, while the focus is still where the reader put it.
            // A dismissal the focus was not inside (a click in an engine that does not focus a
            // clicked button, or a script) leaves the focus alone.
            const root = this.$root;
            const target = root && typeof document !== 'undefined' && typeof root.contains === 'function' && root.contains(document.activeElement)
                ? focusTargetAround(root)
                : null;

            this.shown = false;

            if (this._persistKey) {
                // A failed WRITE is not worth surfacing: the reader dismissed it,
                // that worked, and it will simply return next visit.
                try {
                    window.localStorage.setItem(this._persistKey, '1');
                } catch {
                    // Intentionally silent.
                }
            }

            if (this._event) {
                this.$root.dispatchEvent(new CustomEvent(this._event, { bubbles: true }));
            }
            if (target) {
                target.focus();
            }
        },
    };
}
