<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Which prop spellings on the two shared semantic axes are the older ones, derived from the
 * templates rather than listed here.
 *
 * The kit grew three spellings for one color role — `intent` on button and badge, `variant`
 * on alert and callout, `tone` on feature — and two for one surface treatment, `surface` on
 * button against `variant` on card. Each of those components now declares the canonical name
 * ALONGSIDE the one it shipped with, and resolves the older through the newer. Both spellings
 * work, and both keep working for the whole of v2; what a reader needs is to be told which one
 * the kit calls canonical, once, at the moment they write the other.
 *
 * ⚠️ THE MAP IS DERIVED, AND THAT IS THE WHOLE DESIGN. A hand-written list of pairs is the half
 * of a rule that rots: it was measured against the tree on the day it was typed and never
 * again. Worse, it is wrong the first time — the resolution below finds `progress.circle` and
 * `timeline.item`, two sub-components that no reading of the top-level catalog turns up, and a
 * list assembled by eye would have silently covered seven of nine.
 *
 * The signal is behavioral rather than a comment: an older spelling is one the template reads
 * ONLY when the canonical one is absent — `$intent ?? $variant`. That distinguishes the pairs
 * from the components which declare both names for two GENUINELY different axes and validate
 * each on its own: `chat-marker` pairs a shape (`default | border | separator`) with a color,
 * and `theme-controller` pairs a shape (`button | dropdown | toggle`) with a surface. A rule
 * that keyed on "declares both" would report those two as legacy usage, which is not just
 * noise — it would tell a developer to replace a prop that has no replacement.
 */
final class LegacyAxisProps
{
    /**
     * The canonical spelling of each shared axis. A pair is only a pair when the name on the
     * LEFT of the `??` is one of these — `$foo ?? $bar` between two ordinary props is an
     * ordinary default, not a vocabulary decision.
     *
     * @var list<string>
     */
    private const CANONICAL = ['intent', 'surface'];

    /**
     * Every (component → legacy → canonical) pair in the shipped catalog.
     *
     * Keyed by the registry's own name for the component, so a sub-component appears as
     * `progress.circle` — the spelling a template writes and the spelling the scanner reads
     * back out of one.
     *
     * @return array<string, array<string, string>>
     */
    public static function map(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $base = __DIR__.'/../../resources/views/components/';
        $map = [];

        // Both levels. The top-level glob alone reaches 179 of 267 templates here, and the
        // two pairs it misses are exactly the ones a person misses too.
        $files = [
            ...(glob($base.'*.blade.php') ?: []),
            ...(glob($base.'*/*.blade.php') ?: []),
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match_all(
                '/\$('.implode('|', self::CANONICAL).')\s*\?\?\s*\$(\w+)/',
                $source,
                $matches,
                PREG_SET_ORDER
            ) === 0) {
                continue;
            }

            // The declared set is the filter that keeps this from reading an ordinary local
            // variable as a prop. `$intent ?? $fallbackFromSomewhere` is not a second spelling
            // of anything if the component never offered the second name to its caller.
            $declared = array_map(
                static fn (array $prop): string => $prop['name'],
                PropsParser::parseBlade($file)
            );

            $name = self::registryName($file);

            foreach ($matches as [, $canonical, $legacy]) {
                if ($canonical === $legacy) {
                    continue;
                }

                if (! in_array($canonical, $declared, true) || ! in_array($legacy, $declared, true)) {
                    continue;
                }

                $map[$name][$legacy] = $canonical;
            }
        }

        ksort($map);

        return $cache = $map;
    }

    /**
     * The older spelling a usage wrote, if it wrote one.
     *
     * @param  list<string>  $attributes  attribute names as written on the tag
     * @return array<string, string> legacy => canonical, empty when the usage is canonical
     */
    public static function findIn(string $component, array $attributes): array
    {
        $pairs = self::map()[$component] ?? [];

        if ($pairs === []) {
            return [];
        }

        $found = [];

        foreach ($attributes as $attribute) {
            // `BladeParser::extractWireKitComponentUsagesFromSource()` already strips Blade's
            // binding colon, so this is not doing that work a second time — it keeps the
            // method correct for a caller handing over raw tag attributes. A bound prop is the
            // same prop: `:variant="$x"` binds `variant`, and writing it that way is the same
            // vocabulary choice as writing the literal.
            $name = ltrim($attribute, ':');

            if (isset($pairs[$name])) {
                $found[$name] = $pairs[$name];
            }
        }

        return $found;
    }

    /**
     * `components/progress/circle.blade.php` → `progress.circle`, matching what a template
     * writes as `<x-wirekit::progress.circle>` and what the registry answers to.
     */
    private static function registryName(string $file): string
    {
        // Read off the tail rather than subtracting the base: the base is assembled with `..`
        // segments, so a string subtraction depends on a path shape that nothing guarantees.
        // The catalog is two levels deep, so the last two segments are the whole answer.
        $segments = explode('/', str_replace('\\', '/', $file));
        $leaf = (string) array_pop($segments);
        $parent = (string) array_pop($segments);
        $name = substr($leaf, 0, -strlen('.blade.php'));

        return $parent === 'components' ? $name : $parent.'.'.$name;
    }
}
