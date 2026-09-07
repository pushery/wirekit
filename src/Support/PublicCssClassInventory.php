<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every `wk-*` class WireKit exposes to a developer, from the two places it emits them.
 *
 * The Public CSS API catalog on the documentation site, the drift guard that holds the
 * catalog and the shipped stylesheet in lockstep, and the `css-classes` group of
 * `wirekit:export-api-map` all answer the same question: which `wk-*` identifiers are a
 * public contract? Answering it takes a scan with several learned exclusions, and the
 * scan lived in two hand-kept copies — one of which never received them.
 *
 * ⚠️ That is why this class exists, and the divergence is worth naming: the copy behind
 * the exported manifest advertised ten identifiers as Stable public CSS classes that are
 * emitted as classes nowhere — five morph identities, four DOM-id prefixes and one
 * Tailwind named group. The manifest is a machine-readable discovery surface, so the
 * failure is silent in both directions: a class that does not exist raises no error, and
 * the guard stayed green because it read its own copy of the scan rather than the export.
 * Deleting the second copy is the fix; keeping two regexes in step is not one.
 *
 * TWO EMISSION PATHS, both public:
 *
 *   1. `dist/wirekit.css` — literal `.wk-…` selectors carrying styling rules
 *      (`wk-stagger`, `wk-reading-spine__tick`).
 *   2. `resources/views/components/**` — class names written as static strings in Blade.
 *      Many carry no CSS rule at all: their job is to be an identity marker a developer
 *      can carve out of their own typography styles, e.g.
 *      `.prose :not([class*="wk-"]) { … }`.
 */
final class PublicCssClassInventory
{
    /**
     * The sorted, de-duplicated inventory for a package root.
     *
     * @return list<string>
     */
    public static function forPackage(string $packageRoot): array
    {
        $classes = [];

        foreach (self::fromCompiledCss($packageRoot.'/dist/wirekit.css') as $class) {
            $classes[$class] = true;
        }

        foreach (self::fromBladeTemplates($packageRoot.'/resources/views/components') as $class) {
            $classes[$class] = true;
        }

        $list = array_keys($classes);
        sort($list);

        return $list;
    }

    /**
     * Literal `.wk-…` selectors in the compiled stylesheet.
     *
     * Comments are stripped first, so an illustrative `.wk-animate-{name}` placeholder
     * inside a docblock cannot surface as a class the package does not ship.
     *
     * @return list<string>
     */
    private static function fromCompiledCss(string $stylesheet): array
    {
        if (! is_file($stylesheet)) {
            return [];
        }

        $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($stylesheet));

        preg_match_all(
            '/(?<=^|\s|,)\.(\bwk-[a-z][a-z0-9_-]*(?:__[a-z][a-z0-9_-]*)?(?:--[a-z][a-z0-9_-]*)?)\b/m',
            $css,
            $matches
        );

        return $matches[1];
    }

    /**
     * Static-string `wk-…` tokens in the component templates.
     *
     * The scan is deliberately wider than `class="…"` — it has to reach `:class` bindings
     * and Alpine expressions — which is exactly why it cannot tell a class apart from the
     * other things that carry the house prefix. Each exclusion below is one of those,
     * learned from an identifier that was published as a public class and styles nothing.
     *
     * @return list<string>
     */
    private static function fromBladeTemplates(string $componentsDirectory): array
    {
        if (! is_dir($componentsDirectory)) {
            return [];
        }

        $classes = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($componentsDirectory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // A `wire:key` VALUE is a morph identity, never a CSS class — it appears in no
            // stylesheet and styles nothing. Blanking the VALUE rather than allowlisting the
            // name keeps the exclusion tied to the attribute: a real class sitting beside a
            // `wire:key` is still captured, and the next key needs no new entry.
            $source = (string) preg_replace('/(wire:key\s*=\s*)(["\']).*?\2/s', '$1$2$2', $source);

            // Same reasoning, second shape: the first argument of `WireKit::stableId(…)` is a
            // DOM-ID PREFIX. The scan already carves out the older way of building one —
            // `'wk-slider-' . $name`, caught by its trailing hyphen — but stableId's prefix
            // carries no hyphen, because the method adds the separator itself. So
            // `stableId('wk-tabs', …)` reads as a public class named `wk-tabs`, which is
            // emitted nowhere.
            $source = (string) preg_replace('/(stableId\s*\(\s*)(["\']).*?\2/s', '$1$2$2', $source);

            // The negative lookbehind excludes `--wk-…` CSS variables AND Tailwind named-group
            // / peer identifiers (`group/wk-profile`, `peer/wk-…`): a `/`-prefixed token is a
            // group NAME, not a class. The positive terminator class keeps an interpolated
            // `{$x}` continuation from being captured.
            preg_match_all(
                '/(?<![-a-z0-9\/])(\bwk-[a-z][a-z0-9_-]*(?:__[a-z][a-z0-9_-]*)?(?:--[a-z][a-z0-9_-]*)?)(?=[\s\'">,])/',
                $source,
                $matches
            );

            foreach ($matches[1] as $class) {
                // An identifier ending in `-` is a concatenation fragment such as
                // `'wk-slider-' . $name`, which builds a DOM id rather than a class.
                if (str_ends_with($class, '-')) {
                    continue;
                }

                $classes[] = $class;
            }
        }

        return $classes;
    }
}
