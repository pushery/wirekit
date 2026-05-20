/**
 * Shared theme-colour helpers for chart adapters.
 *
 * Both wirekitChartJs and wirekitApexChart need to read the same WireKit
 * `--color-wk-*` CSS variables and resolve them to a paint-friendly format
 * (rgb()/rgba() — Tailwind v4's OKLCH source values are not reliably parsed
 * by Canvas 2D; SVG handles them natively but we use the same probe trick
 * for consistency across adapters).
 *
 * Three exported helpers:
 *
 * - `resolveThemeColors(style)` — read CSS vars + return `{accent, danger,
 *   success, warning, info, textPrimary, textMuted, border}` as rgb-string
 *   values, with mode-aware fallbacks if a variable is undeclared.
 *
 * - `palette(colors)` — 8-slot dataset palette (accent, danger, success,
 *   warning, info, plus three fixed fallbacks). Identical across adapters
 *   so dataset 0 paints the same visual colour whether Chart.js or
 *   ApexCharts is active.
 *
 * - `withOpacity(color, opacity)` — produce an rgba() variant from hex,
 *   rgb(), or rgba() inputs. Used by wirekitChartJs for dataset
 *   backgroundColor (line/area fills, bar fills); ApexCharts uses its
 *   own opacity helper internally so the function is exported for parity
 *   but not always called from the apex factory.
 */

/**
 * Resolve WireKit theme colours from the current CSS-variable cascade.
 *
 * Implementation note — OKLCH probe trick: setting a CSS variable as
 * `color: var(--name)` on a hidden DOM element forces the browser to
 * resolve it through the cascade and emit the rgb()/rgba() representation
 * via getComputedStyle().color. Sidesteps Canvas 2D's spotty OKLCH parsing
 * (Chart.js silently falls back to black on OKLCH inputs in some browsers
 * we still support per the Tailwind v4 baseline).
 *
 * @param {CSSStyleDeclaration} style - getComputedStyle() result of any
 *   element inside the cascade we want to read (typically the chart's
 *   canvas or mount div). Must be in the live DOM so .dark on <html> /
 *   <body> propagates correctly.
 * @returns {{
 *   accent: string,
 *   danger: string,
 *   success: string,
 *   warning: string,
 *   info: string,
 *   textPrimary: string,
 *   textMuted: string,
 *   border: string
 * }} rgb()/rgba() string values for each token.
 */
