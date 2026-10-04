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
 *
 * A page can fix the mode itself, with `data-wk-theme-fixed="light|dark"` on the
 * root element and a sentence for why in `data-wk-theme-fixed-reason`. While it
 * does, the page is in that mode, the control shows it as the one on and changes
 * nothing when used, and a screen reader hears the reason. The reader's own
 * choice stays stored and comes back the moment the attribute goes.
 *
 * Lifecycle resources held on `this`:
 *   - _media + _onSystemChange (MediaQueryList listener) — removed in destroy().
 *   - _onPeerChange (window listener) — removed in destroy().
 *   - _fixedObserver (MutationObserver on the root element's two attributes) —
 *     disconnected in destroy() AND null-guarded inside its callback.
 */
export default function wirekitThemeController(config = {}) {
    return {
        // 'system' | 'light' | 'dark'
        theme: 'system',
        storageKey: config.storageKey || 'wirekit-theme',
        // 'local' (localStorage, client-only) | 'cookie' (server-readable). The
        // state machine below is identical for both — only _read()/_write() differ.
        storage: config.storage === 'cookie' ? 'cookie' : 'local',
        // Only consulted for the 'cookie' driver. { same_site, max_age, path }.
        cookieAttributes: config.cookieAttributes || {},
        // The mode the page fixes, or null, and why. Read from the root element.
        fixed: null,
        fixedReason: '',
        // What a screen reader hears while the page fixes the mode and names no reason.
        defaultFixedReason: config.fixedReason || 'This page sets the mode.',
        // The menu trigger's name is built from these two; see triggerName.
        controlName: typeof config.name === 'string' ? config.name : '',
        optionLabels: config.optionLabels || {},
        _media: null,
        _onSystemChange: null,
        _onPeerChange: null,
        _fixedObserver: null,

        init() {
            this.theme = this._read() ?? 'system';
            this._readFixed();
            this._apply();

            // Follow the page fixing the mode, and letting go of it, while it runs.
            if (typeof MutationObserver === 'function' && typeof document !== 'undefined') {
                this._fixedObserver = new MutationObserver(() => {
                    // Null-guard against a record delivered after destroy().
                    if (! this._fixedObserver) return;

                    this._readFixed();

                    if (this._apply()) {
                        this._announce();
                    }
                });

                this._fixedObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-wk-theme-fixed', 'data-wk-theme-fixed-reason'],
                });
            }

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

            this._fixedObserver?.disconnect();

            this._media = null;
            this._onSystemChange = null;
            this._onPeerChange = null;
            this._fixedObserver = null;
        },

        /** Is the page dark right now, whatever the reason? */
        get isDark() {
            if (this.fixed !== null) {
                return this.fixed === 'dark';
            }

            return this.theme === 'dark'
                || (this.theme === 'system' && this._systemPrefersDark());
        },

        /** The mode the control shows as on: the page's while it fixes one, the reader's otherwise. */
        get mode() {
            return this.fixed ?? this.theme;
        },

        /**
         * The menu trigger's accessible name: what the control is for, then the mode it shows.
         *
         * The mode is the only text that trigger shows, so it is what a speech user says, and the
         * name has to contain it (WCAG 2.5.3). A name of the control alone also kept the mode from
         * a screen reader, since a name replaces the content it would otherwise be read from.
         */
        get triggerName() {
            const shown = this.optionLabels[this.mode] ?? this.mode;

            return this.controlName !== '' ? `${this.controlName}, ${shown}` : shown;
        },

        /** Why the control changes nothing, while the page fixes the mode; empty otherwise. */
        get fixedMessage() {
            return this.fixed === null ? '' : (this.fixedReason || this.defaultFixedReason);
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
            // While the page fixes the mode, a choice would change nothing on it.
            if (this.fixed !== null) return;
            if (!['system', 'light', 'dark'].includes(theme)) return;

            this.theme = theme;
            this._write(theme);
            this._apply();
            this._announce();
        },

        /**
         * The native `select` variant: take the option chosen, or put the fixed
         * mode back in the box while the page fixes one.
         */
        choose(field) {
            if (this.fixed !== null) {
                field.value = this.mode;

                return;
            }

            this.select(field.value);
        },

        /**
         * The switch variant: a checkbox flips before its change event, so the
         * click is refused while the page fixes the mode.
         */
        refuseWhileFixed(event) {
            if (this.fixed !== null) {
                event.preventDefault();
            }
        },

        /**
         * Tell the rest of the page — the other controls, and any app code with
         * its own colors (a chart, a map, a third-party embed). Being told beats
         * polling a class for a change that may never come.
         *
         * Dispatched on window, not on $el: a sibling control is not an ancestor,
         * so an event that only bubbles up this element's tree never reaches it.
         */
        _announce() {
            window.dispatchEvent(new CustomEvent('wirekit:theme-changed', {
                detail: { theme: this.theme, dark: this.isDark, fixed: this.fixed },
            }));
        },

        _readFixed() {
            const root = typeof document !== 'undefined' ? document.documentElement : null;
            const fixed = root?.getAttribute('data-wk-theme-fixed');

            this.fixed = fixed === 'light' || fixed === 'dark' ? fixed : null;
            this.fixedReason = this.fixed === null ? '' : (root?.getAttribute('data-wk-theme-fixed-reason') || '');
        },

        _systemPrefersDark() {
            return typeof window !== 'undefined'
                && typeof window.matchMedia === 'function'
                && window.matchMedia('(prefers-color-scheme: dark)').matches;
        },

        /**
         * Put the page in the mode this control resolves to. Returns whether the
         * page changed, so the controls that follow the root element's attributes
         * announce a change once between them rather than once each.
         */
        _apply() {
            const root = document.documentElement;
            const was = root.classList.contains('dark');

            root.classList.toggle('dark', this.isDark);

            return was !== this.isDark;
        },

        _read() {
            // Storage throws in private mode and when disabled entirely. Falling
            // back to 'system' is the right answer — the page follows the OS,
            // which is what someone with no stored preference wanted anyway.
            try {
                if (this.storage === 'cookie') {
                    const value = this._readCookie();

                    // 'system' is never written (it is the absence of a cookie),
                    // so a stored value is always an explicit light/dark choice.
                    return ['light', 'dark'].includes(value) ? value : null;
                }

                const value = localStorage.getItem(this.storageKey);

                return ['system', 'light', 'dark'].includes(value) ? value : null;
            } catch {
                return null;
            }
        },

        _write(theme) {
            try {
                if (this.storage === 'cookie') {
                    this._writeCookie(theme);

                    return;
                }

                // 'system' is stored as the ABSENCE of a choice, so it agrees with
                // the head script — which reads "no key" as "follow the OS". A
                // literal "system" string there would fall through to the OS check
                // anyway, but removing it keeps one meaning in one place.
                if (theme === 'system') {
                    localStorage.removeItem(this.storageKey);
                } else {
                    localStorage.setItem(this.storageKey, theme);
                }
            } catch {
                // Nothing to do: the choice applies to this page, it just will not
                // survive a reload. Better than throwing on a click.
            }
        },

        /**
         * Parse the theme out of document.cookie. Scans the pair list by exact
         * name rather than a regex so a storageKey with regex-special characters
         * cannot break the match. Mirrors the head script's own reader so both
         * resolve the SAME cookie to the same theme.
         */
        _readCookie() {
            const cookies = document.cookie ? document.cookie.split('; ') : [];

            for (const pair of cookies) {
                const eq = pair.indexOf('=');
                const name = eq === -1 ? pair : pair.slice(0, eq);

                if (name === this.storageKey) {
                    return decodeURIComponent(pair.slice(eq + 1));
                }
            }

            return null;
        },

        _writeCookie(theme) {
            const attrs = this.cookieAttributes || {};
            const sameSite = attrs.same_site || 'Lax';
            const path = attrs.path || '/';
            // Secure only on HTTPS: a Secure cookie set over plain http is
            // silently dropped by the browser, which would break local dev.
            const secure = typeof location !== 'undefined' && location.protocol === 'https:'
                ? '; Secure'
                : '';

            if (theme === 'system') {
                // Delete: Max-Age=0 expires it now. 'system' is the absence of a
                // cookie, exactly like localStorage stores it as a removed key.
                document.cookie = `${this.storageKey}=; Max-Age=0; Path=${path}; SameSite=${sameSite}${secure}`;

                return;
            }

            const maxAge = attrs.max_age ?? 31536000;

            document.cookie = `${this.storageKey}=${encodeURIComponent(theme)}; Max-Age=${maxAge}; Path=${path}; SameSite=${sameSite}${secure}`;
        },
    };
}
