import { resolveThemeColors, palette, resolveCssVarsDeep } from '../utils/chart-theme-colors.js';

/**
 * Unified tooltip renderer for every ApexCharts type. Emits ApexCharts'
 * NATIVE CSS classes (`.apexcharts-tooltip-title`,
 * `.apexcharts-tooltip-series-group`, `.apexcharts-tooltip-marker`,
 * `.apexcharts-tooltip-text-y-label/value`) — ApexCharts' own stylesheet
 * then renders the grey header + white body + circle marker layout
 * automatically. We only control the SHAPE of the data: header always
 * = x-axis category / x-value, body always = one row per stat (marker
 * + label + value). Per-chart-type renderers ship with inconsistent
 * roles (range-bar puts series-name as header; scatter puts x-value
 * as header; pie has no header). The unified renderer locks the
 * scatter-bubble layout — which the user signalled as canonical —
 * onto every apex demo without touching ApexCharts' visual styling.
 *
 * The PHP adapter ships a `WIREKIT_DEFAULT_TOOLTIP` sentinel under
 * `tooltip.custom`; the Alpine factory swaps it for THIS function
 * before passing the config to ApexCharts.
 */
function renderUnifiedTooltip({ series, seriesIndex, dataPointIndex, w }) {
    const cfg = (w && w.config) || {};
    const g = (w && w.globals) || {};
    const apexType = cfg.chart && cfg.chart.type;
    const fmtTs = (v) => (typeof v === 'number' && v > 1e10)
        ? new Date(v).toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' })
        : v;
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    // Numeric value formatting — driven by the wk* keys the PHP adapter
    // threads onto `tooltip` when the developer sets valueDecimals /
    // valuePrefix / valueSuffix on <x-wirekit-chart>. Only genuine finite
    // numbers are formatted: decimals via toFixed(N), then prefix + suffix.
    // Composite values (range "a – b", OHLC tuples already joined to strings)
    // and non-numeric labels pass through untouched. When all three are unset
    // this returns the value verbatim, preserving pre-feature output.
    const tcfg = cfg.tooltip || {};
    // Clamp to toFixed()'s legal 0–100 range defensively. The PHP adapter
    // already clamps, but Number.prototype.toFixed() throws a RangeError for
    // any out-of-range argument, so guarding here too means no caller can ever
    // crash the tooltip render with a stray decimals value.
    const wkDecimals = (typeof tcfg.wkValueDecimals === 'number' && Number.isFinite(tcfg.wkValueDecimals))
        ? Math.min(100, Math.max(0, Math.trunc(tcfg.wkValueDecimals)))
        : null;
    const wkPrefix = tcfg.wkValuePrefix || '';
    const wkSuffix = tcfg.wkValueSuffix || '';
    const fmtValue = (v) => {
        if (typeof v === 'number' && Number.isFinite(v)) {
            const n = (wkDecimals !== null)
                ? v.toFixed(wkDecimals)
                : String(v);
            return `${wkPrefix}${n}${wkSuffix}`;
        }
        return v;
    };

    // Resolve x-label (header). Priority: hovered series' data-point `x`
    // (scatter / bubble / range-bar object form) → globals.labels (bar /
    // line / area / heatmap) → globals.categoryLabels → seriesX (numeric).
    const hoveredEntry = (cfg.series && cfg.series[seriesIndex]) || {};
    const hoveredPoint = hoveredEntry && hoveredEntry.data && hoveredEntry.data[dataPointIndex];
    let xLabel = '';
    if (hoveredPoint && typeof hoveredPoint === 'object' && !Array.isArray(hoveredPoint) && 'x' in hoveredPoint) {
        xLabel = hoveredPoint.x;
    } else if (g.labels && g.labels[dataPointIndex] !== undefined) {
        xLabel = g.labels[dataPointIndex];
    } else if (g.categoryLabels && g.categoryLabels[dataPointIndex] !== undefined) {
        xLabel = g.categoryLabels[dataPointIndex];
    } else if (g.seriesX && g.seriesX[seriesIndex] && g.seriesX[seriesIndex][dataPointIndex] !== undefined) {
        xLabel = g.seriesX[seriesIndex][dataPointIndex];
    }

    // Per-series row builder — extracts the {label, value} pairs for ONE
    // series at the given data-point index. Used in both shared and
    // single-series modes. Each pair becomes one body row.
    const rowsForSeries = (sIdx) => {
        const entry = (cfg.series && cfg.series[sIdx]) || {};
        const sName = (typeof entry === 'object' && entry.name) || '';
        const rawPoint = entry && entry.data && entry.data[dataPointIndex];
        const rows = [];

        if (Array.isArray(rawPoint) && rawPoint.length === 5) {
            // Boxplot tuple [min, Q1, median, Q3, max]
            const [min, q1, med, q3, max] = rawPoint;
            rows.push({ label: 'Max', value: max });
            rows.push({ label: 'Q3', value: q3 });
            rows.push({ label: 'Median', value: med });
            rows.push({ label: 'Q1', value: q1 });
            rows.push({ label: 'Min', value: min });
        } else if (Array.isArray(rawPoint) && rawPoint.length === 4) {
            // Candlestick tuple [open, high, low, close]
            const [o, h, l, c] = rawPoint;
            rows.push({ label: 'Open', value: o });
            rows.push({ label: 'High', value: h });
            rows.push({ label: 'Low', value: l });
            rows.push({ label: 'Close', value: c });
        } else if (rawPoint && typeof rawPoint === 'object' && !Array.isArray(rawPoint)) {
            // Object form — scatter/bubble {x,y,z?}, range-bar {x,y:[a,b]},
            // candlestick {x,y:[O,H,L,C]}, boxplot {x,y:[5-tuple]}.
            const y = rawPoint.y;
            if (Array.isArray(y) && y.length === 5) {
                const [min, q1, med, q3, max] = y;
                rows.push({ label: 'Max', value: max });
                rows.push({ label: 'Q3', value: q3 });
                rows.push({ label: 'Median', value: med });
                rows.push({ label: 'Q1', value: q1 });
                rows.push({ label: 'Min', value: min });
            } else if (Array.isArray(y) && y.length === 4) {
                const [o, h, l, c] = y;
                rows.push({ label: 'Open', value: o });
                rows.push({ label: 'High', value: h });
                rows.push({ label: 'Low', value: l });
                rows.push({ label: 'Close', value: c });
            } else if (Array.isArray(y) && y.length === 2) {
                rows.push({ label: sName, value: `${fmtTs(y[0])} – ${fmtTs(y[1])}` });
            } else if (y !== undefined) {
                rows.push({ label: sName, value: y });
            }
            if ('z' in rawPoint) {
                rows.push({ label: 'Size', value: rawPoint.z });
            }
        } else if (Array.isArray(rawPoint) && rawPoint.length === 2 && (apexType === 'rangeBar' || apexType === 'rangeArea')) {
            rows.push({ label: sName, value: `${fmtTs(rawPoint[0])} – ${fmtTs(rawPoint[1])}` });
        } else if (Array.isArray(series[sIdx])) {
            // Cartesian (bar / line / area / radar) — series[i] is number[]
            rows.push({ label: sName, value: series[sIdx][dataPointIndex] });
        } else if (typeof series[sIdx] === 'number') {
            // Pie / donut / radialBar / polarArea — series itself is number[]
            rows.push({ label: '', value: series[sIdx] });
        } else if (rawPoint !== undefined) {
            rows.push({ label: sName, value: String(rawPoint) });
        }
        return rows;
    };

    // SHARED-MODE detection — mixed charts and any other chart configured
    // with `tooltip.shared: true` should show EVERY series' value at the
    // hovered x in a single tooltip panel, not just the series whose data
    // shape was directly under the cursor. Without this branch the user
    // sees only one of (Revenue / Growth) on
    // /components/charts-apex/mixed even though both have values at that
    // x. ApexCharts calls the custom callback ONCE per render (not once
    // per series) with `seriesIndex` set to the closest series — so the
    // shared-mode rendering is OUR responsibility inside the custom
    // callback.
    const sharedMode = cfg.tooltip && cfg.tooltip.shared === true;
    const seriesIndices = (sharedMode && Array.isArray(cfg.series) && cfg.series.length > 1)
        ? cfg.series.map((_, i) => i)
        : [seriesIndex];

    // Accumulate rows from every contributing series. Each row carries its
    // own series colour so multi-series tooltips render the correct marker
    // colour per row.
    const bodyRows = [];
    seriesIndices.forEach((sIdx) => {
        const color = (g.colors && g.colors[sIdx]) || '#888';
        rowsForSeries(sIdx).forEach((r) => {
            bodyRows.push({ ...r, color });
        });
    });

    const headerHtml = (xLabel !== '' && xLabel !== undefined && xLabel !== null)
        ? `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${esc(xLabel)}</div>`
        : '';

    const bodyHtml = bodyRows.map(({ label, value, color }) => {
        const labelHtml = label ? `<span class="apexcharts-tooltip-text-y-label">${esc(label)}: </span>` : '';
        return `<div class="apexcharts-tooltip-series-group apexcharts-active" style="order: 1; display: flex;">
            <span class="apexcharts-tooltip-marker" style="background: ${color};"></span>
            <div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">
                <div class="apexcharts-tooltip-y-group">
                    ${labelHtml}<span class="apexcharts-tooltip-text-y-value">${esc(fmtValue(value))}</span>
                </div>
            </div>
        </div>`;
    }).join('');

    return `${headerHtml}${bodyHtml}`;
}

