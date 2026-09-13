/**
 * Reading-spine helper Alpine plugin.
 *
 * Drives the `<x-wirekit::reading-spine>` Blade component. Builds a TOC
 * from headings inside a target container, renders them as ticks,
 * keeps the active heading marked as the page scrolls, and exposes
 * hover/focus expansion + an optional developer-supplied filter input.
 *
 * Why a separate plugin (not inline x-data): the helper carries enough
 * state and methods to warrant a Blade-friendly named Alpine component.
 * Mirrors the wirekitAnimate / wirekitStatAnimate pattern already in the
 * bundle.
 *
 * Options:
 *   target       — CSS selector for the container to scan (default 'main, article')
 *   levels       — array of heading levels to include (default [2, 3])
 *   offset       — pixels below the top of whatever scrolls at which a heading
 *                  becomes the current section (default 96)
 *   numbered     — emit hierarchical numeric labels alongside ticks (default false)
 *   fillSections — fill each tick's background per-section progress (default false)
 *   sectionEvents — debounced section-changed events (default true)
 *
 * Lifecycle resources held on `this`:
 *   - _onScroll (document `scroll` in the capture phase, window `resize`) —
 *     removed in destroy(), and null-guarded in the handler and in its frame
 *     callback against a fire the browser queued before the teardown.
 *   - _onReaderMove (window `wheel`, `touchstart`, `pointerdown`, `keydown`,
 *     capture) — removed in destroy(). It only clears `_held`.
 *   - _scrollRaf (a requestAnimationFrame id) — canceled in destroy().
 *   - _collapseTimer, _sectionEventTimer (timeouts) — cleared in destroy().
 *
 * Honors `prefers-reduced-motion: reduce` — the CSS layer collapses every
 * width / opacity / color transition on this component to 0.01ms via the
 * global @media block. The plugin itself never animates anything in JS.
 */
