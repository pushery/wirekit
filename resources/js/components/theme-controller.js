/**
 * WireKit Theme Controller Alpine Component.
 *
 * Owns the one thing every WireKit app currently hand-rolls: switching the
 * `.dark` class, remembering the choice, and staying out of the way when the
 * reader has no opinion.
 *
 * Three states, not two. "system" is a real answer — it means "look like the
 * rest of my machine", and it keeps following the OS when the reader changes it
 * at sunset. A two-state toggle cannot express that, and silently freezing
 * someone onto light because they once tapped a button is worse than not having
 * the button.
 */
export default function wirekitThemeController(config = {}) {
    return {
        // 'system' | 'light' | 'dark'
        theme: 'system',
        storageKey: config.storageKey || 'wirekit-theme',
        _media: null,
        _onSystemChange: null,
        _onPeerChange: null,

        init() {
            this.theme = this._read() ?? 'system';
            this._apply();

            // Follow the OTHER controls on the page. Each one is its own Alpine
            // scope with its own `theme`, so without this a header toggle and a
            // settings switch would drift apart the moment either was used — the
            // page would be dark while the switch still said light. Reading the
            // document class instead would not help: `theme` distinguishes
            // "explicitly light" from "system, which happens to be light", and the
            // class cannot say which.
            if (typeof window !== 'undefined') {
                this._onPeerChange = (event) => {
                    const theme = event.detail?.theme;
                    if (theme && theme !== this.theme) {
                        this.theme = theme;
                        this._apply();
                    }
                };

                window.addEventListener('wirekit:theme-changed', this._onPeerChange);
            }

            // Follow the OS while the reader has no explicit preference. Without
            // this, "system" would only mean "whatever the OS said when the page
            // loaded" and the page would stay light through the reader's sunset.
            if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
                this._media = window.matchMedia('(prefers-color-scheme: dark)');
                this._onSystemChange = () => {
                    if (this.theme === 'system') this._apply();
                };

                // addEventListener over the deprecated addListener: Safari has
                // supported it since 14, which is below our baseline.
                this._media.addEventListener('change', this._onSystemChange);
            }
        },

        destroy() {
            // Remove the listener explicitly. A MediaQueryList outlives the
            // element, so a left-behind listener keeps firing into a torn-down
            // Alpine scope for as long as the page lives.
            if (this._media && this._onSystemChange) {
                this._media.removeEventListener('change', this._onSystemChange);
            }

            if (this._onPeerChange) {
                window.removeEventListener('wirekit:theme-changed', this._onPeerChange);
            }

            this._media = null;
            this._onSystemChange = null;
            this._onPeerChange = null;
        },

        /** Is the page dark right now, whatever the reason? */
        get isDark() {
            return this.theme === 'dark'
                || (this.theme === 'system' && this._systemPrefersDark());
        },

        /**
         * Flip between light and dark. From 'system' this picks the OPPOSITE of
         * what the reader is currently looking at — pressing a toggle should
         * always change something, and choosing an explicit value is exactly what
         * the reader just asked for.
         */
        toggle() {
            this.select(this.isDark ? 'light' : 'dark');
        },

        select(theme) {
            if (!['system', 'light', 'dark'].includes(theme)) return;

            this.theme = theme;
            this._write(theme);
            this._apply();

            // Tell the rest of the page — the other controls, and any app code
            // with its own colors (a chart, a map, a third-party embed). Being
            // told beats polling a class for a change that may never come.
            //
            // Dispatched on window, not on $el: a sibling control is not an
            // ancestor, so an event that only bubbles up this element's tree
            // never reaches it.
            window.dispatchEvent(new CustomEvent('wirekit:theme-changed', {
                detail: { theme, dark: this.isDark },
            }));
        },

        _systemPrefersDark() {
            return typeof window !== 'undefined'
                && typeof window.matchMedia === 'function'
                && window.matchMedia('(prefers-color-scheme: dark)').matches;
        },

        _apply() {
            document.documentElement.classList.toggle('dark', this.isDark);
        },

        _read() {
            // Storage throws in private mode and when disabled entirely. Falling
            // back to 'system' is the right answer — the page follows the OS,
            // which is what someone with no stored preference wanted anyway.
            try {
                const value = localStorage.getItem(this.storageKey);

                return ['system', 'light', 'dark'].includes(value) ? value : null;
            } catch (e) {
                return null;
            }
        },

        _write(theme) {
            try {
                // 'system' is stored as the ABSENCE of a choice, so it agrees with
                // the head script — which reads "no key" as "follow the OS". A
                // literal "system" string there would fall through to the OS check
                // anyway, but removing it keeps one meaning in one place.
                if (theme === 'system') {
                    localStorage.removeItem(this.storageKey);
                } else {
                    localStorage.setItem(this.storageKey, theme);
                }
            } catch (e) {
                // Nothing to do: the choice applies to this page, it just will not
                // survive a reload. Better than throwing on a click.
            }
        },
    };
}
