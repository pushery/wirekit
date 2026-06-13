/**
 * WireKit color conversion utilities — shared by the popover color picker.
 *
 * The picker holds its state as HSV (the natural space for a saturation/value
 * plane + hue slider) plus an alpha channel, and converts to/from RGB, HEX, and
 * HSL for display and parsing. All channels: h ∈ [0,360], s/v/l ∈ [0,100],
 * r/g/b ∈ [0,255], a ∈ [0,1].
 */

const clamp = (n, min, max) => Math.min(max, Math.max(min, n));
const round = (n) => Math.round(n);

export function hsvToRgb({ h, s, v }) {
    const sN = s / 100;
    const vN = v / 100;
    const c = vN * sN;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = vN - c;
    let r = 0;
    let g = 0;
    let b = 0;
    if (h < 60) { r = c; g = x; } else if (h < 120) { r = x; g = c; } else if (h < 180) { g = c; b = x; } else if (h < 240) { g = x; b = c; } else if (h < 300) { r = x; b = c; } else { r = c; b = x; }

    return { r: round((r + m) * 255), g: round((g + m) * 255), b: round((b + m) * 255) };
}

export function rgbToHsv({ r, g, b }) {
    const rN = r / 255;
    const gN = g / 255;
    const bN = b / 255;
    const max = Math.max(rN, gN, bN);
    const min = Math.min(rN, gN, bN);
    const d = max - min;
    let h = 0;
    if (d !== 0) {
        if (max === rN) { h = ((gN - bN) / d) % 6; } else if (max === gN) { h = (bN - rN) / d + 2; } else { h = (rN - gN) / d + 4; }
        h *= 60;
        if (h < 0) { h += 360; }
    }
    const s = max === 0 ? 0 : (d / max) * 100;

    return { h: round(h), s: round(s), v: round(max * 100) };
}

export function rgbToHsl({ r, g, b }) {
    const rN = r / 255;
    const gN = g / 255;
    const bN = b / 255;
    const max = Math.max(rN, gN, bN);
    const min = Math.min(rN, gN, bN);
    const d = max - min;
    const l = (max + min) / 2;
    let h = 0;
    let s = 0;
    if (d !== 0) {
        s = d / (1 - Math.abs(2 * l - 1));
        if (max === rN) { h = ((gN - bN) / d) % 6; } else if (max === gN) { h = (bN - rN) / d + 2; } else { h = (rN - gN) / d + 4; }
        h *= 60;
        if (h < 0) { h += 360; }
    }

    return { h: round(h), s: round(s * 100), l: round(l * 100) };
}

function componentToHex(n) {
    return clamp(round(n), 0, 255).toString(16).padStart(2, '0');
}

export function rgbToHex({ r, g, b, a = 1 }, withAlpha = false) {
    const base = `#${componentToHex(r)}${componentToHex(g)}${componentToHex(b)}`;
    if (withAlpha && a < 1) {
        return base + componentToHex(a * 255);
    }

    return base;
}

/**
 * Parse a CSS color string (hex / rgb[a] / hsl[a]) to { r, g, b, a } or null.
 */
export function parseColor(input) {
    if (typeof input !== 'string') { return null; }
    const str = input.trim().toLowerCase();

    // #rgb / #rgba / #rrggbb / #rrggbbaa
    const hex = str.match(/^#([0-9a-f]{3,8})$/);
    if (hex) {
        let h = hex[1];
        if (h.length === 3 || h.length === 4) {
            h = h.split('').map((c) => c + c).join('');
        }
        if (h.length === 6 || h.length === 8) {
            return {
                r: parseInt(h.slice(0, 2), 16),
                g: parseInt(h.slice(2, 4), 16),
                b: parseInt(h.slice(4, 6), 16),
                a: h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1,
            };
        }

        return null;
    }

    // rgb() / rgba()
    const rgb = str.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,/]+([\d.]+%?))?\s*\)$/);
    if (rgb) {
        const a = rgb[4] === undefined ? 1 : (rgb[4].endsWith('%') ? parseFloat(rgb[4]) / 100 : parseFloat(rgb[4]));

        return { r: clamp(round(+rgb[1]), 0, 255), g: clamp(round(+rgb[2]), 0, 255), b: clamp(round(+rgb[3]), 0, 255), a: clamp(a, 0, 1) };
    }

    // hsl() / hsla()
    const hsl = str.match(/^hsla?\(\s*([\d.]+)[\s,]+([\d.]+)%[\s,]+([\d.]+)%(?:[\s,/]+([\d.]+%?))?\s*\)$/);
    if (hsl) {
        const a = hsl[4] === undefined ? 1 : (hsl[4].endsWith('%') ? parseFloat(hsl[4]) / 100 : parseFloat(hsl[4]));
        const rgbFromHsl = hslToRgb({ h: +hsl[1], s: +hsl[2], l: +hsl[3] });

        return { ...rgbFromHsl, a: clamp(a, 0, 1) };
    }

    // oklch() — L as 0–1 or a percentage; C unitless; H in degrees (optional `deg`).
    const oklch = str.match(/^oklch\(\s*([\d.]+%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?(?:\s*\/\s*([\d.]+%?))?\s*\)$/);
    if (oklch) {
        const l = oklch[1].endsWith('%') ? parseFloat(oklch[1]) / 100 : parseFloat(oklch[1]);
        const a = oklch[4] === undefined ? 1 : (oklch[4].endsWith('%') ? parseFloat(oklch[4]) / 100 : parseFloat(oklch[4]));

        return { ...oklchToRgb({ l, c: +oklch[2], h: +oklch[3] }), a: clamp(a, 0, 1) };
    }

    return null;
}

