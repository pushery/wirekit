<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Stringable;

/**
 * Reads a Blade component prop that is meant as a number.
 *
 * Blade compiles an unbound attribute to a string, and a bound one passes whatever its
 * expression holds, often straight from data: `:max="$settings->scale"` can arrive as an empty
 * string, a text or `null`. PHP 8 throws on arithmetic with a string that holds no number, and it
 * compares an integer with such a string as text, so a loop `$i <= $max` over `'abc'` never ends.
 * A component reads its numeric props through this class, and a value that holds no number takes
 * the component's default.
 *
 * A reader given the names of the component and the prop also reports, through the strictness
 * gate, a text that holds no number, as an unknown value of a prop with a fixed set is reported:
 * `max="1OO"` and `step="0,5"` are typing errors that would otherwise only show as a default. An
 * empty text is no value and is not reported, and neither is a value that is not text, which
 * comes from data rather than from an attribute. A component leaves the names out where the value
 * is what a reader sent, such as the value of a field after a failed validation.
 */
final class NumericProp
{
    private const A_NUMBER = 'a number such as 4.5';

    private const A_WHOLE_NUMBER = 'a whole number such as 3';

    /**
     * The prop as a number, or the default when it holds none.
     *
     * An integer, and a string holding one, stays an integer, so a component writes `100` where a
     * valid prop gave `100` before; any other numeric string becomes a float. A float that is not
     * finite counts as no number. With the names of the component and the prop, a text that holds
     * no number is reported first, and so it is by the readers below.
     */
    public static function from(mixed $value, int|float $default, ?string $component = null, ?string $prop = null): int|float
    {
        self::reportText(is_string($value) ? $value : null, $component, $prop, self::A_NUMBER, $default);

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
     * The prop as an integer, cut toward zero as `(int)` cuts it, or the default when it holds no
     * number or one no integer holds.
     *
     * `(int)` on a float that is not finite or lies beyond the integer range raises a warning in
     * PHP 8.5, and Laravel turns a warning into an exception that ends the page. A view that needs
     * an integer from a prop, a heading level or a count, reads it here instead of casting.
     *
     * `mixed`, for the reason from() takes it: the prop is whatever the attribute or the data holds.
     */
    public static function int(mixed $value, int $default, ?string $component = null, ?string $prop = null): int
    {
        self::reportText(is_string($value) ? $value : null, $component, $prop, self::A_WHOLE_NUMBER, $default);

        $number = self::from($value, $default);

        if (is_int($number)) {
            return $number;
        }

        return self::fitsAnInt($number) ? (int) $number : $default;
    }

    /**
     * The prop as an integer, cut toward zero as `(int)` cuts it, or null when it holds no number or
     * one no integer holds: for a count or a duration whose absence means something, which a view
     * would otherwise read as `is_numeric($value) ? (int) $value : null`, a cast PHP 8.5 warns
     * about for `NAN` and `INF`, both of which `is_numeric()` accepts.
     *
     * `mixed`, for the reason from() takes it.
     */
    public static function intOrNull(mixed $value, ?string $component = null, ?string $prop = null): ?int
    {
        self::reportText(is_string($value) ? $value : null, $component, $prop, self::A_WHOLE_NUMBER, null);

        $number = self::orNull($value);

        if ($number === null || is_int($number)) {
            return $number;
        }

        return self::fitsAnInt($number) ? (int) $number : null;
    }

    /**
     * The prop as `(int)` reads it, a text by the number it starts with (`300ms` is 300), or null
     * for a number no integer holds and for a list or an object: for a view that draws what the
     * cast draws and reports a text through check(), so this reports nothing itself. `(int)` on a
     * float beyond the integer range warns in PHP 8.5, which ends the page, and below 8.5 it gives
     * an unrelated number: `-1e19` becomes 8446744073709551616. A text that starts with such a
     * number reads as null as well, where `(int)` gives the largest integer.
     *
     * `mixed`, for the reason from() takes it.
     */
    public static function cast(mixed $value): ?int
    {
        if (is_int($value) || is_bool($value) || $value === null) {
            return (int) $value;
        }

        if (is_float($value)) {
            return self::fitsAnInt($value) ? (int) $value : null;
        }

        if (is_string($value)) {
            return self::fitsAnInt((float) trim($value)) ? (int) trim($value) : null;
        }

        return null;
    }

    /**
     * The prop as `(float)` reads it, a text by the number it starts with (`45%` is 45), for a view
     * that draws what the cast draws and reports a text through check(), so this reports nothing
     * itself. A `Stringable` reads as its text. A list or another object reads as the default,
     * since a list holds no number and `(float)` on an object ends the page.
     *
     * `mixed`, for the reason from() takes it.
     */
    public static function castFloat(mixed $value, float $default): float
    {
        if ($value instanceof Stringable) {
            return (float) $value->__toString();
        }

        return is_array($value) || is_object($value) ? $default : (float) $value;
    }

    /**
     * The prop as the text `(string)` writes for it, for a check against a list of allowed values
     * or a report: `NAN`, `INF` or `-INF` for a float that is not finite, without the warning PHP
     * 8.5 raises when one is cast, and the name of the type for a list or an object that is not
     * `Stringable`, which cannot be cast without one either.
     *
     * `mixed`, for the reason from() takes it.
     */
    public static function text(mixed $value): string
    {
        if (is_float($value) && ! is_finite($value)) {
            return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
        }

        return is_scalar($value) || $value === null || $value instanceof Stringable ? (string) $value : get_debug_type($value);
    }

    /**
     * The prop as a view may use it as an array key, for a lookup before it is validated. PHP casts
     * a float key to an integer, and for a float beyond the integer range PHP 8.5 warns about the
     * cast, which ends a request; such a float is given as its text, which matches no key and is
     * then reported as any unknown value is. Every other value is given back as it came: within
     * the integer range PHP casts a float key without a warning, so those lookups need nothing.
     */
    public static function key(mixed $value): mixed
    {
        return is_float($value) && ! self::fitsAnInt($value) ? self::text($value) : $value;
    }

    /**
     * The prop as a number above zero, or the default when it holds none or none above zero: for
     * a maximum or a step, where zero divides by zero and a negative value turns a range around.
     */
    public static function positive(mixed $value, int|float $default, ?string $component = null, ?string $prop = null): int|float
    {
        self::reportText(is_string($value) ? $value : null, $component, $prop, self::A_NUMBER, $default);

        $number = self::from($value, $default);

        return $number > 0 ? $number : $default;
    }

    /**
     * The prop as a number, or null when it holds none: for a prop whose absence means something,
     * such as a start value that defaults to an end of the range.
     */
    public static function orNull(mixed $value, ?string $component = null, ?string $prop = null): int|float|null
    {
        self::reportText(is_string($value) ? $value : null, $component, $prop, self::A_NUMBER, null);

        // NAN is the one float that is no number, so it marks "none" without a second check.
        $number = self::from($value, NAN);

        return is_float($number) && is_nan($number) ? null : $number;
    }

    /**
     * Two bounds in order. A minimum above the maximum would leave a field no valid value and a
     * slider no travel; read as written the two say "between these two", so they change places,
     * as two days do in CalendarLimits. Equal bounds are a fixed value, and a missing one stays
     * missing.
     *
     * @return array{0: int|float|null, 1: int|float|null}
     */
    public static function ordered(int|float|null $min, int|float|null $max): array
    {
        return $min !== null && $max !== null && $min > $max ? [$max, $min] : [$min, $max];
    }

    /**
     * A count of drawn elements, a star or a line each, as a whole number from none to `$cap`.
     * One element per unit of an unbounded count runs a request out of memory before the page is
     * sent, so a larger count is reported (StrictnessGate) and draws `$cap` of them. A count below
     * none draws none: `(int)` would turn a float below the integer range into a large positive
     * count, which is the count the ceiling exists to stop.
     */
    public static function capped(int|float $count, int $cap, string $component, string $prop): int
    {
        if ($count <= $cap) {
            return $count < 0 ? 0 : (int) $count;
        }

        StrictnessGate::reject($component, $prop, self::text($count), "a count of at most {$cap}", (string) $cap);

        return $cap;
    }

    /**
     * A number, or null when the value is none, as orNull(), and a value that is given but is no
     * number is reported through the strictness gate as well. Read as a number, "4,5" would be 4
     * and a word 0, so a component that shows the figure would show a wrong one without a trace.
     */
    public static function orNullReported(mixed $value, string $component, string $prop): int|float|null
    {
        $number = self::orNull($value);

        if ($number === null && ! blank($value)) {
            StrictnessGate::reject($component, $prop, is_scalar($value) ? self::text($value) : get_debug_type($value), 'a number such as 4.5', '');
        }

        return $number;
    }

    /**
     * Reports a text that holds no number, as a reader given the names does, and reads nothing:
     * for a view that reads its prop as PHP casts it, taking the number a text starts with. There
     * `(float) '45%'` is the 45 the page has always drawn, so the report says what the prop takes
     * without changing what it draws. `$whole` is for a view that casts with `(int)`.
     *
     * `mixed`, for the reason from() takes it.
     */
    public static function check(mixed $value, string $component, string $prop, bool $whole = false): void
    {
        if (! is_string($value)) {
            return;
        }

        $read = (float) trim($value);

        self::reportText($value, $component, $prop, $whole ? self::A_WHOLE_NUMBER : self::A_NUMBER, $whole && self::fitsAnInt($read) ? (int) $read : $read);
    }

    /**
     * Reports `$text` through the strictness gate when the names are given and it holds no
     * number: not empty, and not a numeric text that from() reads as a finite number.
     */
    private static function reportText(?string $text, ?string $component, ?string $prop, string $expected, int|float|null $fallback): void
    {
        if ($text === null || $component === null || $prop === null) {
            return;
        }

        $trimmed = trim($text);

        if ($trimmed === '' || (is_numeric($trimmed) && is_finite((float) $trimmed))) {
            return;
        }

        StrictnessGate::reject($component, $prop, $trimmed, $expected, $fallback === null ? '' : self::text($fallback));
    }

    /**
     * Whether `(int)` holds the float without the warning PHP 8.5 raises: the integer range as
     * floats, where PHP_INT_MAX rounds up to 2^63, so the bound above is exclusive, while -2^63 is
     * exact and included. NAN fails both comparisons.
     */
    private static function fitsAnInt(float $number): bool
    {
        return $number >= -9.2233720368547758E18 && $number < 9.2233720368547758E18;
    }
}
