/**
 * Reading-spine helper Alpine plugin.
 *
 * Drives the `<x-wirekit::reading-spine>` Blade component. Builds a TOC
 * from headings inside a target container, renders them as ticks,
 * tracks the active heading via IntersectionObserver, and exposes
 * hover/focus expansion + an optional developer-supplied filter input.
 *
 * Why a separate plugin (not inline x-data): the helper carries enough
 * state and methods (~180 lines) to warrant a Blade-friendly named
 * Alpine component. Mirrors the wirekitAnimate / wirekitStatAnimate
 * pattern already in the bundle. Bundle cost: ~1.5 KB raw / ~600 B gzip.
 *
 * Options:
 *   target       — CSS selector for the container to scan (default 'main, article')
 *   levels       — array of heading levels to include (default [2, 3])
 *   offset       — pixels of viewport-top "active" detection offset (default 96)
 *   numbered     — emit hierarchical numeric labels alongside ticks (default false)
 *   fillSections — fill each tick's background per-section progress (default false)
 *   sectionEvents — debounced section-changed events (default true)
 *
 * Honors `prefers-reduced-motion: reduce` — the CSS layer collapses every
 * width / opacity / colour transition on this component to 0.01ms via the
 * global @media block. The plugin itself never animates anything in JS.
 */
