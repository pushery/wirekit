/**
 * Sidebar collapse toggle — a button outside the sidebar that folds it.
 *
 * The state lives in the sidebar (`wirekitSidebarRail`). A button outside its tree cannot reach
 * that scope, because Alpine merges scope downwards only, so this one speaks the two window
 * events the sidebar already answers: it sends `wirekit:sidebar:toggle` and follows
 * `wirekit:sidebar:toggled`, which the sidebar fires on every change and once after it starts.
 * What it keeps is a copy for its own `aria-expanded`, name and glyph, never a second source of
 * the state: a click only asks, and the button changes when the sidebar answers.
 *
 * @param {Object}       config
 * @param {string|null}  [config.id]         the `id` of the sidebar to fold; null folds every sidebar
 * @param {boolean}      [config.collapsed]  what to draw before the sidebar has announced itself
 */
export default function wirekitSidebarCollapseToggle(config = {}) {
    return {
        collapsed: config.collapsed === true,
        _target: typeof config.id === 'string' && config.id !== '' ? config.id : null,
        _onToggled: null,

        init() {
            this._onToggled = (event) => {
                const detail = event?.detail ?? {};

                // Addressed, only the named sidebar's announcement counts: on a page with two, the
                // other one's state is not this button's. Unaddressed, it follows whichever sidebar
                // speaks, which on a page with one sidebar is that one.
                if (this._target !== null && detail.id !== this._target) {
                    return;
                }

                if (typeof detail.collapsed === 'boolean') {
                    this.collapsed = detail.collapsed;
                }
            };

            window.addEventListener('wirekit:sidebar:toggled', this._onToggled);
        },

        destroy() {
            // A Livewire navigation replaces the button; a listener left behind would keep writing
            // into the scope of one that is no longer in the document.
            if (this._onToggled) {
                window.removeEventListener('wirekit:sidebar:toggled', this._onToggled);
                this._onToggled = null;
            }
        },

        toggle() {
            // No id in the detail addresses every sidebar on the page, which is how the sidebar
            // itself reads an event without one.
            window.dispatchEvent(new CustomEvent('wirekit:sidebar:toggle', {
                detail: this._target !== null ? { id: this._target } : {},
            }));
        },
    };
}
