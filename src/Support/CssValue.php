<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Stringable;

/**
 * One value for one declaration in a `style` attribute, or nothing.
 *
 * A value written into a `style` attribute is CSS, and escaping does not stop a `;` there: a
 * string that carries a declaration of its own behind a length styles the element it lands on.
 * So a length, a ratio, a duration or a shade that a prop hands to a style is read as one value:
 * numbers and units, `%`, `/`, the arithmetic of `calc()`, the parentheses of `min()`, `var()` and
 * `color-mix()`, a `#` color and words. Nothing that ends a declaration or opens a block, no quote,
 * no backslash and no colon, so no `url()` with a scheme either. Colors in the forms the color
 * picker reads are CssColor's; this is the wider set a developer writes for a length.
 */
final class CssValue
{
    /** What a refused value is reported as having been expected. */
    private const EXPECTED = 'a single CSS value such as 20rem, 16/9, 1.5s or calc(100vh - 4rem)';

    /**
     * The value without surrounding whitespace, or '' when it is no single declaration value.
     */
    public static function of(string $value): string
    {
        $css = BrowserTrim::trim($value);

        if ($css === null || $css === '' || preg_match('/^[A-Za-z0-9.%+\-*\/(),#_ \t]+$/', $css) !== 1) {
            return '';
        }

        // Balanced, so a value cannot close a function it did not open.
        $depth = 0;

        foreach (str_split($css) as $char) {
            $depth += match ($char) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return '';
            }
        }

        return $depth === 0 ? $css : '';
    }

    /**
     * The value a prop hands to a style: as given where it is one declaration value, the fallback
     * where it is empty, and the fallback after a report through the strictness gate where it is
     * anything else.
     *
     * The value is `mixed` because a prop arrives as the call site wrote it: a written attribute is
     * a string, a bound one whatever the expression returns, a number among them.
     */
    public static function prop(mixed $value, string $component, string $prop, ?string $fallback = null): ?string
    {
        if ($value === null || $value === false) {
            return $fallback;
        }

        $text = NumericProp::text($value);

        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! $value instanceof Stringable) {
            StrictnessGate::reject($component, $prop, $text, self::EXPECTED, (string) $fallback);

            return $fallback;
        }

        if (trim($text) === '') {
            return $fallback;
        }

        $css = self::of($text);

        if ($css === '') {
            StrictnessGate::reject($component, $prop, $text, self::EXPECTED, (string) $fallback);

            return $fallback;
        }

        return $css;
    }
}
