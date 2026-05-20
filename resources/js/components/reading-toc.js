/**
 * Reading-toc Alpine plugin.
 *
 * Drives the `<x-wirekit::reading-toc>` Blade component. Builds a flat TOC
 * from headings inside a target container, renders them as a horizontal
 * strip of links, and tracks the active heading via IntersectionObserver.
 *
 * Sibling to wirekitReadingSpine — same data-collection + activation model,
 * different rendered shape and different defaults (single level, no
 * hover-expand, no per-section fill, no numbering).
 *
 * Options:
 *   target — CSS selector for the container to scan (default 'main, article')
 *   levels — array of heading levels to include (default [2])
 *   offset — pixels of viewport-top "active" detection offset (default 0)
 *
 * Honors `prefers-reduced-motion: reduce` — the scrollTo handler picks
 * 'auto' over 'smooth' when the OS preference is set.
 *
 * Bundle cost: ~1 KB raw / ~450 B gzip.
 */
export default (options = {}) => ({
    target: options.target || 'main, article',
    levels: Array.isArray(options.levels) ? options.levels : [2],
    offset: typeof options.offset === 'number' ? options.offset : 0,

    items: [],
    activeIndex: -1,

    _observer: null,
    _seq: 0,
    // Timestamp until which IO-driven activeIndex recomputes are gated.
    // Set by `scrollTo()` to `Date.now() + 600` during smooth-scroll.
    _programmaticScrollUntil: 0,

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
        if (!container) return [];
        const sel = this.levels.map((l) => `h${l}`).join(', ');
        return Array.from(container.querySelectorAll(sel)).map((el, idx) => ({
            id: el.id || '',
            text: (el.textContent || '').trim(),
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
     * Active heading = the LAST heading whose top is at or above the
     * viewport-top offset line. Same logic as reading-spine. Tall
     * sections (taller than viewport) become active as the user scrolls
     * past their first paragraph rather than waiting for the next
     * heading to enter the viewport.
     */
    observeActive() {
        const rootMargin = `-${this.offset}px 0px -90% 0px`;
        this._observer = new IntersectionObserver(() => {
            // Suppress intermediate flips during a click-driven smooth-scroll.
            // Without this gate the clicked link briefly de-activates as the
            // IO fires for each heading the scroll passes through — visible
            // as a 150-ms flicker on the clicked item.
            if (Date.now() < this._programmaticScrollUntil) return;
            const above = this.items
                .map((it, i) => ({ i, top: it.el.getBoundingClientRect().top }))
                .filter((it) => it.top < this.offset + 1);
            const next = above.length ? above[above.length - 1].i : 0;
            if (next !== this.activeIndex) {
                this.activeIndex = next;
            }
        }, { rootMargin, threshold: 0 });
        this.items.forEach((item) => this._observer.observe(item.el));
    },

    destroy() {
        this._observer?.disconnect();
    },

    /**
     * Smooth-scroll to a heading and replace the URL hash without
     * pushing a new history entry. Honors prefers-reduced-motion.
     *
     * Target math accounts for:
     *   - `offset` prop — pixels of developer chrome ABOVE the TOC
     *     (e.g. a fixed top nav). Empty by default.
     *   - The TOC's OWN height — the strip is `position: sticky;
     *     top: offset`, so it occupies the viewport band from
     *     `offset` to `offset + tocHeight`. A heading scrolled to
     *     viewport-top would land BEHIND the strip; we have to push
     *     the target down by the TOC's own bottom edge.
     *   - A 24px breathing buffer between the TOC bottom and the
     *     heading's top edge. Smaller values (8-16px) felt cramped
     *     in user testing — the heading visually fused with the
     *     strip's bottom border. 24px gives the heading typographic
     *     breathing room without scrolling past the section start.
     *
     * Earlier versions used `... - this.offset + 8` (overshoot — the
     * heading landed 8px ABOVE the viewport-top line) and ignored the
     * TOC's own height entirely. Both reported as "scrolls too far and
     * ignores the TOC bar as an offset".
     */
    scrollTo(id, event) {
        if (event) event.preventDefault();
        const el = document.getElementById(id);
        if (!el) return;
        const tocHeight = this.$el ? this.$el.offsetHeight : 0;
        const top = el.getBoundingClientRect().top + window.scrollY - this.offset - tocHeight - 24;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' });
        // Suppress IO-driven activeIndex flips during the smooth-scroll
        // window so the clicked link stays active without flickering
        // through every heading between origin and destination. 600 ms
        // covers the typical smooth-scroll settle time.
        this._programmaticScrollUntil = Date.now() + 600;
        // Force the visual activeIndex to match the clicked item up-front
        // so the user sees the click register immediately even before the
        // IO catches up.
        const idx = this.items.findIndex((it) => it.id === id);
        if (idx >= 0) this.activeIndex = idx;
        // `history.replaceState` updates the URL hash without a history-
        // stack push so the back button still goes to the previous page.
        // Wrapped in try/catch because iframe-srcdoc contexts have
        // `origin: null` and reject replaceState calls against the
        // parent page's URL with a SecurityError. The smooth-scroll has
        // already happened by this point, so swallowing the URL-sync
        // failure is the correct degradation — the developer still sees
        // the visual jump, just without the URL hash mirror.
        try {
            history.replaceState(null, '', `#${id}`);
        } catch (e) {
            // Cross-origin iframe-srcdoc — URL hash mirror unavailable,
            // accept the scroll-only behavior.
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