export default (options = {}) => ({
    target: options.target || 'main, article',
    levels: Array.isArray(options.levels) ? options.levels : [2, 3],
    offset: typeof options.offset === 'number' ? options.offset : 96,
    numbered: options.numbered === true,
    fillSections: options.fillSections === true,
    sectionEvents: options.sectionEvents !== false,

    items: [],
    activeIndex: -1,
    expanded: false,
    filter: '',

    _hovering: false,
    _focused: false,
    _observer: null,
    // Debounce timer for collapseOnHover — prevents the rapid expand/
    // collapse flicker when the cursor jitters across the spine's
    // boundary (sub-pixel mouse movements at the top/left edge fire
    // alternating mouseenter/mouseleave events).
    _collapseTimer: null,
    _scrollHandler: null,
    _ticking: false,
    _sectionEventTimer: null,
    _lastDispatchedIndex: -1,
    _seq: 0,
    // Timestamp gate suppressing IO + scroll recomputes during click-driven
    // smooth-scroll. Set by `scrollTo()` to `Date.now() + 600`.
    _programmaticScrollUntil: 0,

    init() {
        this.items = this.collectHeadings();
        this.assignIds(this.items);
        if (this.numbered) this.computeNumbering(this.items);
        this.observeActive();
        if (this.fillSections) {
            this._scrollHandler = () => this.updateSectionFills();
            // Store the bound function ONCE so destroy() can remove the
            // exact same reference. addEventListener+removeEventListener
            // identity-match against the function reference; binding
            // inline created a fresh function each call, so the destroy()
            // removeEventListener was a silent no-op and listeners
            // accumulated across every Livewire morph that re-created
            // this component.
            this._onScrollThrottledBound = this._onScrollThrottled.bind(this);
            window.addEventListener('scroll', this._onScrollThrottledBound, { passive: true });
            window.addEventListener('resize', this._onScrollThrottledBound, { passive: true });
            this.updateSectionFills();
        }
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
                // eslint-disable-next-line no-console
                console.warn(
                    `[wirekit] reading-spine: target selector "${this.target}" matched no element. ` +
                    `Spine will render empty. Check the selector on <x-wirekit::reading-spine target="${this.target}" />.`
                );
            }
            return [];
        }
        const sel = this.levels.map((l) => `h${l}`).join(', ');
        return Array.from(container.querySelectorAll(sel)).map((el, idx) => ({
            id: el.id || '',
            text: (el.textContent || '').trim(),
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

    observeActive() {
        // Active heading = the LAST heading whose top is at or above the
        // viewport-top offset line. Two complementary detection paths,
        // because IntersectionObserver alone misses two real bug classes:
        //
        //   1. Smooth-scroll settle. IO fires on threshold CROSSINGS, not
        //      on position-within-zone changes. After a smooth-scroll lands
        //      the target heading inside the rootMargin zone, no further
        //      IO callback fires — the visual `activeIndex` stays at the
        //      previous heading.
        //   2. Bottom of page. When the page can't scroll further (the
        //      last heading lives inside the last viewport-height of
        //      content), the trailing heading's top never reaches the
        //      offset line. The IO marks the PREVIOUS heading as active
        //      and stays there even when the reader is visually on the
        //      last section.
        //
        // The IO continues to handle the normal mid-page case (cheap, fires
        // once per heading crossed). A `scroll` listener layered on top
        // re-evaluates after smooth-scroll settles and special-cases the
        // at-bottom state.
        const recomputeActive = () => {
            // Gate: while a click-driven smooth-scroll is in flight, ignore
            // intermediate intersection / scroll callbacks. Without the gate
            // the activeIndex ping-pongs through every heading between the
            // current and the target position, producing a visible flicker
            // on the clicked link (each intermediate flip kicks off a
            // 150-200 ms width / background-color transition that doesn't
            // complete before the next flip restarts it).
            if (Date.now() < this._programmaticScrollUntil) return;
            const above = this.items
                .map((it, i) => ({ i, top: it.el.getBoundingClientRect().top }))
                .filter((it) => it.top <= this.offset);
            let next = above.length ? above[above.length - 1].i : 0;

            // Bottom-of-page override — if the viewport bottom has reached
            // the document end (within a 4 px tolerance), force the LAST
            // item active regardless of where its heading sits. Tolerance
            // covers sub-pixel rendering differences.
            const atBottom = (window.scrollY + window.innerHeight) >= (document.documentElement.scrollHeight - 4);
            if (atBottom && this.items.length > 0) {
                next = this.items.length - 1;
            }

            if (next !== this.activeIndex) {
                this.activeIndex = next;
                this._maybeDispatchSectionChange();
            }
        };

        const rootMargin = `-${this.offset}px 0px -90% 0px`;
        this._observer = new IntersectionObserver(() => recomputeActive(), { rootMargin, threshold: 0 });
        this.items.forEach((item) => this._observer.observe(item.el));

        // Scroll-driven settle: re-evaluates ~30 fps on every scroll event
        // so smooth-scroll lands cleanly + the at-bottom override fires.
        let scrollTick = false;
        this._scrollRecompute = () => {
            if (scrollTick) return;
            scrollTick = true;
            requestAnimationFrame(() => {
                scrollTick = false;
                recomputeActive();
            });
        };
        window.addEventListener('scroll', this._scrollRecompute, { passive: true });
        document.addEventListener('scroll', this._scrollRecompute, { passive: true, capture: true });
        window.addEventListener('resize', this._scrollRecompute, { passive: true });
    },

    /**
     * Per-section progress fill. For each heading, compute how far the
     * reader has scrolled THROUGH that section (top of next heading −
     * top of this heading). Stores 0..1 on each item; CSS reads via a
     * style="--reading-spine-fill: NN%" inline binding.
     */
    updateSectionFills() {
        if (!this.fillSections) return;
        const top = window.scrollY + this.offset;
        for (let i = 0; i < this.items.length; i++) {
            const item = this.items[i];
            const next = this.items[i + 1];
            const start = item.el.getBoundingClientRect().top + window.scrollY;
            const end = next ? next.el.getBoundingClientRect().top + window.scrollY : start + window.innerHeight;
            const span = Math.max(1, end - start);
            const progress = Math.max(0, Math.min(1, (top - start) / span));
            item.fill = progress;
        }
    },

    _onScrollThrottled() {
        if (this._ticking) return;
        requestAnimationFrame(() => {
            this.updateSectionFills();
            this._ticking = false;
        });
        this._ticking = true;
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
        this._observer?.disconnect();
        if (this._onScrollThrottledBound) {
            window.removeEventListener('scroll', this._onScrollThrottledBound);
            window.removeEventListener('resize', this._onScrollThrottledBound);
            this._onScrollThrottledBound = null;
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
     * Smooth-scroll to a heading and replace the URL hash without
     * pushing a new history entry — back-button still goes to the
     * previous page rather than the previous heading.
     */
    scrollTo(id, event) {
        if (event) event.preventDefault();
        const el = document.getElementById(id);
        if (!el) return;
        const top = el.getBoundingClientRect().top + window.scrollY - this.offset + 8;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' });
        // Suppress IO / scroll-driven `recomputeActive` callbacks during the
        // smooth-scroll window so the clicked link stays active without
        // ping-ponging through intermediate headings. 600 ms covers the
        // typical smooth-scroll settle time; `prefers-reduced-motion: reduce`
        // collapses scroll to `auto` (instant), the next frame's gate check
        // fails and recomputeActive resumes normal behaviour.
        this._programmaticScrollUntil = Date.now() + 600;
        try {
            history.replaceState(null, '', `#${id}`);
        } catch (e) {
            // Cross-origin iframe-srcdoc — URL hash mirror unavailable
        }
        // Force the visual activeIndex to match the clicked item. The
        // IntersectionObserver callback won't always fire for the LAST
        // heading — short trailing sections can't reach the
        // (rootMargin-top) zone because the document ends first, so
        // the observer keeps the previous heading "active" even though
        // the user scrolled to + clicked the last one. Setting
        // activeIndex here guarantees the spine's visual state matches
        // the click intent regardless of whether the target heading
        // ever enters the active zone.
        const idx = this.items.findIndex((it) => it.id === id);
        if (idx >= 0) this.activeIndex = idx;
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
