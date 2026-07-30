/**
 * Reading progress — how far down the article the reader is.
 *
 * This is the single largest thing the CSP conversion removed from the
 * templates, and the duplication is the reason: the component renders as either
 * a bar or a corner dot, and both carried the SAME ~150-line `x-data`, byte for
 * byte. Two copies of a scroll-metrics implementation is two places for a fix to
 * miss, and the block did not parse under Alpine's CSP build anyway — arrow
 * functions, template literals and method shorthand all appear in it.
 *
 * The scroll math is the delicate part and is unchanged. Two things in it look
 * like they could be simplified and cannot:
 *
 *   The scroll root is PROBED rather than assumed. Reading scrollTop from one
 *   element and scrollHeight from another mixes scroll roots and caps the
 *   proportional math below 100% — the bug that stopped the bar saturating while
 *   the dot still looked close enough. document.scrollingElement always reports
 *   html in standards mode regardless of which element actually scrolls, so the
 *   scrollHeight-vs-clientHeight probe is what decides.
 *
 *   The at-bottom override exists because proportional math alone does not reach
 *   100 in practice: a body with bottom padding inflates scrollHeight past the
 *   reachable scrollTop, sub-pixel rounding leaves a fraction, and iframe-srcdoc
 *   shapes over-report. 32px is below one line-height of body text, so it cannot
 *   saturate while the reader is still inside a paragraph.
 *
 * Lifecycle resources held on `this`:
 *   - _onScroll (scroll + resize listener, on both window and document) —
 *     removed in destroy(). The document listener is registered in the capture
 *     phase because some browsers, notably in iframe-srcdoc contexts where
 *     <body> is the scrollingElement, do not fire reliably on window.
 *
 * @param {Object} config
 * @param {string} config.target             selector of the element to track, '' for the viewport
 * @param {string} config.boundarySelector   ancestor the sticky positioning anchors to, '' for none
 * @param {number} config.showAfter          px of scroll before the indicator leaves 0
 * @param {boolean} config.milestonesEnabled whether to dispatch milestone events
 */
export default function wirekitReadingProgress(config = {}) {
    return {
        progress: 0,

        _ticking: false,
        _onScroll: null,
        _milestonesFired: { 25: false, 50: false, 75: false, 100: false },

        _target: config.target || null,
        _boundarySelector: config.boundarySelector || null,
        _showAfter: Number(config.showAfter || 0),
        _milestonesEnabled: Boolean(config.milestonesEnabled),

        init() {
            this._warnAboutUnmatchedSelectors();

            this._onScroll = () => {
                if (this._ticking) {
                    return;
                }

                requestAnimationFrame(() => {
                    this._update();
                    this._ticking = false;
                });

                this._ticking = true;
            };

            this._update();

            window.addEventListener('scroll', this._onScroll, { passive: true });
            window.addEventListener('resize', this._onScroll, { passive: true });
            document.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
        },

        destroy() {
            window.removeEventListener('scroll', this._onScroll);
            window.removeEventListener('resize', this._onScroll);
            document.removeEventListener('scroll', this._onScroll, { capture: true });
        },

        /**
         * The fill transform, as an OBJECT.
         *
         * Alpine's `bind:style` given a string rewrites the whole style
         * attribute on every reactive update. This element carries its
         * background-color, height, width, transform-origin and transition as
         * static inline styles, so the string form cost all five the moment the
         * user scrolled — the "bar visible at first paint, invisible after
         * scrolling" report. The object form assigns property by property.
         */
        fillStyle() {
            return { transform: `scaleX(${this.progress / 100})` };
        },

        /**
         * What assistive technology hears: a whole percent, not a float with
         * fifteen decimals. `Math` is unreachable from a directive under
         * Alpine's CSP build, so the rounding happens here.
         */
        roundedProgress() {
            return Math.round(this.progress);
        },

        /**
         * Both selectors a developer can pass are checked once, at init.
         *
         * Neither is fatal — the component falls back to viewport scroll and to
         * the nearest positioned ancestor — and that is exactly why they warn:
         * the fallback looks correct on quick inspection and silently ignores
         * what the developer asked for.
         */
        _warnAboutUnmatchedSelectors() {
            if (this._boundarySelector && ! this.$el.closest(this._boundarySelector)) {
                console.warn(
                    `[wirekit] reading-progress: boundary selector '${this._boundarySelector}' did not match any ancestor of this element. `
                    + 'position: sticky will anchor to the nearest positioned ancestor instead. '
                    + "Pass boundary='container' for the no-selector form, or verify the selector matches an ancestor (e.g. add id/class to a wrapper)."
                );
            }

            if (this._target && ! document.querySelector(this._target)) {
                console.warn(
                    `[wirekit] reading-progress: target selector '${this._target}' matched no element. `
                    + 'Falling back to viewport scroll. Check the target prop you passed to the reading-progress component.'
                );
            }
        },

        /** Scroll metrics, read from ONE element so the proportion cannot mix roots. */
        _metrics() {
            const scope = this._target ? document.querySelector(this._target) : null;

            if (scope) {
                return {
                    scrollTop: Math.max(0, -scope.getBoundingClientRect().top),
                    scrollHeight: scope.scrollHeight,
                    clientHeight: window.innerHeight,
                };
            }

            // Candidates in priority order: the CSSOM scroll root, html, body.
            // The first one whose content actually overflows is the one that
            // scrolls — html in the standard iframe-srcdoc case, body where body
            // carries overflow:auto and html fills the viewport.
            const candidates = [
                document.scrollingElement,
                document.documentElement,
                document.body,
            ].filter(Boolean);

            let root = candidates[0];

            for (const candidate of candidates) {
                if (candidate.scrollHeight > candidate.clientHeight) {
                    root = candidate;
                    break;
                }
            }

            return {
                scrollTop: root.scrollTop,
                scrollHeight: root.scrollHeight,
                clientHeight: root.clientHeight || window.innerHeight,
            };
        },

        _update() {
            const { scrollTop, scrollHeight, clientHeight } = this._metrics();
            const max = Math.max(1, scrollHeight - clientHeight);

            // See the header: proportional math alone does not reliably reach
            // 100, so being within 32px of the end counts as the end.
            const bottomTolerance = 32;
            const atBottom = (scrollTop + clientHeight) >= (scrollHeight - bottomTolerance);
            const raw = Math.min(100, Math.max(0, (scrollTop / max) * 100));
            const next = atBottom || raw >= 99 ? 100 : raw;

            this.progress = (this._showAfter > 0 && scrollTop < this._showAfter) ? 0 : next;

            this._maybeFireMilestones(scrollTop, scrollHeight);
        },

        _maybeFireMilestones(scrollTop, scrollHeight) {
            if (! this._milestonesEnabled) {
                return;
            }

            const percent = Math.round(this.progress);

            for (const threshold of [25, 50, 75, 100]) {
                if (! this._milestonesFired[threshold] && percent >= threshold) {
                    this._milestonesFired[threshold] = true;

                    this.$dispatch('wirekit:reading-progress:milestone', {
                        percent: threshold,
                        scrollTop,
                        scrollHeight,
                    });
                }
            }
        },
    };
}
