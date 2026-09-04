/**
 * Reading bookmark — remembering where the reader stopped, and offering it back.
 *
 * The inline `x-data` did not parse under Alpine's CSP build, so under a strict
 * Content-Security-Policy nothing was ever saved and the resume prompt never
 * appeared. That failure is silent by design elsewhere in this component too —
 * see the try/catch discussion below — which made it a bad one to leave.
 *
 * Two things here are subtler than they look:
 *
 *   The scroll root must be the SAME on save and on resume. When the target is
 *   internally scrollable (overflow-y: auto/scroll) the offset belongs to the
 *   element; otherwise it belongs to the window. Mixing them applies an
 *   element-relative offset to the document — which scrolls to the wrong place,
 *   or nowhere at all if the document does not scroll. The same mismatch made
 *   the minimap's bookmark marker appear at the wrong height.
 *
 *   Every localStorage access is wrapped. It throws in private mode, when
 *   storage is disabled, and on quota — and a bookmark is a convenience, so
 *   failing to store one must never take the page down with it.
 *
 * The two custom events exist because the browser's own `storage` event fires
 * only for OTHER tabs. Siblings in THIS tab (the minimap's bookmark marker)
 * would otherwise never hear about a save or a clear.
 *
 * Lifecycle resources held on `this`:
 *   - _saveTimer (setTimeout, 1s debounce) — cleared in destroy().
 *   - _onScroll (passive scroll listener) — removed in destroy().
 *   - _onStorage (storage listener) — removed in destroy().
 *
 * @param {Object} config
 * @param {string} config.key            localStorage key for this article
 * @param {string} config.target         selector of the article being read
 * @param {number} config.threshold      fraction of the article that must pass before a re-save
 * @param {boolean} config.promptEnabled whether a stored position offers a resume
 * @param {number} config.minDwell       seconds on the page before a stored position is worth offering
 * @param {string} config.promptMessage  the offer, already translated, for the live region to speak
 */
import { prefersReducedMotion } from '../utils/motion.js';

