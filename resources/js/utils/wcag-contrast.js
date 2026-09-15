/**
 * WCAG 2.1 contrast, in the browser — the counterpart to `Pushery\WireKit\Theming\WcagContrast`.
 *
 * WHY A SECOND IMPLEMENTATION AT ALL. A page that shows a reader what contrast their theme
 * actually produces has to measure what the browser resolved, not what the registry declares:
 * a theme or mode switch re-measures, and the two can disagree. They did — a preset shipped at
 * 1.04:1 on raised surfaces in dark mode while its registry entry was correct, and a value
 * computed on the server would have reported the correct one. So the numbers have to come from
 * `getComputedStyle`, and that means the arithmetic has to exist here.
 *
 * WHAT KEEPS THE TWO HONEST. `WcagContrastJsMatchesThePhpTest` runs this module in Node against
 * the PHP class over one shared corpus and requires identical answers, `null` for `null`
 * included. Two implementations of one formula drift silently otherwise — each one green
 * against its own expectations.
 *
 * NO `resolveCssVars()` HERE, DELIBERATELY. The PHP class resolves `var()` chains because it
 * reads a stylesheet as text. A browser hands out resolved values already, so a second resolver
 * would be a second place for a chain to be read differently — and it is the half with no
 * reader. `getComputedStyle(el).color` is what a caller passes in.
 *
 * Every exported function takes and returns the same shapes as its PHP twin. Channels are
 * linear sRGB in 0..1; alpha is 0..1; a ratio is >= 1.
 *
 * @module
 */

/** sRGB-encoded channel (0..1) -> linear-sRGB channel (0..1). */
function srgbToLinear(channel) {
    if (channel <= 0.04045) {
        return channel / 12.92;
    }

    return ((channel + 0.055) / 1.055) ** 2.4;
}

/**
 * Gamma-encode a linear sRGB channel (inverse of srgbToLinear). Used to mix in the
 * gamma-encoded sRGB space, as `color-mix(in srgb, …)` requires.
 */
function linearToSrgb(channel) {
    if (channel <= 0.0031308) {
        return 12.92 * channel;
    }

    return 1.055 * channel ** (1 / 2.4) - 0.055;
}

const clamp01 = (n) => Math.max(0, Math.min(1, n));

/**
 * A leading-numeric read, because that is what the PHP side does.
 *
 * `(float) "240deg"` is 240.0 in PHP and `Number("240deg")` is NaN here — the hue in
 * `oklch(L C 240deg)` is exactly that case, and the two would then disagree on a value both
 * accept. `parseFloat` has PHP's behavior.
 */
const leadingFloat = (raw) => {
    const n = parseFloat(raw);

    return Number.isNaN(n) ? 0 : n;
};

/** OKLCH L-component (0..1 decimal OR "NN%") as a 0..1 float. */
function parseOklchL(raw) {
    return raw.endsWith('%') ? leadingFloat(raw) / 100 : leadingFloat(raw);
}

/**
 * OKLCH C-component as a 0..~0.4 float. CSS Color 4 allows chroma as a number or as a
 * percentage where 100% maps to 0.4; an app.css override may legitimately use either, and a
 * percentage read as a raw number would skew the ratio without saying so.
 */
function parseOklchC(raw) {
    return raw.endsWith('%') ? (leadingFloat(raw) / 100) * 0.4 : leadingFloat(raw);
}

/**
 * OKLab -> linear-sRGB. From CSS Color Module 4, two matrix steps.
 *
 * Out-of-gamut values are clipped per channel rather than gamut-mapped: not exact, and the
 * WCAG luminance delta between the two is small. The PHP side clips identically, which is the
 * property that matters here.
 */
function oklabToLinearRgb(L, a, b) {
    const lPrime = L + 0.3963377774 * a + 0.2158037573 * b;
    const mPrime = L - 0.1055613458 * a - 0.0638541728 * b;
    const sPrime = L - 0.0894841775 * a - 1.2914855480 * b;

    const l = lPrime ** 3;
    const m = mPrime ** 3;
    const s = sPrime ** 3;

    return [
        clamp01(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s),
        clamp01(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s),
        clamp01(-0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s),
    ];
}

/** OKLCH -> OKLab -> linear-sRGB. */
function oklchToLinearRgb(L, C, hueDegrees) {
    const rad = (hueDegrees * Math.PI) / 180;

    return oklabToLinearRgb(L, C * Math.cos(rad), C * Math.sin(rad));
}

