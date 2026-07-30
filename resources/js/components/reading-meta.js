/**
 * Reading meta — how long the article takes, and how much of it is left.
 *
 * The inline `x-data` did not parse under Alpine's CSP build (method shorthand,
 * arrow callbacks, spread, template-free but multi-statement bodies), so under a
 * strict Content-Security-Policy the estimate rendered as the static "~0" the
 * markup ships and never moved.
 *
 * Two pieces of the estimate are heuristics rather than arithmetic, and both are
 * deliberate:
 *
 *   Code is not read at the speed of prose, and mostly is not read at all, so
 *   `<pre>`, `<code>`, figures and images are stripped before counting. The
 *   clone is what makes that safe — removing them from the live page would
 *   damage the article.
 *
 *   Whitespace tokenization badly under-counts CJK, which has no spaces between
 *   logographic characters. Above a 40% ideograph ratio the count switches to
 *   characters and is scaled back to word-equivalents at the wpm baseline, so
 *   everything downstream keeps working in one unit.
 *
 * Lifecycle resources held on `this`:
 *   - _scrollHandler (scroll + resize listener) — removed in destroy(). Only
 *     registered when something actually depends on scroll position.
 *
 * @param {Object} config
 * @param {string} config.target             selector of the article to measure
 * @param {number} config.wpm                words per minute for the estimate
 * @param {number} config.cjkCpm             characters per minute for CJK text
 * @param {boolean} config.showRemaining     track how much is left below the fold
 * @param {boolean} config.perParagraph      annotate paragraphs with time-from-here
 * @param {number} config.paragraphMinWords  shortest paragraph worth annotating
 * @param {string} config.paragraphTemplate  annotation copy, with an {n} placeholder
 */
export default function wirekitReadingMeta(config = {}) {
    return {
        totalMinutes: 0,
        remainingMinutes: 0,
        wordCount: 0,

        _target: config.target || '',
        _wpm: Number(config.wpm || 200),
        _cjkCpm: Number(config.cjkCpm || 500),
        _showRemaining: Boolean(config.showRemaining),
        _perParagraph: Boolean(config.perParagraph),
        _paragraphMinWords: Number(config.paragraphMinWords || 0),
        _paragraphTemplate: config.paragraphTemplate || '',

        _scrollHandler: null,
        _ticking: false,

        // [{ el, paragraph, wordCount }] — populated in perParagraph mode.
        _paragraphData: [],

        init() {
            const el = document.querySelector(this._target);

            if (! el) {
                return;
            }

            this.wordCount = this.countWords(el);
            this.totalMinutes = Math.max(1, Math.ceil(this.wordCount / this._wpm));
            this.remainingMinutes = this.totalMinutes;

            if (this._perParagraph) {
                this._injectParagraphAnnotations(el);
            }

            // Only listen when something depends on scroll position. A static
            // estimate costs no listener at all.
            if (this._showRemaining || this._perParagraph) {
                this._scrollHandler = () => this._onScroll();
                window.addEventListener('scroll', this._scrollHandler, { passive: true });
                window.addEventListener('resize', this._scrollHandler, { passive: true });
                this._onScroll();
            }
        },

        destroy() {
            if (this._scrollHandler) {
                window.removeEventListener('scroll', this._scrollHandler);
                window.removeEventListener('resize', this._scrollHandler);
                this._scrollHandler = null;
            }
        },

        /**
         * Words in an article, with the parts nobody reads at prose speed taken
         * out first.
         *
         * The clone matters: these nodes are removed to count them out, and
         * doing that to the live element would delete the article's code blocks.
         */
        countWords(el) {
            const clone = el.cloneNode(true);

            clone.querySelectorAll('pre, code, figure, figcaption, img, picture, svg, [data-language]')
                .forEach((n) => n.remove());

            const text = (clone.textContent || '').trim();

            // See the header: whitespace tokenization under-counts CJK badly.
            const cjkChars = (text.match(/[一-鿿぀-ヿ가-힯]/g) || []).length;

            if (text.length > 0 && cjkChars / text.length > 0.4) {
                // Scaled to word-equivalents at the wpm baseline, so the
                // downstream ceil(wordCount / wpm) still means minutes.
                return Math.ceil(cjkChars * (this._wpm / this._cjkCpm));
            }

            return text.split(/\s+/).filter((w) => w.length > 0).length;
        },

        _onScroll() {
            if (this._ticking) {
                return;
            }

            requestAnimationFrame(() => {
                const el = document.querySelector(this._target);

                if (! el) {
                    this._ticking = false;

                    return;
                }

                if (this._showRemaining) {
                    this.remainingMinutes = this._minutesBelowTheFold(el);
                }

                if (this._perParagraph) {
                    this._updateParagraphLabels();
                }

                this._ticking = false;
            });

            this._ticking = true;
        },

        /** What is left is the slice still below the bottom edge of the viewport. */
        _minutesBelowTheFold(el) {
            const rect = el.getBoundingClientRect();
            const elTop = rect.top + window.scrollY;
            const elBottom = elTop + el.scrollHeight;
            const viewportBottom = window.scrollY + window.innerHeight;

            const remainingFraction = Math.max(0, Math.min(1, (elBottom - viewportBottom) / el.scrollHeight));

            return Math.max(0, Math.ceil(this.wordCount * remainingFraction / this._wpm));
        },

        /**
         * Put a "N minutes from here" marker before every paragraph long enough
         * to be worth one.
         */
        _injectParagraphAnnotations(root) {
            root.querySelectorAll('p').forEach((p) => {
                const text = (p.textContent || '').trim();
                const words = text.split(/\s+/).filter((w) => w.length > 0).length;

                if (words < this._paragraphMinWords) {
                    return;
                }

                const annotation = document.createElement('span');
                annotation.className = 'wk-reading-meta-paragraph';
                annotation.setAttribute('aria-hidden', 'true');
                annotation.dataset.minutes = '';

                p.parentNode?.insertBefore(annotation, p);
                this._paragraphData.push({ el: annotation, paragraph: p, wordCount: words });
            });

            this._updateParagraphLabels();
        },

        /**
         * Each annotation says how long is left FROM THERE, so the counts are
         * accumulated from the end backwards. Annotations above the reading
         * position are hidden rather than removed — the reader may scroll back.
         */
        _updateParagraphLabels() {
            const viewportTop = window.scrollY + 80; // header offset

            const wordsFromHere = new Map();
            let cumulative = 0;

            for (let i = this._paragraphData.length - 1; i >= 0; i--) {
                const item = this._paragraphData[i];
                cumulative += item.wordCount;
                wordsFromHere.set(item, cumulative);
            }

            this._paragraphData.forEach((item) => {
                const paragraphTop = item.paragraph.getBoundingClientRect().top + window.scrollY;

                if (paragraphTop < viewportTop) {
                    item.el.style.display = 'none';

                    return;
                }

                item.el.style.display = '';

                const minutes = Math.max(1, Math.ceil(wordsFromHere.get(item) / this._wpm));

                item.el.dataset.minutes = String(minutes);
                item.el.textContent = this._paragraphTemplate.replace('{n}', String(minutes));
            });
        },
    };
}
