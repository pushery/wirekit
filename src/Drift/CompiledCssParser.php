<?php

declare(strict_types=1);

namespace Pushery\WireKit\Drift;

/**
 * Parses Tailwind-generated CSS files and recovers the source-form class
 * names (e.g. `bg-[var(--color-wk-danger)]`) from the escape-encoded
 * selectors found in the compiled output (e.g. `.bg-\[var\(--color-wk-danger\)\]`).
 *
 * Used by the build-output diff to answer "did every source-emitted class
 * make it into the compiled CSS?". Without escape-aware decoding, the
 * compiled selectors can never be matched against the source-form class
 * inventory.
 *
 * Reference (CSS Selectors Level 4):
 *   - hex escape: `\HHHHHH` (1–6 hex digits, optionally followed by
 *     a single whitespace terminator)
 *   - single-char escape: `\X` where X is any character; produces X
 *
 * Edge cases handled:
 *   - `\32 xl:text-lg` → `2xl:text-lg` (digit leading needs hex escape)
 *   - `\[var\(--x\)\]`  → `[var(--x)]`
 *   - `hover\:bg-red`   → `hover:bg-red`
 *   - `md\:flex`        → `md:flex`
 *   - inside `@media`, `@supports`, `@keyframes` blocks (selectors are
 *     extracted regardless of containing at-rule)
 */
final class CompiledCssParser
{
    /**
     * Read a compiled CSS file and return the deduplicated list of
     * source-form class names found as selectors.
     *
     * @return list<string>
     */
    public static function extractGeneratedSelectors(string $cssPath): array
    {
        $contents = (string) file_get_contents($cssPath);

        return self::extractFromString($contents);
    }

    /**
     * Lower-level entry point for tests / fixture-driven scenarios.
     *
     * @return list<string>
     */
    public static function extractFromString(string $css): array
    {
        $css = self::stripComments($css);

        /*
         * Pre-pass: decode hex escapes (\HHHHHH with optional trailing
         * whitespace) on the WHOLE CSS before the selector match runs.
         * Without this, `\32 xl:text-lg` truncates at the space because
         * the selector regex treats whitespace as a selector boundary —
         * the space is actually the hex-escape terminator, not a delimiter.
         */
        $css = self::decodeHexEscapesInPlace($css);

        /*
         * Pre-pass 2: blank rule bodies (everything between `{` and `}`)
         * before the selector match. Without this, CSS property values
         * like `--spacing:.25rem` produce phantom "class selectors"
         * `.25rem` because the selector regex treats any dot not preceded
         * by a word character as the start of a class.
         *
         * Iterative — handles nested at-rules
         * (`@media (…) { .foo { … } }`) by collapsing the innermost body
         * first, then the outer.
         */
        $css = self::blankRuleBodies($css);

        /*
         * Pre-pass 3: blank function-call bodies. Without this,
         * `oklch(57.7% 0.245 27.325)` inside a SELECTOR string (e.g.
         * `.foo[data-color="oklch(50% 0 0)"]`) would still produce
         * phantom `.245` / `.325` matches. Tailwind v4 doesn't put
         * functions in selectors, but defending in depth is cheap.
         */
        $css = self::blankFunctionBodies($css);

        $pattern = '/(?<![\w-])\.((?:\\\\.|[^\s,;>+~\[(){}:.\\\\])+)/u';

        if (preg_match_all($pattern, $css, $matches) === false) {
            return [];
        }

        $decoded = [];
        foreach ($matches[1] as $rawSelector) {
            $name = self::decodeSingleCharEscapesInPlace($rawSelector);
            if ($name === '') {
                continue;
            }

            /*
             * Defensive: pure-numeric "selectors" are impossible in valid
             * Tailwind output (digit-leading classes get hex-escaped). If
             * one slips through, drop it — it's a fragment of a function-
             * argument numeric literal that the blanking pass missed.
             */
            if (preg_match('/^\d+$/', $name) === 1) {
                continue;
            }

            $decoded[$name] = true;
        }

        return array_keys($decoded);
    }

    /**
     * Replace every CSS function-call body (`name(...)`) with empty parens
     * so numeric / color / gradient arguments don't surface as phantom
     * selectors. Iterative — handles nested calls like
     * `color-mix(in oklab, oklch(...), transparent)`.
     */
    private static function blankFunctionBodies(string $css): string
    {
        $previous = '';
        while ($previous !== $css) {
            $previous = $css;
            $css = preg_replace('/([a-z][a-z0-9-]*)\(\s*[^()]*\s*\)/i', '$1()', $css) ?? $css;
        }

        return $css;
    }

    /**
     * Replace every CSS rule body (`{...}`) with `{}`. Iterative so nested
     * at-rules collapse from the inside out.
     *
     *   `@media (…) { .foo { color: red; } }` → `@media (…) { .foo {} }`
     *                                          → `@media (…) {}`
     *
     * Selector text remains intact for downstream extraction.
     */
    private static function blankRuleBodies(string $css): string
    {
        $previous = '';
        while ($previous !== $css) {
            $previous = $css;
            $css = preg_replace('/\{[^{}]*\}/', '{}', $css) ?? $css;
        }

        return $css;
    }

    /**
     * Decode CSS escape sequences in a selector body back to source-form.
     * Public helper used directly by unit tests; in extractFromString()
     * the two passes are split so the pre-pass can run on the whole CSS.
     */
    public static function decodeCssEscapes(string $escaped): string
    {
        return self::decodeSingleCharEscapesInPlace(
            self::decodeHexEscapesInPlace($escaped),
        );
    }

    /**
     * Pass 1 — hex escapes (\HHHHHH with optional trailing whitespace).
     */
    private static function decodeHexEscapesInPlace(string $input): string
    {
        return preg_replace_callback(
            '/\\\\([0-9a-fA-F]{1,6})[ \t\n]?/',
            fn (array $m) => mb_chr((int) hexdec($m[1]), 'UTF-8') ?: '',
            $input,
        ) ?? $input;
    }

    /**
     * Pass 2 — single-char escapes (\X → X). Removes the leading
     * backslash so `\[` becomes `[`, `\:` becomes `:`, etc.
     */
    private static function decodeSingleCharEscapesInPlace(string $input): string
    {
        return preg_replace('/\\\\(.)/u', '$1', $input) ?? $input;
    }

    /**
     * Strip /* … *\/ comments. Tailwind compiles a "Generated by Tailwind"
     * banner that is harmless but adds noise to the parse if left in.
     */
    private static function stripComments(string $css): string
    {
        return preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
    }
}