export default function wirekitReadingBookmark(config = {}) {
    return {
        showPrompt: false,
        savedTop: 0,

        _key: config.key || '',
        _target: config.target || '',
        _threshold: Number(config.threshold || 0),
        _promptEnabled: Boolean(config.promptEnabled),
        _minDwell: Number(config.minDwell || 0),
        // The offer, already translated. It is assembled on the server, because a
        // sentence built here would be untranslatable and invisible to every
        // extractor — the same route the dismiss control's name takes.
        _promptMessage: typeof config.promptMessage === 'string' ? config.promptMessage : '',

        _saveTimer: null,
        _enterAt: 0,
        _lastSavedTop: 0,
        _onScroll: null,
        _onStorage: null,

        /**
         * What the live region says, which is nothing until there is an offer.
         *
         * The prompt used to BE the region: the pill carried role="status" and was
         * toggled with x-show, so the region and its text arrived together. A region
         * that appears already holding its sentence is a new node rather than a
         * changed one, and assistive technology watches for the change — so the one
         * output this component has for a screen-reader user was the one output
         * likely never to be spoken, on a control pinned to a corner that a reader
         * moving linearly reaches last.
         *
         * Derived rather than assigned so the two can never disagree: every path
         * that takes the prompt down (resume, dismiss, clear, a sibling tab
         * clearing the key) already writes `showPrompt`, and each would otherwise
         * have to remember to empty the region as well.
         */
        get promptAnnouncement() {
            return this.showPrompt ? this._promptMessage : '';
        },

        init() {
            this._enterAt = Date.now();

            this._offerStoredPosition();

            this._onScroll = () => {
                // Debounced rather than throttled: what matters is where the
                // reader SETTLED, not every position they passed through.
                clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => this._save(), 1000);
            };

            window.addEventListener('scroll', this._onScroll, { passive: true });

            // Cross-tab consistency: another tab clearing this key must take the
            // prompt down here too.
            this._onStorage = (e) => {
                if (e.key !== this._key) {
                    return;
                }

                if (! e.newValue) {
                    this.showPrompt = false;
                    this.savedTop = 0;
                }
            };

            window.addEventListener('storage', this._onStorage);
        },

        destroy() {
            if (this._saveTimer) {
                clearTimeout(this._saveTimer);
                this._saveTimer = null;
            }

            window.removeEventListener('scroll', this._onScroll);
            window.removeEventListener('storage', this._onStorage);
        },

        /** Jump back to the stored position, on the scroll root that stored it. */
        resume() {
            this.showPrompt = false;

            // Through the shared helper, NOT window.matchMedia directly: the
            // application's own reduced-motion setting has to be able to reach
            // this, and the OS preference alone cannot see it. Reading matchMedia
            // was what the inline version did — invisible to the guard while it
            // lived in a template, caught the moment it moved here.
            const behavior = prefersReducedMotion() ? 'auto' : 'smooth';

            const target = this._internallyScrollableTarget();

            if (target) {
                target.scrollTo({ top: this.savedTop, behavior });
            } else {
                window.scrollTo({ top: this.savedTop, behavior });
            }
        },

        /** Take the prompt down but KEEP the bookmark — re-offer on the next visit. */
        dismiss() {
            this.showPrompt = false;
        },

        clear() {
            this._forget();
            this.showPrompt = false;
            this.savedTop = 0;
            this._announce('cleared', { key: this._key });
        },

        /**
         * Offer a stored position, if there is one worth offering.
         *
         * The dwell floor is what keeps a bounce from becoming a bookmark: a
         * reader who opened the page and left immediately did not stop reading
         * anywhere, and prompting them to resume a position they never reached
         * is worse than not prompting at all.
         */
        _offerStoredPosition() {
            if (! this._promptEnabled) {
                return;
            }

            const data = this._read();

            if (! data || typeof data.top !== 'number' || data.top <= 0) {
                return;
            }

            if (! (data.dwell >= this._minDwell)) {
                return;
            }

            this.savedTop = data.top;
            this.showPrompt = true;
        },

        _save() {
            const scrollHeight = this._scrollHeight();
            const top = this._scrollTop();

            // Re-save only after the reader has actually moved on. Without the
            // floor every settled scroll rewrites the same value.
            const movedFraction = scrollHeight > 0 ? Math.abs(top - this._lastSavedTop) / scrollHeight : 0;

            if (movedFraction < this._threshold) {
                return;
            }

            // Back at the top means done with the article, not one page into it.
            if (top <= 0) {
                this._forget();
                this._lastSavedTop = 0;
                this._announce('cleared', { key: this._key });

                return;
            }

            const dwell = Math.floor((Date.now() - this._enterAt) / 1000);

            if (this._write({ top, dwell, t: Date.now() })) {
                this._lastSavedTop = top;
                this._announce('saved', { key: this._key, top, dwell });
            }
        },

        /** The target element IF it scrolls internally, otherwise null. */
        _internallyScrollableTarget() {
            const target = document.querySelector(this._target);

            if (! target) {
                return null;
            }

            const overflowY = getComputedStyle(target).overflowY;

            return (overflowY === 'auto' || overflowY === 'scroll') ? target : null;
        },

        _scrollTop() {
            const target = this._internallyScrollableTarget();

            return target ? target.scrollTop : window.scrollY;
        },

        _scrollHeight() {
            const target = document.querySelector(this._target);

            return (target && target.scrollHeight) || document.documentElement.scrollHeight;
        },

        /**
         * Siblings in THIS tab, e.g. the minimap's bookmark marker. The
         * browser's own `storage` event only reaches other tabs.
         */
        _announce(what, detail) {
            window.dispatchEvent(new CustomEvent(`wirekit:reading-bookmark:${what}`, { detail }));
        },

        // ── localStorage, which is allowed to be unavailable ─────────────────
        //
        // It throws in private mode, when storage is disabled, and on quota. A
        // bookmark is a convenience; losing one must never take the page down.

        _read() {
            try {
                const raw = localStorage.getItem(this._key);

                return raw ? JSON.parse(raw) : null;
            } catch (_) {
                return null;
            }
        },

        _write(data) {
            try {
                localStorage.setItem(this._key, JSON.stringify(data));

                return true;
            } catch (_) {
                return false;
            }
        },

        _forget() {
            try {
                localStorage.removeItem(this._key);
            } catch (_) {
                // Nothing to do — the bookmark is gone either way.
            }
        },
    };
}
