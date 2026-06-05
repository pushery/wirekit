import { sanitizeMinimapHtml } from '../utils/sanitize-minimap-html.js';

/**
 * Reading-minimap — every-item density overview + literal page-preview
 * Alpine plugin.
 *
 * Drives the `<x-wirekit::reading-minimap>` Blade component. Two
 * rendering modes:
 *
 *   stripes  (default) — every item matched by `itemSelector` renders
 *     as a colored stripe in a fixed-width column. Lightweight, no
 *     iframe.
 *
 *   rendered — abstract content-texture canvas (code-editor-style
 *     minimap). Walks the source DOM via TreeWalker, draws per-line
 *     rectangles on a DPR-aware canvas at minimap scale. Lazy-init via
 *     IntersectionObserver so the canvas isn't constructed until the
 *     minimap scrolls into view.
 *
 * Four optional extensions layer on top of either mode:
 *
 *   hoverPreview     — magnified 25%-scale popover near cursor on
 *                      pointermove. Re-uses the wirekitFloatingPosition
 *                      pattern (no second Floating-UI import).
 *   showBookmarks    — reads from localStorage on init + listens for
 *                      the `wirekit:reading-bookmark:saved` event +
 *                      the browser `storage` event. Renders a marker
 *                      line at the saved offset percentage.
 *   headingAnchors   — walks the source DOM for h2/h3 (configurable),
 *                      emits real <a href="#id"> anchors on the
 *                      outboard edge. Density-guard collapses
 *                      overlapping labels (< 16 px gap) to avoid
 *                      visual noise; collapsed labels surface on
 *                      minimap hover.
 *   autoFadeIdle     — fades to --reading-minimap-idle-opacity after
 *                      3 s of inactivity. Hover overrides.
 *
 * Two canonical use cases (mount-context-agnostic):
 *   1. Long-form article density-overview — sibling to reading-spine.
 *   2. Sidebar navigation density-overview.
 *
 * Interaction model:
 *   - Click stripe / anchor → smooth-scroll target so the item is
 *     centered. Honors prefers-reduced-motion (instant).
 *   - Drag overlay → pan target's scroll position proportionally.
 *   - Hover stripe (non-touch) → tooltip with item title.
 *   - In rendered mode, click anywhere on the canvas → scroll to the
 *     proportional position.
 */

const RENDERED_MAX_TAGS = 5000;
const RENDERED_MUTATION_DEBOUNCE_MS = 250;
const IDLE_RESET_EVENTS = ['pointermove', 'scroll', 'pointerdown'];

