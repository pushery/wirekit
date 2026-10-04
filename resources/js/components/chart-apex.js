import { resolveThemeColors, palette, resolveCssVarsDeep, themeModeOf } from '../utils/chart-theme-colors.js';
import { prefersReducedMotion, watchReducedMotion } from '../utils/motion.js';
import { awaitPeer } from '../utils/await-peer.js';
import { followChartServerData } from '../utils/chart-server-data.js';
import { formatDecimal } from '../utils/locale-number.js';
import { keepRaw } from '../utils/keep-raw.js';

/**
 * The y-axis label formatters the kit sets itself, on an axis that has none of its own. They are the
 * axis' default, so the tooltip leaves its values to its own formatting rather than to one of them.
 */
const kitAxisFormatters = new WeakSet();

/**
 * The tooltip title formatters the kit sets itself, the escaped series name and a colon, where the
 * page sets none. The tooltip writes that default itself, as text.
 */
const kitTitleFormatters = new WeakSet();

/**
 * Unified tooltip renderer for every ApexCharts type. Emits ApexCharts'
 * NATIVE CSS classes (`.apexcharts-tooltip-title`,
 * `.apexcharts-tooltip-series-group`, `.apexcharts-tooltip-marker`,
 * `.apexcharts-tooltip-text-y-label/value`) — ApexCharts' own stylesheet
 * then renders the gray header + white body + circle marker layout
 * automatically. We only control the SHAPE of the data: header always
 * = x-axis category / x-value, body always = one row per stat (marker
 * + label + value). Per-chart-type renderers ship with inconsistent
 * roles (range-bar puts series-name as header; scatter puts x-value
 * as header; pie has no header). The unified renderer locks the
 * scatter-bubble layout — the one shape that reads correctly for
 * every type — onto every apex demo without touching ApexCharts'
 * visual styling.
 *
 * The PHP adapter ships a `WIREKIT_DEFAULT_TOOLTIP` sentinel under
 * `tooltip.custom`; the Alpine factory swaps it for THIS function
 * before passing the config to ApexCharts.
 */
