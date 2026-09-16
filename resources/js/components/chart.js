import { resolveThemeColors, palette, withOpacity, themeModeOf } from '../utils/chart-theme-colors.js';
import { prefersReducedMotion, watchReducedMotion } from '../utils/motion.js';
import { awaitPeer } from '../utils/await-peer.js';

/**
 * WireKit Chart.js Alpine Component.
 *
 * Initializes a Chart.js instance with automatic WireKit theming via CSS
 * variables. A MutationObserver on <html> watches for .dark class toggles
 * and re-applies theme colors (grid, ticks, legend, datasets) instantly.
 *
 * @param {Object} config - Chart.js configuration object (type, data, options).
 *   Passed from the Blade component via Alpine x-data. Will be unwrapped with
 *   Alpine.raw() before passing to Chart.js to avoid Proxy conflicts.
 *
 * Lifecycle:
 * - init(): Creates chart + observer inside $nextTick (after DOM ready)
 * - destroy(): Cleans up chart, observer, and Livewire event listener
 *
 * Cleanup is automatic on Livewire SPA navigation (livewire:navigating)
 * and Alpine component teardown (destroy() lifecycle hook).
 */
/**
 * Global registry of every Chart.js instance created by `wirekitChartJs`.
 * Maintained on `window` so the proactive-sweep sees stale instances from
 * earlier Alpine mounts on this page (a docs.wirekit.app preview-replay button can
 * replace its iframe's innerHTML in place, which detaches the old canvas
 * without firing Alpine's `destroy()` hook — Chart.js's per-chart RAF loop survives
 * and crashes on the next frame when `chart.ctx` resolves null against the
 * detached canvas). On every fresh init, we sweep the registry and destroy
 * any chart whose canvas is no longer in the document BEFORE the new chart's
 * animator schedules its first RAF — preventing the "two charts racing on
 * the same global Chart.animator" crash class that the per-instance
 * beforeDraw plugin alone can't catch (the stale chart's animator has
 * already queued multiple RAFs before any draw hook can fire).
 */
const REGISTRY_KEY = '__wirekit_chartjs_registry__';
function getRegistry() {
    if (typeof window === 'undefined') return new Set();
    if (!window[REGISTRY_KEY]) window[REGISTRY_KEY] = new Set();
    return window[REGISTRY_KEY];
}

/**
 * One-shot global patch of `Chart.prototype.draw`. The native draw method
 * dereferences `this.ctx` deep inside `_drawDataset` via `ctx.save()`. If
 * `chart.destroy()` set `ctx = null` between an animator-scheduled RAF and
 * the RAF callback firing, the next draw call crashes with `Cannot read
 * properties of null (reading 'save')`. The registry-sweep + per-chart
 * `beforeDraw` plugin handle the common case but race with the animator
 * on the rapid-multi-replay path (every fresh `new Chart()` schedules a
 * cascade of RAF callbacks via `animator.start()`; if any of them target
 * a previous-mount chart whose destroy is in-flight, they slip past the
 * plugin guard because plugin hooks run AFTER `draw()` already entered).
 *
 * Wrapping `Chart.prototype.draw` at the prototype level catches every
 * draw entry-point, regardless of how the animator scheduled it. Null-ctx
 * → early-return + cleanup the animator registration. Non-null ctx →
 * delegate to native draw. The patch installs once per page; subsequent
 * Alpine inits see `Chart.__wirekitDrawPatched` and skip re-patching.
 * Pie / doughnut charts didn't crash before this patch only because
 * their renderer doesn't enter the bar / line-stroke code path that
 * touches `ctx.save()` per-element — the underlying race was identical.
 */
function patchChartDrawOnce() {
    if (typeof Chart === 'undefined') return;
    if (Chart.__wirekitDrawPatched) return;
    if (typeof Chart.prototype !== 'object' || typeof Chart.prototype.draw !== 'function') return;
    Chart.__wirekitDrawPatched = true;
    const originalDraw = Chart.prototype.draw;
    Chart.prototype.draw = function (...args) {
        if (!this.ctx || (this.canvas && !this.canvas.isConnected)) {
            try { Chart.animator?.remove?.(this); } catch { /* defensive */ }
            return;
        }
        return originalDraw.apply(this, args);
    };
}

function sweepStaleCharts() {
    if (typeof Chart === 'undefined') return;
    const registry = getRegistry();
    // Two-pass to avoid mutating the Set during iteration on some engines.
    const stale = [];
    for (const c of registry) {
        if (!c.canvas || !c.canvas.isConnected) stale.push(c);
    }
    for (const c of stale) {
        try { Chart.animator?.remove?.(c); } catch { /* defensive */ }
        try { c.stop?.(); } catch { /* defensive */ }
        try { c.destroy?.(); } catch { /* defensive */ }
        registry.delete(c);
    }
}