/** An alpha value as 0..1, from a number or a percentage. Null for anything else. */
function parseAlpha(token) {
    const m = /^(\d*\.?\d+)(%?)$/.exec(token.trim());

    if (m === null) {
        return null;
    }

    const value = m[2] === '%' ? leadingFloat(m[1]) / 100 : leadingFloat(m[1]);

    return clamp01(value);
}

/**
 * Split a comma-separated list on top-level commas only, respecting nested parentheses so
 * `oklch(…)` and `color-mix(…)` operands stay intact.
 */
function splitTopLevelComma(s) {
    const parts = [];
    let depth = 0;
    let buf = '';

    for (const ch of s) {
        if (ch === '(') {
            depth++;
        } else if (ch === ')') {
            depth--;
        }

        if (ch === ',' && depth === 0) {
            parts.push(buf.trim());
            buf = '';

            continue;
        }

        buf += ch;
    }

    if (buf.trim() !== '') {
        parts.push(buf.trim());
    }

    return parts;
}

/**
 * Parse a color-mix operand `<color> [<pct>%]` into [color, pct|null]. The percentage, when
 * present, is the trailing token.
 */
function parseMixOperand(operand) {
    const trimmed = operand.trim();
    const m = /\s+([\d.]+)%$/.exec(trimmed);

    if (m === null) {
        return [trimmed, null];
    }

    return [trimmed.slice(0, trimmed.length - m[0].length).trim(), leadingFloat(m[1])];
}

/**
 * Parse a CSS color string into linear sRGB plus alpha: [r, g, b, a], each 0..1.
 * Null when the format, or its alpha, is unsupported.
 *
 * Reads #rgb, #rgba, #rrggbb and #rrggbbaa; oklch() and oklab() with an optional `/ alpha`;
 * color(srgb …); rgb() and rgba() in both the comma and the slash form; the `transparent`
 * keyword; and color-mix(in srgb, …), whose operands may themselves be translucent.
 *
 * An alpha this cannot read makes the whole color null. Taken for opaque it would be the
 * overestimate this parser exists to end.
 *
 * @param {string} color
 * @returns {[number, number, number, number]|null}
 */
