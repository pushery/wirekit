<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Reads a Blade component prop that is meant as a number.
 *
 * Blade compiles an unbound attribute to a string, and a bound one passes whatever its
 * expression holds, often straight from data: `:max="$settings->scale"` can arrive as an empty
 * string, a text or `null`. PHP 8 throws on arithmetic with a string that holds no number, and it
 * compares an integer with such a string as text, so a loop `$i <= $max` over `'abc'` never ends.
 * A component reads its numeric props through this class, and a value that holds no number takes
 * the component's default.
 */
final class NumericProp
{
    /**
     * The prop as a number, or the default when it holds none.
     *
     * An integer, and a string holding one, stays an integer, so a component writes `100` where a
     * valid prop gave `100` before; any other numeric string becomes a float. A float that is not
     * finite counts as no number.
     */
    public static function from(mixed $value, int|float $default): int|float
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : $default;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            $trimmed = trim($value);
            $number = preg_match('/^[+-]?\d+$/', $trimmed) === 1 ? (int) $trimmed : (float) $trimmed;

            return is_float($number) && ! is_finite($number) ? $default : $number;
        }

        return $default;
    }

    /**
     * The prop as a number above zero, or the default when it holds none or none above zero: for
     * a maximum or a step, where zero divides by zero and a negative value turns a range around.
     */
    public static function positive(mixed $value, int|float $default): int|float
    {
        $number = self::from($value, $default);

        return $number > 0 ? $number : $default;
    }

    /**
     * The prop as a number, or null when it holds none: for a prop whose absence means something,
     * such as a start value that defaults to an end of the range.
     */
    public static function orNull(mixed $value): int|float|null
    {
        // NAN is the one float that is no number, so it marks "none" without a second check.
        $number = self::from($value, NAN);

        return is_float($number) && is_nan($number) ? null : $number;
    }
}
