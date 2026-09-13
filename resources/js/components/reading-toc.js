/**
 * Reading-toc Alpine plugin.
 *
 * Drives the `<x-wirekit::reading-toc>` Blade component. Builds a flat TOC
 * from headings inside a target container, renders them as a horizontal
 * strip of links, and keeps the active heading marked as the page scrolls.
 *
 * Sibling to wirekitReadingSpine — same data-collection model, different
 * rendered shape and different defaults (single level, no hover-expand,
 * no per-section fill, no numbering).
 *
 * Options:
 *   target — CSS selector for the container to scan (default 'main, article')
 *   levels — array of heading levels to include (default [2])
 *   offset — pixels of developer chrome above the strip (default 0)
 *
 * Lifecycle resources held on `this`:
 *   - _onScroll (document `scroll` in the capture phase, window `resize`) —
 *     removed in destroy(), and null-guarded in the handler and in its frame
 *     callback against a fire the browser queued before the teardown.
 *   - _onReaderMove (window `wheel`, `touchstart`, `pointerdown`, `keydown`,
 *     capture) — removed in destroy(). It only clears `_held`, so a late fire
 *     touches nothing that is still in use.
 *   - _scrollRaf (a requestAnimationFrame id) — canceled in destroy().
 *
 * Honors `prefers-reduced-motion: reduce` — the scrollTo handler picks
 * 'auto' over 'smooth' when the OS preference is set.
 */