export function parseToLinearRgba(color) {
    const value = String(color).trim();

    if (value.toLowerCase() === 'transparent') {
        return [0, 0, 0, 0];
    }

    // Hex form — #rgb, #rgba, #rrggbb, #rrggbbaa.
    const hexMatch = /^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.exec(value);
    if (hexMatch !== null) {
        let hex = hexMatch[1];
        if (hex.length <= 4) {
            hex = [...hex].map((digit) => digit + digit).join('');
        }
        const channel = (offset) => parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return [
            srgbToLinear(channel(0)),
            srgbToLinear(channel(2)),
            srgbToLinear(channel(4)),
            hex.length === 8 ? channel(6) : 1,
        ];
    }

    // OKLCH — oklch(L C H) and oklch(L C H / alpha). L is 0..1 or N%, C a small decimal or N%,
    // H in degrees.
    const oklch = /^oklch\(\s*([^\s,)]+)\s+([^\s,)]+)\s+([^\s,)/]+)(?:\s*\/\s*([^\s)]+))?\s*\)$/i.exec(value);
    if (oklch !== null) {
        const alpha = oklch[4] === undefined ? 1 : parseAlpha(oklch[4]);
        if (alpha === null) {
            return null;
        }

        const [r, g, b] = oklchToLinearRgb(parseOklchL(oklch[1]), parseOklchC(oklch[2]), leadingFloat(oklch[3]));

        return [r, g, b, alpha];
    }

    // OKLab — how a browser serializes a color-mix(in oklab, …), which is the shape WireKit's
    // own translucent text and rail tokens come back from getComputedStyle in. L is 0..1 or N%;
    // a and b are numbers, or N% of 0.4.
    const oklab = /^oklab\(\s*([^\s,)]+)\s+([^\s,)]+)\s+([^\s,)/]+)(?:\s*\/\s*([^\s)]+))?\s*\)$/i.exec(value);
    if (oklab !== null) {
        const alpha = oklab[4] === undefined ? 1 : parseAlpha(oklab[4]);
        if (alpha === null) {
            return null;
        }

        const axis = (raw) => (raw.endsWith('%') ? (leadingFloat(raw) / 100) * 0.4 : leadingFloat(raw));
        const [r, g, b] = oklabToLinearRgb(parseOklchL(oklab[1]), axis(oklab[2]), axis(oklab[3]));

        return [r, g, b, alpha];
    }

    // color(srgb r g b) and color(srgb r g b / alpha) — how a browser serializes a
    // color-mix(in srgb, …). Another color space returns null rather than being read as sRGB.
    const srgb = /^color\(\s*srgb\s+([\d.]+%?)\s+([\d.]+%?)\s+([\d.]+%?)(?:\s*\/\s*([^\s)]+))?\s*\)$/i.exec(value);
    if (srgb !== null) {
        const alpha = srgb[4] === undefined ? 1 : parseAlpha(srgb[4]);
        if (alpha === null) {
            return null;
        }

        const channel = (raw) => clamp01(raw.endsWith('%') ? leadingFloat(raw) / 100 : leadingFloat(raw));

        return [
            srgbToLinear(channel(srgb[1])),
            srgbToLinear(channel(srgb[2])),
            srgbToLinear(channel(srgb[3])),
            alpha,
        ];
    }

    // color-mix(in srgb, <c1> [p1%], <c2> [p2%]) — the softened tinted surfaces (badge, alert,
    // stat) are built with color-mix, so without this arm every tinted background is
    // unauditable. Only `in srgb`; another interpolation space returns null rather than guess.
    const mix = /^color-mix\(\s*in\s+srgb\s*,\s*([\s\S]+)\)$/i.exec(value);
    if (mix !== null) {
        const parts = splitTopLevelComma(mix[1]);
        if (parts.length !== 2) {
            return null;
        }

        const [color1, pct1] = parseMixOperand(parts[0]);
        const [color2, pct2] = parseMixOperand(parts[1]);

        const first = parseToLinearRgba(color1);
        const second = parseToLinearRgba(color2);
        if (first === null || second === null) {
            return null;
        }

        // An omitted percentage takes the remainder; two omitted are a 50/50 mix (CSS default).
        let sum = 100;
        let w1;
        if (pct1 === null && pct2 === null) {
            w1 = 0.5;
        } else if (pct1 === null) {
            w1 = 1 - pct2 / 100;
        } else if (pct2 === null) {
            w1 = pct1 / 100;
        } else {
            sum = pct1 + pct2;
            w1 = sum > 0 ? pct1 / sum : 0.5;
        }
        const w2 = 1 - w1;

        // Percentages adding up to less than 100% leave the result translucent by that much
        // (CSS Color 5's alpha multiplier); above 100% they are only normalized.
        const multiplier = sum < 100 ? sum / 100 : 1;

        // Premultiplied, as CSS mixes: each operand's channels count by its own alpha, so a
        // color mixed with `transparent` fades rather than darkening toward black.
        const alpha = first[3] * w1 + second[3] * w2;
        if (alpha <= 0) {
            return [0, 0, 0, 0];
        }

        const mixed = (i) =>
            srgbToLinear(
                (linearToSrgb(first[i]) * first[3] * w1 + linearToSrgb(second[i]) * second[3] * w2) / alpha
            );

        return [mixed(0), mixed(1), mixed(2), alpha * multiplier];
    }

    // rgb() / rgba() — what getComputedStyle returns, so a control's live border or background
    // read back from the DOM is auditable. Both the legacy comma form and the modern slash form.
    const rgb = /^rgba?\(\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*(?:[,/]\s*([\d.]+%?)\s*)?\)$/i.exec(value);
    if (rgb !== null) {
        const alpha = rgb[4] === undefined ? 1 : parseAlpha(rgb[4]);
        if (alpha === null) {
            return null;
        }

        const channel = (raw) => clamp01(raw.endsWith('%') ? leadingFloat(raw) / 100 : leadingFloat(raw) / 255);

        return [
            srgbToLinear(channel(rgb[1])),
            srgbToLinear(channel(rgb[2])),
            srgbToLinear(channel(rgb[3])),
            alpha,
        ];
    }

    return null;
}

/**
 * Parse a CSS color into linear sRGB, dropping alpha. Null for an unsupported format and for
 * a fully transparent color, which has no color of its own to report.
 *
 * @param {string} color
 * @returns {[number, number, number]|null}
 */
export function parseToLinearRgb(color) {
    const rgba = parseToLinearRgba(color);

    if (rgba === null || rgba[3] <= 0) {
        return null;
    }

    return [rgba[0], rgba[1], rgba[2]];
}

/**
 * WCAG 2.1 relative luminance from a linear sRGB triple.
 *
 * @param {[number, number, number]} linearRgb
 * @returns {number}
 */
export function relativeLuminance(linearRgb) {
    return 0.2126 * linearRgb[0] + 0.7152 * linearRgb[1] + 0.0722 * linearRgb[2];
}

