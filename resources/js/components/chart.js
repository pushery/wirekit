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

            this.$nextTick(() => {
                const ctx = this.$refs.canvas.getContext('2d');

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

                this.chart = new Chart(ctx, rawConfig);

                // Set up dark mode observer AFTER chart is created.
                // This avoids the race condition where the observer fires
                // before $nextTick completes and this.chart is still null.
                this._setupDarkModeObserver();
            });

            // Cleanup on Livewire navigation (SPA mode)
            this._navCleanup = () => this.destroy();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });
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
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
        },

        /**
         * Read WireKit CSS variables and return a color object.
         *
         * Tailwind v4 stores colors as oklch() values. Canvas 2D does NOT
         * reliably support oklch() — Chart.js silently falls back to black.
         * We solve this by setting each CSS variable as `color` on a temporary
         * DOM element and reading `getComputedStyle().color`, which the browser
         * always resolves to rgb()/rgba() regardless of the input format.
         */
        _resolveThemeColors(style) {
            const isDark = document.documentElement.classList.contains('dark')
                || document.body.classList.contains('dark');

            // Probe element: hidden div inside <body> so var() resolves
            // through the correct cascade. .dark can be on <html> OR <body>
            // (both work because the probe inherits from its ancestors).
            // Appending to <html> would fail if .dark is on <body> only,
            // since the probe would be a sibling of <body> outside the cascade.
            const probe = document.createElement('div');
            probe.style.display = 'none';
            document.body.appendChild(probe);

            const resolve = (varName, fallbackLight, fallbackDark) => {
                // Check if the variable is defined at all
                const raw = style.getPropertyValue(varName).trim();
                if (!raw) {
                    return isDark ? fallbackDark : fallbackLight;
                }

                // Set the variable as color on the probe element.
                // getComputedStyle().color always returns rgb()/rgba().
                probe.style.color = `var(${varName})`;
                const rgb = getComputedStyle(probe).color;
                probe.style.color = '';
                return rgb;
            };

            const colors = {
                accent:      resolve('--color-wk-accent', '#3b82f6', '#60a5fa'),
                danger:      resolve('--color-wk-danger', '#ef4444', '#f87171'),
                success:     resolve('--color-wk-success', '#22c55e', '#4ade80'),
                warning:     resolve('--color-wk-warning', '#f59e0b', '#fbbf24'),
                info:        resolve('--color-wk-info', '#06b6d4', '#22d3ee'),
                textPrimary: resolve('--color-wk-text', '#18181b', '#f4f4f5'),
                textMuted:   resolve('--color-wk-text-muted', '#71717a', '#a1a1aa'),
                border:      resolve('--color-wk-border', '#e4e4e7', '#52525b'),
            };

            probe.remove();
            return colors;
        },

        /**
         * Color palette for multiple datasets. Cycles when more datasets than colors.
         */
        _palette(colors) {
            return [
                colors.accent,
                colors.danger,
                colors.success,
                colors.warning,
                colors.info,
                '#8b5cf6', // purple fallback
                '#ec4899', // pink fallback
                '#f97316', // orange fallback
            ];
        },

        /**
         * Create a transparent variant of a color.
         *
         * Works with hex (#rrggbb) and rgb() colors.
         * Returns rgba() which works in all contexts.
         */
        _withOpacity(color, opacity) {
            // Hex -> rgba
            if (color.startsWith('#')) {
                const r = parseInt(color.slice(1, 3), 16);
                const g = parseInt(color.slice(3, 5), 16);
                const b = parseInt(color.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${opacity})`;
            }

            // rgb(r, g, b) -> rgba(r, g, b, opacity)
            if (color.startsWith('rgb(')) {
                return color.replace('rgb(', 'rgba(').replace(')', `, ${opacity})`);
            }

            // rgba already — replace opacity
            if (color.startsWith('rgba(')) {
                return color.replace(/,\s*[\d.]+\)$/, `, ${opacity})`);
            }

            // Fallback: return as-is (hsl etc.)
            return color;
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
                    if (['pie', 'doughnut', 'polarArea'].includes(config.type)) {
                        // Pie-like: each data point gets its own color
                        const len = dataset.data?.length || 1;
                        dataset.backgroundColor = palette.slice(0, len)
                            .map(c => this._withOpacity(c, 0.5));
                        dataset.borderColor = palette.slice(0, len);
                    } else if (config.type === 'bar') {
                        dataset.backgroundColor = this._withOpacity(color, 0.5);
                        dataset.borderColor = color;
                        dataset.borderWidth = 1;
                    } else {
                        // Line, radar, area, scatter, etc.
                        dataset.backgroundColor = this._withOpacity(color, 0.12);
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

                if (['pie', 'doughnut', 'polarArea'].includes(type)) {
                    const len = dataset.data?.length || 1;
                    dataset.backgroundColor = palette.slice(0, len)
                        .map(c => this._withOpacity(c, 0.5));
                    dataset.borderColor = palette.slice(0, len);
                } else if (type === 'bar') {
                    dataset.backgroundColor = this._withOpacity(color, 0.5);
                    dataset.borderColor = color;
                } else {
                    // Line, radar, area, scatter, etc.
                    dataset.backgroundColor = this._withOpacity(color, 0.12);
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