function renderUnifiedTooltip({ series, seriesIndex, dataPointIndex, w }) {
    const cfg = (w && w.config) || {};
    const g = (w && w.globals) || {};
    const apexType = cfg.chart && cfg.chart.type;
    // The application's locale, handed over by the PHP side as `tooltip.wkLocale`, so a date
    // reads in the language of the page around it rather than of the reader's browser. A tag the
    // runtime rejects falls back to the browser's own preference instead of breaking the hover.
    const appLocale = (cfg.tooltip && cfg.tooltip.wkLocale) || undefined;
    const dateOptions = { month: 'short', day: '2-digit', year: 'numeric' };
    // A date is written as the chart's date axis writes one: in `tooltip.x.format` when the options
    // give one, otherwise as a date in the application's locale, and in UTC unless
    // `xaxis.labels.datetimeUTC` is false, which is the zone the axis itself is labeled in.
    const xaxis = cfg.xaxis || {};
    const dateAxis = xaxis.type === 'datetime';
    const utc = !(xaxis.labels && xaxis.labels.datetimeUTC === false);
    const xFormat = (cfg.tooltip && cfg.tooltip.x && typeof cfg.tooltip.x.format === 'string') ? cfg.tooltip.x.format : null;
    const writeDate = (ms) => {
        // A number past the range a Date holds is no instant; it is written as the number it is.
        if (Number.isNaN(new Date(ms).getTime())) {
            return String(ms);
        }

        if (xFormat) {
            return formatApexDate(new Date(ms), xFormat, utc, appLocale);
        }

        // The Gregorian calendar, as the axis is labeled, whatever calendar the locale prefers.
        const options = { ...dateOptions, calendar: 'gregory', ...(utc ? { timeZone: 'UTC' } : {}) };
        try {
            return new Date(ms).toLocaleDateString(appLocale, options);
        } catch {
            return new Date(ms).toLocaleDateString(undefined, options);
        }
    };
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
                ? formatDecimal(v, appLocale, wkDecimals, wkDecimals)
                : String(v);
            return `${wkPrefix}${n}${wkSuffix}`;
        }
        return v;
    };

    // A value of a series is written with the chart's own `valueDecimals`, `valuePrefix` or
    // `valueSuffix` where it sets one. Otherwise a formatter of the page writes it, as it does in
    // ApexCharts' own tooltip: `tooltip.y.formatter` (the series' entry where `tooltip.y` is a
    // list), else the label formatter of the series' y-axis. The chart's options are JSON, so such a
    // formatter is one the page set for every chart. What it returns is written as text.
    const chartFormatsValues = wkDecimals !== null || wkPrefix !== '' || wkSuffix !== '';
    const yaxes = Array.isArray(cfg.yaxis) ? cfg.yaxis : [cfg.yaxis];
    const pageValueFormatter = (sIdx) => {
        const y = tcfg.y;
        const forTooltip = Array.isArray(y) ? y[sIdx] && y[sIdx].formatter : y && y.formatter;
        if (typeof forTooltip === 'function') {
            return forTooltip;
        }
        const axis = yaxes[sIdx] || yaxes[0];
        const forAxis = axis && axis.labels && axis.labels.formatter;

        return (typeof forAxis === 'function' && ! kitAxisFormatters.has(forAxis)) ? forAxis : null;
    };
    const writeValue = (v, sIdx) => {
        const formatter = (! chartFormatsValues && typeof v === 'number' && Number.isFinite(v)) ? pageValueFormatter(sIdx) : null;
        if (formatter) {
            const written = formatter(v, { series, seriesIndex: sIdx, dataPointIndex, w });

            return written === undefined || written === null ? v : written;
        }

        return fmtValue(v);
    };

    // Resolve x-label (header). Priority: hovered series' data-point `x`
    // (scatter / bubble / range-bar object form) → globals.labels (bar /
    // line / area / heatmap) → globals.categoryLabels → seriesX (numeric).
    const hoveredEntry = (cfg.series && cfg.series[seriesIndex]) || {};
    const hoveredPoint = hoveredEntry && hoveredEntry.data && hoveredEntry.data[dataPointIndex];
    let xLabel = '';
    if (hoveredPoint && typeof hoveredPoint === 'object' && !Array.isArray(hoveredPoint) && 'x' in hoveredPoint) {
        xLabel = hoveredPoint.x;
    } else if (
        g.labels && g.labels[dataPointIndex] !== undefined
        // …EXCEPT when ApexCharts has rewritten the axis under us, which it does for a
        // line or area chart given `xaxis.categories`: it empties `categories`, sets
        // `type: 'numeric'` and `isXNumeric: true`, moves the strings to
        // `globals.categoryLabels`, and leaves `globals.labels` holding the numeric
        // POSITIONS. Reading `labels` first then puts the data-point index in the header
        // where the date belongs — `15` instead of `08/18/2026`.
        //
        // Decided on the VALUE rather than on `isXNumeric` or the chart type, because the
        // rewrite is the thing that matters and nothing guarantees it stays tied to one
        // type: a number here with a string at the same index of `categoryLabels` means it
        // happened, whatever the chart is called. A chart whose labels really are numbers
        // has no string there and keeps taking this branch.
        && !(typeof g.labels[dataPointIndex] === 'number'
            && g.categoryLabels
            && typeof g.categoryLabels[dataPointIndex] === 'string')
    ) {
        xLabel = g.labels[dataPointIndex];
    } else if (g.categoryLabels && g.categoryLabels[dataPointIndex] !== undefined) {
        xLabel = g.categoryLabels[dataPointIndex];
    } else if (g.seriesX && g.seriesX[seriesIndex] && g.seriesX[seriesIndex][dataPointIndex] !== undefined) {
        xLabel = g.seriesX[seriesIndex][dataPointIndex];
    }

    // On a date axis the point's x is a date, which ApexCharts parsed into `seriesX` from a number
    // or from a string such as `2024-12-02`. Written as handed over, the title was the raw
    // timestamp, or the string without its format. ApexCharts titles with a date when the x it
    // drew is numeric; a timeline's x is its category, which stays the title.
    if (dateAxis && g.isXNumeric) {
        const parsed = g.seriesX && g.seriesX[seriesIndex] ? g.seriesX[seriesIndex][dataPointIndex] : undefined;
        const instant = typeof parsed === 'number' ? parsed : xLabel;
        if (typeof instant === 'number' && Number.isFinite(instant)) {
            // ApexCharts reads `tooltip.x.format` only where neither the title nor the axis labels
            // have a formatter; where one is set, it decides the title, with the arguments
            // ApexCharts hands it. The component's options are JSON, so such a formatter comes from
            // the page's own options, and what it returns is written as text like every title.
            const ownTitle = typeof (cfg.tooltip && cfg.tooltip.x && cfg.tooltip.x.formatter) === 'function';
            const ownLabels = typeof (xaxis.labels && xaxis.labels.formatter) === 'function';
            const titled = (w.formatters && w.formatters.ttKeyFormatter) || g.ttKeyFormatter;
            if ((ownTitle || ownLabels) && typeof titled === 'function') {
                xLabel = (ownTitle
                    ? titled(instant, { series, seriesIndex, dataPointIndex, w })
                    : titled(instant, instant, {
                        i: undefined,
                        dateFormatter: (date, format) => formatApexDate(date, format, utc, appLocale),
                        w,
                    })) ?? instant;
            } else {
                xLabel = writeDate(instant);
            }
        }
    }

    // The two ends of a range, as ApexCharts parsed them from whichever form the point took
    // (`{ x, y: [a, b] }`, `[x, [a, b]]`), and the given pair where it has not parsed any.
    // ApexCharts writes them as dates on a timeline on a date axis and as values on every other
    // range chart, a range column or a range area, whose dates are on the other axis.
    const timeline = dateAxis && Boolean(g.isRangeBar);
    const writeRange = (sIdx, given) => {
        const start = g.seriesRangeStart && g.seriesRangeStart[sIdx] ? g.seriesRangeStart[sIdx][dataPointIndex] : undefined;
        const end = g.seriesRangeEnd && g.seriesRangeEnd[sIdx] ? g.seriesRangeEnd[sIdx][dataPointIndex] : undefined;
        const ends = (start !== undefined && end !== undefined) ? [start, end] : given;
        const writeEnd = (v) => ((timeline && typeof v === 'number' && Number.isFinite(v)) ? writeDate(v) : writeValue(v, sIdx));

        return `${writeEnd(ends[0])} – ${writeEnd(ends[1])}`;
    };

    // Per-series row builder — extracts the {label, value} pairs for ONE
    // series at the given data-point index. Used in both shared and
    // single-series modes. Each pair becomes one body row.
    const rowsForSeries = (sIdx) => {
        const entry = (cfg.series && cfg.series[sIdx]) || {};
        const sName = (typeof entry === 'object' && entry.name) || '';
        const rawPoint = entry && entry.data && entry.data[dataPointIndex];
        const rows = [];

        // A candlestick and a boxplot are read from what ApexCharts parsed, because the length of
        // a point does not say what it is: ApexCharts takes `{ x, y: [...] }`, `[x, [...]]` and a
        // flat list with the x in front, where a candle `[x, open, high, low, close]` is as long
        // as the five numbers of a boxplot.
        const kind = (typeof entry === 'object' && entry.type) || apexType;
        if (kind === 'candlestick' || kind === 'boxPlot') {
            const [o, h, m, l, c] = ['O', 'H', 'M', 'L', 'C'].map((part) => {
                const parsed = g[`seriesCandle${part}`];

                return parsed && parsed[sIdx] ? parsed[sIdx][dataPointIndex] : undefined;
            });
            if (o !== undefined && c !== undefined) {
                return kind === 'boxPlot'
                    ? [{ label: 'Max', value: c }, { label: 'Q3', value: l }, { label: 'Median', value: m }, { label: 'Q1', value: h }, { label: 'Min', value: o }]
                    : [{ label: 'Open', value: o }, { label: 'High', value: h }, { label: 'Low', value: l }, { label: 'Close', value: c }];
            }
        }

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
                rows.push({ label: sName, value: writeRange(sIdx, y), named: true });
            } else if (y !== undefined) {
                rows.push({ label: sName, value: y, named: true });
            }
            if ('z' in rawPoint) {
                rows.push({ label: 'Size', value: rawPoint.z, size: true });
            }
        } else if (Array.isArray(rawPoint) && rawPoint.length === 2 && (apexType === 'rangeBar' || apexType === 'rangeArea')) {
            rows.push({ label: sName, value: writeRange(sIdx, Array.isArray(rawPoint[1]) ? rawPoint[1] : rawPoint), named: true });
        } else if (Array.isArray(series[sIdx])) {
            // Cartesian (bar / line / area / radar) — series[i] is number[]
            rows.push({ label: sName, value: series[sIdx][dataPointIndex], named: true });
        } else if (typeof series[sIdx] === 'number') {
            // Pie / donut / radialBar / polarArea — series itself is number[]
            rows.push({ label: '', value: series[sIdx] });
        } else if (rawPoint !== undefined) {
            rows.push({ label: sName, value: String(rawPoint), named: true });
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
    // The index is shared, the x need not be: two series with dates of their own have different
    // points at the same index, and the values of one do not belong under the date of the other.
    // So a shared tooltip leaves out a series whose x at the index is another one than the hovered.
    // Where ApexCharts keeps no x for a series, on an axis of categories, the index is the category.
    const xAt = (sIdx) => (g.seriesX && g.seriesX[sIdx] ? g.seriesX[sIdx][dataPointIndex] : undefined);
    const hoveredX = xAt(seriesIndex);
    // `tooltip.enabledOnSeries` lists the series a tooltip shows at all, and `tooltip.inverseOrder`
    // lists them from the last, as in ApexCharts' own tooltip.
    const enabledOn = Array.isArray(tcfg.enabledOnSeries) ? tcfg.enabledOnSeries : null;
    const seriesIndices = ((sharedMode && Array.isArray(cfg.series) && cfg.series.length > 1)
        ? cfg.series.map((_, i) => i).filter((i) => hoveredX === undefined || xAt(i) === undefined || xAt(i) === hoveredX)
        : [seriesIndex]).filter((i) => ! enabledOn || enabledOn.includes(i));
    if (tcfg.inverseOrder === true) {
        seriesIndices.reverse();
    }

    // Accumulate rows from every contributing series. Each row carries its
    // own series color so multi-series tooltips render the correct marker
    // color per row. A series without a value at the index, a shorter one or one the reader
    // switched off in the legend, has no row, as in ApexCharts' own tooltip, and with
    // `tooltip.hideEmptySeries` neither has a value of zero.
    const bodyRows = [];
    seriesIndices.forEach((sIdx) => {
        const color = (g.colors && g.colors[sIdx]) || '#888';
        rowsForSeries(sIdx).forEach((r) => {
            if (r.value !== undefined && r.value !== null && ! (tcfg.hideEmptySeries === true && r.value === 0)) {
                bodyRows.push({ ...r, color, sIdx });
            }
        });
    });

    const headerHtml = (xLabel !== '' && xLabel !== undefined && xLabel !== null)
        ? `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${esc(xLabel)}</div>`
        : '';

    // The name in front of a series' value goes through a `tooltip.y.title.formatter` of the page
    // (the series' entry where `tooltip.y` is a list), as in ApexCharts' own tooltip, which writes
    // what it returns in place of the name and the colon.
    const pageTitleFormatter = (sIdx) => {
        const y = tcfg.y;
        const forSeries = Array.isArray(y) ? y[sIdx] : y;
        const formatter = forSeries && forSeries.title && forSeries.title.formatter;

        return (typeof formatter === 'function' && ! kitTitleFormatters.has(formatter)) ? formatter : null;
    };

    const bodyHtml = bodyRows.map(({ label, value, color, sIdx, size, named }) => {
        const titled = named ? pageTitleFormatter(sIdx) : null;
        const name = titled ? (titled(label, { series, seriesIndex: sIdx, dataPointIndex, w }) ?? '') : (label ? `${label}: ` : '');
        const labelHtml = name ? `<span class="apexcharts-tooltip-text-y-label">${esc(name)}</span>` : '';
        return `<div class="apexcharts-tooltip-series-group apexcharts-active" style="order: 1; display: flex;">
            <span class="apexcharts-tooltip-marker" style="background: ${esc(color)};"></span>
            <div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">
                <div class="apexcharts-tooltip-y-group">
                    ${labelHtml}<span class="apexcharts-tooltip-text-y-value">${esc(size ? fmtValue(value) : writeValue(value, sIdx))}</span>
                </div>
            </div>
        </div>`;
    }).join('');

    return `${headerHtml}${bodyHtml}`;
}

