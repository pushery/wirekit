<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

/**
 * WCAG 2.1 contrast-ratio computation for WireKit theme tokens.
 *
 * Pure utility — no Laravel facade dependencies, no I/O. Given a CSS
 * color string in OKLCH or hex form, returns its relative luminance,
 * and given a pair of strings, returns their WCAG contrast ratio.
 *
 * Scope: this helper covers the color formats WireKit itself emits and the
 * forms a browser hands back from `getComputedStyle` — `oklch(L C H)` (L as a
 * decimal 0–1 or a percentage), `#rrggbb` / `#rgb` hex, `color-mix(in srgb, …)`,
 * and `rgb()` / `rgba()` (both the comma and the modern space form). Anything
 * else — named colors, `currentColor`, `color-mix` in a non-sRGB space — returns
 * `null`, which means "unparseable, skip this pairing" and MUST be treated as such
 * (assert on `null`; do not cast to float — `(float) null` reads as a false 0.00:1),
 * never as a contrast failure.
 *
 * OKLCH → sRGB conversion follows the CSS Color Module 4 specification:
 *
 *   OKLCH → OKLab → linear-sRGB → sRGB
 *
 * Reference matrices from https://www.w3.org/TR/css-color-4/#color-conversion-code
 */
final class WcagContrast
{
    /**
     * Substitute CSS custom-property `var()` references embedded anywhere in a
     * value string with their declared values, so a color such as
     * `oklch(0.55 0.22 var(--theme-hue))` becomes parseable. Per the CSS spec,
     * `var()` is substituted BEFORE a value is parsed, so a static contrast
     * auditor must do the same — otherwise the raw `var(--theme-hue)` token is
     * an unparseable hue and the whole hue-driven (single-source-hue) palette
     * reports "unsupported color format".
     *
     * Resolves recursively (a substituted value may itself contain a `var()`)
     * and is CYCLE-GUARDED by a depth cap plus a no-progress break, so a
     * malformed `--a: var(--b); --b: var(--a)` table terminates (the value is
     * simply left with an unresolved `var()`, which the caller then treats as
     * unsupported) instead of looping forever. A `var(--x, fallback)` whose
     * custom property is absent resolves to its fallback.
     *
     * @param  array<string, string>  $vars  custom-property name => declared value
     */
    public static function resolveCssVars(string $value, array $vars, int $maxDepth = 8): string
    {
        for ($depth = 0; $depth < $maxDepth && str_contains($value, 'var('); $depth++) {
            $next = preg_replace_callback(
                // The fallback capture allows ONE level of balanced parens so a
                // function fallback — `var(--x, oklch(…))` / `rgb(…)` / `calc(…)`,
                // a common token shape — is matched (a bare `[^()]*` stops at the
                // inner `(`, leaves the whole var() unmatched, and the caller then
                // SKIPs the token as unsupported). A fallback nesting TWO+ paren
                // levels (e.g. `color-mix(in srgb, oklch(…), …)`) still falls
                // through unresolved → SKIP — the safe pre-existing degrade, not a crash.
                '/var\(\s*(--[\w-]+)\s*(?:,\s*((?:[^()]|\([^()]*\))*))?\)/',
                static function (array $m) use ($vars): string {
                    if (array_key_exists($m[1], $vars)) {
                        return $vars[$m[1]];
                    }

                    // No declared value: fall back to the var()'s own fallback
                    // arg if present, else leave the token untouched (the caller
                    // will treat the still-unresolved color as unsupported).
                    return isset($m[2]) ? trim($m[2]) : $m[0];
                },
                $value
            );
            if ($next === null || $next === $value) {
                break; // unresolvable or no progress — stop (cycle-safe)
            }
            $value = $next;
        }

        return $value;
    }