export default function wirekitChartJs(config) {
    return {
        chart: null,
        _navCleanup: null,
        _darkModeObserver: null,
        _darkModeDebounce: null,

        // The palette the chart is currently painted with. See the dark-mode observer:
        // it fires on class mutations that have nothing to do with the theme, and this
        // is what tells those apart from a real one.
        _themeSignature: null,

        // Stops the wait for a library that was not on the page at init() — utils/await-peer.js.
        _stopAwaitingLibrary: null,

        // Track which datasets had user-provided colors at init time.
        // These datasets are excluded from dark mode re-theming.
        _manualColorIndices: new Set(),

        /**
         * Paint a visible advisory where the chart would have been.
         *
         * ⚠️ WITHOUT THIS, A MISSING PEER LIBRARY LOOKED LIKE A STYLING BUG. The adapter
         * returned after a console.error, leaving an empty box that the wrapper still
         * announces as a chart — so the page reads as broken CSS to a developer and as an
         * empty chart to a screen reader, and the one message explaining it was in a console
         * nobody had open. The ApexCharts adapter has painted a panel for this case all along;
         * these two behaved differently for the same failure.
         *
         * The canvas is REPLACED rather than filled: a <canvas> renders no HTML children, so
         * there is nowhere inside it to put a message. It is hidden and the panel takes its
         * place in the flow.
         *
         * Inline styles only — no Tailwind utilities and no CSS-variable lookups. The
         * developer may have a misconfiguration there too, and the fallback has to paint no
         * matter what state the surrounding theme is in. Same reasoning, same shape, as the
         * ApexCharts panel.
         */
        _renderMissingLibraryPanel() {
            this.$nextTick(() => {
                const canvas = this.$refs.canvas;
                if (!canvas || !canvas.parentElement) {
                    return;
                }

                if (canvas.parentElement.querySelector('[data-wk-chart-missing]')) {
                    return;
                }

                // How much room the panel actually has, measured BEFORE the canvas is
                // hidden — hiding it first collapses an inline host to zero and the
                // measurement then says the opposite of the truth.
                //
                // Measured 2026-09-08 on /preview/components/sparkline/3: an inline
                // sparkline host is 4rem (64px) wide by design, and this panel rendered
                // inside it as a 19px-wide, 975px-tall column of three characters per
                // line, in the middle of a running sentence. At 1280px too — the width
                // that breaks it is the HOST's, not the viewport's, so it was never a
                // mobile bug even though the mobile sweep is what noticed.
                // An empty mount can measure 0 — the panel is what will give it width —
                // so a bare `width > 0` test defaults to the FULL panel exactly where the
                // compact one is needed. Walk out to the nearest ancestor that has a
                // resolved width; that is the room the panel will actually get.
                const roomFor = (el) => {
                    let n = el;
                    while (n && n !== document.body) {
                        const w = Math.round(n.getBoundingClientRect().width);
                        if (w > 0) return w;
                        n = n.parentElement;
                    }
                    return 0;
                };
                const availablePx = roomFor(canvas.parentElement);
                const compact = availablePx > 0 && availablePx < 240;

                canvas.style.display = 'none';

                const panel = document.createElement('div');
                panel.setAttribute('data-wk-chart-missing', compact ? 'compact' : 'full');

                // `role="alert"` on an element the reader can actually reach. The canvas
                // carries aria-hidden; this panel is a sibling, so it is not inside that
                // subtree — an alert within an aria-hidden subtree is never announced, which
                // is a trap the ApexCharts panel had to be repaired for.
                // The compact form exists because the instructional one cannot be made to
                // fit: it carries a <pre> with two npm/import lines, and a code block in a
                // 64px box is unreadable at any font size. Nothing is lost — the same
                // instructions go to the console (deduplicated, see init()), and the
                // visually-hidden sentence below keeps the full message on the
                // accessibility tree, where the `role="alert"` announces it either way.
                /*
                 * ⚠️ THE BACKGROUND IS OPAQUE, AND THAT IS THE WHOLE FIX RATHER THAN A DETAIL.
                 *
                 * This panel used `background: rgba(254, 243, 199, 0.5)` with
                 * `color: rgb(120, 53, 15)` — amber-100 at half alpha under amber-900. In LIGHT
                 * mode that composites to a pale amber and reads fine. In DARK mode the same
                 * half-alpha fill blends with the page behind it, axe measured the effective
                 * background as #847f69, and amber-900 on that is 2.25:1 against a 4.5:1
                 * threshold. Twenty previews failed the dark sweep on 2026-09-10 (pipeline
                 * 2679), all of them this one span.
                 *
                 * A translucent fill has no contrast ratio of its own — it has whatever the
                 * thing behind it makes. So the pair is now the two tokens the design system
                 * already aligns for exactly this, and they are OPAQUE: measured with the
                 * repository's own WcagContrast, warning-text on warning-bg is 6.24:1 in light
                 * and 7.76:1 in dark.
                 *
                 * The insets keep a tint rather than a fixed white, for the same reason one
                 * level down: `rgba(255,255,255,0.6)` is a light surface in both modes, and in
                 * dark it put a light-mode surface under light-mode-inverted text.
                 *
                 * `--color-wk-border-warning` is deliberately NOT used — it is one of the two
                 * state-border tokens this library does not ship, and the guard's
                 * $absentByDesign list fails the build on introducing one.
                 */
                panel.innerHTML = compact ? `
                    <span role="alert"
                          style="
                             display: inline-block;
                             max-width: 100%;
                             overflow: hidden;
                             text-overflow: ellipsis;
                             white-space: nowrap;
                             padding: 0 0.25rem;
                             border: 1px solid color-mix(in oklab, var(--color-wk-warning-text, #78350f) 40%, transparent);
                             border-radius: 0.25rem;
                             background: var(--color-wk-warning-bg, #fffbeb);
                             color: var(--color-wk-warning-text, #78350f);
                             font-family: system-ui, -apple-system, sans-serif;
                             font-size: 0.6875rem;
                             line-height: 1.4;
                          ">
                        <span aria-hidden="true">! Chart.js missing</span>
                        <span style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;">Chart.js is not loaded. Install the chart.js npm package and register its built-ins; the browser console carries the commands.</span>
                    </span>
                ` : `
                    <div role="alert"
                         style="
                            padding: 1rem 1.25rem;
                            border: 1px solid color-mix(in oklab, var(--color-wk-warning-text, #78350f) 40%, transparent);
                            border-left: 4px solid var(--color-wk-warning-text, #b45309);
                            border-radius: 0.375rem;
                            background: var(--color-wk-warning-bg, #fffbeb);
                            color: var(--color-wk-warning-text, #78350f);
                            font-family: system-ui, -apple-system, sans-serif;
                            font-size: 0.8125rem;
                            line-height: 1.5;
                         ">
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">
                            Chart.js is not loaded.
                        </div>
                        <p style="margin: 0 0 0.5rem 0;">
                            WireKit's Chart.js adapter glue is loaded, but the
                            <code style="font-family: ui-monospace, monospace; font-size: 0.85em; padding: 0.05rem 0.25rem; background: color-mix(in oklab, var(--color-wk-warning-text, #78350f) 12%, transparent); border-radius: 0.2rem;">chart.js</code>
                            npm package is missing or its registerables were never registered.
                        </p>
                        <p style="margin: 0 0 0.5rem 0;">
                            Install it and register the built-ins:
                        </p>
                        <pre tabindex="0" style="margin: 0; padding: 0.625rem 0.75rem; outline-offset: 2px; background: color-mix(in oklab, var(--color-wk-warning-text, #78350f) 12%, transparent); border-radius: 0.25rem; font-family: ui-monospace, monospace; font-size: 0.75rem; line-height: 1.5; overflow-x: auto;">npm install chart.js

// resources/js/app.js
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);</pre>
                    </div>
                `;

                canvas.parentElement.insertBefore(panel, canvas);
            });
        },

        init() {
            // Asked until it can be answered — utils/await-peer.js. The library may land after
            // Alpine has mounted this chart (a lazily imported chart.js), so the panel and the
            // console hint wait for the load plus a grace period instead of speaking at once.
            this._stopAwaitingLibrary = awaitPeer({
                isReady: () => typeof Chart !== 'undefined',
                onReady: () => {
                    this._clearMissingLibraryPanel();
                    this._boot();
                },
                onMissing: () => {
                    this._warnMissingLibrary();
                    this._renderMissingLibraryPanel();
                },
            });
        },

        /**
         * The one-time console hint — DX signal when the Chart.js peer dependency is missing.
         * Deduplicated through a window flag, so a page with N charts says it once.
         */
        _warnMissingLibrary() {
            if (typeof window !== 'undefined') {
                window.__wirekit_chartjs_missing_warned__ ??= false;
                if (!window.__wirekit_chartjs_missing_warned__) {
                    window.__wirekit_chartjs_missing_warned__ = true;
                    console.error(
                        'WireKit: Chart.js is not loaded. Install it via npm:\n' +
                        '  npm install chart.js\n' +
                        'And import it in your app.js:\n' +
                        '  import { Chart, registerables } from "chart.js";\n' +
                        '  Chart.register(...registerables);'
                    );
                }
            }
        },

        /** The library arrived after the panel: take the panel down and show the canvas again. */
        _clearMissingLibraryPanel() {
            const canvas = this.$refs.canvas;
            if (!canvas || !canvas.parentElement) return;

            canvas.parentElement.querySelector('[data-wk-chart-missing]')?.remove();
            canvas.style.display = '';
        },

        /** Build the chart — at once when the library is on the page, or the moment it lands. */
        _boot() {
            // One-shot prototype patch — defensive race-condition guard.
            patchChartDrawOnce();

            // Sweep ANY stale chart from a previous mount whose canvas got
            // detached (replay-button flow). Doing this BEFORE the new
            // chart's constructor runs is critical: once `new Chart()`
            // schedules its first RAF the global animator is iterating
            // every active chart per frame, so a stale chart's null-ctx
            // crash takes the whole animator down (including the new
            // chart's render). Plus: hand the new canvas to `Chart.getChart`
            // first — if Chart.js already has an instance on this exact
            // canvas (rare, but possible in HMR / hot-reload contexts),
            // destroy that one too.
            sweepStaleCharts();

            this.$nextTick(() => {
                const canvas = this.$refs.canvas;
                if (!canvas) return;
                const existing = Chart.getChart?.(canvas);
                if (existing) {
                    try { Chart.animator?.remove?.(existing); } catch { /* defensive */ }
                    try { existing.stop?.(); } catch { /* defensive */ }
                    try { existing.destroy?.(); } catch { /* defensive */ }
                    getRegistry().delete(existing);
                }
                const ctx = canvas.getContext('2d');

                // Read CSS variables from the canvas element — this resolves
                // correctly regardless of whether .dark is on <html> or <body>.
                const style = getComputedStyle(this.$refs.canvas);
                const colors = this._resolveThemeColors(style);

                // Font family from WireKit theming
                const fontFamily = style.getPropertyValue('--font-wk-sans').trim()
                    || 'ui-sans-serif, system-ui, sans-serif';

                // Apply theme via Chart.js global defaults — this avoids
                // mutating the config object directly, which would break
                // Chart.js's internal Proxy-based option resolution
                // (causes "setContext is not a function" errors).
                this._applyGlobalDefaults(colors, fontFamily);

                // Alpine.raw() strips the reactive Proxy wrapper from the
                // config object. Alpine 3.x wraps all x-data properties in
                // reactive Proxies, but Chart.js 4.x creates its own internal
                // Proxies for option resolution (setContext). Proxy-in-Proxy
                // breaks Chart.js's internal chain. Alpine.raw() returns the
                // original plain object so Chart.js can wrap it correctly.
                const rawConfig = Alpine.raw(config);

                // Record which datasets have user-provided colors BEFORE
                // we apply theme defaults. These are excluded from dark
                // mode re-theming to preserve manual color choices.
                (rawConfig.data?.datasets || []).forEach((dataset, i) => {
                    if (dataset.backgroundColor || dataset.borderColor) {
                        this._manualColorIndices.add(i);
                    }
                });

                // Apply theme colors to datasets (only if not manually set)
                this._applyThemeToDatasets(rawConfig, colors);

                // Defensive detach-guard plugin. The docs.wirekit.app replay button
                // replaces the preview frame's HTML in-place (innerHTML
                // reassignment, NOT Alpine teardown), which severs our
                // canvas from the document without firing the Alpine
                // `destroy()` lifecycle hook. Chart.js's animation loop
                // keeps running on the detached canvas, and on the next
                // requestAnimationFrame it crashes with
                // `Cannot read properties of null (reading 'save')` in
                // `_drawDataset` — the 2D context returns null once the
                // canvas leaves the document. A per-draw plugin hook
                // tests `canvas.isConnected` and tears the chart down
                // BEFORE the broken save() call is reached.
                rawConfig.plugins = (rawConfig.plugins || []).concat([{
                    id: 'wirekit-detach-guard',
                    beforeDraw(chart) {
                        if (!chart.canvas || !chart.canvas.isConnected) {
                            try { chart.stop(); } catch { /* idempotent */ }
                            try { chart.destroy(); } catch { /* idempotent */ }
                            return false;
                        }
                    },
                }]);

                // Honor prefers-reduced-motion: switch Chart.js entrance +
                // update animations off entirely when the OS preference is set,
                // so bars/lines don't grow/sweep for motion-sensitive users.
                // Mirrors the chart-apex adapter's reduced-motion handling.
                if (this._reducedMotion()) {
                    rawConfig.options = rawConfig.options || {};
                    rawConfig.options.animation = false;
                    rawConfig.options.animations = false;
                }

                this.chart = new Chart(ctx, rawConfig);
                getRegistry().add(this.chart);

                // Set up dark mode observer AFTER chart is created.
                // This avoids the race condition where the observer fires
                // before $nextTick completes and this.chart is still null.
                // Seed the theme signature from what the chart was just BUILT with, so
                // the first unrelated class toggle after construction is a no-op rather
                // than a full re-theme that lands on the identical palette.
                if (this.$refs.canvas) {
                    const initialStyle = getComputedStyle(this.$refs.canvas);
                    this._themeSignature = (initialStyle.getPropertyValue('--font-wk-sans').trim()
                        || 'ui-sans-serif, system-ui, sans-serif')
                        + '|' + JSON.stringify(this._resolveThemeColors(initialStyle));
                }

                this._setupDarkModeObserver();
            });

            // Reduced motion is a LIVE preference, not a construction-time constant.
            // It was read once at `new Chart()` and never again, so a reader who turns
            // motion down while the page is open kept every animation — and the OS
            // preference is not even the common case here: WireKit's own site-level
            // toggle writes `data-reduce-motion` onto <html>, which a chart already on
            // screen had no way to notice. `watchReducedMotion` was built for exactly
            // this and, until now, only the carousel used it.
            this._motionCleanup = watchReducedMotion((reduced) => {
                if (! this.chart) {
                    return;
                }

                this.chart.options = this.chart.options || {};
                this.chart.options.animation = reduced ? false : undefined;
                this.chart.options.animations = reduced ? false : undefined;

                // `'none'` — repaint without animating the change itself. Animating the
                // switch to no-animation is the one transition nobody asked for.
                try { this.chart.update('none'); } catch { /* defensive */ }
            });

            // Cleanup on Livewire navigation (SPA mode)
            this._navCleanup = () => this.destroy();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Wire-streaming setup — read data-wire-stream-*
            // attributes off the root and register a window listener so
            // Livewire $dispatch('<event>', { point }) calls feed the live chart.
            this._setupWireStream();

            // Annotations plugin warning — Chart.js requires
            // chartjs-plugin-annotation. Emit a console.warn at init time when
            // annotations are present but the plugin is missing, so developers
            // see the cause without a silent visual no-op.
            if (config.options?.plugins?.annotation && typeof Chart !== 'undefined' && !Chart.registry?.plugins?.get?.('annotation')) {
                console.warn(
                    'WireKit: chart annotations supplied but chartjs-plugin-annotation is not registered. '
                    + 'Install via npm i chartjs-plugin-annotation and register: '
                    + 'import annotationPlugin from "chartjs-plugin-annotation"; '
                    + 'Chart.register(annotationPlugin);'
                );
            }
        },

        /**
         * Read data-wire-stream-* attributes off the root element and register
         * a window-level listener for the configured event. Each event delivers
         * a `point` payload (or { datasetIndex, point }) that is appended to
         * the active chart via Chart.js's data.datasets[i].data.push +
         * chart.update('none') (no animation; the new point appears at the
         * far right of the visible window).
         *
         * 'strict' mode (default) shifts FIFO at wireStreamCap data points.
         * 'stream' mode grows unboundedly — developer's responsibility to
         * trim or rotate.
         */
        _setupWireStream() {
            const root = this.$el;
            const eventName = root?.dataset?.wireStreamEvent;
            if (!eventName) return;

            const mode = root.dataset.wireStreamMode || 'strict';
            const cap = parseInt(root.dataset.wireStreamCap, 10) || 100;

            this._wireStreamHandler = (event) => {
                if (!this.chart) return;
                const detail = event.detail || {};
                const datasetIndex = detail.datasetIndex ?? 0;
                const point = detail.point;
                if (point === undefined) return;

                const dataset = this.chart.data.datasets[datasetIndex];
                if (!dataset) return;

                dataset.data.push(point);
                if (mode === 'strict' && dataset.data.length > cap) {
                    dataset.data.shift();
                    if (Array.isArray(this.chart.data.labels) && this.chart.data.labels.length > cap) {
                        this.chart.data.labels.shift();
                    }
                }
                this.chart.update('none');
            };

            window.addEventListener(eventName, this._wireStreamHandler);
        },

        /**
         * Watch for dark mode changes on <html> element.
         *
         * Chart.js reads CSS variables once at init — it doesn't re-read
         * them when .dark toggles. MutationObserver detects class changes
         * and re-applies theme colors + updates the chart.
         *
         * Debounced at 50ms to handle rapid toggles gracefully (e.g.
         * system preference changes that fire multiple mutations).
         */
        _setupDarkModeObserver() {
            this._darkModeObserver = new MutationObserver((mutations) => {
                const hasClassChange = mutations.some(
                    (m) => m.attributeName === 'class'
                );
                if (!hasClassChange || !this.chart) return;

                // Debounce: coalesce rapid toggles into one update
                clearTimeout(this._darkModeDebounce);
                this._darkModeDebounce = setTimeout(() => {
                    if (!this.chart || !this.$refs.canvas) return;

                    // Alpine.raw() strips the reactive Proxy wrapper.
                    // Without this, mutating chart.options triggers Alpine's
                    // Proxy getter recursively through Chart.js's internal
                    // option-resolver Proxies → infinite call stack.
                    const chart = Alpine.raw(this.chart);

                    // Read from canvas element — resolves correctly regardless
                    // of whether .dark is on <html>, <body>, or a wrapper.
                    const style = getComputedStyle(this.$refs.canvas);
                    const colors = this._resolveThemeColors(style);
                    const fontFamily = style.getPropertyValue('--font-wk-sans').trim()
                        || 'ui-sans-serif, system-ui, sans-serif';

                    /*
                     * Nothing below runs unless the THEME actually changed.
                     *
                     * The observer's only gate is "was the mutated attribute `class`",
                     * and html/body carry a great many classes that have nothing to do
                     * with the theme — a scroll lock, an open navigation, a Livewire
                     * state flag, the application's own. Every one of them re-resolved
                     * the palette, re-applied it to every dataset and ran a full
                     * Chart.js style pass with an animated redraw, on every chart on
                     * the page. On a dashboard that is the most expensive thing a
                     * class toggle can cost.
                     *
                     * Compared on the RESOLVED values rather than on the presence of a
                     * `.dark` class: a theme preset can change the palette without
                     * touching that class, and a dark-flag test would call it unchanged
                     * and leave the chart on the old colors. This costs one string
                     * comparison and cannot miss a change the old code would have seen.
                     */
                    const signature = fontFamily + '|' + JSON.stringify(colors);
                    if (signature === this._themeSignature) {
                        return;
                    }
                    this._themeSignature = signature;

                    // Re-apply global defaults with new dark/light colors.
                    // Note: Chart.js v4 caches resolved options per chart
                    // instance at construction time, so changing Chart.defaults
                    // alone does NOT propagate to existing charts. This call
                    // is still useful for any *new* charts created afterwards.
                    this._applyGlobalDefaults(colors, fontFamily);

                    // Mutate THIS chart instance's options directly so the
                    // new colors actually show up on the next update(). Without
                    // this, grid / tick / legend / tooltip colors stay frozen
                    // on whatever was resolved at `new Chart(...)` time — the
                    // exact "requires page refresh to see new colors" bug.
                    this._applyThemeToChartOptions(chart, colors, fontFamily);

                    // Re-apply dataset colors (skip manually colored datasets)
                    this._reapplyThemeToDatasets(chart.config, colors);

                    // Redraw with new colors. Using update() (not "none") forces
                    // Chart.js v4 to run the full style-resolver pass, which picks
                    // up mutated dataset colors. "none" skips that pass when only
                    // style properties changed (no data/layout change), leaving old
                    // colors on the canvas. The animation transition is a UX win.
                    chart.update();
                }, 50);
            });

            // Observe both <html> and <body> — different apps place .dark
            // on different elements. Tailwind's dark variant matches either.
            const observerOpts = { attributes: true, attributeFilter: ['class'] };
            this._darkModeObserver.observe(document.documentElement, observerOpts);
            // `observe(null)` throws, and the throw would leave the chart uninitialized
            // rather than merely un-themed. <html> is always there; <body> may not be.
            if (document.body) {
                this._darkModeObserver.observe(document.body, observerOpts);
            }
        },

        /**
         * Destroy chart instance, release references, and remove listeners.
         * Safe to call multiple times (idempotent).
         */
        destroy() {
            // A library that lands after this chart is gone must not build it.
            this._stopAwaitingLibrary?.();
            this._stopAwaitingLibrary = null;

            // Clear debounce timer first to prevent stale callbacks
            clearTimeout(this._darkModeDebounce);

            if (this._darkModeObserver) {
                this._darkModeObserver.disconnect();
                this._darkModeObserver = null;
            }
            if (this._navCleanup) {
                document.removeEventListener('livewire:navigating', this._navCleanup);
                this._navCleanup = null;
            }
            if (this._motionCleanup) {
                this._motionCleanup();
                this._motionCleanup = null;
            }
            if (this._wireStreamHandler) {
                const eventName = this.$el?.dataset?.wireStreamEvent;
                if (eventName) {
                    window.removeEventListener(eventName, this._wireStreamHandler);
                }
                this._wireStreamHandler = null;
            }
            if (this.chart) {
                getRegistry().delete(this.chart);
                try { Chart.animator?.remove?.(this.chart); } catch { /* defensive */ }
                try { this.chart.stop?.(); } catch { /* defensive */ }
                try { this.chart.destroy(); } catch { /* defensive */ }
                this.chart = null;
            }
        },

        /**
         * Theme-color readers — thin wrappers around the shared util in
         * resources/js/utils/chart-theme-colors.js — single source of truth.
         * Both wirekitChartJs and wirekitApexChart consume the same helpers
         * so dataset palettes, fallbacks, and probe behavior stay in lockstep.
         */
        _resolveThemeColors(style) { return resolveThemeColors(style, this.$refs?.canvas ?? null); },
        _palette(colors)           { return palette(colors); },
        _withOpacity(color, op)    { return withOpacity(color, op); },

        // Read the OS-level prefers-reduced-motion preference. Mirrors the
        // chart-apex adapter; consulted once at new Chart() time to switch
        // Chart.js animations off for motion-sensitive users.
        _reducedMotion() {
            return typeof window !== 'undefined'
                && window.matchMedia
                && prefersReducedMotion();
        },

        /**
         * Apply theme colors to datasets that don't have manual colors set.
         *
         * Called once at init on the raw config before Chart.js wraps it.
         * Datasets with existing backgroundColor/borderColor are skipped
         * (tracked in _manualColorIndices for dark mode re-theming).
         */
        _applyThemeToDatasets(config, colors) {
            const palette = this._palette(colors);

            (config.data?.datasets || []).forEach((dataset, i) => {
                const color = palette[i % palette.length];

                if (!dataset.backgroundColor) {
                    if (['pie', 'doughnut'].includes(config.type)) {
                        // Pie / doughnut: each slice is a flat color wedge.
                        // Solid (no opacity) keeps adjacent wedges crisply
                        // distinguishable; pie charts have no gridlines for
                        // transparency to reveal.
                        const len = dataset.data?.length || 1;
                        dataset.backgroundColor = palette.slice(0, len);
                        dataset.borderColor = palette.slice(0, len);
                    } else if (config.type === 'polarArea') {
                        // Polar area: segments overlay a radial gridline
                        // backdrop. Drop fill opacity to ~0.55 so the
                        // gridline rings stay visible behind each segment.
                        const len = dataset.data?.length || 1;
                        dataset.backgroundColor = palette.slice(0, len)
                            .map(c => this._withOpacity(c, 0.55));
                        dataset.borderColor = palette.slice(0, len);
                    } else if (config.type === 'bar') {
                        dataset.backgroundColor = this._withOpacity(color, 0.6);
                        dataset.borderColor = color;
                        dataset.borderWidth = 1;
                    } else if (config.type === 'radar') {
                        // Radar: polygon fill MUST stay translucent so the
                        // axial gridlines + value rings remain visible
                        // through the polygon. 0.18 gives a noticeable
                        // hue tint without obscuring the chart structure.
                        dataset.backgroundColor = this._withOpacity(color, 0.18);
                        dataset.borderColor = color;
                        dataset.borderWidth = 2;
                        dataset.pointBackgroundColor = color;
                    } else {
                        // Line, area, scatter, etc.
                        dataset.backgroundColor = this._withOpacity(color, 0.18);
                        dataset.borderColor = color;
                        dataset.borderWidth = 2;
                        dataset.pointBackgroundColor = color;
                    }
                }
            });
        },

        /**
         * Re-apply theme colors to an existing chart's datasets on dark
         * mode toggle. Only updates auto-themed datasets — datasets with
         * user-provided colors (tracked in _manualColorIndices) are skipped.
         */
        _reapplyThemeToDatasets(config, colors) {
            const palette = this._palette(colors);
            const type = config.type;

            (config.data?.datasets || []).forEach((dataset, i) => {
                // Skip datasets with user-provided manual colors
                if (this._manualColorIndices.has(i)) return;

                const color = palette[i % palette.length];

                if (['pie', 'doughnut'].includes(type)) {
                    const len = dataset.data?.length || 1;
                    dataset.backgroundColor = palette.slice(0, len);
                    dataset.borderColor = palette.slice(0, len);
                } else if (type === 'polarArea') {
                    const len = dataset.data?.length || 1;
                    dataset.backgroundColor = palette.slice(0, len)
                        .map(c => this._withOpacity(c, 0.55));
                    dataset.borderColor = palette.slice(0, len);
                } else if (type === 'bar') {
                    dataset.backgroundColor = this._withOpacity(color, 0.6);
                    dataset.borderColor = color;
                } else if (type === 'radar') {
                    dataset.backgroundColor = this._withOpacity(color, 0.18);
                    dataset.borderColor = color;
                    dataset.pointBackgroundColor = color;
                } else {
                    // Line, area, scatter, etc.
                    dataset.backgroundColor = this._withOpacity(color, 0.18);
                    dataset.borderColor = color;
                    dataset.pointBackgroundColor = color;
                }
            });
        },

        /**
         * Apply WireKit theme to a specific chart instance's options.
         *
         * Chart.js v4 resolves option values at construction time and caches
         * them per-chart — subsequent changes to `Chart.defaults.*` do NOT
         * propagate to existing charts. To make dark-mode switching work
         * without a page refresh, we must mutate `chart.options.*` on the
         * live instance for every property we care about (grid, ticks,
         * legend, tooltip, global text color), then call chart.update().
         *
         * Scale keys (`x`, `y`, `r`, etc.) are discovered via `chart.scales`,
         * the runtime map of scale instances Chart.js creates for this chart.
         * This works for cartesian (bar, line), radial (radar, polarArea)
         * and scale-less (pie, doughnut) charts alike.
         */
        _applyThemeToChartOptions(chart, colors, fontFamily) {
            const options = chart.options;
            const isDark = themeModeOf(this.$refs?.canvas ?? null) === 'dark';

            // Global text color + font family
            options.color = colors.textMuted;
            if (!options.font) options.font = {};
            options.font.family = fontFamily;

            // Per-scale grid + tick + axis-border colors. Walk chart.scales
            // (runtime instances) rather than options.scales (user config) so
            // we also reach auto-created scales that were never explicitly
            // configured by the caller.
            //
            // In Chart.js v4 the axis line itself lives under `scale.border`
            // (separate from `scale.grid` which is the inner tick lines). If
            // we only update grid.color the axis line stays stuck on whatever
            // was resolved at construction time — this is one of the "still
            // looks stale after dark-mode toggle" artifacts.
            Object.keys(chart.scales || {}).forEach((scaleId) => {
                if (!options.scales) options.scales = {};
                if (!options.scales[scaleId]) options.scales[scaleId] = {};
                const scale = options.scales[scaleId];
                if (!scale.grid) scale.grid = {};
                scale.grid.color = colors.border;
                if (!scale.ticks) scale.ticks = {};
                scale.ticks.color = colors.textMuted;
                if (!scale.border) scale.border = {};
                scale.border.color = colors.border;

                // Radial charts (radar, polarArea) also expose angleLines and
                // pointLabels. Theme those too so the web lines don't stay
                // frozen on the initial-render color.
                if (scale.angleLines !== undefined || scale.pointLabels !== undefined
                    || chart.scales[scaleId]?.type === 'radialLinear') {
                    if (!scale.angleLines) scale.angleLines = {};
                    scale.angleLines.color = colors.border;
                    if (!scale.pointLabels) scale.pointLabels = {};
                    scale.pointLabels.color = colors.textMuted;
                }
            });

            // Plugins: legend + tooltip
            if (!options.plugins) options.plugins = {};

            if (!options.plugins.legend) options.plugins.legend = {};
            if (!options.plugins.legend.labels) options.plugins.legend.labels = {};
            options.plugins.legend.labels.color = colors.textPrimary;

            // Tooltip theming — Chart.js defaults use a hard-coded dark bubble
            // (rgba(0,0,0,0.8) background, white text) which looks fine in
            // light mode but reads as a weirdly out-of-place dark blob on a
            // dark background. Mirror the background against the current
            // theme so the bubble remains a subtle elevated surface in both
            // modes.
            if (!options.plugins.tooltip) options.plugins.tooltip = {};
            options.plugins.tooltip.backgroundColor = isDark
                ? 'rgba(244, 244, 245, 0.95)' // near-white bubble in dark mode
                : 'rgba(24, 24, 27, 0.9)';     // near-black bubble in light mode
            options.plugins.tooltip.titleColor = isDark ? '#18181b' : '#f4f4f5';
            options.plugins.tooltip.bodyColor = isDark ? '#27272a' : '#e4e4e7';
            options.plugins.tooltip.borderColor = colors.border;
            options.plugins.tooltip.borderWidth = 1;
            options.plugins.tooltip.titleFont = {
                ...(options.plugins.tooltip.titleFont || {}),
                family: fontFamily,
            };
            options.plugins.tooltip.bodyFont = {
                ...(options.plugins.tooltip.bodyFont || {}),
                family: fontFamily,
            };
        },

        /**
         * Apply WireKit theme via Chart.js global defaults.
         *
         * Using Chart.defaults instead of mutating individual config objects
         * ensures Chart.js's internal Proxy-based option resolution stays
         * intact. Direct config mutation can replace Proxy objects with
         * plain objects, causing "setContext is not a function" errors
         * when Chart.js tries to resolve scriptable options.
         *
         * User-provided options in the config always take priority over
         * these defaults (Chart.js merges user → defaults automatically).
         *
         * NOTE: This only affects charts constructed AFTER the call —
         * existing chart instances use cached options and must be
         * re-themed via _applyThemeToChartOptions() instead.
         *
         * Page-wide, and still right with charts in different theme scopes: each construction
         * writes its own chart's colors here immediately before `new Chart`, and the observer
         * below records that Chart.js keeps what it resolved at construction, so the defaults a
         * later chart writes do not repaint an earlier one.
         */
        _applyGlobalDefaults(colors, fontFamily) {
            const isDark = themeModeOf(this.$refs?.canvas ?? null) === 'dark';

            // Global text color and font
            Chart.defaults.color = colors.textMuted;
            Chart.defaults.font.family = fontFamily;

            // Scale (axes) theming — grid, ticks, and axis-line border
            Chart.defaults.scale.grid.color = colors.border;
            Chart.defaults.scale.ticks.color = colors.textMuted;
            if (Chart.defaults.scale.border) {
                Chart.defaults.scale.border.color = colors.border;
            }

            // Legend theming
            Chart.defaults.plugins.legend.labels.color = colors.textPrimary;

            // Tooltip theming — mirror the bubble surface against the active
            // theme so the popover reads as an elevated surface in both
            // light and dark mode instead of always being a dark blob.
            Chart.defaults.plugins.tooltip.backgroundColor = isDark
                ? 'rgba(244, 244, 245, 0.95)'
                : 'rgba(24, 24, 27, 0.9)';
            Chart.defaults.plugins.tooltip.titleColor = isDark ? '#18181b' : '#f4f4f5';
            Chart.defaults.plugins.tooltip.bodyColor = isDark ? '#27272a' : '#e4e4e7';
            Chart.defaults.plugins.tooltip.borderColor = colors.border;
            Chart.defaults.plugins.tooltip.borderWidth = 1;
            Chart.defaults.plugins.tooltip.titleFont = { family: fontFamily };
            Chart.defaults.plugins.tooltip.bodyFont = { family: fontFamily };
        },
    };
}
