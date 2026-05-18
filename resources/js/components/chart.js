import { resolveThemeColors, palette, withOpacity } from '../utils/chart-theme-colors.js';

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
 * earlier Alpine mounts on this page (a docs-site preview-replay button can
 * replace its iframe's innerHTML in place, which detaches the old canvas
 * without firing Alpine's `destroy()` hook — Chart.js's per-chart RAF loop
 * survives
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
            try { Chart.animator?.remove?.(this); } catch (e) { /* defensive */ }
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
        try { Chart.animator?.remove?.(c); } catch (e) { /* defensive */ }
        try { c.stop?.(); } catch (e) { /* defensive */ }
        try { c.destroy?.(); } catch (e) { /* defensive */ }
        registry.delete(c);
    }
}

export default function wirekitChartJs(config) {
    return {
        chart: null,
        _navCleanup: null,
        _darkModeObserver: null,
        _darkModeDebounce: null,

        // Track which datasets had user-provided colors at init time.
        // These datasets are excluded from dark mode re-theming.
        _manualColorIndices: new Set(),

        init() {
            // Intentional console.error — DX hint when Chart.js peer dependency is missing
            if (typeof Chart === 'undefined') {
                console.error(
                    'WireKit: Chart.js is not loaded. Install it via npm:\n' +
                    '  npm install chart.js\n' +
                    'And import it in your app.js:\n' +
                    '  import { Chart, registerables } from "chart.js";\n' +
                    '  Chart.register(...registerables);'
                );
                return;
            }

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
                    try { Chart.animator?.remove?.(existing); } catch (e) { /* defensive */ }
                    try { existing.stop?.(); } catch (e) { /* defensive */ }
                    try { existing.destroy?.(); } catch (e) { /* defensive */ }
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

                // Defensive detach-guard plugin. The docs-site replay button
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
                            try { chart.stop(); } catch (e) { /* idempotent */ }
                            try { chart.destroy(); } catch (e) { /* idempotent */ }
                            return false;
                        }
                    },
                }]);

                this.chart = new Chart(ctx, rawConfig);
                getRegistry().add(this.chart);

                // Set up dark mode observer AFTER chart is created.
                // This avoids the race condition where the observer fires
                // before $nextTick completes and this.chart is still null.
                this._setupDarkModeObserver();
            });

            // Cleanup on Livewire navigation (SPA mode)
            this._navCleanup = () => this.destroy();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Wire-streaming setup (Extension 12.3) — read data-wire-stream-*
            // attributes off the root and register a window listener so
            // Livewire $dispatch('<event>', { point }) calls feed the live chart.
            this._setupWireStream();

            // Annotations plugin warning (Extension 12.4) — Chart.js requires
            // chartjs-plugin-annotation. Emit a console.warn at init time when
            // annotations are present but the plugin is missing, so consumers
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
         * 'stream' mode grows unboundedly — consumer's responsibility to
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
            this._darkModeObserver.observe(document.body, observerOpts);
        },

        /**
         * Destroy chart instance, release references, and remove listeners.
         * Safe to call multiple times (idempotent).
         */
        destroy() {
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
            if (this._wireStreamHandler) {
                const eventName = this.$el?.dataset?.wireStreamEvent;
                if (eventName) {
                    window.removeEventListener(eventName, this._wireStreamHandler);
                }
                this._wireStreamHandler = null;
            }
            if (this.chart) {
                getRegistry().delete(this.chart);
                try { Chart.animator?.remove?.(this.chart); } catch (e) { /* defensive */ }
                try { this.chart.stop?.(); } catch (e) { /* defensive */ }
                try { this.chart.destroy(); } catch (e) { /* defensive */ }
                this.chart = null;
            }
        },

        /**
         * Theme-colour readers — thin wrappers around the shared util in
         * resources/js/utils/chart-theme-colors.js — single source of truth.
         * Both wirekitChartJs and wirekitApexChart consume the same helpers
         * so dataset palettes, fallbacks, and probe behaviour stay in lockstep.
         */
        _resolveThemeColors(style) { return resolveThemeColors(style); },
        _palette(colors)           { return palette(colors); },
        _withOpacity(color, op)    { return withOpacity(color, op); },

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
                        // Pie / doughnut: each slice is a flat colour wedge.
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
            const isDark = document.documentElement.classList.contains('dark')
                || document.body.classList.contains('dark');

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
            // looks stale after dark-mode toggle" artefacts.
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
         */
        _applyGlobalDefaults(colors, fontFamily) {
            const isDark = document.documentElement.classList.contains('dark')
                || document.body.classList.contains('dark');

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
