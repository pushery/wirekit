<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Read and filter the utilities of a class string by what they set.
 *
 * Two utilities that set the same property under the same variants are not decided by their order
 * in the `class` attribute. The browser applies whichever rule comes later in the compiled
 * stylesheet, and Tailwind sorts those rules by value: `p-0` sorts before
 * `p-[var(--padding-wk-x-md)]`, so appending `p-0` to a padded block leaves the padding in place.
 * A component that wants one of two values therefore has to emit only that one. When the other
 * comes from a block a developer can personalize, the component cannot know what the block holds,
 * and these methods take the competing utilities out of it.
 *
 * Only utilities WITHOUT a variant are read. `hover:p-2` or `sm:h-6` applies in a state or at a
 * width of its own, so it does not compete with the base utility, and it stays.
 */
final class UtilityClasses
{
    /** Every padding utility: `p-*`, and the axis and side forms (`px-*`, `pt-*`, `ps-*` …). */
    public const PADDING = '/^p[xytrblse]?-/';

    /**
     * A background COLOR: an arbitrary value other than an image, a palette color, or one of the
     * keywords. `bg-cover`, `bg-center` or `bg-[url(…)]` set other properties and do not match.
     */
    public const BACKGROUND_COLOR = '/^bg-(?:\[(?!url\()|transparent$|current$|inherit$|white$|black$|[a-z]+-\d{2,3}(?:\/\d+)?$)/';

    /**
     * A text COLOR: `text-[color:…]`, an arbitrary `var(--color…)`, a palette color, or one of
     * the keywords. A size (`text-sm`, `text-[length:…]`) or an alignment does not match.
     */
    public const TEXT_COLOR = '/^text-(?:\[(?:color:|var\(--color)|(?:black|white|transparent|current|inherit)$|[a-z]+-\d{2,3}(?:\/\d+)?$)/';

    /**
     * The class string without the variant-free utilities whose name matches `$pattern`.
     *
     * The pattern is matched against the utility alone: an important marker (`!p-0`, `p-0!`) and the
     * minus sign of a negative value (`-mt-2`) are removed before it is read.
     */
    public static function without(string $classes, string $pattern): string
    {
        return implode(' ', array_filter(
            self::tokens($classes),
            static fn (string $token): bool => ! self::matches($token, $pattern),
        ));
    }

    /** Whether the class string carries a variant-free utility whose name matches `$pattern`. */
    public static function has(string $classes, string $pattern): bool
    {
        foreach (self::tokens($classes) as $token) {
            if (self::matches($token, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function tokens(string $classes): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($classes)) ?: [],
            static fn (string $token): bool => $token !== '',
        ));
    }

    private static function matches(string $token, string $pattern): bool
    {
        if (self::hasVariant($token)) {
            return false;
        }

        $utility = ltrim(trim($token, '!'), '-');

        return preg_match($pattern, $utility) === 1;
    }

    /**
     * Whether the token carries a variant: a colon outside brackets and parentheses. The colon in
     * `text-[color:var(--x)]` or `bg-[url(a:b)]` is part of the value, not a variant.
     */
    private static function hasVariant(string $token): bool
    {
        $depth = 0;

        foreach (str_split($token) as $char) {
            if ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth--;
            } elseif ($char === ':' && $depth === 0) {
                return true;
            }
        }

        return false;
    }
}