export function resolveThemeColors(style) {
    const isDark = document.documentElement.classList.contains('dark')
        || document.body.classList.contains('dark');

    // Probe element: hidden div appended to <body> so var() resolves
    // through the correct cascade. Appending to <html> would fail when
    // .dark is on <body> only (probe would be a sibling outside the cascade).
    const probe = document.createElement('div');
    probe.style.display = 'none';
    document.body.appendChild(probe);

    // Canvas 2D parser + paint-and-read pixel trick — forces every colour
    // input into a definitive `rgb()` / `rgba()` representation regardless
    // of the source format (`oklch()`, `color(srgb ...)`, `hsl()`, `#rrggbb`,
    // bare colour names, etc.). Just setting `ctx.fillStyle = colorStr` and
    // reading it back is NOT enough on Chrome 111+ baselines: when the input
    // is `oklch(...)`, the browser preserves the OKLCH literal in fillStyle,
    // and downstream code (isGrayscale, withOpacity, ApexCharts series.color)
    // either fails to parse it (silent black fallback) or paints it directly
    // — producing the chart-bars-render-pure-black bug the user reported.
    //
    // Painting a 1×1 pixel and reading the ImageData back gives us the
    // browser's actual sRGB-rendered pixel value, which is always rgb()/
    // rgba() regardless of source colour space.
    const probeCanvas = document.createElement('canvas');
    probeCanvas.width = 1;
    probeCanvas.height = 1;
    const canvasCtx = probeCanvas.getContext('2d', { willReadFrequently: true });
    const normalizeToRgb = (colorStr) => {
        try {
            // Clear to fully transparent (so reading alpha is meaningful when
            // the source colour itself carries an alpha channel).
            canvasCtx.clearRect(0, 0, 1, 1);
            canvasCtx.fillStyle = colorStr;
            canvasCtx.fillRect(0, 0, 1, 1);
            const data = canvasCtx.getImageData(0, 0, 1, 1).data;
            const [r, g, b, a] = data;
            // Alpha channel is 0..255 in ImageData; rgba() expects 0..1.
            return a === 255
                ? `rgb(${r}, ${g}, ${b})`
                : `rgba(${r}, ${g}, ${b}, ${(a / 255).toFixed(3)})`;
        } catch {
            // Invalid colour → preserve original so the rest of the pipeline
            // still has something to work with.
            return colorStr;
        }
    };

    const resolve = (varName, fallbackLight, fallbackDark) => {
        const raw = style.getPropertyValue(varName).trim();
        if (!raw) {
            return isDark ? fallbackDark : fallbackLight;
        }
        probe.style.color = `var(${varName})`;
        const rgb = getComputedStyle(probe).color;
        probe.style.color = '';
        return normalizeToRgb(rgb);
    };

    // Test whether a resolved colour string is effectively grayscale —
    // R, G, B channels within an 8-unit window of each other (0..255 scale).
    // Reads from the Canvas-normalized output so we only have to handle
    // rgb()/rgba()/#rrggbb — not oklch()/color()/etc.
    const isGrayscale = (colorStr) => {
        const m = colorStr.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
        if (m) {
            const r = +m[1];
            const g = +m[2];
            const b = +m[3];
            return Math.max(r, g, b) - Math.min(r, g, b) <= 8;
        }
        const hex = colorStr.match(/^#([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i);
        if (hex) {
            const r = parseInt(hex[1], 16);
            const g = parseInt(hex[2], 16);
            const b = parseInt(hex[3], 16);
            return Math.max(r, g, b) - Math.min(r, g, b) <= 8;
        }

        return false;
    };

    // Chart-DATA colours (fills, strokes, dataset colours). These must be
    // visible against the chart background. The WireKit Default theme uses
    // a neutral palette (--color-wk-accent: oklch(20.5% 0 0) — near-black
    // for UI chrome) which would render a chart polygon as a black blob
    // on a white background. Detect that case via isGrayscale() and
    // substitute the softer fallback so out-of-the-box chart legibility
    // doesn't depend on the developer overriding the accent token. Themes
    // that DO want neutral charts can override with a chroma > 0 oklch
    // (e.g. oklch(50% 0.01 250) — a near-neutral with just enough chroma
    // to register as non-grayscale) or with a hex/rgb declaration.
    const resolveChartColor = (varName, fallbackLight, fallbackDark) => {
        const resolved = resolve(varName, fallbackLight, fallbackDark);
        if (isGrayscale(resolved)) {
            return isDark ? fallbackDark : fallbackLight;
        }
        return resolved;
    };

    const colors = {
        // Chart-data palette — Tailwind 300-shade light tones, 200-shade
        // dark tones (one step lighter on dark backgrounds, matching the
        // standard `bg-sky-300 dark:bg-sky-200` Tailwind pattern). Sits in
        // a soft, professional saturation band that reads as polished
        // rather than alarming when used as polygon fills + dataset
        // markers, while keeping enough contrast against both light and
        // dark surfaces.
        //
        // Sky-blue replaces flat blue for a friendlier accent default;
        // rose replaces red for a less-alarming negative signal that still
        // reads as semantic. The light/dark pair is deliberate — a chart
        // toggling between modes MUST visibly recolour to signal the theme
        // change, otherwise dark-mode regression tests fail and developers
        // perceive the chart as not theme-aware.
        accent:      resolveChartColor('--color-wk-accent', '#7dd3fc', '#bae6fd'),
        danger:      resolveChartColor('--color-wk-danger', '#fda4af', '#fecdd3'),
        success:     resolveChartColor('--color-wk-success', '#86efac', '#bbf7d0'),
        warning:     resolveChartColor('--color-wk-warning', '#fcd34d', '#fde68a'),
        info:        resolveChartColor('--color-wk-info', '#67e8f9', '#a5f3fc'),
        // UI-chrome tokens (text, muted, border) stay strictly themed — a
        // neutral theme should keep neutral text and borders; only chart-
        // data fills get the legibility fallback above.
        textPrimary: resolve('--color-wk-text', '#18181b', '#f4f4f5'),
        textMuted:   resolve('--color-wk-text-muted', '#71717a', '#a1a1aa'),
        border:      resolve('--color-wk-border', '#e4e4e7', '#52525b'),
    };

    probe.remove();
    return colors;
}

/**
 * 8-slot dataset palette. Identical across Chart.js + ApexCharts adapters
 * so a chart's dataset[0] paints in the same brand colour regardless of
 * which library renders it.
 *
 * @param {ReturnType<resolveThemeColors>} colors
 * @returns {string[]}
 */
export function palette(colors) {
    return [
        colors.accent,
        colors.danger,
        colors.success,
        colors.warning,
        colors.info,
        // Tailwind 300-shade tones for slots 6-8 — match the softer
        // saturation band used in the resolveChartColor fallbacks above.
        // These three are non-semantic accents (no "this means good/bad"
        // meaning): violet, pink, orange. Together with the five semantic
        // slots they cover up to 8 series without repeating colours.
        '#c4b5fd', // violet-300
        '#f9a8d4', // pink-300
        '#fdba74', // orange-300
    ];
}

/**
 * Produce an rgba() variant from hex (#rrggbb), rgb(r, g, b), or rgba()
 * input. hsl() / oklch() / etc. fall through unchanged.
 *
 * @param {string} color
 * @param {number} opacity 0..1
 * @returns {string}
 */
export function withOpacity(color, opacity) {
    if (color.startsWith('#')) {
        const r = parseInt(color.slice(1, 3), 16);
        const g = parseInt(color.slice(3, 5), 16);
        const b = parseInt(color.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }
    if (color.startsWith('rgb(')) {
        return color.replace('rgb(', 'rgba(').replace(')', `, ${opacity})`);
    }
    if (color.startsWith('rgba(')) {
        return color.replace(/,\s*[\d.]+\)$/, `, ${opacity})`);
    }
    return color;
}
