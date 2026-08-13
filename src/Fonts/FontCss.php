<?php

declare(strict_types=1);

namespace Pushery\WireKit\Fonts;

/**
 * Reads and rewrites the `font-display` of a bundled family's stylesheet.
 *
 * The bundled CSS files are static: they ship with `swap`, which is the right
 * default because every family also ships a metric-matched fallback face, so
 * the swap costs no layout shift. An application that would rather risk never
 * showing the web font than show it late wants `optional` — a real trade, and
 * one the application makes rather than the library.
 *
 * Static files cannot read config, so the value is substituted by whoever hands
 * the CSS over: the package route while serving it, and `wirekit:publish-fonts`
 * while writing it. A raw `vendor:publish` is a framework-side file copy and
 * reaches neither, which is why {@see declaredDisplays()} exists — the drift it
 * exposes is reported instead of being left to be discovered by not happening.
 */
final class FontCss
{
    /**
     * The `font-display` values CSS defines. Anything else is a typo, and a
     * typo written into every @font-face would disable the property entirely.
     */
    public const VALID = ['auto', 'block', 'swap', 'fallback', 'optional'];

    public const DEFAULT = 'swap';

    /**
     * The configured value, or the default when it is absent or not a real
     * `font-display` keyword.
     *
     * An unknown value is corrected rather than written through: `swap` is what
     * the files already carry, so falling back to it means a typo changes
     * nothing instead of silently turning off font loading behavior. The doctor
     * reports the typo separately — this method's job is to never emit invalid
     * CSS.
     */
    public static function display(): string
    {
        $configured = config('wirekit.fonts.display', self::DEFAULT);

        if (! is_string($configured)) {
            return self::DEFAULT;
        }

        $normalized = strtolower(trim($configured));

        return in_array($normalized, self::VALID, true) ? $normalized : self::DEFAULT;
    }

    /**
     * Rewrite every `font-display` declaration in the stylesheet.
     *
     * Only existing declarations are replaced; none are inserted. That matters
     * for the generated fallback face, which deliberately carries no
     * `font-display` at all — its `src` is `local()` only, so there is no
     * download to sequence and the property would describe nothing.
     */
    public static function applyDisplay(string $css, ?string $display = null): string
    {
        $display ??= self::display();

        if (! in_array($display, self::VALID, true)) {
            $display = self::DEFAULT;
        }

        return (string) preg_replace(
            '/font-display\s*:\s*[a-z-]+\s*;/i',
            "font-display: {$display};",
            $css,
        );
    }

    /**
     * Every distinct `font-display` value the stylesheet declares.
     *
     * Returns an empty array for a stylesheet that declares none — which is not
     * the same as one that disagrees, and callers must not collapse the two.
     *
     * @return list<string>
     */
    public static function declaredDisplays(string $css): array
    {
        preg_match_all('/font-display\s*:\s*([a-z-]+)\s*;/i', $css, $matches);

        return array_values(array_unique(array_map(
            static fn (string $value): string => strtolower($value),
            $matches[1],
        )));
    }

    /**
     * What the publisher writes for a file in a family's directory.
     *
     * One shared closure rather than two similar ones, because
     * `wirekit:publish-fonts` and `wirekit:verify` have to agree on what a
     * current published copy looks like. If they disagreed, the doctor would
     * report drift the publisher just finished removing.
     *
     * @return \Closure(string, string): string
     */
    public static function publishTransform(): \Closure
    {
        $display = self::display();

        return static fn (string $relativePath, string $contents): string => str_ends_with(strtolower($relativePath), '.css')
            ? self::applyDisplay($contents, $display)
            : $contents;
    }

    /**
     * Whether a published stylesheet still matches the configured value.
     *
     * A file declaring nothing is reported as matching: there is no promise to
     * break. The failure this answers is the narrow one — a published copy that
     * says `swap` while the config says `optional`, which is the shape a plain
     * `vendor:publish` leaves behind.
     */
    public static function matchesConfiguredDisplay(string $css, ?string $display = null): bool
    {
        $display ??= self::display();
        $declared = self::declaredDisplays($css);

        return $declared === [] || $declared === [$display];
    }
}