/** Lay a translucent color over an opaque one: source-over, blended in encoded sRGB. */
function compositeOver(top, under) {
    const alpha = top[3];
    const blend = (i) => srgbToLinear(linearToSrgb(top[i]) * alpha + linearToSrgb(under[i]) * (1 - alpha));

    return [blend(0), blend(1), blend(2)];
}

/**
 * WCAG 2.1 contrast ratio between two color strings: >= 1 (1:1 identical, 21:1 max), or null
 * when either input is in an unsupported format, or when the BACKGROUND is translucent.
 *
 * A translucent foreground is laid over the background first, the way a browser composites it.
 * Measured as if it were opaque, 50% black on white reads 21:1 where a reader sees 3.98:1 —
 * the dangerous direction, because nothing turns red. A translucent background has no contrast
 * of its own: what shows through depends on whatever lies beneath it, which the two strings do
 * not say, so it is refused rather than guessed.
 *
 * @param {string} foreground
 * @param {string} background
 * @returns {number|null}
 */
export function ratio(foreground, background) {
    const fg = parseToLinearRgba(foreground);
    const bg = parseToLinearRgba(background);

    if (fg === null || bg === null || bg[3] < 1) {
        return null;
    }

    const lFg = relativeLuminance(fg[3] < 1 ? compositeOver(fg, bg) : [fg[0], fg[1], fg[2]]);
    const lBg = relativeLuminance([bg[0], bg[1], bg[2]]);

    return (Math.max(lFg, lBg) + 0.05) / (Math.min(lFg, lBg) + 0.05);
}

/**
 * Why ratio() returned null for this pair, or null when it would return a number.
 *
 * A translucent background and an unreadable value are different findings, and only the second
 * is about how a value is written. Reporting both as a format problem sends a developer looking
 * for a typo in a value that parsed perfectly well.
 *
 * @param {string} foreground
 * @param {string} background
 * @returns {'translucent-background'|'unsupported-format'|null}
 */
export function unmeasurableReason(foreground, background) {
    const fg = parseToLinearRgba(foreground);
    const bg = parseToLinearRgba(background);

    if (fg === null || bg === null) {
        return 'unsupported-format';
    }

    return bg[3] < 1 ? 'translucent-background' : null;
}

/**
 * Classify a ratio against WCAG 2.1 AA with a warning band: "pass", "warn" or "fail".
 *
 * Anything within 0.5 below the AA threshold is "warn" rather than a hard "fail". The band is
 * this function's, and callers rely on it — `conformance()` is the one without it.
 *
 * @param {number} value
 * @param {'text'|'ui'|string} [threshold]
 * @returns {'pass'|'warn'|'fail'}
 */
export function classify(value, threshold = 'text') {
    const aa = threshold === 'ui' ? 3 : 4.5;

    if (value >= aa) {
        return 'pass';
    }

    return value >= aa - 0.5 ? 'warn' : 'fail';
}

/**
 * Grade a ratio against WCAG 2.1: "AAA", "AA" or "fail".
 *
 * - text: AA at 4.5:1 (1.4.3), AAA at 7:1 (1.4.6)
 * - large-text: AA at 3:1 (1.4.3), AAA at 4.5:1 (1.4.6)
 * - ui — components, graphics, focus indicators: AA at 3:1 (1.4.11). WCAG 2.x defines no AAA
 *   level for non-text contrast, so the best a UI pairing can be is "AA".
 *
 * An unknown use throws, as the PHP twin does: grading it as text would answer a question
 * nobody asked.
 *
 * @param {number} value
 * @param {'text'|'large-text'|'ui'} [use]
 * @returns {'AAA'|'AA'|'fail'}
 */
export function conformance(value, use = 'text') {
    const levels = {
        text: [4.5, 7],
        'large-text': [3, 4.5],
        ui: [3, null],
    };

    if (!Object.prototype.hasOwnProperty.call(levels, use)) {
        throw new Error(`Unknown contrast use "${use}". Expected text, large-text or ui.`);
    }

    const [aa, aaa] = levels[use];

    if (aaa !== null && value >= aaa) {
        return 'AAA';
    }

    return value >= aa ? 'AA' : 'fail';
}

/**
 * The same surface as one object, for a caller who would rather hold a namespace than six
 * imports. `import { contrast } from '…/wirekit.esm.js'` reads like the PHP class does.
 */
export const contrast = {
    parseToLinearRgba,
    parseToLinearRgb,
    relativeLuminance,
    ratio,
    unmeasurableReason,
    classify,
    conformance,
};