import { prefersReducedMotion } from '../utils/motion.js';
import { focusHeading } from '../utils/focus-heading.js';
import { accessibleText } from '../utils/accessible-text.js';
import { scrollRootOf } from '../utils/scroll-root.js';
export default (options = {}) => ({
    target: options.target || 'main, article',
    // A selector for subtrees whose headings are NOT this page's structure — an embedded
    // demo, a sidebar of related links. `null` keeps every existing call site rendering
    // exactly what it renders now.
    exclude: options.exclude || null,
    levels: Array.isArray(options.levels) ? options.levels : [2, 3],
    offset: typeof options.offset === 'number' ? options.offset : 96,
    numbered: options.numbered === true,
    fillSections: options.fillSections === true,
    sectionEvents: options.sectionEvents !== false,

    /**
     * Start expanded — always, or only from the md breakpoint up.
     *
     * These arrive as options rather than as an `x-init` because the template's
     * version was a pair of STATEMENTS wrapped in Blade `@if`, and Alpine's CSP
     * build parses one expression: under a strict policy the whole attribute was
     * refused, which does not fail loudly — it leaves the element with an empty
     * scope, so every other directive on it goes quiet too. The condition is
     * ordinary JavaScript here.
     */
    forceExpanded: options.forceExpanded === true,
    forceExpandedMd: options.forceExpandedMd === true,

    /** The minimum heading level in the spine — the indent is measured from it. */
    baseLevel: typeof options.baseLevel === 'number' ? options.baseLevel : 2,

    items: [],
    activeIndex: -1,
    expanded: false,
    filter: '',

    _hovering: false,
    _focused: false,
    // Debounce timer for collapseOnHover — prevents the rapid expand/
    // collapse flicker when the cursor jitters across the spine's
    // boundary (sub-pixel mouse movements at the top/left edge fire
    // alternating mouseenter/mouseleave events).
    _collapseTimer: null,
    _onScroll: null,
    _onReaderMove: null,
    _scrollRaf: 0,
    _sectionEventTimer: null,
    _lastDispatchedIndex: -1,
    _seq: 0,
    // True from a jump until the reader moves the page themselves, so the scroll a jump starts
    // cannot walk the mark through every section on the way. It used to be a 600 ms timer, which
    // a long smooth scroll outlasts on a busy machine.
    _held: false,

    /**
     * The indent for one entry, by how far its heading sits below the shallowest.
     *
     * Built here rather than in the template for the CSP build's sake — the
     * template's version was a string concatenation carrying a Blade expression,
     * and while concatenation itself parses, keeping the arithmetic beside the
     * tick style is what stops the two from drifting apart. The base level comes
     * from the options rather than from Blade, so the same number reaches both.
     */
    linkStyle(item) {
        const depth = (Number(item.level) - this.baseLevel) * 0.5;

        return `padding-left: ${depth}rem; padding-top: 0.125rem; padding-bottom: 0.125rem; text-decoration: none;`;
    },

    /**
     * The tick's style, including the per-section progress fill when it is on.
     *
     * This was a TEMPLATE LITERAL in the attribute, which Alpine's CSP build
     * cannot parse at all — and, as everywhere else, a refused expression does
     * not announce itself: the element simply renders with no style, the tick
     * collapses to zero width, and the spine loses the only affordance that says
     * it can be expanded. The Blade `@if` around the gradient is now the
     * `fillSections` flag, decided here where it is ordinary JavaScript.
     */
    tickStyle(item) {
        const base = 'display: block; height: var(--reading-spine-tick-height);';

        if (! this.fillSections) {
            return base;
        }

        const pct = Number(item.fill || 0) * 100;

        return `${base} background: linear-gradient(to right, var(--reading-spine-color-active) ${pct}%, var(--reading-spine-color-idle) ${pct}%);`;
    },

    /**
     * Whether the spine starts expanded.
     *
     * A method rather than three lines inside init() so the decision can be
     * measured on its own: init() goes on to collect headings and attach
     * observers, so a test of the expansion rule would otherwise need a whole
     * document to ask a question about two booleans.
     *
     * `matchMedia` is read ONCE, here, rather than watched — this is a starting
     * position, not a responsive binding. The spine's own hover and focus
     * handlers own the state from this point on, and re-deciding it on a resize
     * would fight them.
     */
    initialExpanded() {
        if (this.forceExpanded) {
            return true;
        }

        return this.forceExpandedMd && window.matchMedia('(min-width: 768px)').matches;
    },

    init() {
        // Before anything else, so the first paint is already in the right state.
        if (this.initialExpanded()) {
            this.expanded = true;
        }

        this.items = this.collectHeadings();
        this.assignIds(this.items);
        if (this.numbered) this.computeNumbering(this.items);
        // One scroll listener drives both the active section and the per-section fill (see
        // observeActive()). There used to be two, and the fill's sat on the WINDOW, so in a page
        // that scrolls inside its own region the fill never moved.
        this.observeActive();
    },

    /**
     * Collect headings inside the matching `target` container at the
     * configured levels. Resolution prefers the nearest ancestor of the
     * component itself — keeps the spine scoped to its enclosing page
     * region when the component is rendered inside another document
     * (e.g. an inline preview, an iframe, a portal). Falls back to the
     * first match in the document if no ancestor matches (handles the
     * case where the spine sits outside `<main>`/`<article>`, e.g.
     * position:fixed at body root). Returns an empty array if neither
     * resolves — the Blade caller checks `items.length`.
     */
    collectHeadings() {
        const container = this.$el?.closest(this.target) ?? document.querySelector(this.target);
        if (!container) {
            // Warn when a non-default custom target resolved to nothing.
            // The default `'main, article'` is intentionally permissive —
            // most apps have one or the other, and a Blade-level miss isn't
            // a developer error. A custom selector that misses, however,
            // almost always means the developer typo'd the id / class /
            // ancestor name; surface it so they know why the spine is empty.
            if (this.target !== 'main, article') {
                 
                console.warn(
                    `[wirekit] reading-spine: target selector "${this.target}" matched no element. ` +
                    `Spine will render empty. Check the selector on <x-wirekit::reading-spine target="${this.target}" />.`
                );
            }
            return [];
        }
        const sel = this.levels.map((l) => `h${l}`).join(', ');
        const found = Array.from(container.querySelectorAll(sel));

        // A page can hold headings that are not its own structure — a demo embedded in the
        // prose brings an accordion's panel titles, a carousel's product names. Measured on
        // the documentation site: one page listed 41 entries of which 23 came from demos,
        // and 36 pages carried 183 such entries between them. `target` cannot help, because
        // the demos sit INSIDE the article that holds the real sections.
        //
        // `closest` rather than a descendant test, so naming the wrapper is enough and the
        // caller does not have to enumerate what is inside it.
        const kept = this.exclude
            ? found.filter((el) => !el.closest(this.exclude))
            : found;

        return kept.map((el, idx) => ({
            id: el.id || '',
            text: accessibleText(el),
            level: parseInt(el.tagName.slice(1), 10),
            label: '', // populated by computeNumbering when numbered=true
            fill: 0,   // populated by updateSectionFills when fillSections=true
            el,
            index: idx,
        }));
    },

    /**
     * Ensure every heading has an `id` so the spine link works as an
     * anchor and the browser back/forward + scroll-restoration work
     * naturally. Existing IDs respected; duplicates are disambiguated
     * with -2 / -3 suffixes (so two h2s with identical text get
     * stable, unique IDs).
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
     * Compute hierarchical numeric labels for every heading. Top-level
     * headings (lowest level value in the items list) get integer
     * labels (1, 2, 3); nested levels get dotted suffixes (1.1, 1.2,
     * 2.1). Mirrors the legal/spec-document outline convention.
     */
    computeNumbering(items) {
        if (items.length === 0) return;
        const baseLevel = Math.min(...items.map((i) => i.level));
        const counters = {};
        items.forEach((item) => {
            // Reset any deeper counters when we ascend back up the tree.
            Object.keys(counters).forEach((lvl) => {
                if (parseInt(lvl, 10) > item.level) delete counters[lvl];
            });
            counters[item.level] = (counters[item.level] || 0) + 1;
            const parts = [];
            for (let l = baseLevel; l <= item.level; l++) {
                if (counters[l]) parts.push(counters[l]);
            }
            item.label = parts.join('.');
        });
    },

    /**
     * Keep `activeIndex` on the section the reader is in, recomputed as the page scrolls.
     *
     * Every scroll in the document is a reason to look, at most once a frame: the listener sits
     * on `document` in the capture phase, because a scroll event does not bubble and capture is
     * how one listener hears an inner region as well as the page. The same frame moves the
     * per-section fill.
     *
     * ⚠️ THIS USED TO SPEAK TO THE WINDOW EVERYWHERE. An IntersectionObserver banded to the window
     * decided the active section, and the end-of-page rule compared the window's scroll with the
     * document's height. In a page whose content scrolls inside its own region the document never
     * scrolls, so it is always at its end, and the last section won every recompute.
     */
    observeActive() {
        this._onScroll = () => {
            // A scroll the browser queued before destroy() can arrive after it.
            if (! this._onScroll || this._scrollRaf) return;

            this._scrollRaf = requestAnimationFrame(() => {
                this._scrollRaf = 0;
                if (! this._onScroll) return;
                this.updateSectionFills();
                this.recomputeActive();
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

        this.updateSectionFills();
        this.recomputeActive();
    },

    /**
     * The active section is the LAST heading at or above the spine's line.
     *
     * A tall section becomes active once the reader scrolls past its first paragraph, rather than
     * waiting for the next heading. At the end of the scroller the LAST section is forced active:
     * a short final section can never reach the line, and the reader looking at it is in it. A
     * region that cannot scroll at all is at its start, not at its end.
     */
    recomputeActive() {
        if (this._held || this.items.length === 0) return;

        const root = scrollRootOf(this.items[0].el);
        const line = this._line(root);
        let next = 0;

        this.items.forEach((item, i) => {
            if (item.el.getBoundingClientRect().top <= line) next = i;
        });

        if (this._atEnd(root)) next = this.items.length - 1;

        if (next !== this.activeIndex) {
            this.activeIndex = next;
            this._maybeDispatchSectionChange();
        }
    },

    /** The line a heading has to reach to be the current section: `offset` below the top of whatever scrolls. */
    _line(root) {
        return (root ? root.getBoundingClientRect().top : 0) + this.offset;
    },

    /** Whether the reader has scrolled as far as the region goes, having scrolled at all. */
    _atEnd(root) {
        if (root) {
            return root.scrollTop > 0 && root.scrollTop + root.clientHeight >= root.scrollHeight - 4;
        }

        return window.scrollY > 0
            && window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4;
    },

    /**
     * Per-section progress fill. For each heading, compute how far the reader has scrolled THROUGH
     * that section (top of next heading − top of this heading). Stores 0..1 on each item; CSS reads
     * via a style="--reading-spine-fill: NN%" inline binding.
     *
     * Measured in the scroller's own content space (its top, its scroll position, its height)
     * because the window's are the wrong ones in a page that scrolls inside its own region: the
     * window never moves there, so the fill never did either.
     */
    updateSectionFills() {
        if (!this.fillSections || this.items.length === 0) return;
        const root = scrollRootOf(this.items[0].el);
        const rootTop = root ? root.getBoundingClientRect().top : 0;
        const scrolled = root ? root.scrollTop : window.scrollY;
        const height = root ? root.clientHeight : window.innerHeight;
        const top = scrolled + this.offset;
        for (let i = 0; i < this.items.length; i++) {
            const item = this.items[i];
            const next = this.items[i + 1];
            const start = item.el.getBoundingClientRect().top - rootTop + scrolled;
            const end = next ? next.el.getBoundingClientRect().top - rootTop + scrolled : start + height;
            const span = Math.max(1, end - start);
            item.fill = Math.max(0, Math.min(1, (top - start) / span));
        }
    },

    _maybeDispatchSectionChange() {
        if (!this.sectionEvents) return;
        if (this._sectionEventTimer) clearTimeout(this._sectionEventTimer);
        this._sectionEventTimer = setTimeout(() => {
            const i = this.activeIndex;
            if (i === this._lastDispatchedIndex || i < 0 || !this.items[i]) return;
            this._lastDispatchedIndex = i;
            this.$dispatch('wirekit:reading-spine:section-changed', {
                index: i,
                id: this.items[i].id,
                text: this.items[i].text,
                level: this.items[i].level,
            });
        }, 80);
    },

    destroy() {
        // The capture flag has to be repeated here. `removeEventListener` matches on target, type,
        // listener AND capture; leave it off and the call is a silent no-op, and a teardown that
        // leaves a live handler keeps this component alive and recomputing against a spine that is
        // gone. Under Livewire navigation that accumulated one full set per visit.
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

        if (this._sectionEventTimer) clearTimeout(this._sectionEventTimer);
        if (this._collapseTimer) clearTimeout(this._collapseTimer);
    },

    expandOnHover() {
        // Cancel any pending collapse — the cursor re-entered before
        // the debounce window closed, so the collapse is no longer wanted.
        if (this._collapseTimer) {
            clearTimeout(this._collapseTimer);
            this._collapseTimer = null;
        }
        this._hovering = true;
        this.expanded = true;
    },
    collapseOnHover() {
        // Debounce the collapse to absorb cursor jitter at the spine's
        // boundary. Without this, a 1-2px mouse movement that
        // alternately crosses and uncrosses the spine's edge fires
        // mouseleave/mouseenter every frame, toggling expanded state
        // continuously — visible to the user as a rapid flicker.
        if (this._collapseTimer) clearTimeout(this._collapseTimer);
        this._collapseTimer = setTimeout(() => {
            this._hovering = false;
            this.expanded = this._focused;
            this._collapseTimer = null;
        }, 120);
    },
    expandOnFocus() { this._focused = true; this.expanded = true; },
    collapseOnFocus() { this._focused = false; this.expanded = this._hovering; },

    /**
     * Tick width per heading level — h2 wider than h3 wider than h4,
     * representing nesting visually at minified scale. Drives a
     * Tailwind utility class via x-bind.
     */
    tickWidthClass(level) {
        return ({ 2: 'w-3', 3: 'w-2', 4: 'w-1', 5: 'w-1', 6: 'w-1' })[level] || 'w-1';
    },

    /**
     * Back to the top of the article.
     *
     * A sibling of scrollTo() with no heading to aim at, so it does not touch the hash or put a
     * hold on a section: the mark follows the page up. It scrolls whatever the headings scroll in,
     * which is the window only when nothing else does. It lives here rather than inline because
     * `window` is unreachable from a directive under Alpine's CSP build — the evaluator resolves
     * names against the Alpine scope alone.
     */
    scrollToTop() {
        const root = this.items[0] ? scrollRootOf(this.items[0].el) : null;

        (root ?? window).scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    },

    /**
     * Smooth-scroll to a heading and replace the URL hash without pushing a new history entry —
     * back-button still goes to the previous page rather than the previous heading.
     *
     * The heading lands 8px above the spine's line, so it is unambiguously past it, in whatever
     * the headings scroll in. This moved the window only, which in a page with its own scroll
     * region has nowhere to go: the click moved nothing.
     */
    scrollTo(id, event) {
        if (event) event.preventDefault();
        const el = document.getElementById(id);
        if (!el) return;
        const root = scrollRootOf(el);
        const top = el.getBoundingClientRect().top - (this._line(root) - 8) + (root ? root.scrollTop : window.scrollY);
        const reduced = prefersReducedMotion();
        (root ?? window).scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' });

        // The jump holds its section until the reader moves the page themselves, so the scroll it
        // starts cannot walk the mark through every section on the way. The hold also covers a
        // short last section, whose heading no scroll can bring up to the line.
        this._held = true;

        try {
            history.replaceState(null, '', `#${id}`);
        } catch {
            // Cross-origin iframe-srcdoc — URL hash mirror unavailable
        }

        const idx = this.items.findIndex((it) => it.id === id);
        if (idx >= 0) this.activeIndex = idx;

        // Focus goes where the reader asked to go. `preventDefault()` at the top suppressed
        // the anchor's default, and that default moves TWO things: the scroll and the
        // sequential-navigation starting point. Only the first was replaced here, so a
        // keyboard reader who jumped to a section was returned to the spine on their very
        // next Tab. Shared with reading-toc, which had the identical gap.
        focusHeading(el);
    },

    /**
     * Filter predicate. When the developer composed a filter slot and
     * the input two-way-bound to `this.filter`, this returns whether
     * the item's text matches the search. Empty filter = always show.
     */
    matchesFilter(item) {
        if (!this.filter) return true;
        const needle = this.filter.toLowerCase();
        return item.text.toLowerCase().includes(needle);
    },

    /**
     * Stable URL-friendly slug. Lowercase, ASCII alphanumerics and
     * hyphens only. Empty input falls back to a sequenced "section-N"
     * label.
     */
    slugify(text) {
        return String(text).toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-');
    },
});