/**
 * WireKit ApexCharts Alpine Component.
 *
 * Initializes an ApexCharts instance with automatic WireKit theming via CSS
 * variables. A MutationObserver on <html> + <body> watches for .dark class
 * toggles and re-applies theme colors via chart.updateOptions().
 *
 * License notice — ApexCharts is non-MIT. Developers below the $2M USD
 * annual-revenue threshold use the Community License (free); developers
 * above must purchase a Commercial License from ApexCharts. WireKit ships
 * only this Alpine glue (MIT); the JS library (apexcharts npm package) is
 * the developer's install + license responsibility.
 *
 * @param {Object} config - ApexCharts options object (chart, series, xaxis,
 *   etc.) emitted by ApexChartsAdapter.normalizeData() + defaultOptions().
 *   Passed via Alpine x-data; unwrapped with Alpine.raw() before handing to
 *   ApexCharts to avoid Proxy-in-Proxy issues.
 *
 * Lifecycle:
 * - init(): creates chart + observer inside $nextTick (after DOM ready)
 * - destroy(): cleans up chart, observer, and Livewire event listener
 *
 * Cleanup is automatic on Livewire SPA navigation (livewire:navigating)
 * and Alpine component teardown (destroy() lifecycle hook).
 */
export default function wirekitApexChart(config) {
    return {
        chart: null,
        _navCleanup: null,
        _darkModeObserver: null,
        _darkModeDebounce: null,
        _manualColorIndices: new Set(),
        // Focus-guard for the aria-hidden mount (see _setupFocusGuard).
        _focusGuardMount: null,
        _focusBlurHandler: null,

        init() {
            // ApexCharts peer-dependency guard. WireKit ships only the
            // adapter glue (`dist/wirekit-apex.js`, ~2 KB) — the developer
            // installs `apexcharts` via npm and exposes it on
            // `window.ApexCharts` per the chart-component docs.
            //
            // When the global is missing we render a visible in-DOM
            // fallback panel inside the chart container (instead of
            // returning silently with only a console.error, which paints
            // a blank white box that's indistinguishable from a styling
            // bug). The on-screen panel surfaces the install command, the
            // license reminder, and a link to apexcharts.com/license so
            // the developer can act without opening DevTools first.
            if (typeof ApexCharts === 'undefined') {
                // Deduplicate the console.error so N apex charts on the same
                // page emit ONE warning instead of N. The in-DOM fallback
                // panel still renders per-chart (each chart needs its own
                // visible advisory). (v2.4.0 R5 polish.)
                if (typeof window !== 'undefined' && !window.__wirekit_apexcharts_missing_warned__) {
                    window.__wirekit_apexcharts_missing_warned__ = true;
                    console.error(
                        'WireKit: ApexCharts is not loaded. Install it via npm:\n' +
                        '  npm install apexcharts\n' +
                        'And import it in your app.js:\n' +
                        '  import ApexCharts from "apexcharts";\n' +
                        '  window.ApexCharts = ApexCharts;\n' +
                        '\nLicense reminder: ApexCharts is non-MIT.\n' +
                        'See https://apexcharts.com/license/ for terms.'
                    );
                }

                this.$nextTick(() => {
                    const mount = this.$refs.mount;
                    if (!mount) {
                        return;
                    }

                    // Inline styles only — no Tailwind utilities, no CSS-
                    // variable lookups (the developer might have a misconfig
                    // there too; the fallback must paint reliably no matter
                    // what state the surrounding theme is in). Reads as a
                    // muted-yellow advisory panel on light backgrounds and
                    // adapts to dark mode via CSS color-scheme inheritance.
                    mount.innerHTML = `
                        <div role="alert"
                             style="
                                padding: 1rem 1.25rem;
                                border: 1px solid rgba(180, 83, 9, 0.4);
                                border-left: 4px solid rgb(180, 83, 9);
                                border-radius: 0.375rem;
                                background: rgba(254, 243, 199, 0.5);
                                color: rgb(120, 53, 15);
                                font-family: system-ui, -apple-system, sans-serif;
                                font-size: 0.8125rem;
                                line-height: 1.5;
                             ">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">
                                ApexCharts is not loaded.
                            </div>
                            <p style="margin: 0 0 0.5rem 0;">
                                WireKit's ApexCharts adapter glue is loaded, but the
                                <code style="font-family: ui-monospace, monospace; font-size: 0.85em; padding: 0.05rem 0.25rem; background: rgba(255, 255, 255, 0.6); border-radius: 0.2rem;">apexcharts</code>
                                npm package is missing or not exposed on
                                <code style="font-family: ui-monospace, monospace; font-size: 0.85em; padding: 0.05rem 0.25rem; background: rgba(255, 255, 255, 0.6); border-radius: 0.2rem;">window.ApexCharts</code>.
                            </p>
                            <p style="margin: 0 0 0.5rem 0;">
                                Install it and expose it globally:
                            </p>
                            <pre style="margin: 0 0 0.5rem 0; padding: 0.625rem 0.75rem; background: rgba(255, 255, 255, 0.7); border-radius: 0.25rem; font-family: ui-monospace, monospace; font-size: 0.75rem; line-height: 1.5; overflow-x: auto;">npm install apexcharts

// resources/js/app.js
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;</pre>
                            <p style="margin: 0; font-size: 0.75rem; opacity: 0.9;">
                                <strong>License reminder:</strong> ApexCharts is non-MIT.
                                See <a href="https://apexcharts.com/license/"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       style="color: rgb(120, 53, 15); text-decoration: underline;">apexcharts.com/license</a>
                                for terms (Community free under $2M USD revenue, Commercial above).
                            </p>
                        </div>
                    `;
                });

                return;
            }

            this.$nextTick(() => {
                const mount = this.$refs.mount;
                if (!mount) return;

                // Alpine.raw() strips the reactive Proxy wrapper. ApexCharts
                // creates its own internal data structures; feeding it a
                // reactive Proxy can cause unexpected double-tracking.
                const rawConfig = this._flattenApexConfig(Alpine.raw(config));

                // Read CSS variables off the mount element — resolves correctly
                // regardless of whether .dark is on <html>, <body>, or an ancestor.
                const style = getComputedStyle(mount);
                const colors = this._resolveThemeColors(style);
                const fontFamily = style.getPropertyValue('--font-wk-sans').trim()
                    || 'ui-sans-serif, system-ui, sans-serif';

                // Track datasets that carry user-supplied colours BEFORE we apply
                // theme defaults — those are excluded from dark-mode re-theming
                // so the developer's explicit colour choices stay frozen across
                // the toggle.
                (rawConfig.series || []).forEach((series, i) => {
                    if (series && series.color) this._manualColorIndices.add(i);
                });

                const themed = this._themeApexConfig(rawConfig, colors, fontFamily);

                // Swap the PHP sentinel for the unified tooltip renderer.
                // Developer-supplied `tooltip.custom` (a function) wins.
                if (themed.tooltip && themed.tooltip.custom === 'WIREKIT_DEFAULT_TOOLTIP') {
                    themed.tooltip.custom = renderUnifiedTooltip;
                }

                // Per-type tooltip auto-formatters. ApexCharts' native
                // renderers don't always handle tuple-shaped y-values
                // gracefully — range-bar shows a raw timestamp,
                // boxplot collapses the 5-tuple into a single value.
                // No PHP path exists to pass a JS formatter function
                // via the developer's `:options`, so we auto-install
                // sensible defaults here. Developer overrides win
                // (we only install when no formatter / custom is
                // already set).
                const apexType = themed.chart?.type;

                // Per-type auto-formatters previously lived here for
                // range-bar / boxplot / candlestick. All three (plus
                // every other ApexCharts type) are now subsumed by the
                // unified `renderUnifiedTooltip` swap above — single
                // tooltip layout across every demo, matched to the
                // scatter-bubble canonical look the user signalled.

                // Honour reduced-motion at chart-construction time: disable
                // animations entirely when the OS preference is set, so the
                // first paint is instant.
                if (this._reducedMotion()) {
                    if (!themed.chart) themed.chart = {};
                    themed.chart.animations = { enabled: false };
                }

                // Mixed-chart line-marker suppression. ApexCharts has a
                // DOCUMENTED limitation — `tooltip.shared: true, intersect:
                // false` (which mixed bar+line charts need so hovering
                // anywhere shows all series' values) is mutually exclusive
                // with per-series active-marker TRACKING on the line series.
                // ApexCharts still renders a STATIC active-marker dot on
                // every line vertex on hover — the dot stays at the
                // highest-Y vertex regardless of where the cursor is, which
                // reads as a "ghost data point stuck in mid-air" (visible on
                // /components/charts-apex/mixed as the red dot at the line's
                // peak even when the cursor is hovering a different bar).
                // An earlier overlay workaround (custom <circle> per line
                // series, repositioned in a mouseMove handler that
                // recomputed the nearest x-index from grid geometry) made
                // things worse — the overlay anchored to a different
                // x-index than the bar tooltip, producing two visibly
                // disconnected red dots. Right fix: SUPPRESS the active-
                // marker dot on every line-like series in a mixed chart.
                // The shared tooltip already shows the line's y-value for
                // the hovered x, so the marker is redundant; killing it
                // removes the ghost and matches the bar-only mental model
                // ("hover anywhere on the chart → tooltip shows all values
                // at that x").
                const hasBar = (themed.series || []).some(
                    (s) => s && (s.type === 'bar' || s.type === 'column')
                );
                const hasLineLike = (themed.series || []).some(
                    (s) => s && (s.type === 'line' || s.type === 'area' || s.type === undefined)
                );
                const isMixed = apexType === 'line' && hasBar && hasLineLike;
                if (isMixed) {
                    themed.markers = themed.markers || {};
                    // size: 0 — line draws as a pure smooth curve with no
                    // dot at each data point (visually cleaner for the
                    // overlay-on-bars composition).
                    if (themed.markers.size === undefined) themed.markers.size = 0;
                    // hover.size: 0 — kills the active-marker dot that
                    // ApexCharts otherwise renders on the line when the
                    // shared tooltip fires (the ghost-dot symptom).
                    themed.markers.hover = themed.markers.hover || {};
                    if (themed.markers.hover.size === undefined) themed.markers.hover.size = 0;
                    if (themed.markers.hover.sizeOffset === undefined) themed.markers.hover.sizeOffset = 0;
                }

                // Hover state for cells with colour-scale shading (heatmap +
                // treemap). ApexCharts defaults `states.hover.filter.type =
                // 'lighten'` with a strong value, which drives already-pale
                // colour-scaled cells (low-value heatmap cells, small treemap
                // cells) toward near-white on hover, making the hovered cell
                // effectively invisible. Force a subtle `darken` instead so
                // light cells get a touch darker (visible affordance) and
                // dark cells stay hoverable.
                //
                // Tooltip POSITIONING for all five cell-shape types (radar,
                // heatmap, treemap, range-bar, range-area) is handled by the
                // native-DOM rAF pin loop further down — after this.chart.render().
                // The previous event-based `themed.chart.events.dataPointMouseEnter`
                // / `mouseMove` overrides for these types were SUPERSEDED in
                // commit 9afe772 (the wobble fix) and are no longer registered.
                if (apexType === 'heatmap' || apexType === 'treemap') {
                    themed.states = themed.states || {};
                    themed.states.hover = themed.states.hover || {};
                    themed.states.hover.filter = themed.states.hover.filter || {
                        type: 'darken',
                        value: 0.12,
                    };
                }

                // Clear any pre-existing rendered SVG inside the mount
                // before constructing a fresh ApexCharts instance. On
                // the FIRST mount the mount is empty (Blade rendered
                // nothing inside `<div x-ref="mount">`), so this is a
                // no-op. On REPLAY (a host application that saves the
                // mount's innerHTML, clears it, re-injects, then calls
                // Alpine.initTree) the saved markup contains the
                // previously-rendered chart SVG. Without this clear,
                // ApexCharts detects existing canvas children and skips
                // the entrance animation — visible to the user as "the
                // replay button does nothing". Forcing an empty mount
                // guarantees a clean slate so `render()` always plays
                // the entrance animation.
                mount.innerHTML = '';
                // Resolve every `var(--token)` reference in the config
                // tree — per-dataset `color`, heatmap colorScale ranges,
                // annotation fillColor / borderColor, candlestick stroke
                // colours, timeline fillColor — ApexCharts hands these
                // straight to SVG `fill="…"`, which does NOT parse CSS
                // vars. Without this walk, every such reference silently
                // falls back to ApexCharts' default first-series blue.
                const resolvedThemed = resolveCssVarsDeep(themed, style);
                this.chart = new ApexCharts(mount, resolvedThemed);
                this.chart.render();

                // Radar tooltip — bypass ApexCharts' event system.
                // Three previous iterations relying on themed.chart.events.
                // dataPointMouseEnter ALL had no observable effect: ApexCharts'
                // radar implementation uses cursor-proximity over the polygon
                // fill to determine the "active vertex" without firing a
                // standard data-point event we can hook. Native DOM events on
                // the rendered SVG ARE reliable. Strategy:
                //   1. Track which marker `<path>` is currently under the
                //      cursor via `pointermove` on the chart's baseEl.
                //   2. Observe the tooltip element's inline style for changes
                //      (ApexCharts repositions it on every cursor move); each
                //      time it changes, overwrite with our marker-anchored
                //      position. A reentry guard prevents infinite loops.
                //   3. Use the marker `<path>`'s getBoundingClientRect() —
                //      the browser composes all parent SVG transforms
                //      (`translate(2, 30)` + `translate(centreX, centreY)` +
                //      `cx/cy` offsets) automatically.
                // Native-DOM tooltip anchor for cell-shape charts.
                //
                // Why this exists: for radar / heatmap / treemap / range-bar
                // / range-area, ApexCharts' internal tooltip positioning is
                // unreliable — radar uses proximity-based vertex detection
                // and skips dataPointMouseEnter; treemap stamps a top-of-
                // canvas fallback on first hover that persists; range-bar
                // misses bars near the chart edge. The user-visible result
                // is tooltips floating away from the hovered cell/marker.
                //
                // Why an rAF pin loop (and NOT a MutationObserver):
                //   - ApexCharts continuously rewrites `style.left/top`
                //     during cursor movement, sometimes via `cssText`
                //     replacement which strips `!important`.
                //   - A MutationObserver that fires on each write and
                //     overrides creates a visible 2-state wobble: my
                //     position → ApexCharts' position → my position → …
                //   - An rAF loop running at ~60 fps writes our position
                //     every frame, racing ApexCharts' once-per-mousemove
                //     writes, so the tooltip stays visually pinned with
                //     no oscillation.
                //
                // The loop is bounded: it runs only while the cursor is
                // inside the chart (pointerenter / pointerleave gates it)
                // and only while the tooltip carries `apexcharts-active`,
                // so it's cheap when no one is hovering.
                if (['heatmap', 'treemap', 'radar', 'rangeBar', 'rangeArea'].includes(apexType)) {
                    const cellChart = this.chart;
                    // Verified against actual ApexCharts DOM via sample/
                    // diagnostic tests — classes are `apexcharts-{type}-area`
                    // with hyphens (rangebar / rangearea), NOT camelCase
                    // (the chart-type config key IS camelCase: rangeBar,
                    // rangeArea — different convention).
                    const cellShapeSelectorByType = {
                        heatmap: '.apexcharts-heatmap-rect',
                        treemap: '.apexcharts-treemap-rect',
                        rangeBar: '.apexcharts-rangebar-area',
                        rangeArea: '.apexcharts-rangearea-area',
                    };

                    const waitForTooltip = (attempts) => {
                        const baseEl = cellChart?.w?.globals?.dom?.baseEl;
                        const tooltipEl = cellChart?.w?.globals?.dom?.elTooltip
                            || baseEl?.querySelector('.apexcharts-tooltip');
                        if (!baseEl || !tooltipEl) {
                            if (attempts > 0) requestAnimationFrame(() => waitForTooltip(attempts - 1));
                            return;
                        }
                        setupNativeAnchor(baseEl, tooltipEl);
                    };

                    const setupNativeAnchor = (baseEl, tooltipEl) => {
                        let pointerTarget = null;
                        let pinLoopActive = false;
                        let pinRaf = 0;

                        // Per-type "which data element is hovered?" resolver.
                        //
                        // For radar: prefer the marker the cursor is OVER
                        // (pointerTarget). Title-based lookup is the
                        // fallback for the case where the cursor moved off
                        // the marker into the polygon fill — ApexCharts
                        // keeps the tooltip active via its proximity
                        // detection but pointerTarget points at the
                        // polygon `<path>` (caught + cached by onActivity
                        // when `.apexcharts-marker` matches; null when the
                        // cursor is over the polygon only).
                        const resolveRadarMarkerByTitle = () => {
                            const title = tooltipEl.querySelector('.apexcharts-tooltip-title')?.textContent?.trim();
                            if (!title) return null;
                            const labels = baseEl.querySelectorAll('.apexcharts-xaxis-label');
                            let idx = -1;
                            labels.forEach((label, i) => {
                                if (label.textContent.trim() === title) idx = i;
                            });
                            if (idx < 0) return null;
                            return baseEl.querySelector(`.apexcharts-marker[j="${idx}"]`);
                        };
                        const resolveActiveElement = () => {
                            if (apexType === 'radar') {
                                // 1) Pointer target if it's a marker AND still
                                //    in the DOM.
                                if (pointerTarget && pointerTarget.isConnected
                                    && pointerTarget.classList?.contains('apexcharts-marker')) {
                                    return pointerTarget;
                                }
                                // 2) Title-based lookup fallback.
                                return resolveRadarMarkerByTitle();
                            }
                            // Cell-shape types — last cell the cursor passed
                            // over. `intersect: true` means the tooltip can
                            // only be active while the cursor is over a cell.
                            return pointerTarget;
                        };

                        // The actual per-frame pin. Computes the desired
                        // position once and writes it inline. We do NOT
                        // use applyPositionFor's double-rAF retry here —
                        // by the time the loop starts, the tooltip has
                        // real dimensions, and any 0×0 frame just gets
                        // re-tried on the next rAF tick.
                        const pinFrame = () => {
                            pinRaf = 0;
                            if (!pinLoopActive) return;
                            // Defensive: if the chart has been destroyed
                            // (livewire navigate, replay re-mount), the
                            // tooltip / base elements may already be
                            // detached from the document. Reading classList
                            // / getBoundingClientRect on a detached element
                            // is safe but pointless — bail without queuing
                            // another frame so the loop terminates cleanly.
                            if (!tooltipEl || !tooltipEl.isConnected || !baseEl || !baseEl.isConnected) {
                                stopPin();
                                return;
                            }
                            // No-op when the tooltip is hidden — ApexCharts
                            // controls show/hide via the
                            // `apexcharts-active` class.
                            if (tooltipEl.classList.contains('apexcharts-active')) {
                                const target = resolveActiveElement();
                                if (target && typeof target.getBoundingClientRect === 'function') {
                                    const cellRect = target.getBoundingClientRect();
                                    const baseRect = baseEl.getBoundingClientRect();
                                    const tipRect = tooltipEl.getBoundingClientRect();
                                    if (tipRect.width > 0 && tipRect.height > 0) {
                                        const cellCentreX = cellRect.left + cellRect.width / 2;
                                        const cellCentreY = cellRect.top + cellRect.height / 2;
                                        let left;
                                        let top;
                                        if (apexType === 'radar') {
                                            // Bottom-left anchor at marker centre —
                                            // panel extends up-and-right of the vertex
                                            // so the marker stays visible.
                                            left = cellCentreX - baseRect.left;
                                            top = cellCentreY - baseRect.top - tipRect.height;
                                        } else if (apexType === 'rangeBar' || apexType === 'rangeArea') {
                                            // Half-overlap anchor — tooltip's BOTTOM
                                            // edge sits at the bar's CENTRE Y, so the
                                            // top half of the bar gets covered and
                                            // the bottom half stays visible (the user
                                            // can still see where the cursor is).
                                            // Horizontal centre-on-bar with edge-clamp.
                                            //
                                            // If the tooltip would clip the chart top
                                            // (bar near the top edge), flip to BELOW:
                                            // tooltip's TOP edge at the bar's centre Y.
                                            // The bar's bottom half gets covered; top
                                            // half stays visible.
                                            left = cellCentreX - tipRect.width / 2 - baseRect.left;
                                            const aboveTop = cellCentreY - baseRect.top - tipRect.height;
                                            if (aboveTop >= 4) {
                                                top = aboveTop;
                                            } else {
                                                top = cellCentreY - baseRect.top;
                                            }
                                        } else {
                                            // Centre anchor on cell centre —
                                            // cells are large enough that the panel
                                            // doesn't hide the data.
                                            left = cellCentreX - tipRect.width / 2 - baseRect.left;
                                            top = cellCentreY - tipRect.height / 2 - baseRect.top;
                                        }
                                        // Edge-clamp: keep the panel inside the
                                        // chart, with a 4 px gutter. Math.max
                                        // ensures the clamp doesn't produce a
                                        // negative max when the tooltip is wider
                                        // than the chart.
                                        const gutter = 4;
                                        const maxLeft = baseRect.width - tipRect.width - gutter;
                                        const maxTop = baseRect.height - tipRect.height - gutter;
                                        left = Math.max(gutter, Math.min(left, Math.max(gutter, maxLeft)));
                                        top = Math.max(gutter, Math.min(top, Math.max(gutter, maxTop)));
                                        // Write with !important. ApexCharts' own
                                        // CSS has `transition: .15s ease all`
                                        // on `.apexcharts-tooltip` which causes
                                        // every position change to animate over
                                        // 150 ms — combined with our 60 fps
                                        // rAF writes, the tooltip lives in a
                                        // permanent mid-transition state and
                                        // visibly chases the cursor with a
                                        // smear. Kill the transition so each
                                        // frame's write paints immediately.
                                        tooltipEl.style.setProperty('transition', 'none', 'important');
                                        tooltipEl.style.setProperty('transform', 'none', 'important');
                                        tooltipEl.style.setProperty('left', `${left}px`, 'important');
                                        tooltipEl.style.setProperty('top', `${top}px`, 'important');
                                    }
                                }
                            }
                            pinRaf = requestAnimationFrame(pinFrame);
                        };

                        const startPin = () => {
                            if (pinLoopActive) return;
                            pinLoopActive = true;
                            pinRaf = requestAnimationFrame(pinFrame);
                        };
                        const stopPin = () => {
                            pinLoopActive = false;
                            if (pinRaf) {
                                cancelAnimationFrame(pinRaf);
                                pinRaf = 0;
                            }
                            pointerTarget = null;
                        };

                        // Activity handlers — any pointer/mouse motion
                        // inside the chart kicks the pin loop on
                        // (idempotent) and refreshes pointerTarget.
                        // pointerleave kills it. Multiple event kinds
                        // because synthetic test events sometimes only
                        // dispatch one variant and we want to be robust
                        // both in tests and in real browsers.
                        const pointerSelector = cellShapeSelectorByType[apexType];
                        const onActivity = (evt) => {
                            if (pointerSelector) {
                                const el = evt.target?.closest?.(pointerSelector);
                                if (el) pointerTarget = el;
                            }
                            startPin();
                        };

                        const handlePointerLeave = () => stopPin();

                        // Passive — these only schedule a rAF pin loop, never
                        // call preventDefault, so they must not block scroll.
                        baseEl.addEventListener('pointermove', onActivity, { passive: true });
                        baseEl.addEventListener('mousemove', onActivity, { passive: true });
                        baseEl.addEventListener('pointerenter', onActivity, { passive: true });
                        baseEl.addEventListener('mouseover', onActivity, { passive: true });
                        baseEl.addEventListener('pointerleave', handlePointerLeave, { passive: true });
                        baseEl.addEventListener('mouseleave', handlePointerLeave, { passive: true });

                        this._cellShapeTooltipCleanup = () => {
                            stopPin();
                            baseEl.removeEventListener('pointermove', onActivity);
                            baseEl.removeEventListener('mousemove', onActivity);
                            baseEl.removeEventListener('pointerenter', onActivity);
                            baseEl.removeEventListener('mouseover', onActivity);
                            baseEl.removeEventListener('pointerleave', handlePointerLeave);
                            baseEl.removeEventListener('mouseleave', handlePointerLeave);
                        };
                    };
                    requestAnimationFrame(() => waitForTooltip(30));
                }

                // Pause-on-hover for streaming charts. When the cursor enters
                // the chart area, set `_hoverPaused = true` — the wire-stream
                // handler queues incoming points but skips the appendData
                // flush. When the cursor leaves, fire a flush that processes
                // every queued point in one call so the chart "catches up"
                // to the live state in a single quick animation. Net effect:
                // hover = tooltip stays readable on a stable chart; mouseout
                // = chart resyncs to current.
                // No in-component `wirekit:replay` listener. Earlier
                // attempts to destroy + re-mount the ApexCharts instance
                // in place on `wirekit:replay` looked correct on paper
                // (call `destroy()`, build a fresh config, `new
                // ApexCharts(...)`) but read `Alpine.raw(config)` AFTER
                // ApexCharts had mutated the config object in place
                // during the first render — series values, axis ranges,
                // and theme arrays were all overwritten with internal
                // state, so the second mount rendered against corrupted
                // numbers (visible as: bars shrunk to half-height,
                // legend colours scrambled, axis labels wrong). The
                // docs.wirekit.app replay button already reloads the preview
                // iframe — same effect as a fresh page-load, identical
                // behaviour to every other replayable component — so
                // the right fix is to NOT intercept the event here and
                // let the iframe reload take over. `<x-wirekit-chart
                // data-replayable="true">` continues to opt INTO the
                // docs.wirekit.app's button surface; we just don't fight the
                // standard reload behaviour with broken in-place logic.

                this._hoverPaused = false;
                if (this._wireStreamHandler) {
                    this._hoverEnterHandler = () => { this._hoverPaused = true; };
                    this._hoverLeaveHandler = () => {
                        this._hoverPaused = false;
                        if (this._wireStreamQueue && this._wireStreamQueue.length > 0
                            && !this._wireStreamFlushScheduled
                            && typeof this._wireStreamFlush === 'function') {
                            this._wireStreamFlushScheduled = true;
                            queueMicrotask(this._wireStreamFlush);
                        }
                    };
                    // Store mount ref + bound handlers on `this` so destroy()
                    // can remove them. Without this, `mount` was a closure-
                    // local in the wireStream branch and destroy() couldn't
                    // reach it; the hover-pause listeners stayed attached
                    // to the DOM node even after Alpine torn down the
                    // component — a leak across every Livewire morph that
                    // recreated the chart.
                    this._mount = mount;
                    mount.addEventListener('mouseenter', this._hoverEnterHandler);
                    mount.addEventListener('mouseleave', this._hoverLeaveHandler);
                }

                // Detach-guard — docs.wirekit.app replay button reassigns the
                // preview frame's innerHTML, which severs our mount node
                // without firing Alpine's destroy() hook. ApexCharts'
                // internal ResizeObserver and animation loop then run on a
                // detached SVG, sometimes throwing on the next dark-mode
                // re-theme or wire-stream append. Poll cheaply via RAF and
                // tear down on first detached frame so the orphaned chart
                // can't cascade-crash a follow-up preview re-render.
                this._setupDetachGuard(mount);

                // A11y + visual fix (all ApexCharts types). The parent wrapper
                // carries `role="img"` + aria-label (the accessible
                // representation); THIS mount is `aria-hidden="true"` to hide
                // ApexCharts' verbose SVG DOM from assistive tech. But the
                // ApexCharts <svg> takes focus on click/tap, which both (a)
                // trips Chrome's "Blocked aria-hidden because a descendant
                // retained focus" console warning and (b) paints a stray focus
                // ring on the chart's FIRST element (looks like the wrong bar
                // is highlighted when you click another). Since the whole mount
                // is decorative by design, nothing inside it should hold focus
                // — immediately blur anything that gains it. Capture-phase so
                // it fires before focus settles; pointer-driven tooltips are
                // unaffected (blur does not cancel mouse/touch events, and
                // ApexCharts' own dataPointSelection styling is pointer-based,
                // not focus-based).
                this._focusGuardMount = mount;
                this._focusBlurHandler = (event) => {
                    const target = event.target;
                    if (target && typeof target.blur === 'function') {
                        target.blur();
                    }
                };
                mount.addEventListener('focusin', this._focusBlurHandler, { capture: true });

                this._setupDarkModeObserver(rawConfig);
            });

            // Cleanup on Livewire navigation (SPA mode).
            this._navCleanup = () => this.destroy();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Wire-streaming setup (Extension 12.3) — read data-wire-stream-*
            // attributes off the root + register a window listener that
            // appends incoming points via ApexCharts' chart.appendData().
            this._setupWireStream();
        },

        /**
         * Wire-streaming for ApexCharts (Extension 12.3). Mirrors the Chart.js
         * factory's setup but uses ApexCharts' imperative APIs:
         *   - chart.appendData([{ data: [point] }, ...]) for cartesian charts
         *   - chart.appendSeries(...) when starting a fresh series
         *
         * 'strict' mode trims old points by re-rendering with a sliced series;
         * 'stream' mode grows unboundedly.
         */
        _setupWireStream() {
            const root = this.$el;
            const eventName = root?.dataset?.wireStreamEvent;
            if (!eventName) return;

            // `wireStreamMode` / `wireStreamCap` are read but no longer drive
            // an in-line trim — every dispatch flows through `appendData`
            // for smooth slide-in animation. The props remain part of the
            // public Blade API for developer-side memory management
            // (e.g. periodic `chart.resetSeries()` triggered when the cap
            // is exceeded — left to userland because the trim-via-
            // updateSeries path produces a per-position-Y wobble that
            // breaks the streaming visual contract).
            // eslint-disable-next-line no-unused-vars
            const _mode = root.dataset.wireStreamMode || 'strict';
            // eslint-disable-next-line no-unused-vars
            const _cap = parseInt(root.dataset.wireStreamCap, 10) || 100;

            // Per-microtask batching queue. Streaming-demo / developer code
            // often dispatches ONE event per series per tick (3 events for
            // a 3-series chart at 750 ms cadence). Without batching, each
            // event triggered its own `updateSeries(next, true)` call —
            // ApexCharts then ran 3 partial-overlap animations in rapid
            // succession, which made the LAST series visually appear to
            // "rebuild from scratch" each tick (the 3rd updateSeries
            // cancelled the in-progress animation from the 2nd, re-
            // interpolating the same path the 2nd had just animated to).
            // Symptom on /components/charts-apex/streaming's Multi-series
            // preview: blue + red streamed smoothly, green rebuilt on
            // every tick. Microtask-batching coalesces all dispatches
            // within the same event-loop tick into a single updateSeries.
            this._wireStreamQueue = [];
            this._wireStreamFlushScheduled = false;

            this._wireStreamFlush = () => {
                this._wireStreamFlushScheduled = false;
                if (!this.chart) return;
                // Pause-on-hover gate. When the cursor is over the chart,
                // we keep queueing incoming points without flushing — the
                // tooltip stays stable on whatever the user is hovering.
                // On mouseleave, the hover-leave handler triggers a fresh
                // flush that drains the whole queue at once (visible as a
                // quick catch-up animation).
                if (this._hoverPaused) return;
                const queue = this._wireStreamQueue;
                this._wireStreamQueue = [];
                if (queue.length === 0) return;

                // `chart.appendData()` is the ONLY correct primitive for
                // streaming line / area / bar / column charts. It animates
                // the new point sliding in from the right edge — points
                // already on the chart keep their existing Y-values and
                // just shift LEFT.
                //
                // The previous strict-mode implementation called
                // `chart.updateSeries(trimmed, true)` per tick, which
                // ApexCharts interprets as "interpolate every existing
                // position's Y-value to the NEW series' Y-value at that
                // position". Each X-position then morphs into the value
                // shifted from its right neighbour — visually the entire
                // chart "wobbles upside-down" on every tick (reported as
                // "all three lines reverse direction on every data tick").
                // `appendData` doesn't have this property: existing
                // points stay anchored, only the new one animates in.
                //
                // Batching multi-series dispatches: every queued point
                // for each series goes into a single appendData call so
                // all series advance in lockstep, no partial-overlap
                // animation cancellation across the three latency
                // percentiles' separate dispatches per tick.
                //
                // Strict-mode cap: we no longer manually trim. Letting
                // the series grow unbounded for a docs-preview session
                // (~30 seconds of ticks → max ~60 points at 1 Hz) is
                // cheap, and avoids the per-position-Y-interpolation
                // wobble that any updateSeries-based trim re-introduces.
                // Real developers managing a long-running stream can
                // periodically call `chart.resetSeries()` or attach
                // `xaxis.range` to bound the VISIBLE window without
                // touching the underlying series state.
                const maxIdx = queue.reduce((m, q) => Math.max(m, q.seriesIndex), 0);
                const payload = [];
                for (let i = 0; i <= maxIdx; i++) {
                    payload.push({ data: queue.filter((q) => q.seriesIndex === i).map((q) => q.point) });
                }
                this.chart.appendData(payload);
            };

            this._wireStreamHandler = (event) => {
                if (!this.chart) return;
                const detail = event.detail || {};
                const seriesIndex = detail.datasetIndex ?? 0;
                const point = detail.point;
                if (point === undefined) return;

                this._wireStreamQueue.push({ seriesIndex, point });
                if (this._wireStreamFlushScheduled) return;
                this._wireStreamFlushScheduled = true;
                queueMicrotask(this._wireStreamFlush);
            };

            window.addEventListener(eventName, this._wireStreamHandler);
        },

        /**
         * Watch <html> + <body> for .dark class changes, re-theme via
         * chart.updateOptions(). Debounced at 50 ms to coalesce rapid toggles
         * (e.g. system-preference changes that fire multiple mutations).
         *
         * Dark-mode preset transitions: the update uses dynamicAnimation so
         * colour interpolation runs over ~250 ms instead of snapping. Collapsed
         * to instant when prefers-reduced-motion is set.
         */
        _setupDarkModeObserver(rawConfig) {
            this._darkModeObserver = new MutationObserver((mutations) => {
                const hasClassChange = mutations.some(
                    (m) => m.attributeName === 'class'
                );
                if (!hasClassChange || !this.chart) return;

                clearTimeout(this._darkModeDebounce);
                this._darkModeDebounce = setTimeout(() => {
                    if (!this.chart || !this.$refs.mount) return;

                    const style = getComputedStyle(this.$refs.mount);
                    const colors = this._resolveThemeColors(style);
                    const fontFamily = style.getPropertyValue('--font-wk-sans').trim()
                        || 'ui-sans-serif, system-ui, sans-serif';

                    const themed = this._themeApexConfig(rawConfig, colors, fontFamily);

                    // Smooth transition (Extension 12.7) — collapsed to instant
                    // under prefers-reduced-motion.
                    const reduced = this._reducedMotion();
                    if (!themed.chart) themed.chart = {};
                    themed.chart.animations = {
                        enabled: !reduced,
                        dynamicAnimation: { enabled: !reduced, speed: reduced ? 0 : 250 },
                    };

                    // Re-resolve var() references on the dark-mode swap so
                    // per-dataset / colorScale / annotation colours pick up
                    // the new .dark cascade values. Same reasoning as the
                    // initial-mount resolveCssVarsDeep() above.
                    const resolvedThemed = resolveCssVarsDeep(themed, style);
                    this.chart.updateOptions(resolvedThemed, false, !reduced);
                }, 50);
            });

            const observerOpts = { attributes: true, attributeFilter: ['class'] };
            this._darkModeObserver.observe(document.documentElement, observerOpts);
            this._darkModeObserver.observe(document.body, observerOpts);
        },

        /**
         * Idempotent destroy. Safe to call multiple times — Alpine's
         * teardown can fire alongside livewire:navigating; the chart instance
         * is nulled after the first destroy so the second call is a no-op.
         */
        destroy() {
            clearTimeout(this._darkModeDebounce);
            if (this._detachRaf) {
                cancelAnimationFrame(this._detachRaf);
                this._detachRaf = null;
            }

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
            if (this._mount && this._hoverEnterHandler) {
                this._mount.removeEventListener('mouseenter', this._hoverEnterHandler);
                this._mount.removeEventListener('mouseleave', this._hoverLeaveHandler);
                this._hoverEnterHandler = null;
                this._hoverLeaveHandler = null;
                this._mount = null;
            }
            if (this._focusGuardMount && this._focusBlurHandler) {
                this._focusGuardMount.removeEventListener('focusin', this._focusBlurHandler, { capture: true });
                this._focusBlurHandler = null;
                this._focusGuardMount = null;
            }
            if (this._cellShapeTooltipCleanup) {
                this._cellShapeTooltipCleanup();
                this._cellShapeTooltipCleanup = null;
            }
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
        },

        /**
         * Theme-colour readers — thin wrappers around the shared util in
         * resources/js/utils/chart-theme-colors.js. Identical helpers used
         * by wirekitChartJs so dataset palettes, fallbacks, and probe
         * behaviour stay in lockstep across both adapters.
         */
        _resolveThemeColors(style) { return resolveThemeColors(style); },
        _palette(colors)           { return palette(colors); },

        _setupDetachGuard(mount) {
            const check = () => {
                if (!this.chart) return;
                if (!mount.isConnected) {
                    this.destroy();
                    return;
                }
                this._detachRaf = requestAnimationFrame(check);
            };
            this._detachRaf = requestAnimationFrame(check);
        },

        /**
         * Flatten the PHP-emitted chart config for ApexCharts.
         *
         * The shared PHP Chart component wraps `defaultOptions + user :options`
         * under a `.options` subkey so the same shape feeds both adapters:
         * Chart.js expects `{type, data, options}` — adapter takes it verbatim.
         * ApexCharts expects flat top-level keys (`{chart, series, xaxis,
         * plotOptions, ...}`) and silently ignores anything nested under an
         * unknown `options` key. Without this flattening step, every
         * `:options="..."` prop on every ApexCharts chart was dropped —
         * visible as range-bar with `plotOptions.bar.horizontal: true` never
         * flipping orientation, custom yaxis bounds being ignored, and
         * date-axis label formatters never reaching the renderer.
         *
         * We deep-merge so a default `chart.toolbar.show: false` from
         * `ApexChartsAdapter::defaultOptions()` still composes with a
         * caller-supplied `chart.height: '420px'` without clobbering the
         * sibling key.
         */
        _flattenApexConfig(config) {
            const opts = config.options || {};
            const flat = Object.assign({}, config);
            delete flat.options;
            for (const key of Object.keys(opts)) {
                flat[key] = this._deepMerge(flat[key], opts[key]);
            }
            return flat;
        },

        /**
         * Recursive plain-object merger. Arrays and primitives from `source`
         * REPLACE the target (we never concat arrays — that would silently
         * stack defaults onto user-supplied categories etc.). Plain objects
         * recurse so sibling keys are preserved on both sides.
         */
        _deepMerge(target, source) {
            if (source === undefined) return target;
            if (target === undefined) return source;
            const isPlainObject = (v) =>
                v !== null && typeof v === 'object' && !Array.isArray(v);
            if (!isPlainObject(target) || !isPlainObject(source)) return source;
            const out = Object.assign({}, target);
            for (const key of Object.keys(source)) {
                out[key] = this._deepMerge(target[key], source[key]);
            }
            return out;
        },

        /**
         * Build a fully-themed ApexCharts options object from the developer's
         * raw config + the resolved theme colours. Preserves developer-supplied
         * fields (color, type, plotOptions, etc.) — the merge prefers caller
         * values where present.
         */
        _themeApexConfig(rawConfig, colors, fontFamily) {
            const isDark = document.documentElement.classList.contains('dark')
                || document.body.classList.contains('dark');

            // Auto-fill series-level colors when not user-set. _manualColorIndices
            // captures developer choices at init time so dark-mode re-theme skips them.
            //
            // pie / donut / radialBar / polarArea expect `series` as an array of
            // raw numbers (`[10, 20, 30]`), NOT an array of objects. Wrapping a
            // number with Object.assign({}, 10, { color }) returns `{color: …}`
            // — an object with no `data` property — and ApexCharts throws
            // `Unsupported series format for pie/donut/radialBar. Expected
            // series objects with data property.` Per-slice colours for these
            // chart types live in the top-level `colors` array (set further
            // down), not on the series entries themselves; skip the wrapping.
            const palette = this._palette(colors);
            const isFlatSeries = (rawConfig.series || []).every((s) => typeof s === 'number');
            const themedSeries = isFlatSeries
                ? rawConfig.series
                : (rawConfig.series || []).map((series, i) => {
                    if (this._manualColorIndices.has(i) || (series && series.color)) {
                        return series;
                    }
                    return Object.assign({}, series, { color: palette[i % palette.length] });
                });

            // Treemap (and heatmap) use ApexCharts' built-in `colorScale`
            // to vary cell shade by VALUE — small cells get a light tint,
            // large cells get the full accent. Forcing a single-colour
            // `colors: [accent]` collapses every cell to the same blue,
            // breaking the "I can read magnitude from colour" affordance
            // that's the whole point of a treemap. Skip the colours
            // override for these types so ApexCharts' native shading
            // kicks in. Developer-supplied `colors` still wins.
            const apexChartType = rawConfig?.chart?.type;
            const colourScaleTypes = ['treemap', 'heatmap'];
            const resolvedColors = rawConfig.colors
                || (colourScaleTypes.includes(apexChartType)
                    ? undefined
                    : palette.slice(0, Math.max(themedSeries.length, 1)));

            return Object.assign({}, rawConfig, {
                series: themedSeries.length > 0 ? themedSeries : rawConfig.series,
                colors: resolvedColors,
                chart: Object.assign({}, rawConfig.chart, {
                    fontFamily: fontFamily,
                    background: 'transparent',
                    foreColor: colors.textMuted,
                    toolbar: Object.assign(
                        { show: false },
                        (rawConfig.chart && rawConfig.chart.toolbar) || {},
                    ),
                    zoom: Object.assign(
                        { enabled: false },
                        (rawConfig.chart && rawConfig.chart.zoom) || {},
                    ),
                }),
                grid: Object.assign({}, rawConfig.grid, {
                    borderColor: colors.border,
                    strokeDashArray: 0,
                }),
                xaxis: this._themeAxis(rawConfig.xaxis, colors, fontFamily),
                yaxis: this._themeYaxis(rawConfig.yaxis, colors, fontFamily),
                tooltip: Object.assign({}, rawConfig.tooltip, {
                    theme: isDark ? 'dark' : 'light',
                    // Object.assign is shallow — without nesting the style
                    // merge, the themer's `{ fontFamily }` would CLOBBER any
                    // existing style sub-keys from rawConfig.tooltip.style
                    // (most importantly `fontSize: '12px'` from the adapter
                    // defaults that locks per-chart-type uniform tooltip
                    // typography). Merge nested explicitly so both stay.
                    style: Object.assign(
                        { fontFamily },
                        (rawConfig.tooltip && rawConfig.tooltip.style) || {},
                    ),
                }),
                legend: Object.assign({}, rawConfig.legend, {
                    labels: { colors: colors.textPrimary },
                    fontFamily,
                }),
                dataLabels: Object.assign({ enabled: false }, rawConfig.dataLabels, {
                    style: Object.assign(
                        { fontFamily, colors: [colors.textPrimary] },
                        (rawConfig.dataLabels && rawConfig.dataLabels.style) || {},
                    ),
                }),
                stroke: this._resolveStroke(rawConfig.stroke),
            });
        },

        /**
         * Stroke shape guard. ApexCharts expects `stroke` to be a plain
         * object (`{curve, width, colors, lineCap, dashArray}`) or absent.
         * Some callers (including `ApexChartsAdapter::defaultStroke()` for
         * non-line/area chart types prior to the empty-array suppression
         * fix) pass an empty PHP array `[]` which JSON-encodes to `[]` and
         * reaches the renderer as a JS array. ApexCharts' internal stroke
         * reader treats the array as iterable and bails out of normal
         * stroke-config decoding, which cascades into broken fill-path
         * generation on bar / column / pie charts (the symptom: axes +
         * legend render correctly, but the actual bars / slices never
         * appear). Treat empty array OR empty object as "no stroke" and
         * fall through to ApexCharts' built-in defaults (which for bar
         * charts means no stroke, the correct shape). Line/area charts
         * with a real stroke object pass through untouched; the smooth
         * curve fallback only kicks in when stroke is genuinely absent.
         */
        _resolveStroke(stroke) {
            if (stroke == null) return { curve: 'smooth', width: 2 };
            if (Array.isArray(stroke)) {
                return stroke.length === 0 ? undefined : stroke;
            }
            if (typeof stroke === 'object' && Object.keys(stroke).length === 0) {
                return undefined;
            }
            return stroke;
        },

        _themeAxis(axis, colors, fontFamily) {
            const base = axis || {};
            return Object.assign({}, base, {
                labels: Object.assign({}, base.labels, {
                    style: Object.assign(
                        { colors: colors.textMuted, fontFamily },
                        (base.labels && base.labels.style) || {},
                    ),
                }),
                axisBorder: Object.assign({ color: colors.border }, base.axisBorder),
                axisTicks: Object.assign({ color: colors.border }, base.axisTicks),
            });
        },

        /**
         * yaxis can be either a single object (one y-axis) or an array
         * (multi-axis charts). We theme each axis individually and preserve
         * the array shape when the developer passed one. The themed result
         * also gets a default integer-friendly label formatter so a
         * dataset of `[42, 58, 71, 89]` renders y-axis ticks as
         * `42 / 58 / 71 / 89` instead of `42.00000000000000 …`. Developer-
         * supplied formatters always win.
         */
        _themeYaxis(yaxis, colors, fontFamily) {
            const themeOne = (axis) => {
                const themed = this._themeAxis(axis, colors, fontFamily);
                if (! themed.labels) themed.labels = {};
                if (typeof themed.labels.formatter !== 'function') {
                    themed.labels.formatter = (value) => {
                        if (value === null || value === undefined) return '';
                        const n = Number(value);
                        if (! Number.isFinite(n)) return String(value);
                        // Integers (or values that round to an integer) render
                        // without trailing decimals — covers the 99% case of
                        // dataset values like 42, 58, 71. Non-integer floats
                        // get up to two decimals (e.g. 2.4 → "2.4", 0.18 → "0.18").
                        if (Number.isInteger(n)) return String(n);
                        const rounded = Math.round(n * 100) / 100;
                        return Number.isInteger(rounded)
                            ? String(rounded)
                            : rounded.toFixed(2).replace(/\.?0+$/, '');
                    };
                }
                return themed;
            };

            if (Array.isArray(yaxis)) {
                return yaxis.map(themeOne);
            }
            return themeOne(yaxis);
        },

        /**
         * Read the OS-level prefers-reduced-motion preference at the moment
         * of call. Cheap; ApexCharts theming branches on this in two places
         * (init + dark-mode re-theme).
         */
        _reducedMotion() {
            return typeof window !== 'undefined'
                && window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },
    };
}
