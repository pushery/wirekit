<?php

declare(strict_types=1);

namespace Pushery\WireKit\Theming;

use InvalidArgumentException;

/**
 * WCAG 2.1 contrast-ratio computation for WireKit theme tokens.
 *
 * Pure utility — no Laravel facade dependencies, no I/O. Given a CSS
 * color string, returns its relative luminance, and given a pair of strings,
 * returns their WCAG contrast ratio as a reader sees it.
 *
 * Scope: this helper covers the color formats WireKit itself emits and the
 * forms a browser hands back from `getComputedStyle` — `oklch(L C H)` and
 * `oklab(L a b)` (L as a decimal 0–1 or a percentage), hex in all four lengths,
 * `rgb()` / `rgba()` (both the comma and the modern space form),
 * `color(srgb …)`, `color-mix(in srgb, …)` and `transparent`, each with its
 * alpha. A translucent foreground is composited over its background before the
 * ratio is taken; a translucent background has no ratio of its own. Anything
 * else — named colors, `currentColor`, `color-mix` in a non-sRGB space, a
 * `color()` in another space — returns `null`, which means "unmeasurable, skip
 * this pairing" and MUST be treated as such (assert on `null`; do not cast to
 * float — `(float) null` reads as a false 0.00:1), never as a contrast failure.
 * unmeasurableReason() says which of the two it was.
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
     * The alpha channel is ignored here, as it always has been: a translucent color returns the
     * color it is made of. Every contrast question needs the alpha, and ratio() reads it through
     * parseToLinearRgba(). A fully transparent color has no color to return and is null, which is
     * also what the `transparent` keyword gave before this parser could read it.
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    public static function parseToLinearRgb(string $color): ?array
    {
        $rgba = self::parseToLinearRgba($color);

        if ($rgba === null || $rgba[3] <= 0.0) {
            return null;
        }

        return [$rgba[0], $rgba[1], $rgba[2]];
    }

    /**
     * Parse a CSS color string into linear sRGB plus alpha: [r, g, b, a], each in 0..1.
     * Returns null when the format, or its alpha, is unsupported.
     *
     * Reads #rgb, #rgba, #rrggbb and #rrggbbaa; oklch(L C H) with an optional `/ alpha`; rgb() and
     * rgba() in the comma form and the slash form; the `transparent` keyword; and
     * color-mix(in srgb, …), whose operands may themselves be translucent. An alpha is a number or
     * a percentage, and one this cannot read makes the whole color null: taken for opaque, it
     * would be the overestimate this parser exists to end.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function parseToLinearRgba(string $color): ?array
    {
        $color = trim($color);

        if (strcasecmp($color, 'transparent') === 0) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        // Hex form — #rgb, #rgba, #rrggbb, #rrggbbaa.
        if (preg_match('/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color, $m) === 1) {
            $hex = $m[1];
            if (strlen($hex) <= 4) {
                $hex = implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($hex)));
            }
            $channel = static fn (int $offset): float => hexdec(substr($hex, $offset, 2)) / 255.0;

            return [
                self::srgbToLinear($channel(0)),
                self::srgbToLinear($channel(2)),
                self::srgbToLinear($channel(4)),
                strlen($hex) === 8 ? $channel(6) : 1.0,
            ];
        }

        // OKLCH form: oklch(L C H) and oklch(L C H / alpha). L may be 0..1 decimal or N%.
        // C is a small decimal (typically 0..0.4). H is degrees (any value).
        if (preg_match('#^oklch\(\s*([^\s,)]+)\s+([^\s,)]+)\s+([^\s,)/]+)(?:\s*/\s*([^\s)]+))?\s*\)$#i', $color, $m) === 1) {
            $alpha = isset($m[4]) ? self::parseAlpha($m[4]) : 1.0;
            if ($alpha === null) {
                return null;
            }

            [$r, $g, $b] = self::oklchToLinearRgb(self::parseOklchL($m[1]), self::parseOklchC($m[2]), (float) $m[3]);

            return [$r, $g, $b, $alpha];
        }

        // OKLab form: oklab(L a b) and oklab(L a b / alpha). This is how a browser serializes a
        // color-mix(in oklab, …) — WireKit's own translucent text and rail tokens come back from
        // getComputedStyle in exactly this shape. L is 0..1 or N%; a and b are numbers, or N% of 0.4.
        if (preg_match('#^oklab\(\s*([^\s,)]+)\s+([^\s,)]+)\s+([^\s,)/]+)(?:\s*/\s*([^\s)]+))?\s*\)$#i', $color, $m) === 1) {
            $alpha = isset($m[4]) ? self::parseAlpha($m[4]) : 1.0;
            if ($alpha === null) {
                return null;
            }

            $axis = static fn (string $raw): float => str_ends_with($raw, '%') ? ((float) rtrim($raw, '%')) / 100.0 * 0.4 : (float) $raw;
            [$r, $g, $b] = self::oklabToLinearRgb(self::parseOklchL($m[1]), $axis($m[2]), $axis($m[3]));

            return [$r, $g, $b, $alpha];
        }

        // color(srgb r g b) and color(srgb r g b / alpha): how a browser serializes a
        // color-mix(in srgb, …), with channels 0..1 or N%. Another color space is not converted
        // here, and returns null rather than being read as sRGB.
        if (preg_match('#^color\(\s*srgb\s+([\d.]+%?)\s+([\d.]+%?)\s+([\d.]+%?)(?:\s*/\s*([^\s)]+))?\s*\)$#i', $color, $m) === 1) {
            $alpha = isset($m[4]) ? self::parseAlpha($m[4]) : 1.0;
            if ($alpha === null) {
                return null;
            }

            $channel = static fn (string $raw): float => max(0.0, min(1.0, str_ends_with($raw, '%') ? ((float) $raw) / 100.0 : (float) $raw));

            return [
                self::srgbToLinear($channel($m[1])),
                self::srgbToLinear($channel($m[2])),
                self::srgbToLinear($channel($m[3])),
                $alpha,
            ];
        }

        // color-mix(in srgb, <c1> [p1%], <c2> [p2%]) — the softened tinted surfaces (badge / alert /
        // stat) are built with color-mix, so without this arm every tinted background is
        // unauditable. Only `in srgb` is supported (the space WireKit uses); other interpolation
        // spaces return null rather than guess.
        if (preg_match('#^color-mix\(\s*in\s+srgb\s*,\s*(.+)\)$#is', $color, $m) === 1) {
            $parts = self::splitTopLevelComma($m[1]);
            if (count($parts) !== 2) {
                return null;
            }

            [$color1, $pct1] = self::parseMixOperand($parts[0]);
            [$color2, $pct2] = self::parseMixOperand($parts[1]);

            $first = self::parseToLinearRgba($color1);
            $second = self::parseToLinearRgba($color2);
            if ($first === null || $second === null) {
                return null;
            }

            // An omitted percentage takes the remainder; two omitted are a 50/50 mix (CSS default).
            $sum = 100.0;
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

            // Percentages adding up to less than 100% leave the result translucent by that much
            // (CSS Color 5's alpha multiplier); above 100% they are only normalized.
            $multiplier = $sum < 100.0 ? $sum / 100.0 : 1.0;

            // Premultiplied, as CSS mixes: each operand's channels count by its own alpha, so a
            // color mixed with `transparent` fades rather than darkening toward black. For two
            // opaque operands this is exactly the plain weighted blend it replaced.
            $alpha = $first[3] * $w1 + $second[3] * $w2;
            if ($alpha <= 0.0) {
                return [0.0, 0.0, 0.0, 0.0];
            }

            $mix = static fn (int $i): float => self::srgbToLinear(
                (self::linearToSrgb($first[$i]) * $first[3] * $w1 + self::linearToSrgb($second[$i]) * $second[3] * $w2) / $alpha
            );

            return [$mix(0), $mix(1), $mix(2), $alpha * $multiplier];
        }

        // rgb() / rgba() — getComputedStyle returns resolved colors in this form, so a form
        // control's live border or background read back from the DOM is auditable. Both the
        // legacy comma form (rgba(255, 0, 0, .5)) and the modern slash form (rgb(255 0 0 / 50%)).
        if (preg_match('#^rgba?\(\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*(?:[,/]\s*([\d.]+%?)\s*)?\)$#i', $color, $m) === 1) {
            $alpha = isset($m[4]) ? self::parseAlpha($m[4]) : 1.0;
            if ($alpha === null) {
                return null;
            }

            $channel = static fn (string $value): float => max(0.0, min(1.0, str_ends_with($value, '%')
                ? ((float) $value) / 100.0
                : ((float) $value) / 255.0));

            return [
                self::srgbToLinear($channel($m[1])),
                self::srgbToLinear($channel($m[2])),
                self::srgbToLinear($channel($m[3])),
                $alpha,
            ];
        }

        return null;
    }

    /**
     * An alpha value as 0..1, from a number or a percentage. Null for anything else.
     */
    private static function parseAlpha(string $token): ?float
    {
        if (preg_match('/^(\d*\.?\d+)(%?)$/', trim($token), $m) !== 1) {
            return null;
        }

        $value = (float) $m[1];
        if ($m[2] === '%') {
            $value /= 100.0;
        }

        return max(0.0, min(1.0, $value));
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
     * Returns a float >= 1.0 (1:1 = identical, 21:1 = max), or null if either input is in an
     * unsupported format, or if the BACKGROUND is translucent.
     *
     * A translucent foreground is laid over the background first, source-over in encoded sRGB,
     * the way a browser composites it. Measured as if it were opaque, 50% black on white read
     * 21:1 where a reader sees 3.98:1 — the dangerous direction, because nothing turns red. A
     * translucent background has no contrast of its own: what shows through depends on whatever
     * lies beneath it, which the two strings do not say, so it is refused rather than guessed.
     */
    public static function ratio(string $foreground, string $background): ?float
    {
        $fg = self::parseToLinearRgba($foreground);
        $bg = self::parseToLinearRgba($background);
        if ($fg === null || $bg === null || $bg[3] < 1.0) {
            return null;
        }

        $lFg = self::relativeLuminance($fg[3] < 1.0 ? self::compositeOver($fg, $bg) : [$fg[0], $fg[1], $fg[2]]);
        $lBg = self::relativeLuminance([$bg[0], $bg[1], $bg[2]]);
        $lighter = max($lFg, $lBg);
        $darker = min($lFg, $lBg);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Why ratio() returned null for this pair, or null when it would return a number.
     *
     * "translucent-background" and "unsupported-format" are different findings, and only the
     * second is about how a value is written. Reporting both as a format problem sends a
     * developer looking for a typo in a value that parsed perfectly well.
     *
     * @return 'translucent-background'|'unsupported-format'|null
     */
    public static function unmeasurableReason(string $foreground, string $background): ?string
    {
        $fg = self::parseToLinearRgba($foreground);
        $bg = self::parseToLinearRgba($background);

        if ($fg === null || $bg === null) {
            return 'unsupported-format';
        }

        return $bg[3] < 1.0 ? 'translucent-background' : null;
    }

    /**
     * Lay a translucent color over an opaque one: source-over, blended in encoded sRGB.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $top
     * @param  array{0: float, 1: float, 2: float, 3: float}  $under
     * @return array{0: float, 1: float, 2: float}
     */
    private static function compositeOver(array $top, array $under): array
    {
        $alpha = $top[3];
        $blend = static fn (int $i): float => self::srgbToLinear(
            self::linearToSrgb($top[$i]) * $alpha + self::linearToSrgb($under[$i]) * (1.0 - $alpha)
        );

        return [$blend(0), $blend(1), $blend(2)];
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

    /**
     * Grade a contrast ratio against WCAG 2.1: "AAA", "AA" or "fail".
     *
     * - text: AA at 4.5:1 (1.4.3), AAA at 7:1 (1.4.6)
     * - large-text: AA at 3:1 (1.4.3), AAA at 4.5:1 (1.4.6)
     * - ui — components, graphics, focus indicators: AA at 3:1 (1.4.11). WCAG 2.x defines no AAA
     *   level for non-text contrast, so the best a UI pairing can be is "AA".
     *
     * No warning band: that is classify()'s, and its callers rely on it. This answers the question
     * a page asks when it labels a pairing AA or AAA.
     *
     * @return 'AAA'|'AA'|'fail'
     *
     * @throws InvalidArgumentException for a use it does not know; grading it as text would answer a question nobody asked
     */
    public static function conformance(float $ratio, string $use = 'text'): string
    {
        [$aa, $aaa] = match ($use) {
            'text' => [4.5, 7.0],
            'large-text' => [3.0, 4.5],
            'ui' => [3.0, null],
            default => throw new InvalidArgumentException(sprintf('Unknown contrast use "%s". Expected text, large-text or ui.', $use)),
        };

        if ($aaa !== null && $ratio >= $aaa) {
            return 'AAA';
        }

        return $ratio >= $aa ? 'AA' : 'fail';
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
