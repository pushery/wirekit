<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * Whether a caller's attribute bag writes an attribute onto the element it is printed on.
 *
 * A component that writes an attribute of its own beside the bag writes it only where the bag does
 * not, so no start tag carries the attribute twice. The bag can hold a key it never writes: Blade
 * leaves out a `null` or `false` value when it prints the bag, and a component that passes a value of
 * its own on to a child with `:aria-labelledby="$given"` hands over `null` where there is none. Such a
 * key is not the caller's, and the component's own value stands.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class CallerAttribute
{
    /**
     * The attributes for which `false` or a blank text names nothing either. Blade prints nothing
     * for `false`, and the browser treats an empty name, an empty list of ids or an empty live
     * setting as if the attribute were not there, while the component's own value is gone. No
     * component takes either from them as a value, the way some hand `spellcheck` to a script.
     *
     * @var list<string>
     */
    private const NOTHING_WHEN_FALSE_OR_BLANK = ['aria-label', 'aria-labelledby', 'aria-describedby', 'aria-live'];

    public static function writes(ComponentAttributeBag $attributes, string $key): bool
    {
        $value = $attributes->get($key);

        return $value !== null && $value !== false;
    }

    /**
     * Drop every attribute the caller bound to `null`, in place, so the bag a view reads holds what
     * Blade prints. A key with `null` printed nothing and still answered `has()` and won over a
     * default in `merge()`, so `:aria-label="$label"` with no label took the component's own name
     * away and put none in its place.
     *
     * A name, a description or a live region bound to `false` or given as a blank text goes as
     * well, so `:aria-label="$section->title"` over a record without a title leaves the component
     * its own name. Elsewhere `false` and an empty text stay: some components hand an attribute
     * such as `spellcheck` on to their script, where `false` is a value, and `alt=""` is one too.
     *
     * The tag hands each attribute to the view's data as well, under its camelCase name, and
     * `@props` reads a prop's value there: `aria-label` reaches a prop `ariaLabel` that way. What
     * was dropped comes back, so that the view's data can lose it too.
     *
     * @return array<string, mixed> The attributes dropped, by name.
     */
    public static function normalize(ComponentAttributeBag $attributes): array
    {
        $all = $attributes->getAttributes();
        $kept = array_filter(
            $all,
            static fn (mixed $value, string|int $key): bool => $value !== null
                && ! (($value === false || (is_string($value) && trim($value) === ''))
                    && in_array($key, self::NOTHING_WHEN_FALSE_OR_BLANK, true)),
            ARRAY_FILTER_USE_BOTH,
        );

        if (count($kept) === count($all)) {
            return [];
        }

        $attributes->setAttributes($kept);

        return array_diff_key($all, $kept);
    }
}