// Named export of the otherwise-internal tooltip renderer, so its escaping
// contract — every dataset- and palette-derived value is HTML-escaped before it
// reaches innerHTML — can be asserted in isolation. The Alpine factory below is
// the public default export; esbuild tree-shakes this unused named export out of
// the browser bundles, so it adds no shipped weight.
export { renderUnifiedTooltip };

/**
 * A date written in an ApexCharts date format such as `MMM dd, yyyy HH:mm`, with the tokens
 * ApexCharts' own tooltip reads, and the month and weekday names in `locale`.
 *
 * The format is read in one pass, longest token first, so a name that holds a token letter is
 * never read again; a backslash keeps the character after it as written, as in ApexCharts.
 *
 * @param {Date} date
 * @param {string} format
 * @param {boolean} utc - Write the date in UTC rather than the reader's zone.
 * @param {string|undefined} locale - A BCP-47 tag; the runtime's own when it rejects the tag.
 * @returns {string}
 */
export function formatApexDate(date, format, utc, locale) {
    if (Number.isNaN(date.getTime())) {
        return String(date);
    }

    const timeZone = utc ? 'UTC' : undefined;
    // The names come from the Gregorian calendar the numbers are read in, also for a locale whose
    // own calendar is another one, as ApexCharts writes them.
    const name = (options) => {
        try {
            return new Intl.DateTimeFormat(locale, { ...options, timeZone, calendar: 'gregory' }).format(date);
        } catch {
            return new Intl.DateTimeFormat(undefined, { ...options, timeZone, calendar: 'gregory' }).format(date);
        }
    };
    const pad = (n) => String(n).padStart(2, '0');
    const year = String(utc ? date.getUTCFullYear() : date.getFullYear());
    const month = (utc ? date.getUTCMonth() : date.getMonth()) + 1;
    const day = utc ? date.getUTCDate() : date.getDate();
    const hours = utc ? date.getUTCHours() : date.getHours();
    const minutes = utc ? date.getUTCMinutes() : date.getMinutes();
    const seconds = utc ? date.getUTCSeconds() : date.getSeconds();
    const milliseconds = utc ? date.getUTCMilliseconds() : date.getMilliseconds();
    // The shorter fractions are rounded from the longer one, as ApexCharts does: `ff` is hundredths,
    // `f` tenths.
    const hundredths = Math.round(milliseconds / 10);
    const twelve = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
    const meridiem = hours < 12 ? 'AM' : 'PM';
    // `K` is the zone as an offset, `Z` in UTC and for a reader whose zone has none.
    const offset = -date.getTimezoneOffset();
    const zone = (utc || offset === 0)
        ? 'Z'
        : `${offset > 0 ? '+' : '-'}${pad(Math.floor(Math.abs(offset) / 60))}:${pad(Math.abs(offset) % 60)}`;
    const values = {
        yyyy: year, yy: year.slice(2, 4), y: year,
        MMMM: () => name({ month: 'long' }), MMM: () => name({ month: 'short' }), MM: pad(month), M: String(month),
        dddd: () => name({ weekday: 'long' }), ddd: () => name({ weekday: 'short' }), dd: pad(day), d: String(day),
        HH: pad(hours), H: String(hours), hh: pad(twelve), h: String(twelve),
        mm: pad(minutes), m: String(minutes), ss: pad(seconds), s: String(seconds),
        fff: String(milliseconds).padStart(3, '0'), ff: pad(hundredths), f: String(Math.round(hundredths / 10)),
        TT: meridiem, T: meridiem.charAt(0), tt: meridiem.toLowerCase(), t: meridiem.charAt(0).toLowerCase(),
        K: zone,
    };

    return format.replace(/\\(.)|y{4,}|yy|y|M{4,}|MMM|MM|M|d{4,}|ddd|dd|d|H{2,}|H|h{2,}|h|m{2,}|m|s{2,}|s|f{3,}|ff|f|T{2,}|T|t{2,}|t|K/g, (token, escaped) => {
        if (escaped !== undefined) {
            return escaped;
        }

        // A run longer than a token reads as the longest token it starts with: `yyyyy` as `yyyy`.
        const value = values[token] ?? values[token.slice(0, 4)] ?? values[token.slice(0, 3)] ?? values[token.slice(0, 2)];

        return typeof value === 'function' ? value() : value;
    });
}

/**
 * A value from the chart's data, as text inside markup.
 *
 * @param {*} value
 * @returns {string}
 */
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/**
 * A data point whose goals carry their name, value and color as text, and any other point as it is.
 *
 * ApexCharts writes the goals of a bar into its tooltip as markup, the color inside a `style`
 * attribute, and it does so behind `tooltip.custom` as well. A valid color holds none of the
 * characters escaping changes, and neither does a number, so a goal draws as before.
 *
 * @param {*} point
 * @returns {*}
 */
export function goalsAsText(point) {
    if (! point || typeof point !== 'object' || ! Array.isArray(point.goals)) {
        return point;
    }

    return Object.assign({}, point, {
        goals: point.goals.map((goal) => {
            if (! goal || typeof goal !== 'object') {
                return goal;
            }

            const own = Object.assign({}, goal);
            for (const key of ['name', 'value', 'strokeColor']) {
                if (typeof own[key] === 'string') {
                    own[key] = escapeHtml(own[key]);
                }
            }

            return own;
        }),
    });
}

/**
 * The series of a chart with the goals of every point as text (`goalsAsText()`).
 *
 * @param {*} series
 * @returns {*}
 */
export function seriesAsText(series) {
    if (! Array.isArray(series)) {
        return series;
    }

    return series.map((one) => (one && typeof one === 'object' && Array.isArray(one.data)
        ? Object.assign({}, one, { data: one.data.map(goalsAsText) })
        : one));
}

/**
 * A function the page set for every chart through ApexCharts' global options, `window.Apex`.
 *
 * ApexCharts lays a chart's own options over those globals, so a formatter or a handler set on the
 * chart here would hide one the page set there. Where the page set one, it stays in charge.
 *
 * @param {string[]} path - The keys down to the option, as in `['legend', 'formatter']`.
 * @returns {Function|undefined}
 */
function pageWideApexOption(path) {
    let node = typeof window === 'undefined' ? undefined : window.Apex;
    for (const key of path) {
        if (node === null || typeof node !== 'object') {
            return undefined;
        }
        node = node[key];
    }

    return typeof node === 'function' ? node : undefined;
}

/**
 * The title ApexCharts writes over a tooltip when no formatter is set, on an axis that holds no dates:
 * the label through the value axis of a horizontal bar chart, through the category axis otherwise.
 *
 * @param {*} value - The label of the hovered point.
 * @param {Object} [opts] - What ApexCharts hands a formatter, `w` among it.
 * @returns {*}
 */