import { prefersReducedMotion } from '../utils/motion.js';
import { focusHeading } from '../utils/focus-heading.js';
import { accessibleText } from '../utils/accessible-text.js';
import { scrollRootOf } from '../utils/scroll-root.js';
export default (options = {}) => ({
    target: options.target || 'main, article',
    levels: Array.isArray(options.levels) ? options.levels : [2],
    offset: typeof options.offset === 'number' ? options.offset : 0,

    items: [],
    activeIndex: -1,

    _onScroll: null,
    _onReaderMove: null,
    _scrollRaf: 0,
    _seq: 0,
    // True from a jump until the reader moves the page themselves. A jump says which section the
    // reader wants, and it holds for as long as they have not said anything else. It used to hold
    // for 600 ms, which a long smooth scroll outlasts on a busy machine, and which was the only
    // reason the wrong active line went unnoticed on a fast one.
    _held: false,

    init() {
        this.items = this.collectHeadings();
        this.assignIds(this.items);
        this.observeActive();
    },

    /**
     * Collect headings inside the matching `target` container at the
     * configured levels. Resolution prefers the nearest ancestor of the
     * component itself — keeps the toc scoped to its enclosing page
     * region when the component is rendered inside another document
     * (e.g. an inline preview, an iframe, a portal). Falls back to the
     * first match in the document if no ancestor matches (handles the
     * case where the toc sits outside `<main>`/`<article>`, e.g.
     * position:fixed at body root). Returns an empty array if neither
     * resolves — the Blade caller checks `items.length`.
     */
    collectHeadings() {
        const container = this.$el?.closest(this.target) ?? document.querySelector(this.target);
        if (!container) {
            // Warn on missed non-default target — mirrors reading-spine's
            // diagnostic. Default `'main, article'` stays silent because a
            // miss there is a Blade-level concern (the page just doesn't
            // have those landmarks); custom selectors that miss are almost
            // always developer typos worth surfacing.
            if (this.target !== 'main, article') {
                 
                console.warn(
                    `[wirekit] reading-toc: target selector "${this.target}" matched no element. ` +
                    `TOC will render empty. Check the selector on <x-wirekit::reading-toc target="${this.target}" />.`
                );
            }
            return [];
        }
        const sel = this.levels.map((l) => `h${l}`).join(', ');
        return Array.from(container.querySelectorAll(sel)).map((el, idx) => ({
            id: el.id || '',
            text: accessibleText(el),
            level: parseInt(el.tagName.slice(1), 10),
            el,
            index: idx,
        }));
    },

    /**
     * Ensure every heading has an id (existing ids respected, duplicates
     * disambiguated with -2 / -3 suffixes). Mirrors reading-spine.
     */
    assignIds(items) {
        const seen = new Map();
        items.forEach((item) => {
            if (item.el.id) {
                item.id = item.el.id;
                return;
            }
            let base = this.slugify(item.text);
            if (!base) base = `section-${++this._seq}`;
            let final = base;
            const count = seen.get(base) || 0;
            if (count > 0) final = `${base}-${count + 1}`;
            seen.set(base, count + 1);
            item.el.id = final;
            item.id = final;
        });
    },

    /**
     * Keep `activeIndex` on the section the reader is in, recomputed as the page scrolls.
     *
     * ⚠️ THIS USED TO BE AN IntersectionObserver WITH A 600 MS TIMER, and both halves were
     * wrong. Its band sat at the top of the WINDOW, while `scrollTo()` stands a heading
     * below the strip and inside whatever region actually scrolls, so a clicked heading landed
     * below the line it was measured against, and the previous section came back the moment
     * anything recomputed. An observer also fires only when a heading crosses ITS band, so a
     * heading crossing the right line said nothing at all. The timer hid it on a fast machine.
     *
     * Now every scroll in the document is a reason to look, at most once a frame. The listener
     * sits on `document` in the capture phase: a scroll event does not bubble, and capture is
     * how one listener hears an inner region as well as the page.
     */
    observeActive() {
        this._onScroll = () => {
            // A scroll the browser queued before destroy() can arrive after it.
            if (! this._onScroll || this._scrollRaf) return;

            this._scrollRaf = requestAnimationFrame(() => {
                this._scrollRaf = 0;
                if (this._onScroll) this.recomputeActive();
            });
        };

        // The reader moving the page themselves ends the hold a jump put on its section.
        this._onReaderMove = () => {
            this._held = false;
        };

        document.addEventListener('scroll', this._onScroll, { capture: true, passive: true });
        window.addEventListener('resize', this._onScroll, { passive: true });
        window.addEventListener('wheel', this._onReaderMove, { capture: true, passive: true });
        window.addEventListener('touchstart', this._onReaderMove, { capture: true, passive: true });
        window.addEventListener('pointerdown', this._onReaderMove, { capture: true, passive: true });
        window.addEventListener('keydown', this._onReaderMove, { capture: true, passive: true });

        this.recomputeActive();
    },

    /**
     * The active section is the LAST heading at or above the jump line.
     *
     * A tall section becomes active once the reader scrolls past its first paragraph, rather than
     * waiting for the next heading. At the end of the scroller the last heading in view wins: a
     * short final section can never scroll up to the line, and the reader looking at it is in
     * it. A region that cannot scroll at all is at its start, not at its end.
     */
    recomputeActive() {
        if (this._held || this.items.length === 0) return;

        const root = this._scrollRoot(this.items[0].el);
        const line = this._line(root) + 1;
        const tops = this.items.map((item) => item.el.getBoundingClientRect().top);
        let next = 0;

        tops.forEach((top, i) => {
            if (top <= line) next = i;
        });

        if (this._atEnd(root)) {
            const bottom = root ? root.getBoundingClientRect().bottom : window.innerHeight;

            tops.forEach((top, i) => {
                if (top < bottom) next = Math.max(next, i);
            });
        }

        if (next !== this.activeIndex) this.activeIndex = next;
    },

    /**
     * The viewport line a jump stands a heading on, and the line a heading has to reach to be the
     * active one. One answer to both questions is the whole fix: they used to differ by the
     * strip's height plus 24px, and by the distance from the window's top to the region's.
     *
     * The strip covers `offset` plus its own height at the top of the region, unless it is pinned
     * to the bottom, where it covers nothing up here. The 24px keeps a heading from sitting fused
     * to the strip's edge.
     */
    _line(root) {
        const top = root ? root.getBoundingClientRect().top : 0;
        const atTop = this.$el?.dataset?.position !== 'bottom';
        const strip = atTop ? this.offset + (this.$el ? this.$el.offsetHeight : 0) : 0;

        return top + strip + 24;
    },

    /** Whether the reader has scrolled as far as the region goes, having scrolled at all. */
    _atEnd(root) {
        if (root) {
            return root.scrollTop > 0 && root.scrollTop + root.clientHeight >= root.scrollHeight - 2;
        }

        return window.scrollY > 0
            && window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2;
    },

    destroy() {
        if (this._onScroll) {
            document.removeEventListener('scroll', this._onScroll, { capture: true });
            window.removeEventListener('resize', this._onScroll);
            this._onScroll = null;
        }

        if (this._onReaderMove) {
            window.removeEventListener('wheel', this._onReaderMove, { capture: true });
            window.removeEventListener('touchstart', this._onReaderMove, { capture: true });
            window.removeEventListener('pointerdown', this._onReaderMove, { capture: true });
            window.removeEventListener('keydown', this._onReaderMove, { capture: true });
            this._onReaderMove = null;
        }

        if (this._scrollRaf) {
            cancelAnimationFrame(this._scrollRaf);
            this._scrollRaf = 0;
        }
    },

    /**
     * The element that actually scrolls, or null when the window does. The walk and the reasoning
     * behind it live in `utils/scroll-root.js`, shared with reading-spine: the spine never had
     * this walk, and in a page with its own scroll region it scrolled the window instead.
     */
    _scrollRoot(el) {
        return scrollRootOf(el);
    },

    /**
     * Smooth-scroll to a heading and replace the URL hash without pushing a history entry.
     * Honors prefers-reduced-motion.
     *
     * The target is the jump line from `_line()`: below the developer's chrome (`offset`), below
     * the strip's own height while the strip sits at the top, plus 24px of breathing room — a
     * heading 8-16px under the strip read as fused to its border. Earlier versions used
     * `... - this.offset + 8`, which landed the heading 8px ABOVE the line, and ignored the
     * strip's own height, which left the target heading hidden behind the strip.
     */
    scrollTo(id, event) {
        if (event) event.preventDefault();
        const el = document.getElementById(id);
        if (!el) return;
        const reduced = prefersReducedMotion();
        const root = this._scrollRoot(el);

        // The heading's distance below the jump line, added to how far its scroller has already
        // moved. In a region that is `root.scrollTop`: `window.scrollY` means nothing there,
        // because the page is not what moves, and `_line()` already stands on the region's top.
        const top = el.getBoundingClientRect().top - this._line(root) + (root ? root.scrollTop : window.scrollY);

        (root ?? window).scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' });

        // The jump holds its section until the reader moves the page themselves, so the scroll it
        // starts cannot walk the mark through every section on the way.
        this._held = true;

        const clicked = this.items.findIndex((it) => it.id === id);
        if (clicked !== -1) this.activeIndex = clicked;

        // Both kinds of scroller mirror the hash. The region branch used to RETURN before this
        // line, so on a page with its own scroll container the URL never followed the heading,
        // and a reader who copied the address bar after a jump got a link to the top of the page.
        this._mirrorHash(id);

        // Focus goes where the reader asked to go. `preventDefault()` above suppressed the
        // anchor's default, and that default moves TWO things: the scroll and the
        // sequential-navigation starting point. Only the first was replaced, so the next Tab
        // went to the next TOC link instead of into the section.
        focusHeading(el);
    },

    /**
     * Put the heading's id in the address bar, without a history entry.
     *
     * `replaceState` rather than a push, so the back button still goes to the previous PAGE
     * rather than walking back up the article one heading at a time.
     *
     * Extracted because both scroll paths need it and only one of them had it. The
     * try/catch is not defensive noise: an iframe-srcdoc context has `origin: null` and
     * rejects a replaceState against the parent page's URL with a SecurityError. The scroll
     * has already happened by then, so swallowing the URL sync is the right degradation —
     * the reader still gets the jump, just without the mirror.
     */
    _mirrorHash(id) {
        try {
            history.replaceState(null, '', `#${id}`);
        } catch {
            // Cross-origin iframe-srcdoc — URL hash mirror unavailable, and the scroll has
            // already landed. Accept the scroll-only behavior.
        }
    },

    /**
     * Stable URL-friendly slug — lowercase, alphanumerics + hyphens.
     * Empty input falls back to a sequenced "section-N" label.
     */
    slugify(text) {
        return String(text).toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-');
    },
});