export default (options = {}) => ({
    target: options.target || null,
    itemSelector: options.itemSelector || 'a, h2, h3, [data-minimap-item]',
    side: options.side === 'left' ? 'left' : 'right',
    draggable: options.draggable !== false,

    // Rendered mode + extension props
    mode: options.mode === 'rendered' ? 'rendered' : 'stripes',
    itemStyle: options.itemStyle === 'block' ? 'block' : 'line',
    renderTarget: options.renderTarget || null,
    hoverPreview: options.hoverPreview === true,
    showBookmarks: options.showBookmarks !== false,
    headingAnchors: options.headingAnchors === true,
    headingLevels: Array.isArray(options.headingLevels) && options.headingLevels.length > 0
        ? options.headingLevels
        : [2, 3],
    autoFadeIdle: options.autoFadeIdle !== false,

    // Stripe-mode state (preserved from v1)
    items: [],
    activeIndex: -1,
    viewportTop: 0,
    viewportHeight: 0,
    tooltipText: '',
    tooltipTop: 0,

    // Rendered-mode state
    _renderedReady: false,

    // Extension E2 — bookmark marker
    bookmarkPct: null,

    // Extension E3 — heading anchors
    headingAnchorsList: [],

    // Extension E4 — auto-fade idle
    idle: false,

    // Internal handles + observers
    _scrollHost: null,
    _renderHost: null,
    _resizeObserver: null,
    _scrollHandler: null,
    _ticking: false,
    _dragging: false,
    _dragStartY: 0,
    _dragStartScroll: 0,
    _priorUserSelect: '',
    _scrollEaseTarget: 0,
    _scrollEaseRaf: 0,
    _intersectionObserver: null,
    _mutationObserver: null,
    _minimapResizeObserver: null,
    _mutationDebounceTimer: 0,
    _idleTimer: 0,
    _idleResetHandler: null,
    _bookmarkKey: null,
    _bookmarkStorageHandler: null,
    _bookmarkEventHandler: null,
    _hoverPreviewHandler: null,
    _hoverPreviewLeaveHandler: null,
    _hoverPreviewEscapeHandler: null,

    init() {
        this._scrollHost = this.resolveTarget();
        if (!this._scrollHost) return;

        this._renderHost = this.renderTarget
            ? document.querySelector(this.renderTarget)
            : this._scrollHost;

        this.collectItems();
        this.updateViewport();

        this._scrollHandler = () => this.handleScroll();
        this._scrollHost.addEventListener('scroll', this._scrollHandler, { passive: true });
        if (this._scrollHost === document.documentElement) {
            window.addEventListener('scroll', this._scrollHandler, { passive: true });
        }

        if (typeof ResizeObserver !== 'undefined') {
            this._resizeObserver = new ResizeObserver(() => {
                this.collectItems();
                this.updateViewport();
                if (this._renderedReady) this._scheduleRebuild();
                if (this.headingAnchors) this._collectHeadingAnchors();
            });
            this._resizeObserver.observe(this._scrollHost);
            // Also observe the minimap wrapper itself so a viewport
            // resize that changes the minimap's available height
            // triggers a scale recompute. Without this, a window
            // resize would leave the source rendered at the
            // pre-resize scale until the next scroll-driven rebuild.
            this._minimapResizeObserver = new ResizeObserver(() => {
                if (this._renderedReady) this._scheduleRebuild();
            });
            this._minimapResizeObserver.observe(this.$el);
        }

        if (this.mode === 'rendered') {
            this._initRenderedMode();
        }
        if (this.headingAnchors) {
            this._collectHeadingAnchors();
        }
        if (this.showBookmarks) {
            this._initBookmarkSync();
        }
        if (this.hoverPreview) {
            this._initHoverPreview();
        }
        if (this.autoFadeIdle) {
            this._initIdleFade();
        }
    },

    /**
     * Resolve the target prop into a scrollable element.
     * null → nearest scrollable ancestor of the minimap mount point;
     *        falls back to documentElement (whole-page scroll).
     */
    resolveTarget() {
        if (this.target) {
            const el = document.querySelector(this.target);
            if (!el) {
                // The minimap's `target` prop is `null` by default — only
                // a developer-supplied selector reaches this branch. A
                // miss is therefore always a typo worth surfacing.
                // eslint-disable-next-line no-console
                console.warn(
                    `[wirekit] reading-minimap: target selector "${this.target}" matched no element. ` +
                    `Minimap will not render. Check the selector on <x-wirekit::reading-minimap target="${this.target}" />.`
                );
            }
            return el;
        }
        let el = this.$el?.parentElement;
        while (el && el !== document.body) {
            const overflow = getComputedStyle(el).overflowY;
            if (overflow === 'auto' || overflow === 'scroll') {
                return el;
            }
            el = el.parentElement;
        }
        return document.documentElement;
    },

    collectItems() {
        if (!this._scrollHost) return;
        const matches = this._scrollHost.querySelectorAll(this.itemSelector);
        const hostScrollHeight = this._scrollHost.scrollHeight;
        const items = [];
        matches.forEach((el) => {
            const rect = el.getBoundingClientRect();
            const hostRect = this._scrollHost.getBoundingClientRect();
            const top = rect.top - hostRect.top + this._scrollHost.scrollTop;
            const label = el.dataset?.minimapLabel || (el.textContent || '').trim().slice(0, 80);
            // heightFraction — only used in stripe-mode `itemStyle="block"`.
            // The Blade template reads it inline to render each stripe as a
            // skeleton-style rectangle whose height matches the source
            // item's natural height (in fraction-of-scrollHeight terms),
            // producing a content-texture view instead of a sparse list
            // of equal-height lines. Clamped to a sane min so very-short
            // items (single-line links) stay visible.
            const heightFraction = hostScrollHeight > 0
                ? Math.max(0.005, rect.height / hostScrollHeight)
                : 0.005;
            items.push({
                el,
                top,
                fraction: hostScrollHeight > 0 ? top / hostScrollHeight : 0,
                heightFraction,
                label,
            });
        });
        this.items = items;
    },

    handleScroll() {
        if (this._ticking) return;
        requestAnimationFrame(() => {
            this.updateViewport();
            this._ticking = false;
        });
        this._ticking = true;
    },

    updateViewport() {
        if (!this._scrollHost) return;
        const host = this._scrollHost;
        const scrollTop = host === document.documentElement ? window.scrollY : host.scrollTop;
        const scrollHeight = host.scrollHeight;
        const clientHeight = host === document.documentElement ? window.innerHeight : host.clientHeight;
        if (scrollHeight === 0) return;

        // No-scroll hide: when content fits in the viewport (no scrollable
        // range), suppress the viewport-overlay rectangle. The "you are
        // here" affordance is meaningless when the entire article is
        // already visible — and at that point the proportional math
        // produces an overlay that covers ~90%+ of the minimap, which
        // reads as "I am in the middle" even at scrollTop=0. The CSS
        // template renders the overlay with `height: viewportHeight px`,
        // so a 0 value collapses the element to invisible.
        //
        // Threshold: 2px slack so sub-pixel rounding (scrollHeight =
        // clientHeight + 1 due to integer-vs-fractional measurements)
        // still suppresses correctly.
        if (scrollHeight - clientHeight <= 2) {
            this.viewportTop = 0;
            this.viewportHeight = 0;
            // Active-index detection still runs below — stripes still
            // highlight as the reader's gaze crosses headings even
            // without an overlay.
        } else {
            const minimapHeight = this.$el.clientHeight;
            const ratio = minimapHeight / scrollHeight;
            this.viewportTop = scrollTop * ratio;
            this.viewportHeight = clientHeight * ratio;
        }

        const center = scrollTop + clientHeight / 2;
        let active = -1;
        for (let i = 0; i < this.items.length; i++) {
            if (this.items[i].top <= center) {
                active = i;
            } else {
                break;
            }
        }
        this.activeIndex = active;
    },

    scrollToItem(item) {
        if (!this._scrollHost) return;
        const host = this._scrollHost;
        const clientHeight = host === document.documentElement ? window.innerHeight : host.clientHeight;
        const targetScroll = Math.max(0, item.top - clientHeight / 2);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (host === document.documentElement) {
            window.scrollTo({ top: targetScroll, behavior: reducedMotion ? 'auto' : 'smooth' });
        } else {
            host.scrollTo({ top: targetScroll, behavior: reducedMotion ? 'auto' : 'smooth' });
        }
    },

    showTooltip(item, event) {
        this.tooltipText = item.label;
        // Position the tooltip next to the cursor's actual Y, then keep
        // it tracking via trackTooltip() on mousemove. Cursor-Y is the
        // most intuitive behavior — the tooltip appears next to whatever
        // the user is pointing at, not at some computed mid-point of a
        // stripe that could be many pixels away (especially in block-mode
        // where each stripe's height tracks its source paragraph and can
        // span 80–150 px, putting the geometric center far from the
        // pointer). mousemove tracking also obsoletes the old "stuck at
        // top" failure mode where mouseenter fired with the cursor at
        // the stripe's top edge — the first mousemove corrects it.
        this._positionTooltip(event);
    },

    trackTooltip(event) {
        if (!this.tooltipText) return;
        this._positionTooltip(event);
    },

    _positionTooltip(event) {
        if (!this.$el) return;
        const wrapperRect = this.$el.getBoundingClientRect();
        const y = event.clientY - wrapperRect.top;
        // Clamp inside the wrapper so the tooltip can't escape the column
        // bounds when the pointer skirts the top/bottom padding.
        this.tooltipTop = Math.max(0, Math.min(y, wrapperRect.height));
    },

    hideTooltip() {
        this.tooltipText = '';
    },

    /**
     * Pointer-down on the viewport overlay starts a drag-pan gesture.
     */
    startDrag(event) {
        if (!this.draggable) return;
        event.preventDefault();
        this._dragging = true;
        this._dragStartY = event.clientY;
        this._dragStartScroll = this._scrollHost === document.documentElement
            ? window.scrollY
            : this._scrollHost.scrollTop;
        this._priorUserSelect = document.body.style.userSelect;
        document.body.style.userSelect = 'none';
        this.$el.setPointerCapture?.(event.pointerId);
    },

    moveDrag(event) {
        if (!this._dragging) return;
        const host = this._scrollHost;
        const scrollHeight = host.scrollHeight;
        // Use the scroll host's clientHeight (= the native browser
        // scrollbar's track length) as the drag-translation denominator
        // — NOT the minimap's own clientHeight. Reason: users compare
        // minimap-drag feel against the browser scrollbar drag-feel,
        // and if the two ratios differ they perceive the minimap as
        // "scrolling faster" (the minimap column is shorter than the
        // viewport — top:1rem; bottom:1rem subtracts 32 px, plus any
        // developer-side padding/border). Matching the scrollbar's
        // translation gives consistent muscle memory across both
        // surfaces. Trade-off: the overlay's center lags the finger
        // slightly during drag (by 1 - minimapHeight/clientHeight) —
        // same as the browser scrollbar's own thumb-vs-finger lag, and
        // the scroll handler re-anchors the overlay to its proportional
        // position on the next frame so the visual end-state is correct.
        const clientHeight = host === document.documentElement ? window.innerHeight : host.clientHeight;
        if (clientHeight === 0 || scrollHeight === 0) return;
        const ratio = scrollHeight / clientHeight;
        const delta = (event.clientY - this._dragStartY) * ratio;
        const targetScroll = Math.max(0, this._dragStartScroll + delta);
        // `behavior: 'instant'` (CSSOM spec 2023+) overrides developer-side
        // CSS `scroll-behavior: smooth` on html/body — without the
        // override, modern browsers ease every imperative scroll over
        // ~400 ms, which the user perceived as the drag "lag/delay".
        // No synchronous viewportTop write here; the scroll handler
        // updates the overlay one frame later. The earlier sync-write
        // pattern produced a visible flicker (two writes per frame
        // with sub-pixel differences).
        if (host === document.documentElement) {
            window.scrollTo({ top: targetScroll, behavior: 'instant' });
        } else {
            host.scrollTo({ top: targetScroll, behavior: 'instant' });
        }
    },

    endDrag(event) {
        if (!this._dragging) return;
        this._dragging = false;
        document.body.style.userSelect = this._priorUserSelect ?? '';
        this.$el.releasePointerCapture?.(event.pointerId);
    },

    /**
     * Rendered-mode lazy-init. Sets up an IntersectionObserver that
     * defers canvas construction until the minimap is in the viewport.
     * Saves cold-start cost on pages where the minimap is below the
     * fold (rare but real — long-form articles often mount the
     * minimap inside an article element that itself starts below the
     * fold).
     */
    _initRenderedMode() {
        // If IntersectionObserver isn't available (impossible per our
        // Tailwind v4 baseline, but defense-in-depth), build immediately.
        if (typeof IntersectionObserver === 'undefined') {
            this._buildCanvas();
            return;
        }
        this._intersectionObserver = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                // Null-guard against post-destroy fire — Alpine teardown may
                // have nulled this._intersectionObserver via destroy() before
                // the browser-queued callback executes. Same race class as
                // wirekitStatAnimate / wirekitAnimate's IO callbacks.
                if (! this._intersectionObserver) return;
                this._intersectionObserver.disconnect();
                this._intersectionObserver = null;
                this._buildCanvas();
                this._initMutationObserver();
            }
        }, { rootMargin: '50px' });
        this._intersectionObserver.observe(this.$el);
    },

    /**
     * Build the code-editor-style minimap canvas. Walks every
     * text-bearing node in the source, gets per-rendered-line bounding
     * rects via Range.getClientRects(), and draws each rect onto a
     * scaled-down canvas. Element type → color family (heading /
     * prose / code / default). The result is an abstract content-
     * texture map, not a literal scaled clone — the editor-minimap
     * convention readers know from IDEs.
     *
     * This replaces an earlier iframe-srcdoc-clone approach that
     * scaled the live DOM via `transform: scale(0.1)`. The clone
     * approach produced a tiny LEGIBLE preview that surprised users
     * (minimap convention is abstract blocks, not readable text),
     * and the iframe added ~100 ms of cold-start cost on long
     * articles. Canvas is a single rAF-fast pass, no iframe, no
     * sanitizer, no cross-origin stylesheet plumbing.
     */
    _buildCanvas() {
        if (!this._renderHost) return;
        const wrapper = this.$refs?.renderedContainer;
        if (!wrapper) return;

        const source = this._renderHost;
        const sourceRect = source.getBoundingClientRect();
        const sourceWidth = source.offsetWidth || sourceRect.width;
        const sourceHeight = source.scrollHeight || sourceRect.height;
        if (sourceWidth <= 0 || sourceHeight <= 0) return;

        // Tag-count density check — above the threshold we fall back to
        // stripe mode silently (memory + perf budget). The code-editor-style
        // canvas walks every text node + builds a Range per node, so the
        // cost is O(textNodes) rather than O(htmlSize). 5000-tag threshold
        // ports from the iframe-clone path; comparable enough.
        const tagCount = source.querySelectorAll('*').length;
        if (tagCount > RENDERED_MAX_TAGS) {
            // eslint-disable-next-line no-console
            console.warn(`[wirekit] reading-minimap: source DOM has ${tagCount} tags (>${RENDERED_MAX_TAGS}), falling back to stripe mode.`);
            this.mode = 'stripes';
            this.$el.setAttribute('data-mode', 'stripes');
            return;
        }

        const minimapWidth = wrapper.clientWidth;
        const minimapHeight = wrapper.clientHeight;
        if (minimapWidth <= 0 || minimapHeight <= 0) return;

        // Two independent scale factors. The minimap is a fixed-aspect
        // column (typically 60-80px wide, viewport-tall) while the source
        // article is typically much wider than tall — so we DON'T enforce
        // aspect ratio. Horizontal scale: makes the widest text line fit
        // the minimap width. Vertical scale: makes the whole article fit
        // the minimap height.
        const scaleX = minimapWidth / sourceWidth;
        const scaleY = minimapHeight / sourceHeight;

        // DPR-aware canvas — keeps the small rectangles crisp on retina.
        // Canvas backing-store dimensions are physical pixels; CSS
        // dimensions are logical pixels. The ctx scale aligns drawing
        // calls to logical pixels.
        const dpr = window.devicePixelRatio || 1;
        const canvas = document.createElement('canvas');
        canvas.className = 'wk-reading-minimap__rendered-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        canvas.width = Math.max(1, Math.round(minimapWidth * dpr));
        canvas.height = Math.max(1, Math.round(minimapHeight * dpr));
        canvas.style.width = minimapWidth + 'px';
        canvas.style.height = minimapHeight + 'px';
        canvas.style.display = 'block';
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        // Element-type → color palette. Each category is overridable
        // via a CSS custom property on the minimap root, so developers
        // can re-theme without touching JS. Defaults are alpha-modulated
        // so the canvas reads as a translucent texture over whatever
        // background the surrounding minimap surface paints.
        //
        // Walk-priority (first match wins, walking UP from each text
        // node toward the source root):
        //   1. code        (<pre>, <code>, <kbd>, <samp>)
        //   2. heading     (h1..h6 — each gets its own color)
        //   3. table       (<table>, <td>, <th>, <caption>)
        //   4. blockquote
        //   5. prose       (<p>, <li>, <dd>, <dt>, <figcaption>)
        //   6. wirekit     (any [class*="wk-"] except wk-reading-* —
        //                   to keep the minimap's own DOM out of the map)
        //   7. default     (anything unrecognised)
        //
        // Image-like elements (<img>, <figure>, <picture>, <svg>,
        // <video>, <canvas>) carry no text and are handled in a
        // separate pass below — they draw the element's bounding-rect
        // as a single block.
        const cs = getComputedStyle(this.$el);
        const tok = (name, fallback) => {
            const v = cs.getPropertyValue(name).trim();
            return v || fallback;
        };
        const STYLES = {
            h1: tok('--reading-minimap-color-h1', 'rgba(15, 23, 42, 0.85)'),
            h2: tok('--reading-minimap-color-h2', 'rgba(15, 23, 42, 0.75)'),
            h3: tok('--reading-minimap-color-h3', 'rgba(15, 23, 42, 0.65)'),
            h4: tok('--reading-minimap-color-h4', 'rgba(15, 23, 42, 0.55)'),
            h5: tok('--reading-minimap-color-h5', 'rgba(15, 23, 42, 0.48)'),
            h6: tok('--reading-minimap-color-h6', 'rgba(15, 23, 42, 0.42)'),
            code: tok('--reading-minimap-color-code', 'rgba(99, 102, 241, 0.55)'),
            table: tok('--reading-minimap-color-table', 'rgba(244, 63, 94, 0.50)'),
            blockquote: tok('--reading-minimap-color-blockquote', 'rgba(71, 85, 105, 0.55)'),
            prose: tok('--reading-minimap-color-prose', 'rgba(71, 85, 105, 0.42)'),
            wirekit: tok('--reading-minimap-color-wirekit', 'rgba(16, 185, 129, 0.50)'),
            image: tok('--reading-minimap-color-image', 'rgba(245, 158, 11, 0.55)'),
            default: tok('--reading-minimap-color-default', 'rgba(71, 85, 105, 0.32)'),
        };

        const CODE_TAGS = new Set(['PRE', 'CODE', 'KBD', 'SAMP']);
        const HEADING_TAGS = new Set(['H1', 'H2', 'H3', 'H4', 'H5', 'H6']);
        const TABLE_TAGS = new Set(['TABLE', 'TD', 'TH', 'CAPTION']);
        const BLOCKQUOTE_TAGS = new Set(['BLOCKQUOTE']);
        const PROSE_TAGS = new Set(['P', 'LI', 'DD', 'DT', 'FIGCAPTION']);
        const IMAGE_TAGS = ['img', 'picture', 'figure', 'svg', 'video', 'canvas'];

        function classify(textNode) {
            let p = textNode.parentElement;
            while (p && p !== source) {
                const tag = p.tagName;
                if (!tag) { p = p.parentElement; continue; }
                if (CODE_TAGS.has(tag)) return 'code';
                if (HEADING_TAGS.has(tag)) return tag.toLowerCase();
                if (TABLE_TAGS.has(tag)) return 'table';
                if (BLOCKQUOTE_TAGS.has(tag)) return 'blockquote';
                if (PROSE_TAGS.has(tag)) return 'prose';
                // WireKit wildcard — any wk-* class EXCEPT wk-reading-*
                // (the minimap shares wk-reading-* prefixes with the
                // family siblings; recursing into the minimap's own
                // DOM would draw the wrapper element itself).
                if (p.classList && p.classList.length) {
                    let hasWkComponent = false;
                    let hasReading = false;
                    for (const c of p.classList) {
                        if (c.startsWith('wk-reading-')) hasReading = true;
                        else if (c.startsWith('wk-')) hasWkComponent = true;
                    }
                    if (hasWkComponent && !hasReading) return 'wirekit';
                }
                p = p.parentElement;
            }
            return 'default';
        }

        // Reset + paint a faint background so the canvas reads as a
        // discrete surface even when source is short and most of the
        // canvas is empty.
        ctx.clearRect(0, 0, minimapWidth, minimapHeight);

        // Walk text nodes. NodeFilter rejects whitespace-only nodes so
        // empty text between tags doesn't pollute the map.
        const walker = document.createTreeWalker(source, NodeFilter.SHOW_TEXT, {
            acceptNode: (n) => (n.nodeValue && n.nodeValue.trim().length > 0)
                ? NodeFilter.FILTER_ACCEPT
                : NodeFilter.FILTER_REJECT,
        });

        const sourceScrollY = sourceRect.top + (window.scrollY || document.documentElement.scrollTop);
        const sourceScrollX = sourceRect.left + (window.scrollX || document.documentElement.scrollLeft);

        let textNode;
        while ((textNode = walker.nextNode())) {
            const type = classify(textNode);
            ctx.fillStyle = STYLES[type] || STYLES.default;

            const range = document.createRange();
            range.selectNodeContents(textNode);
            const rects = range.getClientRects();
            for (let i = 0; i < rects.length; i++) {
                const rect = rects[i];
                if (rect.width === 0 || rect.height === 0) continue;

                // Convert viewport-coordinates to source-relative coords.
                // (rect.top + scrollY) is the absolute document Y; subtract
                // the source's absolute Y to get position WITHIN the source.
                const srcY = (rect.top + (window.scrollY || document.documentElement.scrollTop)) - sourceScrollY;
                const srcX = (rect.left + (window.scrollX || document.documentElement.scrollLeft)) - sourceScrollX;

                // Apply minimap scale.
                const x = srcX * scaleX;
                const y = srcY * scaleY;
                const w = Math.max(1, rect.width * scaleX);
                // Per-line height — at least 1px so very small scaleY
                // doesn't render zero-height invisible lines. Two-pixel
                // ceiling avoids fat horizontal stripes when scaleY is
                // generous on short articles.
                const h = Math.max(1, Math.min(2, rect.height * scaleY));

                ctx.fillRect(x, y, w, h);
            }
            range.detach?.();
        }

        // Second pass: visual-empty elements (images, figures, SVGs,
        // videos, canvases). These carry no walkable text, so the text-
        // node walker above misses them. Draw each as a single rect
        // with the image color so the minimap reflects content that
        // is visually significant even when it has no characters.
        const visualSelector = IMAGE_TAGS.join(',');
        const visualElements = source.querySelectorAll(visualSelector);
        ctx.fillStyle = STYLES.image;
        for (const el of visualElements) {
            // Skip SVGs that are NESTED inside another image-tag element
            // — e.g. an <svg> inside a <figure> already gets covered by
            // the figure's bounding rect, drawing both would double-paint.
            // Use the outermost image-tag wrapper.
            let outermost = el;
            let p = el.parentElement;
            while (p && p !== source) {
                if (IMAGE_TAGS.includes(p.tagName.toLowerCase())) {
                    outermost = p;
                }
                p = p.parentElement;
            }
            if (outermost !== el) continue;

            const rect = el.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) continue;
            const srcY = (rect.top + (window.scrollY || document.documentElement.scrollTop)) - sourceScrollY;
            const srcX = (rect.left + (window.scrollX || document.documentElement.scrollLeft)) - sourceScrollX;
            const x = srcX * scaleX;
            const y = srcY * scaleY;
            const w = Math.max(2, rect.width * scaleX);
            const h = Math.max(2, rect.height * scaleY);
            ctx.fillRect(x, y, w, h);
        }

        wrapper.innerHTML = '';
        wrapper.appendChild(canvas);

        this._renderedReady = true;
    },

    /**
     * MutationObserver — re-render the iframe when the source DOM
     * mutates. Debounced 250 ms so a flurry of related mutations (e.g.
     * Livewire morph) collapses to one rebuild. Filters
     * attribute-only mutations (style / class changes during animations)
     * to keep the rebuild cost low.
     */
    _initMutationObserver() {
        if (typeof MutationObserver === 'undefined' || !this._renderHost) return;
        this._mutationObserver = new MutationObserver((mutations) => {
            const meaningful = mutations.some((m) => m.type === 'childList' && (m.addedNodes.length > 0 || m.removedNodes.length > 0));
            if (meaningful) this._scheduleRebuild();
        });
        this._mutationObserver.observe(this._renderHost, {
            childList: true,
            subtree: true,
            attributes: false,
            characterData: false,
        });
    },

    _scheduleRebuild() {
        // Short-circuit when we've already fallen back to stripes mode
        // (the tag-count guard in _buildCanvas() set mode = 'stripes').
        // Re-running _buildCanvas would re-fire the console.warn on
        // every ResizeObserver / MutationObserver tick — noisy and
        // useless.
        if (this.mode !== 'rendered') return;
        if (this._mutationDebounceTimer) clearTimeout(this._mutationDebounceTimer);
        this._mutationDebounceTimer = setTimeout(() => {
            this._buildCanvas();
        }, RENDERED_MUTATION_DEBOUNCE_MS);
    },

    /**
     * Extension E2 — bookmark marker. Reads the existing reading-bookmark
     * localStorage payload on init + listens for the custom event the
     * bookmark dispatches on save + the browser storage event for
     * cross-tab consistency.
     *
     * Bookmark key resolution: looks for a sibling reading-bookmark in
     * the same scroll-host's ancestor chain via the
     * `data-reading-bookmark-key` attribute the bookmark blade writes.
     */
    _initBookmarkSync() {
        this._bookmarkKey = this._resolveBookmarkKey();
        if (!this._bookmarkKey) return;
        this._readBookmark();
        this._bookmarkStorageHandler = (e) => {
            if (e.key === this._bookmarkKey) this._readBookmark();
        };
        this._bookmarkEventHandler = (e) => {
            if (e.detail?.key === this._bookmarkKey) this._readBookmark();
        };
        window.addEventListener('storage', this._bookmarkStorageHandler);
        window.addEventListener('wirekit:reading-bookmark:saved', this._bookmarkEventHandler);
        window.addEventListener('wirekit:reading-bookmark:cleared', this._bookmarkEventHandler);
    },

    _resolveBookmarkKey() {
        const host = this._scrollHost === document.documentElement ? document.body : this._scrollHost;
        if (!host) return null;
        const el = host.querySelector('[data-reading-bookmark-key]')
            || document.querySelector('[data-reading-bookmark-key]');
        return el?.getAttribute('data-reading-bookmark-key') || null;
    },

    _readBookmark() {
        if (!this._bookmarkKey) return;
        try {
            const raw = localStorage.getItem(this._bookmarkKey);
            if (!raw) {
                this.bookmarkPct = null;
                return;
            }
            const data = JSON.parse(raw);
            const top = data?.top;
            if (typeof top !== 'number' || top <= 0) {
                this.bookmarkPct = null;
                return;
            }
            const scrollHeight = this._scrollHost.scrollHeight;
            this.bookmarkPct = scrollHeight > 0 ? Math.max(0, Math.min(1, top / scrollHeight)) : null;
        } catch (_) {
            this.bookmarkPct = null;
        }
    },

    /**
     * Extension E3 — heading anchors. Walks the source target for
     * h2/h3 (or whatever headingLevels CSV resolves to), emits each
     * heading as a clickable anchor at its proportional position.
     *
     * Density guard: anchors whose top would land within 16 px of the
     * previous anchor get `collapsed: true` and are hidden via CSS
     * until the minimap is hovered (`.wk-reading-minimap:hover
     * .wk-reading-minimap__anchor[data-collapsed]`).
     */
    _collectHeadingAnchors() {
        if (!this._renderHost) return;
        // Anchors deliberately scope to _renderHost (the canvas source),
        // not _scrollHost. When `renderTarget` is unset they're identical
        // (both default to the article container). When `renderTarget`
        // narrows to a sub-element (e.g. `renderTarget="article"` while
        // `target="#scrollable-wrapper"`), headings OUTSIDE the article
        // but INSIDE the scroll container are intentionally dropped —
        // the rendered-mode canvas shows the article only, so anchors
        // should match that scope. Developers wanting full-host anchor
        // coverage should leave `renderTarget` undefined.
        const sel = this.headingLevels.map((l) => `h${l}`).join(', ');
        const headings = Array.from(this._renderHost.querySelectorAll(sel));
        const hostRect = this._scrollHost.getBoundingClientRect();
        const scrollHeight = this._scrollHost.scrollHeight;
        const minimapHeight = this.$el.clientHeight || 1;
        const collapseThresholdPx = 16;
        let lastPx = -Infinity;
        const anchors = headings
            .filter((h) => !!h.id || !!h.textContent?.trim())
            .map((h, i) => {
                // Ensure every heading has an id for the anchor href to work.
                if (!h.id) h.id = this._slugifyHeading(h.textContent || `section-${i + 1}`);
                const rect = h.getBoundingClientRect();
                const top = rect.top - hostRect.top + this._scrollHost.scrollTop;
                const fraction = scrollHeight > 0 ? top / scrollHeight : 0;
                const px = fraction * minimapHeight;
                const collapsed = px - lastPx < collapseThresholdPx;
                if (!collapsed) lastPx = px;
                return {
                    id: h.id,
                    label: (h.textContent || '').trim().slice(0, 18) + ((h.textContent || '').trim().length > 18 ? '…' : ''),
                    fraction,
                    collapsed,
                };
            });
        this.headingAnchorsList = anchors;
    },

    _slugifyHeading(text) {
        return String(text).toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .slice(0, 60) || `section-${Date.now()}`;
    },

    scrollToAnchor(anchor, event) {
        event?.preventDefault?.();
        const el = document.getElementById(anchor.id);
        if (!el) return;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        el.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
        // Update the URL fragment without pushing a history entry (back-
        // button still goes to the previous PAGE, not the previous heading).
        history.replaceState(null, '', `#${anchor.id}`);
    },

    /**
     * Extension E1 — hover preview popover. On pointermove inside the
     * minimap, position the popover near the cursor (flipped to stay
     * inside the viewport) and clone the surrounding source content
     * into it. Re-uses the iframe-clone technique at a higher scale
     * (25% vs. 10%) — same sanitization pipeline.
     *
     * Throttled via rAF — pointermove can fire 60+ times/sec on a fast
     * mouse, and DOM cloning is expensive. The preview only re-renders
     * when the underlying source content changes (driven by the same
     * MutationObserver as the main iframe).
     */
    _initHoverPreview() {
        let raf = 0;
        let lastClientY = 0;
        let lastClientX = 0;
        this._hoverPreviewHandler = (event) => {
            lastClientX = event.clientX;
            lastClientY = event.clientY;
            if (raf) return;
            raf = requestAnimationFrame(() => {
                raf = 0;
                this._positionHoverPreview(lastClientX, lastClientY);
            });
        };
        this._hoverPreviewLeaveHandler = () => {
            const preview = this.$refs?.hoverPreview;
            if (preview) preview.setAttribute('data-visible', 'false');
        };
        this._hoverPreviewEscapeHandler = (event) => {
            if (event.key === 'Escape') this._hoverPreviewLeaveHandler();
        };
        // Passive — the handler is rAF-throttled and only reads cursor
        // coords; it never calls preventDefault, so it must not block scroll.
        this.$el.addEventListener('pointermove', this._hoverPreviewHandler, { passive: true });
        this.$el.addEventListener('pointerleave', this._hoverPreviewLeaveHandler, { passive: true });
        window.addEventListener('keydown', this._hoverPreviewEscapeHandler);
    },

    _positionHoverPreview(clientX, clientY) {
        const preview = this.$refs?.hoverPreview;
        if (!preview) return;
        // Build the popover's iframe contents on demand — first hover only.
        if (!preview.dataset.built && this._renderHost) {
            const sanitized = sanitizeMinimapHtml(this._renderHost.outerHTML);
            const styleLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
                .filter((l) => {
                    try { return new URL(l.href, location.href).origin === location.origin; }
                    catch (_) { return false; }
                })
                .map((l) => `<link rel="stylesheet" href="${l.href}">`)
                .join('\n');
            // Parse the developer-set token through parseFloat so non-
            // numeric junk (e.g. an attacker-controlled
            // `--reading-minimap-preview-scale: 0.25}body{background:url(...);`)
            // can't break out of the <style> block. The sandbox already
            // forbids scripts, but a CSS-injection through the iframe
            // srcdoc would still let developer-controlled CSS exfiltrate
            // via background-image url() requests. Number stringify
            // produces a guaranteed-numeric output.
            const rawScale = getComputedStyle(this.$el).getPropertyValue('--reading-minimap-preview-scale').trim();
            const parsedScale = Number.parseFloat(rawScale);
            const previewScale = (Number.isFinite(parsedScale) && parsedScale > 0 && parsedScale <= 1) ? parsedScale : 0.25;
            const srcdoc = `<!doctype html><html><head><meta charset="utf-8">${styleLinks}<style>html,body{margin:0;padding:0;background:var(--color-wk-bg-elevated,#fff)}body{pointer-events:none;transform:scale(${previewScale});transform-origin:0 0;width:calc(100% / ${previewScale});height:calc(100% / ${previewScale})}</style></head><body data-wk-rendered-minimap="true">${sanitized}</body></html>`;
            preview.innerHTML = '';
            const frame = document.createElement('iframe');
            frame.setAttribute('aria-hidden', 'true');
            frame.setAttribute('tabindex', '-1');
            frame.setAttribute('sandbox', 'allow-same-origin');
            frame.setAttribute('title', 'Hover preview');
            frame.style.cssText = 'border:0;width:100%;height:100%;pointer-events:none;background:transparent';
            frame.srcdoc = srcdoc;
            preview.appendChild(frame);
            preview.dataset.built = '1';
        }
        // Scroll the preview's iframe so the content under the cursor's
        // proportional position is centered in the popover viewport.
        const minimapRect = this.$el.getBoundingClientRect();
        const cursorFraction = Math.max(0, Math.min(1, (clientY - minimapRect.top) / minimapRect.height));
        const previewFrame = preview.querySelector('iframe');
        if (previewFrame?.contentDocument) {
            const doc = previewFrame.contentDocument;
            const max = doc.body.scrollHeight - previewFrame.clientHeight;
            previewFrame.contentWindow?.scrollTo(0, max * cursorFraction);
        }
        // Position the popover near the cursor, flipped to keep inside viewport.
        const previewSize = preview.offsetWidth || 200;
        const margin = 16;
        let left = clientX + margin;
        let top = clientY - previewSize / 2;
        if (left + previewSize > window.innerWidth) left = clientX - previewSize - margin;
        if (top < margin) top = margin;
        if (top + previewSize > window.innerHeight - margin) top = window.innerHeight - previewSize - margin;
        preview.style.left = `${left}px`;
        preview.style.top = `${top}px`;
        preview.setAttribute('data-visible', 'true');
    },

    /**
     * Extension E4 — auto-fade after idle. Starts a timer on init;
     * resets on every pointermove / scroll / pointerdown. When the
     * timer fires, set idle=true (CSS class kicks in, opacity fades).
     * Hovering the minimap clears the idle state instantly via CSS
     * `:hover` rule (not via JS) so there's no jank.
     */
    _initIdleFade() {
        const delay = this._parseDurationToken('--reading-minimap-idle-delay', 3000);
        const startTimer = () => {
            if (this._idleTimer) clearTimeout(this._idleTimer);
            this._idleTimer = setTimeout(() => {
                this.idle = true;
            }, delay);
        };
        this._idleResetHandler = () => {
            if (this.idle) this.idle = false;
            startTimer();
        };
        IDLE_RESET_EVENTS.forEach((evt) => {
            window.addEventListener(evt, this._idleResetHandler, { passive: true });
        });
        startTimer();
    },

    _parseDurationToken(name, fallbackMs) {
        try {
            const v = getComputedStyle(this.$el).getPropertyValue(name).trim();
            if (v.endsWith('ms')) return parseFloat(v);
            if (v.endsWith('s')) return parseFloat(v) * 1000;
        } catch (_) { /* ignore */ }
        return fallbackMs;
    },

    destroy() {
        if (this._scrollHandler && this._scrollHost) {
            this._scrollHost.removeEventListener('scroll', this._scrollHandler);
            if (this._scrollHost === document.documentElement) {
                window.removeEventListener('scroll', this._scrollHandler);
            }
        }
        this._resizeObserver?.disconnect();
        this._minimapResizeObserver?.disconnect();
        this._intersectionObserver?.disconnect();
        this._mutationObserver?.disconnect();
        if (this._mutationDebounceTimer) clearTimeout(this._mutationDebounceTimer);
        if (this._idleTimer) clearTimeout(this._idleTimer);
        if (this._idleResetHandler) {
            IDLE_RESET_EVENTS.forEach((evt) => {
                window.removeEventListener(evt, this._idleResetHandler);
            });
        }
        if (this._bookmarkStorageHandler) window.removeEventListener('storage', this._bookmarkStorageHandler);
        if (this._bookmarkEventHandler) {
            window.removeEventListener('wirekit:reading-bookmark:saved', this._bookmarkEventHandler);
            window.removeEventListener('wirekit:reading-bookmark:cleared', this._bookmarkEventHandler);
        }
        if (this._hoverPreviewHandler) this.$el.removeEventListener('pointermove', this._hoverPreviewHandler);
        if (this._hoverPreviewLeaveHandler) this.$el.removeEventListener('pointerleave', this._hoverPreviewLeaveHandler);
        if (this._hoverPreviewEscapeHandler) window.removeEventListener('keydown', this._hoverPreviewEscapeHandler);
    },
});