function defaultApexTitle(value, opts) {
    const w = opts && opts.w;
    const formatters = w && w.formatters;
    if (!formatters) {
        return value;
    }

    const formatter = w.globals && w.globals.isBarHorizontal
        ? (Array.isArray(formatters.yLabelFormatters) ? formatters.yLabelFormatters[0] : undefined)
        : formatters.xLabelFormatter;
    if (typeof formatter !== 'function') {
        return value;
    }

    const shown = formatter(value, opts);

    return shown === undefined || shown === null ? value : shown;
}

/**
 * Give every legend entry under `root` the name it shows as its accessible name.
 *
 * ApexCharts builds the accessible name of a legend entry from the same string it writes into the
 * entry as markup, so with the name escaped for that markup the entry was named `R&amp;D` where it
 * shows `R&D`. The chart component hides its mount from assistive technology, but the factory can be
 * mounted without that, and then the legend is what a screen reader reads. Once an entry is toggled,
 * ApexCharts builds the name from the text the entry shows; this does the same after every draw. An
 * accessible name that does not begin with the escaped text came from a formatter of the developer and
 * is left as it is.
 *
 * @param {Element} [root] - The element the chart was drawn into.
 */
export function legendNamesAsText(root) {
    if (!root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    for (const entry of root.querySelectorAll('.apexcharts-legend-series[aria-label]')) {
        const text = entry.querySelector('.apexcharts-legend-text');
        const label = entry.getAttribute('aria-label');
        if (!text || label === null) {
            continue;
        }

        const name = text.textContent;
        const escaped = escapeHtml(name);
        if (escaped !== name && label.startsWith(`${escaped}, `)) {
            entry.setAttribute('aria-label', name + label.slice(escaped.length));
        }
    }
}

/**
 * Switch off ApexCharts' keyboard navigation where the chart's SVG is hidden from assistive
 * technology.
 *
 * ApexCharts 7 navigates a chart's data points with the keyboard: it gives its SVG `tabindex="0"`
 * and a `focus` listener. The kit mounts the SVG inside `aria-hidden`, because the chart's meaning
 * is its parent's `role="img"` and name, and a tab stop in there takes the keyboard to something a
 * screen reader is told to skip. `_removeHiddenTabStop()` takes the `tabindex` away, which Chromium
 * does not count as enough: an SVG with a focus listener stays in its tab order. So the navigation
 * is not started at all, which also leaves its legend entries plain. A version without the option
 * ignores the key.
 *
 * @param {Object} config - The ApexCharts options, completed in place.
 * @param {boolean} hidden - Whether the chart's mount sits inside `aria-hidden`.
 * @returns {Object} The same options.
 */
export function withoutHiddenKeyboardNavigation(config, hidden) {
    if (!hidden) {
        return config;
    }

    config.chart = Object.assign({}, config.chart);
    config.chart.accessibility = Object.assign({}, config.chart.accessibility);
    config.chart.accessibility.keyboard = Object.assign({}, config.chart.accessibility.keyboard, { enabled: false });

    return config;
}

/**
 * Hand ApexCharts the labels and series names of the chart as text wherever it writes them as markup.
 *
 * ApexCharts draws its axes and data labels as SVG text, but it writes a series name or a label into
 * its legend and into its own tooltip with `innerHTML`: the legend entry, the tooltip's title and the
 * name in front of each value. It writes the tooltip's parts even while `tooltip.custom` draws the
 * tooltip the reader sees, into elements the custom markup has replaced, and an image in such a string
 * still loads. So a label from the application's data, a product a user named
 * `<img src=x onerror=…>`, ran in the page when the chart was drawn or hovered.
 *
 * A formatter on each of the three places returns the text escaped, and otherwise the text ApexCharts'
 * own default gives: a label of several lines stays a list of lines, and the title of a horizontal bar
 * goes through the value axis as ApexCharts' own title does. On a date axis the title is left to
 * ApexCharts, which writes a date formatted with `tooltip.x.format` there rather than the label itself;
 * a formatter of ours would replace that date with the raw value. A timeline, a horizontal chart on a
 * date axis, titles its tooltip with the category instead and so keeps the formatter. A formatter
 * that the options or the page's global options already carry is left alone: it is the developer's,
 * and it owns what it returns.
 *
 * ApexCharts also gives a legend entry the escaped string as its accessible name, so after every draw
 * the name is taken from the text the entry shows (`legendNamesAsText()`), and a `mounted` or `updated`
 * handler the options or the page already carry still runs after it.
 *
 * The goals of a bar have no formatter to go through, so their name, value and color are handed
 * over as text in the series itself (`seriesAsText()`), here and wherever new data reaches the chart.
 *
 * @param {Object} config - The ApexCharts options, completed in place.
 */
export function textOnlyInApexMarkup(config) {
    config.series = seriesAsText(config.series);

    config.legend = Object.assign({}, config.legend);
    if (typeof config.legend.formatter !== 'function' && !pageWideApexOption(['legend', 'formatter'])) {
        // ApexCharts joins the lines of a list with a space, in the entry and in its name.
        config.legend.formatter = (name) => (Array.isArray(name) ? name.map(escapeHtml) : escapeHtml(name));
    }

    config.tooltip = Object.assign({}, config.tooltip);
    config.tooltip.x = Object.assign({}, config.tooltip.x);
    // A horizontal chart on a date axis, a timeline, titles its tooltip with the category from the
    // data rather than with a date, so it needs the escaping formatter like any other category.
    const horizontal = Boolean(config.plotOptions && config.plotOptions.bar && config.plotOptions.bar.horizontal);
    const datesInTitle = Boolean(config.xaxis && config.xaxis.type === 'datetime') && !horizontal;
    if (!datesInTitle && typeof config.tooltip.x.formatter !== 'function' && !pageWideApexOption(['tooltip', 'x', 'formatter'])) {
        config.tooltip.x.formatter = (value, opts) => escapeHtml(defaultApexTitle(value, opts));
    }

    // `tooltip.y` may be one object for every series or one per series.
    const pageWideTitle = pageWideApexOption(['tooltip', 'y', 'title', 'formatter']);
    const titled = (y) => {
        const own = Object.assign({}, y);
        own.title = Object.assign({}, own.title);
        if (typeof own.title.formatter !== 'function' && !pageWideTitle) {
            // ApexCharts' own default, with the name escaped.
            own.title.formatter = (name) => (name ? `${escapeHtml(name)}: ` : '');
            kitTitleFormatters.add(own.title.formatter);
        }

        return own;
    };
    config.tooltip.y = Array.isArray(config.tooltip.y) ? config.tooltip.y.map(titled) : titled(config.tooltip.y);

    config.chart = Object.assign({}, config.chart);
    config.chart.events = Object.assign({}, config.chart.events);
    for (const name of ['mounted', 'updated']) {
        const given = typeof config.chart.events[name] === 'function'
            ? config.chart.events[name]
            : pageWideApexOption(['chart', 'events', name]);
        config.chart.events[name] = (chart, options) => {
            legendNamesAsText(chart && chart.el);
            if (given) {
                given(chart, options);
            }
        };
    }
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
 *   Passed via Alpine x-data. Alpine hands a factory its argument as given and
 *   makes only the returned object reactive, so this is the plain object;
 *   `Alpine.raw()` before ApexCharts keeps it plain for a caller who passes a
 *   reactive one.
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
        // Handles set while the component runs, declared so that they are its own: Alpine stores a
        // property no scope declares on the outermost scope around the component.
        _cellShapeTooltipCleanup: null,
        _hoverPaused: null,
        _hoverEnterHandler: null,
        _hoverLeaveHandler: null,
        _wireStreamFlushScheduled: null,
        _mount: null,
        _wireStreamQueue: null,
        _wireStreamFlush: null,
        _wireStreamHandler: null,
        _detachRaf: null,

        chart: null,
        _navCleanup: null,
        // Stops following the data the component renders beside the chart; set once the
        // chart exists. See utils/chart-server-data.js.
        _stopFollowingServerData: null,
        _darkModeObserver: null,
        _darkModeDebounce: null,

        // The palette the chart is currently painted with. See the dark-mode observer:
        // it fires on class mutations that have nothing to do with the theme, and this
        // is what tells those apart from a real one.
        _themeSignature: null,
        // The config a theme swap re-themes from, holding the data the chart shows now: a
        // Livewire update and a streamed point write theirs into it (see init()).
        _rawConfig: null,
        // The bounded retry for the first render, and the standing watch that
        // keeps the tab stops stripped through every later rebuild. Declared
        // here with the other handles so destroy() has a complete list to work
        // from — a handle that only ever appears inside a method is one nobody
        // reading the teardown knows to look for.
        _hiddenTabStopRaf: null,
        _hiddenTabStopObserver: null,
        // The frame the cell-shape tooltip anchor waits on before the tooltip
        // element exists. It is held here for the same reason as the two above:
        // the wait is a chain of up to 31 frames, and a chain nobody can cancel
        // outlives the component it belongs to.
        _tooltipAnchorRaf: null,
        // The unsubscribe for the live reduced-motion watch. Declared here with the
        // other handles for the reason the comment above gives: a handle that only ever
        // appears inside a method is one nobody reading the teardown knows to look for.
        _motionCleanup: null,
        // Stops the wait for a library that was not on the page at init() — utils/await-peer.js.
        _stopAwaitingLibrary: null,
        _manualColorIndices: new Set(),

        init() {
            // ApexCharts peer-dependency guard. WireKit ships only the
            // adapter glue (`dist/wirekit-apex.js`, its size listed in
            // `dist/README.md`) — the developer installs `apexcharts` via npm and exposes it on
            // `window.ApexCharts` per the chart-component docs.
            //
            // When the global is missing we render a visible in-DOM
            // fallback panel inside the chart container (instead of
            // returning silently with only a console.error, which paints
            // a blank white box that's indistinguishable from a styling
            // bug). The on-screen panel surfaces the install command, the
            // license reminder, and a link to apexcharts.com/license so
            // the developer can act without opening DevTools first.
            // Asked until it can be answered — utils/await-peer.js. The library may land after
            // Alpine has mounted this chart (a lazily imported apexcharts), so the panel and the
            // console hint wait for the load plus a grace period instead of speaking at once.
            this._stopAwaitingLibrary = awaitPeer({
                isReady: () => typeof ApexCharts !== 'undefined',
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

        _warnMissingLibrary() {
            // Deduplicate the console.error so N apex charts on the same
            // page emit ONE warning instead of N. The in-DOM fallback
            // panel still renders per-chart (each chart needs its own
            // visible advisory).
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
        },

        _renderMissingLibraryPanel() {
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
                // The panel goes into the mount, and the mount carries `aria-hidden`
                // (see _removeHiddenTabStop). A `role="alert"` inside an aria-hidden subtree
                // is never announced — the browser does not walk into it — so the one
                // message that exists to reach a reader when the chart cannot render would
                // reach nobody. The attribute is lifted for as long as the fallback is
                // the only thing in there; the guard puts it back when a chart renders.
                mount.removeAttribute('aria-hidden');

                // Same host-width branch as the Chart.js panel, and for the same
                // reason: `sparkline` is in this adapter's supportedTypes(), so an
                // inline sparkline on the ApexCharts engine puts this panel in a 4rem
                // box, where the full panel would become a column of a few characters
                // per line inside a sentence.
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
                const availablePx = roomFor(mount);
                const compact = availablePx > 0 && availablePx < 240;
                mount.setAttribute('data-wk-chart-missing', compact ? 'compact' : 'full');

                mount.innerHTML = compact ? `
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
                        <span aria-hidden="true">! ApexCharts missing</span>
                        <span style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;">ApexCharts is not loaded. Install the apexcharts npm package and expose it on window.ApexCharts; the browser console carries the commands.</span>
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
                            ApexCharts is not loaded.
                        </div>
                        <p style="margin: 0 0 0.5rem 0;">
                            WireKit's ApexCharts adapter glue is loaded, but the
                            <code style="font-family: ui-monospace, monospace; font-size: 0.85em; padding: 0.05rem 0.25rem; background: color-mix(in oklab, var(--color-wk-warning-text, #78350f) 12%, transparent); border-radius: 0.2rem;">apexcharts</code>
                            npm package is missing or not exposed on
                            <code style="font-family: ui-monospace, monospace; font-size: 0.85em; padding: 0.05rem 0.25rem; background: color-mix(in oklab, var(--color-wk-warning-text, #78350f) 12%, transparent); border-radius: 0.2rem;">window.ApexCharts</code>.
                        </p>
                        <p style="margin: 0 0 0.5rem 0;">
                            Install it and expose it globally:
                        </p>
                        <pre tabindex="0" style="margin: 0 0 0.5rem 0; padding: 0.625rem 0.75rem; outline-offset: 2px; background: color-mix(in oklab, var(--color-wk-warning-text, #78350f) 12%, transparent); border-radius: 0.25rem; font-family: ui-monospace, monospace; font-size: 0.75rem; line-height: 1.5; overflow-x: auto;">npm install apexcharts

// resources/js/app.js
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;</pre>
                        <p style="margin: 0; font-size: 0.75rem; opacity: 0.9;">
                            <strong>License reminder:</strong> ApexCharts is non-MIT.
                            See <a href="https://apexcharts.com/license/"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   style="color: var(--color-wk-warning-text, #78350f); text-decoration: underline;">apexcharts.com/license</a>
                            for terms (Community free under $2M USD revenue, Commercial above).
                        </p>
                    </div>
                `;
            });
        },

        /**
         * The library arrived after the panel. The boot clears the mount before it renders, so
         * only the marker has to go — and the marker is the one thing that says the mount is
         * showing a panel rather than a chart.
         */
        _clearMissingLibraryPanel() {
            const mount = this.$refs.mount;
            if (!mount || typeof mount.removeAttribute !== 'function') return;

            mount.removeAttribute('data-wk-chart-missing');
        },

        /** Build the chart — at once when the library is on the page, or the moment it lands. */
        _boot() {
            this.$nextTick(() => {
                const mount = this.$refs.mount;
                if (!mount) return;

                // ApexCharts builds its own structures from the options, and a
                // reactive Proxy fed to it is tracked twice. `config` is the
                // factory's argument, which Alpine passes through unwrapped, so
                // for the server-rendered payload Alpine.raw() returns it as it
                // is; the call covers a caller who passes a reactive object.
                const rawConfig = this._flattenApexConfig(Alpine.raw(config));

                // A theme swap re-themes from this config, so it has to hold the data the chart
                // shows: a Livewire update and a streamed point write theirs into it too. New
                // arrays are assigned rather than shared ones changed, because the copy above is
                // shallow and its arrays belong to the factory's argument.
                this._rawConfig = rawConfig;

                // Read CSS variables off the mount element — resolves correctly
                // regardless of whether .dark is on <html>, <body>, or an ancestor.
                const style = getComputedStyle(mount);
                const colors = this._resolveThemeColors(style);
                const fontFamily = style.getPropertyValue('--font-wk-sans').trim()
                    || 'ui-sans-serif, system-ui, sans-serif';

                // Track datasets that carry user-supplied colors BEFORE we apply
                // theme defaults — those are excluded from dark-mode re-theming
                // so the developer's explicit color choices stay frozen across
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

                textOnlyInApexMarkup(themed);
                withoutHiddenKeyboardNavigation(themed, mount.closest('[aria-hidden="true"]') !== null);

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

                // Range-bar, boxplot and candlestick need no per-type
                // formatter here: every ApexCharts type goes through the
                // unified `renderUnifiedTooltip` swap above, one tooltip
                // layout matched to the scatter-bubble shape described at
                // the top of this file.

                // Honor reduced-motion at chart-construction time: disable
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

                // Hover state for cells with color-scale shading (heatmap +
                // treemap). ApexCharts defaults `states.hover.filter.type =
                // 'lighten'` with a strong value, which drives already-pale
                // color-scaled cells (low-value heatmap cells, small treemap
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
                // colors, timeline fillColor — ApexCharts hands these
                // straight to SVG `fill="…"`, which does NOT parse CSS
                // vars. Without this walk, every such reference silently
                // falls back to ApexCharts' default first-series blue.
                const resolvedThemed = resolveCssVarsDeep(themed, style, new Map(), { host: this.$refs?.mount?.parentElement ?? null });
                // Kept out of Alpine's reactivity, so every call reaches ApexCharts itself.
                this.chart = keepRaw(new ApexCharts(mount, resolvedThemed));
                this.chart.render();
                this._removeHiddenTabStop(mount);

                // A Livewire update renders new labels and series beside the chart, outside its
                // `wire:ignore`; follow them and update the chart in place.
                this._stopFollowingServerData = followChartServerData(this.$el, (payload) => this._applyServerData(payload));

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
                //      (`translate(2, 30)` + `translate(centerX, centerY)` +
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
                    //
                    // The element pointer tracking records per type: the
                    // data point under the cursor. For the cell-shape types
                    // it is the cell, and their tooltip is `intersect: true`,
                    // so it is only active over one. For radar it is the
                    // marker, which resolveActiveElement() prefers over the
                    // title lookup. Radar was missing from this map once, so
                    // that preferred route never ran and the title carried
                    // every hover: on two axes named alike it pinned the
                    // tooltip to the LAST one.
                    const pointerSelectorByType = {
                        heatmap: '.apexcharts-heatmap-rect',
                        treemap: '.apexcharts-treemap-rect',
                        radar: '.apexcharts-marker',
                        rangeBar: '.apexcharts-rangebar-area',
                        rangeArea: '.apexcharts-rangearea-area',
                    };

                    const waitForTooltip = (attempts) => {
                        // The chart is nulled by destroy(), and this chain is a
                        // queued frame the teardown cannot reach into. Without
                        // this line the wait kept running on a torn-down
                        // component and — if ApexCharts' DOM was still around —
                        // went on to attach six listeners and assign
                        // `_cellShapeTooltipCleanup` AFTER destroy() had already
                        // looked at it, so the cleanup was never called by
                        // anyone.
                        if (!this.chart) {
                            this._tooltipAnchorRaf = null;
                            return;
                        }

                        const baseEl = cellChart?.w?.globals?.dom?.baseEl;
                        const tooltipEl = cellChart?.w?.globals?.dom?.elTooltip
                            || baseEl?.querySelector('.apexcharts-tooltip');
                        if (!baseEl || !tooltipEl) {
                            this._tooltipAnchorRaf = attempts > 0
                                ? requestAnimationFrame(() => waitForTooltip(attempts - 1))
                                : null;
                            return;
                        }
                        this._tooltipAnchorRaf = null;
                        setupNativeAnchor(baseEl, tooltipEl);
                    };

                    const setupNativeAnchor = (baseEl, tooltipEl) => {
                        let pointerTarget = null;
                        let pinLoopActive = false;
                        let pinRaf = 0;

                        // Per-type "which data element is hovered?" resolver.
                        //
                        // For radar: prefer the marker the cursor is OVER
                        // (pointerTarget, recorded by onActivity whenever
                        // the pointer crosses a marker and kept until it
                        // leaves the chart). The title lookup is the
                        // fallback for a tooltip no pointer opened, and it
                        // cannot tell two axes named alike apart. Keeping
                        // the last marker is safe: off the markers
                        // ApexCharts hides a radar tooltip, so a kept
                        // marker is never paired with another vertex's
                        // tooltip.
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
                                        const cellCenterX = cellRect.left + cellRect.width / 2;
                                        const cellCenterY = cellRect.top + cellRect.height / 2;
                                        let left;
                                        let top;
                                        if (apexType === 'radar') {
                                            // Bottom-left anchor at marker center —
                                            // panel extends up-and-right of the vertex
                                            // so the marker stays visible.
                                            left = cellCenterX - baseRect.left;
                                            top = cellCenterY - baseRect.top - tipRect.height;
                                        } else if (apexType === 'rangeBar' || apexType === 'rangeArea') {
                                            // Half-overlap anchor — tooltip's BOTTOM
                                            // edge sits at the bar's CENTER Y, so the
                                            // top half of the bar gets covered and
                                            // the bottom half stays visible (the user
                                            // can still see where the cursor is).
                                            // Horizontal center-on-bar with edge-clamp.
                                            //
                                            // If the tooltip would clip the chart top
                                            // (bar near the top edge), flip to BELOW:
                                            // tooltip's TOP edge at the bar's center Y.
                                            // The bar's bottom half gets covered; top
                                            // half stays visible.
                                            left = cellCenterX - tipRect.width / 2 - baseRect.left;
                                            const aboveTop = cellCenterY - baseRect.top - tipRect.height;
                                            if (aboveTop >= 4) {
                                                top = aboveTop;
                                            } else {
                                                top = cellCenterY - baseRect.top;
                                            }
                                        } else {
                                            // Center anchor on cell center —
                                            // cells are large enough that the panel
                                            // doesn't hide the data.
                                            left = cellCenterX - tipRect.width / 2 - baseRect.left;
                                            top = cellCenterY - tipRect.height / 2 - baseRect.top;
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
                        const pointerSelector = pointerSelectorByType[apexType];
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
                    this._tooltipAnchorRaf = requestAnimationFrame(() => waitForTooltip(30));
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
                // legend colors scrambled, axis labels wrong). The
                // docs.wirekit.app replay button already reloads the preview
                // iframe — same effect as a fresh page-load, identical
                // behavior to every other replayable component — so
                // the right fix is to NOT intercept the event here and
                // let the iframe reload take over. `<x-wirekit-chart
                // data-replayable="true">` continues to opt INTO the
                // docs.wirekit.app's button surface; we just don't fight the
                // standard reload behavior with broken in-place logic.

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

                // The focus stops ApexCharts creates are removed at the source
                // (see _removeHiddenTabStop), not blurred away on `focusin`: a
                // tab stop that immediately blurs is still a tab stop. The
                // keyboard user still lands there, axe still reports
                // aria-hidden-focus because it reads the markup and cannot see
                // a listener, and the ring still flashes on the chart's first
                // element.


                this._setupDarkModeObserver(rawConfig);
            });

            // Cleanup on Livewire navigation (SPA mode).
            // Reduced motion is a LIVE preference. It was read once at construction and
            // never again, so a reader who turns motion down while the page is open kept
            // every animation — and the OS preference is not even the common case: this
            // library's own site-level toggle writes `data-reduce-motion` onto <html>,
            // which a chart already on screen had no way to notice.
            this._motionCleanup = watchReducedMotion((reduced) => {
                if (! this.chart) {
                    return;
                }

                // `false, false` — no redraw-with-animation, no series reset. Animating
                // the switch to no-animation is the one transition nobody asked for, and
                // resetting the series would replay the entrance this is turning off.
                try {
                    this.chart.updateOptions(
                        { chart: { animations: { enabled: ! reduced } } },
                        false,
                        false
                    );
                } catch { /* defensive: the chart may be mid-teardown */ }
            });

            this._navCleanup = () => this.destroy();
            document.addEventListener('livewire:navigating', this._navCleanup, { once: true });

            // Wire-streaming setup — read data-wire-stream-*
            // attributes off the root + register a window listener that
            // appends incoming points via ApexCharts' chart.appendData().
            this._setupWireStream();
        },

        /**
         * Take the labels and series of a Livewire update into the chart that is already drawn.
         *
         * One `updateOptions` call carrying the series and the labels together, so the chart
         * redraws once rather than once for the categories and again for the values. The theme
         * lives in the options the first render set (palette, axis colors, fonts), and a merge
         * keeps them. The chart TYPE is not taken from the payload: switching it in place is not
         * something ApexCharts does cleanly, and a type change re-renders the component anyway.
         *
         * @param {{series?: Array, labels?: Array, xaxis?: {categories?: Array}}} payload
         */
        _applyServerData(payload) {
            if (! this.chart || ! payload || ! Array.isArray(payload.series)) {
                return;
            }

            // New data reaches ApexCharts' markup as the first data did (`textOnlyInApexMarkup()`).
            const next = { series: seriesAsText(payload.series) };

            // The data a later theme swap re-themes from: as handed over, since the swap escapes it.
            if (this._rawConfig) {
                this._rawConfig.series = payload.series;
            }

            if (Array.isArray(payload.labels)) {
                next.labels = payload.labels;
                if (this._rawConfig) {
                    this._rawConfig.labels = payload.labels;
                }
            }

            if (Array.isArray(payload.xaxis?.categories)) {
                next.xaxis = { categories: payload.xaxis.categories };
                if (this._rawConfig) {
                    this._rawConfig.xaxis = Object.assign({}, this._rawConfig.xaxis, { categories: payload.xaxis.categories });
                }
            }

            try {
                this.chart.updateOptions(next, false, ! this._reducedMotion());
            } catch { /* defensive: the chart may be mid-teardown */ }
        },

        /**
         * Wire-streaming for ApexCharts. Mirrors the Chart.js
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
             
            const _mode = root.dataset.wireStreamMode || 'strict';
             
            const _cap = parseInt(root.dataset.wireStreamCap, 10) || 100;

            // Per-microtask batching queue. Streaming-demo / developer code
            // often dispatches ONE event per series per tick (3 events for
            // a 3-series chart at 750 ms cadence). Without batching, each
            // event triggered its own `updateSeries(next, true)` call —
            // ApexCharts then ran 3 partial-overlap animations in rapid
            // succession, which made the LAST series visually appear to
            // "rebuild from scratch" each tick (the 3rd updateSeries
            // canceled the in-progress animation from the 2nd, re-
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
                // shifted from its right neighbor — visually every line in
                // the chart reverses direction on every data tick.
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
                const given = [];
                for (let i = 0; i <= maxIdx; i++) {
                    given.push(queue.filter((q) => q.seriesIndex === i).map((q) => q.point));
                    payload.push({ data: given[i].map(goalsAsText) });
                }
                this.chart.appendData(payload);

                // The points belong to the data a theme swap re-themes from as well.
                if (this._rawConfig && Array.isArray(this._rawConfig.series)) {
                    this._rawConfig.series = this._rawConfig.series.map((series, i) => (
                        given[i] && given[i].length && series && Array.isArray(series.data)
                            ? Object.assign({}, series, { data: series.data.concat(given[i]) })
                            : series
                    ));
                }
            };

            this._wireStreamHandler = (event) => {
                if (!this.chart) return;
                const detail = event.detail || {};
                const seriesIndex = detail.datasetIndex ?? 0;
                // Queued as handed over: the flush escapes it for ApexCharts and keeps it as it is for
                // the data a theme swap re-themes from, which escapes it there.
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
         * color interpolation runs over ~250 ms instead of snapping. Collapsed
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

                    /*
                     * Same gate as chart.js, for the same reason and against the same
                     * defect: the observer only asks whether the mutated attribute was
                     * `class`, and html/body carry many classes that say nothing about
                     * the theme. Every one of them re-themed the config, re-resolved
                     * every var() reference in it and updated the chart.
                     *
                     * Compared on the RESOLVED palette rather than on a `.dark` class,
                     * so a preset switch that changes colors without touching that class
                     * still goes through.
                     */
                    const signature = fontFamily + '|' + JSON.stringify(colors);
                    if (signature === this._themeSignature) {
                        return;
                    }
                    this._themeSignature = signature;

                    const themed = this._themeApexConfig(rawConfig, colors, fontFamily);

                    // The theme swap hands ApexCharts the options again; the labels stay text,
                    // and the keyboard navigation stays off where the chart is hidden.
                    textOnlyInApexMarkup(themed);
                    withoutHiddenKeyboardNavigation(themed, (this.$refs.mount || this.$el).closest('[aria-hidden="true"]') !== null);

                    // Smooth transition — collapsed to instant
                    // under prefers-reduced-motion.
                    const reduced = this._reducedMotion();
                    if (!themed.chart) themed.chart = {};
                    themed.chart.animations = {
                        enabled: !reduced,
                        dynamicAnimation: { enabled: !reduced, speed: reduced ? 0 : 250 },
                    };

                    // Re-resolve var() references on the dark-mode swap so
                    // per-dataset / colorScale / annotation colors pick up
                    // the new .dark cascade values. Same reasoning as the
                    // initial-mount resolveCssVarsDeep() above.
                    const resolvedThemed = resolveCssVarsDeep(themed, style, new Map(), { host: this.$refs?.mount?.parentElement ?? null });
                    this.chart.updateOptions(resolvedThemed, false, !reduced);

                    // A theme swap can rebuild the SVG root, which arrives
                    // carrying ApexCharts' tabindex again. Re-apply.
                    this._removeHiddenTabStop(this.$refs.mount || this.$el);
                }, 50);
            });

            const observerOpts = { attributes: true, attributeFilter: ['class'] };
            this._darkModeObserver.observe(document.documentElement, observerOpts);
            // `observe(null)` throws, and the throw would leave the chart uninitialized
            // rather than merely un-themed. <html> is always there; <body> may not be.
            if (document.body) {
                this._darkModeObserver.observe(document.body, observerOpts);
            }
        },

        /**
         * Take the rendered SVG out of the tab order when — and only when —
         * it sits inside an aria-hidden subtree.
         *
         * ApexCharts stamps `tabindex="0"` on its own `<svg class="apexcharts-svg">`
         * root. The chart component mounts that SVG inside a container marked
         * `aria-hidden="true"`, because the semantics live on the PARENT
         * (`role="img"` + `aria-label`). The two together are a real defect,
         * not a lint opinion: a keyboard user tabs into a subtree assistive
         * tech has been told to ignore, so focus lands somewhere that
         * announces nothing (axe `aria-hidden-focus`, severity serious).
         *
         * The condition is the point. We do NOT strip unconditionally — if a
         * chart is ever mounted WITHOUT aria-hidden, the SVG being focusable
         * is correct and this must leave it alone. Stripping regardless would
         * trade one accessibility bug for another.
         *
         * Nothing operable is lost: the toolbar and zoom are off by default,
         * so the focus stop led to no keyboard-reachable action.
         *
         * Bounded rAF retry because `render()` resolves asynchronously — the
         * SVG may not exist on the frame we are called.
         */
        _removeHiddenTabStop(container) {
            if (! container) {
                return;
            }

            let framesLeft = 10;

            const attempt = () => {
                this._hiddenTabStopRaf = null;

                const svg = container.querySelector('.apexcharts-svg');

                if (svg) {
                    // Every tab stop in the subtree, not just the SVG root.
                    // ApexCharts also gives each legend entry `tabindex="0"`,
                    // which is the same defect one level down — the first cut
                    // of this fix handled only the root and left the legends
                    // reachable on pie, donut, radialBar and mixed charts.
                    //
                    // Two kinds of focusable, and they need OPPOSITE treatment.
                    //
                    // A uniform `tabindex="-1"` was the first version, chosen
                    // because one rule with no branches cannot be half-right.
                    // It was wrong, and the check that now watches the console
                    // caught it: axe reports `nested-interactive` for a
                    // negative tabindex inside an interactive control, because
                    // assistive tech can still reach it. Silencing the tab stop
                    // that way traded one serious finding for another.
                    //
                    // An element focusable BECAUSE of the attribute loses it
                    // entirely — that is what ApexCharts stamps on its SVG and
                    // on each legend entry. An element focusable BY NATURE — a
                    // link, a button — keeps existing and only leaves the tab
                    // order, so `-1` is the only option there.
                    const FOCUSABLE = 'a[href],button,input,select,textarea,[tabindex],iframe,object,embed,area[href],summary';
                    const NATIVE = 'a[href],button,input,select,textarea,iframe,object,embed,area[href],summary';

                    for (const el of container.querySelectorAll(FOCUSABLE)) {
                        // `closest()` walks ancestors, so this holds whether the
                        // flag sits on the mount or higher up the chart root.
                        if (! el.closest('[aria-hidden="true"]')) {
                            continue;
                        }

                        if (el.matches(NATIVE)) {
                            el.setAttribute('tabindex', '-1');

                            continue;
                        }

                        el.removeAttribute('tabindex');
                    }

                    // Keep watching, because a render is not the only moment
                    // ApexCharts stamps these.
                    //
                    // Calling this at chosen moments — after render, after a
                    // theme swap — is a list somebody has to remember to extend,
                    // and a page streaming live data rebuilds its SVG on every
                    // tick: a strip that ran once would be undone by the next
                    // update. Invisible on every static page, because there is
                    // no second render there to undo it.
                    //
                    // An observer is a property rather than a list: whatever
                    // rebuilds the subtree, and whenever, the stamp does not
                    // survive it. No disconnect inside the callback — the sweep
                    // makes at most one more batch of records, the next pass
                    // finds nothing to change, and it settles. Disconnecting
                    // and re-observing would be the post-destroy race this
                    // codebase already guards against.
                    if (! this._hiddenTabStopObserver) {
                        this._hiddenTabStopObserver = new MutationObserver(() => {
                            if (! container.isConnected) {
                                return;
                            }

                            attempt();
                        });

                        this._hiddenTabStopObserver.observe(container, {
                            subtree: true,
                            childList: true,
                            attributes: true,
                            attributeFilter: ['tabindex', 'aria-hidden'],
                        });
                    }

                    return;
                }

                if (framesLeft-- > 0) {
                    this._hiddenTabStopRaf = requestAnimationFrame(attempt);
                }
            };

            attempt();
        },

        /**
         * Idempotent destroy. Safe to call multiple times — Alpine's
         * teardown can fire alongside livewire:navigating; the chart instance
         * is nulled after the first destroy so the second call is a no-op.
         */
        destroy() {
            // A library that lands after this chart is gone must not build it.
            this._stopAwaitingLibrary?.();
            this._stopAwaitingLibrary = null;

            this._stopFollowingServerData?.();
            this._stopFollowingServerData = null;

            if (this._hiddenTabStopRaf) {
                cancelAnimationFrame(this._hiddenTabStopRaf);
                this._hiddenTabStopRaf = null;
            }
            // An observer that outlives its component keeps the whole scope
            // alive and writes into it on every mutation of a subtree nobody
            // owns any more.
            if (this._hiddenTabStopObserver) {
                this._hiddenTabStopObserver.disconnect();
                this._hiddenTabStopObserver = null;
            }
            if (this._tooltipAnchorRaf) {
                cancelAnimationFrame(this._tooltipAnchorRaf);
                this._tooltipAnchorRaf = null;
            }
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
            if (this._mount && this._hoverEnterHandler) {
                this._mount.removeEventListener('mouseenter', this._hoverEnterHandler);
                this._mount.removeEventListener('mouseleave', this._hoverLeaveHandler);
                this._hoverEnterHandler = null;
                this._hoverLeaveHandler = null;
                this._mount = null;
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
         * Theme-color readers — thin wrappers around the shared util in
         * resources/js/utils/chart-theme-colors.js. Identical helpers used
         * by wirekitChartJs so dataset palettes, fallbacks, and probe
         * behavior stay in lockstep across both adapters.
         */
        _resolveThemeColors(style) { return resolveThemeColors(style, this.$refs?.mount ?? null); },
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

            // Drop Chart.js-only vocabulary before ApexCharts sees it.
            //
            // A WireKit chart config is written once and rendered by whichever
            // adapter is active, so several components (sparkline is the clearest
            // case) emit BOTH vocabularies and let the inactive one fall away.
            // ApexCharts does not ignore keys it does not know: its plugins feature
            // runs `(config.plugins || []).map(...)` over the config — and our
            // `plugins` is a Chart.js OBJECT (`{legend, tooltip}`), so `.map` is
            // not a function and the chart would throw before drawing anything.
            //
            // The failure is silent where it hurts most. A sparkline is usually
            // decorative and aria-hidden beside the number it illustrates, so the
            // page still looks complete, the markup is unchanged, and only an
            // empty box gives it away.
            //
            // Filtering here rather than at each call site is deliberate: the
            // boundary is what must be clean, and a developer's own `:options`
            // can carry the same foreign keys as our components do.
            for (const foreign of ['plugins', 'scales', 'elements']) {
                delete flat[foreign];
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
                // Prototype-pollution guard (defense-in-depth): never merge a key
                // that can re-parent an object or reach a shared prototype. The
                // chart config is assembled from JSON that may be influenced by
                // app data, so a `__proto__` / `constructor` / `prototype` key
                // must not flow through the merge. (This merge already writes only
                // to fresh objects, so global pollution wasn't reachable — but
                // skipping the keys also keeps the result's prototype intact.)
                if (key === '__proto__' || key === 'constructor' || key === 'prototype') {
                    continue;
                }
                out[key] = this._deepMerge(target[key], source[key]);
            }
            return out;
        },

        /**
         * Build a fully-themed ApexCharts options object from the developer's
         * raw config + the resolved theme colors. Preserves developer-supplied
         * fields (color, type, plotOptions, etc.) — the merge prefers caller
         * values where present.
         */
        _themeApexConfig(rawConfig, colors, fontFamily) {
            const isDark = themeModeOf(this.$refs?.mount ?? null) === 'dark';

            // Auto-fill series-level colors when not user-set. _manualColorIndices
            // captures developer choices at init time so dark-mode re-theme skips them.
            //
            // pie / donut / radialBar / polarArea expect `series` as an array of
            // raw numbers (`[10, 20, 30]`), NOT an array of objects. Wrapping a
            // number with Object.assign({}, 10, { color }) returns `{color: …}`
            // — an object with no `data` property — and ApexCharts throws
            // `Unsupported series format for pie/donut/radialBar. Expected
            // series objects with data property.` Per-slice colors for these
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
            // large cells get the full accent. Forcing a single-color
            // `colors: [accent]` collapses every cell to the same blue,
            // breaking the "I can read magnitude from color" affordance
            // that's the whole point of a treemap. Skip the colors
            // override for these types so ApexCharts' native shading
            // kicks in. Developer-supplied `colors` still wins.
            const apexChartType = rawConfig?.chart?.type;
            const colorScaleTypes = ['treemap', 'heatmap'];
            const resolvedColors = rawConfig.colors
                || (colorScaleTypes.includes(apexChartType)
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
                // The application's locale for a decimal tick, handed over on the tooltip by the
                // PHP side; without it the tick keeps the English spelling it always had.
                yaxis: this._themeYaxis(rawConfig.yaxis, colors, fontFamily, rawConfig?.tooltip?.wkLocale),
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
        _themeYaxis(yaxis, colors, fontFamily, locale) {
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
                            : formatDecimal(rounded, locale, 2);
                    };
                    kitAxisFormatters.add(themed.labels.formatter);
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
                && prefersReducedMotion();
        },
    };
}