// ── OKLCH (CSS Color 4) ──────────────────────────────────────────────────
// sRGB ↔ linear-sRGB ↔ OKLab ↔ OKLCH per https://www.w3.org/TR/css-color-4/
// (Björn Ottosson's OKLab matrices). The picker always holds in-gamut RGB, so
// rgbToOklch is exact; oklchToRgb clamps any out-of-gamut result to the nearest
// sRGB per channel — good enough for a picker, where the HSV state is re-derived
// from the clamped RGB on the next paint.

const srgbToLinear = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
const linearToSrgb = (c) => (c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055);

export function rgbToOklch({ r, g, b }) {
    const lr = srgbToLinear(r / 255);
    const lg = srgbToLinear(g / 255);
    const lb = srgbToLinear(b / 255);

    const l = 0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb;
    const m = 0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb;
    const s = 0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb;

    const l_ = Math.cbrt(l);
    const m_ = Math.cbrt(m);
    const s_ = Math.cbrt(s);

    const L = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_;
    const A = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_;
    const B = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_;

    const c = Math.sqrt(A * A + B * B);
    let h = Math.atan2(B, A) * 180 / Math.PI;
    if (h < 0) { h += 360; }

    return { l: L, c, h };
}

export function oklchToRgb({ l: L, c: C, h: H }) {
    const hr = H * Math.PI / 180;
    const A = C * Math.cos(hr);
    const B = C * Math.sin(hr);

    const l_ = L + 0.3963377774 * A + 0.2158037573 * B;
    const m_ = L - 0.1055613458 * A - 0.0638541728 * B;
    const s_ = L - 0.0894841775 * A - 1.2914855480 * B;

    const l = l_ ** 3;
    const m = m_ ** 3;
    const s = s_ ** 3;

    const lr = 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
    const lg = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
    const lb = -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s;

    return {
        r: clamp(round(linearToSrgb(lr) * 255), 0, 255),
        g: clamp(round(linearToSrgb(lg) * 255), 0, 255),
        b: clamp(round(linearToSrgb(lb) * 255), 0, 255),
    };
}

export function hslToRgb({ h, s, l }) {
    const sN = s / 100;
    const lN = l / 100;
    const c = (1 - Math.abs(2 * lN - 1)) * sN;
    const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = lN - c / 2;
    let r = 0;
    let g = 0;
    let b = 0;
    if (h < 60) { r = c; g = x; } else if (h < 120) { r = x; g = c; } else if (h < 180) { g = c; b = x; } else if (h < 240) { g = x; b = c; } else if (h < 300) { r = x; b = c; } else { r = c; b = x; }

    return { r: round((r + m) * 255), g: round((g + m) * 255), b: round((b + m) * 255) };
}

/**
 * Format an { r, g, b, a } color in the requested format ('hex' | 'rgb' | 'hsl').
 */
export function formatColor({ r, g, b, a = 1 }, format, withAlpha = false) {
    const alpha = withAlpha ? a : 1;
    if (format === 'rgb') {
        return alpha < 1
            ? `rgba(${r}, ${g}, ${b}, ${+alpha.toFixed(2)})`
            : `rgb(${r}, ${g}, ${b})`;
    }
    if (format === 'hsl') {
        const { h, s, l } = rgbToHsl({ r, g, b });

        return alpha < 1
            ? `hsla(${h}, ${s}%, ${l}%, ${+alpha.toFixed(2)})`
            : `hsl(${h}, ${s}%, ${l}%)`;
    }
    if (format === 'oklch') {
        const { l, c, h } = rgbToOklch({ r, g, b });
        const parts = `${+(l * 100).toFixed(1)}% ${+c.toFixed(3)} ${+h.toFixed(1)}`;

        return alpha < 1 ? `oklch(${parts} / ${+alpha.toFixed(2)})` : `oklch(${parts})`;
    }

    return rgbToHex({ r, g, b, a: alpha }, withAlpha);
}