    /**
     * Parse a CSS color string into linear sRGB [r, g, b] in 0..1.
     * Returns null when the format is unsupported.
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    public static function parseToLinearRgb(string $color): ?array
    {
        $color = trim($color);

        // Hex form — #rgb / #rrggbb (8-digit alpha not supported for contrast).
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $m) === 1) {
            $hex = $m[1];
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            $r = hexdec(substr($hex, 0, 2)) / 255.0;
            $g = hexdec(substr($hex, 2, 2)) / 255.0;
            $b = hexdec(substr($hex, 4, 2)) / 255.0;

            return [self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b)];
        }

        // OKLCH form. Accept oklch(L C H) and oklch(L C H / alpha) — alpha
        // ignored for contrast computation. L may be 0..1 decimal or N%.
        // C is a small decimal (typically 0..0.4). H is degrees (any value).
        if (preg_match('#^oklch\(\s*([^\s,)]+)\s+([^\s,)]+)\s+([^\s,)/]+)(?:\s*/\s*[^\s)]+)?\s*\)$#i', $color, $m) === 1) {
            $L = self::parseOklchL($m[1]);
            $C = self::parseOklchC($m[2]);
            $H = (float) $m[3];

            return self::oklchToLinearRgb($L, $C, $H);
        }

        // color-mix(in srgb, <c1> [p1%], <c2> [p2%]) — the softened tinted
        // surfaces (badge / alert / stat) are built with color-mix, so without
        // this arm every tinted background is unauditable (returns null) and a
        // real AA failure on a soft surface can only be caught downstream by a
        // developer's axe run. Mix in the sRGB space per the CSS
        // spec: convert operands to gamma-encoded sRGB, blend by weight, convert
        // back to linear. Only `in srgb` is supported (the space WireKit uses);
        // other interpolation spaces return null rather than guess.
        if (preg_match('#^color-mix\(\s*in\s+srgb\s*,\s*(.+)\)$#is', $color, $m) === 1) {
            $parts = self::splitTopLevelComma($m[1]);
            if (count($parts) !== 2) {
                return null;
            }

            [$color1, $pct1] = self::parseMixOperand($parts[0]);
            [$color2, $pct2] = self::parseMixOperand($parts[1]);

            $lin1 = self::parseToLinearRgb($color1);
            $lin2 = self::parseToLinearRgb($color2);
            if ($lin1 === null || $lin2 === null) {
                return null;
            }

            // Normalize weights: if one percentage is omitted it takes the
            // remainder; if both are omitted it is a 50/50 mix (CSS default).
            if ($pct1 === null && $pct2 === null) {
                $w1 = 0.5;
            } elseif ($pct1 === null) {
                $w1 = 1.0 - ($pct2 / 100.0);
            } elseif ($pct2 === null) {
                $w1 = $pct1 / 100.0;
            } else {
                $sum = $pct1 + $pct2;
                $w1 = $sum > 0 ? $pct1 / $sum : 0.5;
            }
            $w2 = 1.0 - $w1;

            // Blend in gamma-encoded sRGB, then return linear for luminance.
            $srgb1 = array_map(self::linearToSrgb(...), $lin1);
            $srgb2 = array_map(self::linearToSrgb(...), $lin2);
            $mixed = [
                self::srgbToLinear($srgb1[0] * $w1 + $srgb2[0] * $w2),
                self::srgbToLinear($srgb1[1] * $w1 + $srgb2[1] * $w2),
                self::srgbToLinear($srgb1[2] * $w1 + $srgb2[2] * $w2),
            ];

            return $mixed;
        }

        // rgb() / rgba() — getComputedStyle returns resolved colors in this form,
        // so a form control's live border/background read back from the DOM was
        // unauditable (returned null) without this arm. Match both the
        // legacy comma form (rgb(255, 0, 0) / rgba(255,0,0,.5)) AND the modern space
        // form (rgb(255 255 255 / 50%)); alpha is ignored, as with oklch's `/ alpha`.
        if (preg_match('/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i', $color, $m) === 1) {
            $r = ((float) $m[1]) / 255.0;
            $g = ((float) $m[2]) / 255.0;
            $b = ((float) $m[3]) / 255.0;

            return [self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b)];
        }

        return null;
    }

    /**
     * Split a comma-separated list on top-level commas only, respecting nested
     * parentheses (so `oklch(…)` / `color-mix(…)` operands stay intact).
     *
     * @return list<string>
     */
    private static function splitTopLevelComma(string $s): array
    {
        $parts = [];
        $depth = 0;
        $buf = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                $parts[] = trim($buf);
                $buf = '';

                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $parts[] = trim($buf);
        }

        return $parts;
    }

    /**
     * Parse a color-mix operand `<color> [<pct>%]` into [color, pct|null].
     * The percentage, when present, is the trailing token.
     *
     * @return array{0: string, 1: float|null}
     */
    private static function parseMixOperand(string $operand): array
    {
        $operand = trim($operand);
        if (preg_match('#\s+([\d.]+)%$#', $operand, $m) === 1) {
            $color = trim(substr($operand, 0, -strlen($m[0])));

            return [$color, (float) $m[1]];
        }

        return [$operand, null];
    }

    /**
     * Gamma-encode a linear sRGB channel (inverse of srgbToLinear). Used to mix
     * in the gamma-encoded sRGB space, as `color-mix(in srgb, …)` requires.
     */
    private static function linearToSrgb(float $channel): float
    {
        if ($channel <= 0.0031308) {
            return 12.92 * $channel;
        }

        return 1.055 * $channel ** (1 / 2.4) - 0.055;
    }

    /**
     * WCAG 2.1 relative luminance (L) from linear sRGB triples.
     *
     * @param  array{0: float, 1: float, 2: float}  $linearRgb
     */
    public static function relativeLuminance(array $linearRgb): float
    {
        // WCAG defines L = 0.2126 * R + 0.7152 * G + 0.0722 * B in linear-sRGB.
        return 0.2126 * $linearRgb[0] + 0.7152 * $linearRgb[1] + 0.0722 * $linearRgb[2];
    }

    /**
     * WCAG 2.1 contrast ratio between two color strings.
     * Returns a float >= 1.0 (1:1 = identical, 21:1 = max), or null if
     * either input is in an unsupported format.
     */
    public static function ratio(string $foreground, string $background): ?float
    {
        $fg = self::parseToLinearRgb($foreground);
        $bg = self::parseToLinearRgb($background);
        if ($fg === null || $bg === null) {
            return null;
        }

        $lFg = self::relativeLuminance($fg);
        $lBg = self::relativeLuminance($bg);
        $lighter = max($lFg, $lBg);
        $darker = min($lFg, $lBg);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Classify a contrast ratio against WCAG 2.1 AA thresholds.
     * Returns one of "pass", "warn", "fail".
     *
     * - text (normal-size body) needs >= 4.5
     * - large-text / UI components / focus indicators need >= 3.0
     */
    public static function classify(float $ratio, string $threshold = 'text'): string
    {
        $aa = $threshold === 'ui' ? 3.0 : 4.5;
        // Generous tolerance: anything within 0.5 of the AA threshold (but
        // below it) is "warn" rather than a hard "fail" — e.g. text 4.0–4.49
        // or UI 2.5–2.99. Pinned by WcagContrastTest's classify() cases.
        if ($ratio >= $aa) {
            return 'pass';
        }
        if ($ratio >= $aa - 0.5) {
            return 'warn';
        }

        return 'fail';
    }

    /** Convert oklch L-component (0..1 decimal OR "NN%" percentage) to 0..1 float. */
    private static function parseOklchL(string $raw): float
    {
        if (str_ends_with($raw, '%')) {
            return ((float) rtrim($raw, '%')) / 100.0;
        }

        return (float) $raw;
    }

    /**
     * Convert oklch C-component to a 0..~0.4 float. CSS Color 4 allows chroma
     * as a number (0..0.4 typical) OR a percentage where 100% maps to 0.4 — a
     * developer's app.css override can legitimately use either form, so the
     * doctor's per-token audit must parse both. Without this, a percentage
     * chroma would be read as a huge raw number and silently skew the ratio.
     */
    private static function parseOklchC(string $raw): float
    {
        if (str_ends_with($raw, '%')) {
            return ((float) rtrim($raw, '%')) / 100.0 * 0.4;
        }

        return (float) $raw;
    }

    /**
     * OKLCH → OKLab → linear-sRGB. Returns linear-sRGB triples in 0..1
     * (clamped — out-of-gamut values get pinned to the gamut boundary;
     * not perfectly accurate but sufficient for WCAG comparison).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function oklchToLinearRgb(float $L, float $C, float $Hdeg): array
    {
        $Hrad = deg2rad($Hdeg);
        $a = $C * cos($Hrad);
        $b = $C * sin($Hrad);

        return self::oklabToLinearRgb($L, $a, $b);
    }

    /**
     * OKLab → linear-sRGB. From CSS Color Module 4, two matrix steps:
     *   OKLab → LMS' (apply cube of the L'M'S' vector)
     *   LMS  → linear-sRGB
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function oklabToLinearRgb(float $L, float $a, float $b): array
    {
        // Step 1 — OKLab → LMS' (Oklab paper, M2 inverse).
        $lPrime = $L + 0.3963377774 * $a + 0.2158037573 * $b;
        $mPrime = $L - 0.1055613458 * $a - 0.0638541728 * $b;
        $sPrime = $L - 0.0894841775 * $a - 1.2914855480 * $b;

        // Cube each to get LMS.
        $lms = [$lPrime ** 3, $mPrime ** 3, $sPrime ** 3];

        // Step 2 — LMS → linear-sRGB (Oklab paper, M1 inverse).
        $r = 4.0767416621 * $lms[0] - 3.3077115913 * $lms[1] + 0.2309699292 * $lms[2];
        $g = -1.2684380046 * $lms[0] + 2.6097574011 * $lms[1] - 0.3413193965 * $lms[2];
        $bl = -0.0041960863 * $lms[0] - 0.7034186147 * $lms[1] + 1.7076147010 * $lms[2];

        // Clamp to [0, 1] — out-of-gamut OKLCH values get pinned. Not
        // perfectly accurate (gamut-mapping would push the hue out
        // instead of clipping each channel), but the WCAG luminance
        // delta for clipped vs gamut-mapped is small in practice.
        return [max(0.0, min(1.0, $r)), max(0.0, min(1.0, $g)), max(0.0, min(1.0, $bl))];
    }

    /** sRGB-encoded channel (0..1) → linear-sRGB channel (0..1). */
    private static function srgbToLinear(float $channel): float
    {
        if ($channel <= 0.04045) {
            return $channel / 12.92;
        }

        return (($channel + 0.055) / 1.055) ** 2.4;
    }
}
