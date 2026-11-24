<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Pushery\WireKit\VariantResolver;

/**
 * The class lists the compact sheet declares under one short name each.
 *
 * Compact classes give every utility of the kit a short name. A part that repeats in every row
 * of a long table still writes each of its utilities, a button some thirty of them. A bundle
 * stands for a fixed list of them: the compact sheet lists its short name beside the short name
 * of each utility it stands for, in that utility's rule, so the bundle carries exactly their
 * declarations at their places in Tailwind's order, and an element that carries the bundle
 * instead of the list looks the same.
 *
 * The lists are read from what the kit renders, not written a second time: a button's base block
 * is the classes the root of every plain button carries, whatever its intent, surface and size,
 * and the classes of an intent on a surface are what VariantResolver returns, without the base
 * block's. The package's build writes them into resources/catalog/compact-bundles.php and gives
 * each a short name in the compact sheet.
 *
 * @internal Build tooling of the package. Not a developer-facing API.
 */
final class CompactBundles
{
    /** The sizes the base block is read across: a size changes the classes of its own block only. */
    private const SIZES = ['xs', 'sm', 'md-compact', 'md', 'lg', 'xl'];

    /**
     * Each bundle by name, with the utilities it stands for. The base blocks come first: a class
     * list that holds one of them and the classes of a variant gives the base block's utilities to
     * the base block.
     *
     * @return array<string, list<string>>
     */
    public static function definitions(): array
    {
        $bundles = [
            'button-base' => self::buttonBase(false),
            'button-base-wrap' => self::buttonBase(true),
        ];

        $base = array_merge($bundles['button-base'], $bundles['button-base-wrap']);

        foreach (VariantResolver::INTENTS as $intent) {
            foreach (VariantResolver::SURFACES as $surface) {
                $bundles["variant-{$intent}-{$surface}"] = array_values(array_diff(self::tokens(VariantResolver::resolve($intent, $surface)), $base));
            }
        }

        return array_filter($bundles, static fn (array $classes): bool => count($classes) > 1);
    }

    /**
     * The classes every plain button's root carries, whatever its intent, surface and size, without
     * the kit's markers. Read in their long names whatever the switch says: under compact classes a
     * render carries short names and bundles, which `CompactClasses::spelledOut()` writes out again,
     * so the switch is never changed here.
     *
     * @return list<string>
     */
    private static function buttonBase(bool $wrapLabel): array
    {
        $common = null;

        foreach (VariantResolver::INTENTS as $intent) {
            foreach (VariantResolver::SURFACES as $surface) {
                foreach (self::SIZES as $size) {
                    $html = Blade::render(
                        '<x-wirekit::button :intent="$intent" :surface="$surface" :size="$size" :wrap-label="$wrap">Label</x-wirekit::button>',
                        ['intent' => $intent, 'surface' => $surface, 'size' => $size, 'wrap' => $wrapLabel],
                    );
                    $root = HTMLDocument::createFromString('<!DOCTYPE html><body>'.$html, LIBXML_NOERROR, 'UTF-8')->querySelector('button');
                    $classes = self::tokens(CompactClasses::spelledOut((string) $root?->getAttribute('class')));
                    $common = $common === null ? $classes : array_values(array_intersect($common, $classes));
                }
            }
        }

        // A marker of the kit's own stylesheet is no utility, and keeps its name on the element.
        return array_values(array_filter($common, static fn (string $class): bool => ! str_starts_with($class, 'wk-')));
    }

    /** @return list<string> */
    private static function tokens(string $classes): array
    {
        return array_values(array_unique(preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }
}
