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
 *   - _headingChanges (a MutationObserver on the target) — disconnected in
 *     destroy(), and null-guarded in its callback and in its frame callback.
 *   - _refreshRaf (a requestAnimationFrame id) — canceled in destroy().
 *   - _railObserver (a ResizeObserver on the rail's list and links) — disconnected
 *     in destroy(), and null-guarded in its callback.
 *   - _railDraw (one coalesced measurement a frame) — canceled in destroy().
 *   - _railReadyFrame (a requestAnimationFrame id) — canceled in destroy().
 *   - _collapseTimer, _sectionEventTimer (timeouts) — cleared in destroy().
 *
 * Honors `prefers-reduced-motion: reduce` — the global reduced-motion rule in
 * dist/wirekit.css cuts every width / opacity / color transition on this
 * component short. The plugin itself never animates anything in JS.
 */
import { prefersReducedMotion, scrollBehavior } from '../utils/motion.js';
import { focusHeading } from '../utils/focus-heading.js';
import { accessibleText } from '../utils/accessible-text.js';
import { scrollRootOf } from '../utils/scroll-root.js';
import { assignHeadingIds } from '../utils/heading-ids.js';
import { foldForSearch } from '../utils/search-fold.js';
import { isComposing } from '../utils/ime.js';
import { HEADING_CHANGES, changesOutside, sameHeadings, indexInFresh } from '../utils/heading-changes.js';
import { frameCoalesce } from '../utils/frame-coalesce.js';
import { isRendered } from '../utils/rendered.js';

/**
 * The rail's path through the links' text boxes, in the `d` syntax of an SVG path.
 *
 * Each box is `{ x, top, bottom }` relative to the `<nav>`; a missing one, a link the filter hid,
 * is skipped. Each link contributes a straight segment from its top to its bottom at its own x,
 * and every link after the first is joined to the one before by a cubic curve whose two control
 * points sit at the middle of the gap between them: straight where both stand at the same depth,
 * an S-bend inside the gap where the depth changes, so the rail never crosses a title.
 *
 * @param {Array<{x: number, top: number, bottom: number}|undefined>} boxes
 * @returns {string}
 */
export function railPath(boxes) {
    let d = '';
    let previous = null;

    for (const box of boxes) {
        if (! box) {
            continue;
        }

        if (previous) {
            const middle = (previous.bottom + box.top) / 2;
            d += ` C ${previous.x} ${middle} ${box.x} ${middle} ${box.x} ${box.top}`;
        } else {
            d += `M ${box.x} ${box.top}`;
        }

        d += ` L ${box.x} ${box.bottom}`;
        previous = box;
    }

    return d;
}

/**
 * Where a scroll box has to stand so an entry is in view, or `null` when it already is.
 *
 * `top` and `bottom` are the entry's edges in the box's content, `scrollTop` and `height` the box's
 * position and visible height. The box moves no further than it has to, and an entry taller than
 * the box shows its top. Half a pixel either way counts as in view, so a rounded edge does not
 * start a scroll of nothing.
 *
 * @param {number} scrollTop
 * @param {number} height
 * @param {number} top
 * @param {number} bottom
 * @returns {number|null}
 */
export function revealScroll(scrollTop, height, top, bottom) {
    if (top < scrollTop - 0.5) {
        return top;
    }

    if (bottom > scrollTop + height + 0.5) {
        return Math.min(top, bottom - height);
    }

    return null;
}

export default (options = {}) => ({
    target: options.target || 'main, article',
    // A selector for subtrees whose headings are NOT this page's structure — an embedded
    // demo, a sidebar of related links. `null` excludes nothing.
    exclude: options.exclude || null,
    levels: Array.isArray(options.levels) ? options.levels : [2, 3],
    offset: typeof options.offset === 'number' ? options.offset : 96,
    numbered: options.numbered === true,
    fillSections: options.fillSections === true,
    sectionEvents: options.sectionEvents !== false,
    // `layout="rail"`: an outline in the page's flow, with a line through its links and a band
    // on that line at the current section (railPath(), measureRail(), railBand()).
    rail: options.rail === true,
    railD: '',
    railBoxes: [],
    // Bound to `data-rail-ready`, which shows the rails and arms the band's transition one frame
    // after the first measurement, so the first paint places the band without sliding it there.
    railReady: false,
    // Below md a rail is a disclosure, closed at first: bound to `data-rail-open` and to its
    // button's `aria-expanded`.
    railOpen: false,

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

    /**
     * The level the indent and the numbering count from. init() sets it to the shallowest
     * heading the page has; until then, and on a page without one, it is the smallest
     * configured level.
     */
    baseLevel: typeof options.baseLevel === 'number' ? options.baseLevel : 2,

    items: [],
    activeIndex: -1,
    expanded: false,
    filter: '',

    _hovering: false,
    _focused: false,
    // Set by Escape: the reader folded the spine, and it stays folded until the pointer and the
    // focus have both left it.
    _dismissed: false,
    // Debounce timer for collapseOnHover — prevents the rapid expand/
    // collapse flicker when the cursor jitters across the spine's
    // boundary (sub-pixel mouse movements at the top/left edge fire
    // alternating mouseenter/mouseleave events).
    _collapseTimer: null,
    _onScroll: null,
    _onReaderMove: null,
    _scrollRaf: 0,
    _headingChanges: null,
    _refreshRaf: 0,
    _railObserver: null,
    _railDraw: null,
    _railReadyFrame: 0,
    _sectionEventTimer: null,
    _lastDispatchedIndex: -1,
    _seq: 0,
    // True from a jump until the reader moves the page themselves, so the scroll a jump starts
    // cannot walk the mark through every section on the way. A timer would not do: a long smooth
    // scroll outlasts any fixed delay on a busy machine.
    _held: false,

    /**
     * The indent for one entry, by how far its heading sits below the shallowest.
     *
     * Built here rather than in the template for the CSP build's sake — the
     * template's version was a string concatenation carrying a Blade expression,
     * and while concatenation itself parses, keeping the arithmetic beside the
     * tick style is what stops the two from drifting apart. The base level is the
     * one init() takes from the headings, so the indent and the numbering start at
     * the same level.
     */
    linkStyle(item) {
        // The rail indents in its stylesheet rule, by this depth and a token, and gives the link
        // its own block padding there, so nothing inline may set either.
        if (this.rail) {
            return { '--wk-rail-depth': String(Math.max(0, Number(item.level) - this.baseLevel)), textDecoration: 'none' };
        }

        const depth = (Number(item.level) - this.baseLevel) * 0.5;

        return { paddingLeft: `${depth}rem`, paddingTop: '0.125rem', paddingBottom: '0.125rem', textDecoration: 'none' };
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
        const base = { display: 'block', height: 'var(--reading-spine-tick-height)' };

        if (! this.fillSections) {
            return base;
        }

        const pct = Number(item.fill || 0) * 100;

        return { ...base, background: `linear-gradient(to right, var(--reading-spine-color-active) ${pct}%, var(--reading-spine-color-idle) ${pct}%)` };
    },

    /**
     * Whether the spine starts expanded.
     *
     * A method rather than three lines inside init() so the decision can be
     * tested on its own: init() goes on to collect headings and attach
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
        this.baseLevel = this.shallowestLevel(this.items);
        this.assignIds(this.items);
        if (this.numbered) this.computeNumbering(this.items);
        // One scroll listener drives both the active section and the per-section fill (see
        // observeActive()), on the element that actually scrolls: a listener on the window would
        // never move the fill in a page that scrolls inside its own region.
        this.observeActive();
        this.followHeadings();
        if (this.rail) this.watchRail();
    },

    /**
     * Measure the rail whenever its links can have moved, at most once a frame.
     *
     * Not in init() itself: the `x-for` renders the links after it, so the first measurement is
     * the observer's first notification, after layout. The list is observed for a new column
     * width, a crossed `hide-below` or a filter that changes its height, and each link as well
     * (measureRail() adds them): a web font that rewraps two titles, or a filter that swaps one
     * link for another of the same height, leaves the list's size as it was.
     */
    watchRail() {
        const list = this.$root?.querySelector?.('[data-wk-reading-spine-list]');
        if (! list || typeof ResizeObserver !== 'function') return;

        this._railDraw = frameCoalesce(() => this.measureRail());
        this._railObserver = new ResizeObserver(() => {
            // A notification the browser queued before destroy() can arrive after it.
            if (! this._railObserver) return;
            this._railDraw?.schedule();
        });
        this._railObserver.observe(list);
    },

    /**
     * Each rendered link's text box in the `<nav>`'s content, and the rail's path through them.
     *
     * The x is the link's own inline-start edge: the stylesheet indents the link and pads its text
     * clear of the rail, so the script reads where the link starts rather than computing it from
     * a token, and in a right-to-left page that edge is the right one. Boxes are indexed by
     * `data-index`, so a link the filter hides leaves a hole and the band still finds the current
     * entry by its index. Rectangles rather than `offsetTop`, which counts from each link's
     * positioned `li`.
     *
     * From `md` up the outline scrolls inside the `<nav>`, and the rails scroll with it, so the
     * boxes count from the top of its content rather than from what is in view, and from inside
     * its scrollbar, which a right-to-left page puts on the left.
     */
    measureRail() {
        const list = this.$root?.querySelector?.('[data-wk-reading-spine-list]');
        const nav = list?.parentElement;
        if (! nav) return;

        // Where the content's top-left corner is in the viewport right now.
        const origin = nav.getBoundingClientRect();
        const originX = origin.left + nav.clientLeft;
        const originY = origin.top + nav.clientTop - nav.scrollTop;
        const rtl = getComputedStyle(nav).direction === 'rtl';
        const boxes = [];

        list.querySelectorAll('a[data-index]').forEach((link) => {
            this._railObserver?.observe(link);
            if (! isRendered(link)) return;

            const box = link.getBoundingClientRect();
            const style = getComputedStyle(link);
            const edge = (rtl ? box.right : box.left) - originX;

            boxes[Number(link.dataset.index)] = {
                // On the pixel's middle, so a 1px stroke covers one pixel instead of two halves.
                x: Math.round(edge) + (rtl ? -0.5 : 0.5),
                top: box.top - originY + parseFloat(style.paddingTop),
                bottom: box.bottom - originY - parseFloat(style.paddingBottom),
            };
        });

        this.railBoxes = boxes;
        this.railD = railPath(boxes);

        if (! this.railReady && ! this._railReadyFrame && boxes.length > 0) {
            // The first time there are boxes, the outline is placed on the current entry at once:
            // a page opened at a section far down shows its entry, and nothing slides there.
            this.revealActive(false);
            this._railReadyFrame = requestAnimationFrame(() => {
                this._railReadyFrame = 0;
                if (this._railDraw) this.railReady = true;
            });
        }
    },

    /**
     * The band on the rail: a clip of the active rail to the current entry's text box.
     *
     * Four points whose top and bottom edges are the box's, collapsed to a line while no entry is
     * current, so a change of entry keeps the shape and the transition in the stylesheet slides
     * the edges. With `fill-sections` the top edge stays on the first entry the rail shows, so the
     * band fills the line down to the current entry, as far as the reader has come. An object, so
     * the binding merges into the element's style rather than replacing it.
     *
     * @returns {{clipPath: string}}
     */
    railBand() {
        const box = this.railBoxes[this.activeIndex];
        // The boxes have a hole for every entry the filter hides, so the first is the first one set.
        const from = this.fillSections ? this.railBoxes.find((entry) => entry) : box;
        const top = box ? from.top : 0;
        const bottom = box ? box.bottom : 0;

        return { clipPath: `polygon(0 ${top}px, 100% ${top}px, 100% ${bottom}px, 0 ${bottom}px)` };
    },

    /**
     * Scroll the rail's outline so the current entry is in it, when the outline is taller than
     * the window allows.
     *
     * Only the `<nav>` moves, and only as far as it takes (revealScroll()): `scrollIntoView()`
     * would move the page under the reader as well. It glides, and jumps where the reader asked
     * for reduced motion. A reader who scrolls the outline themselves is left there until the
     * current section changes.
     *
     * @param {boolean} [animate=true] - false for the first placement, which nothing should be seen sliding to.
     */
    revealActive(animate = true) {
        const nav = this.$root?.querySelector?.('[data-wk-reading-spine-list]')?.parentElement;
        if (! nav || nav.scrollHeight <= nav.clientHeight) return;

        const link = nav.querySelector(`a[data-index="${this.activeIndex}"]`);
        if (! isRendered(link)) return;

        const box = link.getBoundingClientRect();
        const top = box.top - nav.getBoundingClientRect().top - nav.clientTop + nav.scrollTop;
        const next = revealScroll(nav.scrollTop, nav.clientHeight, top, top + box.height);

        if (next !== null) {
            nav.scrollTo({ top: next, behavior: scrollBehavior(animate && ! prefersReducedMotion()) });
        }
    },

    /** Open or close the rail's outline below md, from its disclosure button. */
    toggleRail() {
        this.railOpen = ! this.railOpen;
    },

    /**
     * Collect the headings again when the page changes under the spine, at most once a frame.
     *
     * A section a Livewire morph adds, removes or renames after init() would otherwise never
     * reach the spine: it would go on listing what is gone, and measure the active section
     * against elements no longer on the page (utils/heading-changes.js).
     */
    followHeadings() {
        // Checked first: without the platform's observer there is nothing to follow with.
        if (typeof MutationObserver !== 'function') return;

        const container = this._container();
        if (!container) return;

        this._headingChanges = new MutationObserver((records) => {
            // A change the browser queued before destroy() can arrive after it.
            if (! this._headingChanges || this._refreshRaf || ! changesOutside(records, this.$el)) return;

            this._refreshRaf = requestAnimationFrame(() => {
                this._refreshRaf = 0;
                if (this._headingChanges) this.refresh();
            });
        });
        this._headingChanges.observe(container, HEADING_CHANGES);
    },

    /**
     * Take a fresh collection of the headings, when it lists other ones than the spine does.
     *
     * The reader is still in the same section, wherever it now stands in the list, so the mark
     * stays on it and no section-changed event announces a move that did not happen.
     */
    refresh() {
        const items = this.collectHeadings();
        if (sameHeadings(items, this.items)) return;

        this.assignIds(items);
        this.baseLevel = this.shallowestLevel(items);
        if (this.numbered) this.computeNumbering(items);

        this.activeIndex = indexInFresh(items, this.items, this.activeIndex);
        this._lastDispatchedIndex = indexInFresh(items, this.items, this._lastDispatchedIndex);
        this.items = items;

        this.updateSectionFills();
        this.recomputeActive();

        // The links the `x-for` renders for the new list are measured once they are there.
        this._railDraw?.schedule();
    },

    /** The element the headings are collected in: the nearest ancestor that matches `target`, else the first match in the document. */
    _container() {
        return this.$el?.closest(this.target) ?? document.querySelector(this.target);
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
        const container = this._container();
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
        // prose brings an accordion's panel titles, a carousel's product names. `target`
        // cannot help, because the demos sit inside the article that holds the real sections.
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
     * Ensure every heading has an `id` so the spine link works as an anchor and the browser's
     * back, forward and scroll restoration work naturally, and give every entry a key of its own
     * for `x-for` (utils/heading-ids.js). An id a heading already carries is kept, even when two
     * headings share it; a generated one never duplicates an id on the page.
     */
    assignIds(items) {
        assignHeadingIds(items, (text) => this.slugify(text), () => ++this._seq);
    },

    /**
     * The shallowest heading level among the entries, which is where the indent and the
     * numbering both start.
     *
     * `levels` names the levels the spine may show, not the ones a page uses. A page with only
     * h3 under `levels="2,3"` has its top entries at h3. They are numbered 1, 2, 3, and they
     * stand without an indent rather than as if an h2 stood above them.
     */
    shallowestLevel(items) {
        return items.length > 0 ? Math.min(...items.map((item) => Number(item.level))) : this.baseLevel;
    },

    /**
     * Compute hierarchical numeric labels for every heading. Top-level
     * headings (lowest level value in the items list) get integer
     * labels (1, 2, 3); nested levels get dotted suffixes (1.1, 1.2,
     * 2.1). Mirrors the legal/spec-document outline convention.
     */
    computeNumbering(items) {
        if (items.length === 0) return;
        const baseLevel = this.shallowestLevel(items);
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
     * Not banded to the window: in a page whose content scrolls inside its own region the
     * document never scrolls, so it is always at its end, and a window-banded observer or an
     * end-of-page rule on the window's scroll would hand the last section every recompute.
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
            if (this.rail) this.revealActive();
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
     * Computed in the scroller's own content space (its top, its scroll position, its height)
     * because the window's are the wrong ones in a page that scrolls inside its own region: the
     * window never moves there, so a fill read from it would never move either.
     */
    updateSectionFills() {
        // The rail fills its line by entry in railBand() and paints no ticks, so it needs no fill
        // within a section, and no work on every frame of a scroll.
        if (!this.fillSections || this.rail || this.items.length === 0) return;
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

        if (this._headingChanges) {
            this._headingChanges.disconnect();
            this._headingChanges = null;
        }

        if (this._refreshRaf) {
            cancelAnimationFrame(this._refreshRaf);
            this._refreshRaf = 0;
        }

        if (this._railObserver) {
            this._railObserver.disconnect();
            this._railObserver = null;
        }

        if (this._railDraw) {
            this._railDraw.cancel();
            this._railDraw = null;
        }

        if (this._railReadyFrame) {
            cancelAnimationFrame(this._railReadyFrame);
            this._railReadyFrame = 0;
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
        this.expanded = !this._dismissed;
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
            this._settle();
            this._collapseTimer = null;
        }, 120);
    },
    expandOnFocus() { this._focused = true; this.expanded = !this._dismissed; },
    /**
     * Focus left an element of the spine.
     *
     * Focus that moves from one entry to the next stays in the spine, so it changes nothing:
     * without the check the spine folded and unfolded between the two, and an Escape that
     * folded it would have been undone by the next Tab.
     *
     * @param {FocusEvent} [event]  focusout, whose relatedTarget is where the focus goes
     */
    collapseOnFocus(event) {
        if (event?.relatedTarget && this.$root?.contains?.(event.relatedTarget)) return;

        this._focused = false;
        this._settle();
    },
    /** Expanded while pointer or focus is in the spine and the reader has not folded it. */
    _settle() {
        if (!this._hovering && !this._focused) this._dismissed = false;

        this.expanded = (this._hovering || this._focused) && !this._dismissed;
    },

    /**
     * Escape folds a spine the pointer or the focus opened, and it stays folded until both have
     * left it.
     *
     * The expanded list lies over the article, and WCAG 1.4.13 asks content shown on hover or
     * focus to be dismissible without moving either. Heard on the window in the capture phase,
     * as the tooltip's Escape is: a pointer moves no focus, so a key bound on the spine would
     * miss the press meant for it. A press that folded the spine is marked handled, so a dialog
     * around it leaves that press alone. A spine that is open because the page opened it, with
     * neither pointer nor focus in it, is not this reader's to dismiss and lets the key pass.
     *
     * @param {KeyboardEvent} [event]
     */
    keydownEscape(event) {
        // An Escape that abandons an input method's conversion belongs to the input method.
        if (isComposing(event)) return;
        if (!this.expanded || !(this._hovering || this._focused)) return;

        event?.preventDefault();

        if (this._collapseTimer) {
            clearTimeout(this._collapseTimer);
            this._collapseTimer = null;
        }

        this._dismissed = true;
        this.expanded = false;
    },

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

        (root ?? window).scrollTo({ top: 0, behavior: scrollBehavior(!prefersReducedMotion()) });
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
        // An entry's key, or an id. Two headings may share an id, and each entry jumps to its own
        // heading rather than to the first element that carries the id.
        const entry = this.items.find((it) => it.key === id);
        const el = entry ? entry.el : document.getElementById(id);
        if (!el) return;
        const root = scrollRootOf(el);
        const top = el.getBoundingClientRect().top - (this._line(root) - 8) + (root ? root.scrollTop : window.scrollY);
        const reduced = prefersReducedMotion();
        (root ?? window).scrollTo({ top, behavior: scrollBehavior(!reduced) });

        // The jump holds its section until the reader moves the page themselves, so the scroll it
        // starts cannot walk the mark through every section on the way. The hold also covers a
        // short last section, whose heading no scroll can bring up to the line.
        this._held = true;

        try {
            history.replaceState(null, '', `#${entry ? entry.id : id}`);
        } catch {
            // Cross-origin iframe-srcdoc — URL hash mirror unavailable
        }

        const idx = entry ? this.items.indexOf(entry) : this.items.findIndex((it) => it.id === id);
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
        // Accents folded away on both sides, so `munchen` finds `München` (utils/search-fold.js).
        const needle = foldForSearch(this.filter);
        return foldForSearch(item.text).includes(needle);
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
