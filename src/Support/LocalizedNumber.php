<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * Formats a number for the active application locale.
 *
 * PHP's `number_format()` always groups the English way: `1,000`. In every
 * locale that groups with a period or a space (de `1.000`, fr `1 000`,
 * it/es/pt `1.000`) its readout would come out wrong — directly beside labels
 * the same component had just translated correctly. Half-localized output
 * reads as a defect rather than a missing locale.
 *
 * Two things this deliberately does NOT do:
 *
 * 1. It does not reach for `Number::format()` unconditionally. That helper
 *    throws when `ext-intl` is absent, and WireKit does not require the
 *    extension — making it a hard dependency would break every application
 *    without it. Without intl the output is `number_format()`'s.
 * 2. It does not rely on `Number::format()` picking up the locale by itself.
 *    Its locale is a static on the class, defaulting to `en`, and the framework
 *    never wires it to `App::getLocale()` — so the locale is passed explicitly.
 *    Omitted, it would change nothing.
 */
final class LocalizedNumber
{
    /**
     * The application's own way of writing a number, or null for the locale's.
     *
     * @var (Closure(float, ?int, ?int): string)|null
     */
    private static ?Closure $formatter = null;

    /**
     * Give WireKit the application's own way of writing a plain number. Every number WireKit
     * formats on the server goes through it from then on: the summary of a pagination, the values
     * a chart's tooltip names beside a series, the readouts of its point links. A percentage and an
     * amount in a currency keep the locale's way of writing them, where the sign stands and the space
     * beside it, and take the grouping mark and the decimal mark this formatter writes
     * (`separators()`). A chart writes its values in the browser, and takes the same two marks.
     *
     * The formatter receives the number, the exact number of decimals asked for (trailing zeros
     * kept) and the most decimals asked for (trailing zeros dropped); both are null for a figure
     * that keeps its own decimals, up to three and without zeros at the end, as the locale writes
     * it, and a whole number comes with `0` decimals. `null` restores the locale's way. Registered
     * once, in a service provider's `boot()`, it applies to every request.
     *
     * @param  (callable(float, ?int, ?int): string)|null  $formatter
     */
    public static function formatUsing(?callable $formatter): void
    {
        self::$formatter = $formatter === null ? null : Closure::fromCallable($formatter);
    }

    /**
     * Whether the application gave WireKit its own way of writing a number.
     */
    public static function hasFormatter(): bool
    {
        return self::$formatter !== null;
    }

    /**
     * A whole number, a count or a position, the way the application writes one: through its own
     * formatter where it gave one, else grouped the locale's way (`10.514` in German). Without intl
     * the digits stay ungrouped, since the English grouping beside a page in another language would
     * read as a decimal mark.
     */
    public static function integer(int $value, ?bool $intlAvailable = null): string
    {
        if (self::$formatter !== null) {
            return (self::$formatter)((float) $value, 0, null);
        }

        $intlAvailable ??= extension_loaded('intl');

        return $intlAvailable ? self::format((float) $value, precision: 0, intlAvailable: true) : (string) $value;
    }

    /**
     * A count as the reader sees it, the number in a badge or beside a column's title: a whole
     * number is grouped as integer() groups it, `12.500` in German. Anything else comes back as it
     * is: a word such as `New` or `99+`, a number with a fraction, markup, and digits with a leading
     * zero, which name something rather than count it. A slot that holds a whole number and nothing
     * else counts as one; a slot with anything more, a Livewire marker around a condition among it,
     * is markup and stays as written.
     */
    public static function count(Htmlable|Stringable|string|int|float|bool|null $value): Htmlable|string
    {
        if (is_int($value)) {
            return self::integer($value);
        }

        // A float that is not finite comes back as the text PHP writes for it, `NAN` or `INF`, without
        // the warning PHP 8.5 raises when one is cast.
        $text = match (true) {
            $value instanceof Htmlable => trim($value->toHtml()),
            is_bool($value) => $value ? '1' : '',
            default => NumericProp::text($value),
        };

        if (preg_match('/^-?\d+$/', $text) === 1 && (string) (int) $text === $text) {
            return self::integer((int) $text);
        }

        return $value instanceof Htmlable ? $value : $text;
    }

    /**
     * The text of a badge as count() writes it, or null where there is nothing to draw: null,
     * false, a text of whitespace, and a slot that holds nothing but whitespace and comments, the
     * markers Livewire writes around a condition among them. A component draws its pill only around
     * text, so `:badge="false"` draws nothing, as an empty badge does, and `0` stays a count.
     */
    public static function countOrNull(Htmlable|Stringable|string|int|float|bool|null $value): Htmlable|string|null
    {
        if ($value === null || $value === false) {
            return null;
        }

        $text = self::count($value);
        $html = $text instanceof Htmlable ? $text->toHtml() : $text;

        return trim((string) preg_replace('/<!--.*?-->/s', '', $html)) === '' ? null : $text;
    }

    /**
     * A number on a scale, the value of a slider or the label of one of its marks: the decimal mark
     * the locale writes, and no grouping. A scale may count years, and `2.026` reads as neither.
     * At most `$maxPrecision` decimals with trailing zeros dropped, or exactly `$precision` with them
     * kept. An application's own formatter gives the decimal mark. The browser writes the same with
     * `scaleText()` in `resources/js/utils/locale-number.js`.
     */
    public static function scale(float $value, ?int $precision = null, ?int $maxPrecision = null, ?string $locale = null, ?bool $intlAvailable = null): string
    {
        $decimal = self::separators()['decimal'] ?? null;

        if ($decimal === null && ($intlAvailable ?? extension_loaded('intl'))) {
            $formatter = new \NumberFormatter(IcuLocale::usable($locale), \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);

            if ($maxPrecision !== null) {
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
            } elseif ($precision !== null) {
                $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $precision);
            }

            $formatted = self::unsignedZero($value, static fn (float $number): string|false => $formatter->format($number));

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $decimal ??= '.';
        $formatted = number_format($value, $maxPrecision ?? $precision ?? 0, $decimal, '');

        // At most, not exactly: the zeros after the last digit go, and a decimal mark left alone.
        if ($maxPrecision !== null && str_contains($formatted, $decimal)) {
            $formatted = (string) preg_replace('/'.preg_quote($decimal, '/').'$/u', '', rtrim($formatted, '0'));
        }

        return $formatted;
    }

    /**
     * The grouping mark and the decimal mark the application writes, read off its formatter: what
     * stands between the digits of a seven-digit number with one decimal. Null without a
     * formatter, and when what it writes for that number shows no decimal mark to read.
     *
     * @return array{group: string, decimal: string}|null
     */
    public static function separators(): ?array
    {
        if (self::$formatter === null) {
            return null;
        }

        $written = (self::$formatter)(1234567.5, 1, null);

        if (preg_match('/^\D*1(\D*)234(\D*)567(\D+)5\D*$/u', $written, $marks) !== 1 || $marks[1] !== $marks[2]) {
            return null;
        }

        return ['group' => $marks[1], 'decimal' => $marks[3]];
    }

    /**
     * Argument order mirrors `Number::format()` so the two read the same at a
     * call site.
     *
     * @param  int|null  $precision  Exactly this many decimals, trailing zeros KEPT
     *                               (`2.0` stays `2.0` — a file size reads as "2.0 KB").
     * @param  int|null  $maxPrecision  At most this many decimals, trailing zeros DROPPED
     *                                  (`4.0` becomes `4` — a rating reads as "4", not "4.0").
     *                                  With neither, at most three, the locale's own default.
     * @param  string|null  $locale  Defaults to the active application locale.
     * @param  bool|null  $intlAvailable  Test seam — production callers omit it. The
     *                                    intl-less path is a real shipped behavior, so it
     *                                    has to be provable without unloading an extension.
     */
    public static function format(
        float $value,
        ?int $precision = null,
        ?int $maxPrecision = null,
        ?string $locale = null,
        ?bool $intlAvailable = null,
    ): string {
        if (self::$formatter !== null) {
            return (self::$formatter)($value, $precision, $maxPrecision);
        }

        $intlAvailable ??= extension_loaded('intl');

        if (! $intlAvailable) {
            return self::withoutIntl($value, $precision, $maxPrecision);
        }

        // `Number::format()`, with one difference: a tie rounds half away from zero, as the text
        // without intl and the browser's `Intl.NumberFormat` round it. ICU's own default, half to
        // even, would write `2` for 2.5 where both of them write `3`.
        $formatter = new \NumberFormatter(IcuLocale::usable($locale), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        if ($maxPrecision !== null) {
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        } elseif ($precision !== null) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $precision);
        }

        $formatted = self::unsignedZero($value, static fn (float $number): string|false => $formatter->format($number));

        return $formatted === false ? self::withoutIntl($value, $precision, $maxPrecision) : $formatted;
    }

    /**
     * An amount in a currency, the way the active locale writes it: `1.234,56 €` in German,
     * `€1,234.56` in English.
     *
     * Rounded half away from zero, the way a till and an invoice round: `0.125` reads `0,13 €` and
     * `-0.125` reads `-0,13 €`. ICU's own default is half to even, which would write `0,12 €`, while
     * PHP's `number_format()` and with it the text without intl below round the other way.
     *
     * `$maxPrecision` lets an amount show more decimals than its currency has, as a price per piece
     * needs: at least the currency's own, at most this many, and trailing zeros dropped down to the
     * currency's. In euros with `4`, `0.045` reads `0,045 €` and `12.5` still reads `12,50 €`; an
     * amount in yen keeps no decimals unless it has some. A value below the currency's digits takes
     * nothing away from them.
     *
     * Without intl there is no table of currency symbols and their positions to read, so the
     * ISO code follows the number, grouped the way `format()` groups without intl:
     * `1,234.56 EUR`. The amount stays exact and the currency unambiguous, which is what a price
     * has to be; the symbol and where it goes are the part the extension adds. Without the
     * extension a currency's own digits are not known either, so the text keeps two, or more up
     * to `$maxPrecision`.
     *
     * A currency the formatter has no code for, such as a symbol given where a code belongs, is
     * written in the locale's own pattern for an amount with its text where the symbol stands, and
     * two decimals or up to `$maxPrecision`: `€` then reads as `EUR` does, `1.234,50 €` in German,
     * and not as the text without intl writes it, `1,234.50 €`, which a German reader takes for
     * another number. That text stands in only where the formatter refuses the amount as well.
     * Where the application gave its own formatter, the grouping mark and the decimal mark are the
     * ones it writes, with intl and without it.
     *
     * @param  string  $currency  An ISO 4217 code, `EUR`.
     * @param  int|null  $maxPrecision  The most decimals to show, never fewer than the currency's.
     */
    public static function currency(
        float $amount,
        string $currency,
        ?string $locale = null,
        ?bool $intlAvailable = null,
        ?int $maxPrecision = null,
    ): string {
        $intlAvailable ??= extension_loaded('intl');
        $plain = self::plainWithTheApplicationsMarks(self::currencyWithoutIntl($amount, $currency, $maxPrecision));

        if (! $intlAvailable) {
            return $plain;
        }

        $formatter = new \NumberFormatter(IcuLocale::usable($locale), \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
        self::markWithTheApplicationsMarks($formatter);

        // The currency's own digits, two for the euro and none for the yen, are the formatter's once
        // it is set to that currency. The precision asked for adds to them and never takes away.
        if ($maxPrecision !== null && $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency)) {
            $digits = (int) $formatter->getAttribute(\NumberFormatter::FRACTION_DIGITS);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $digits);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, max($digits, $maxPrecision));
        }

        $formatted = self::unsignedZero($amount, static fn (float $number): string|false => $formatter->formatCurrency($number, $currency));
        $formatted = $formatted === false ? self::withTheTextAsSymbol($amount, $currency, $locale, $maxPrecision) : $formatted;

        return $formatted === false ? $plain : $formatted;
    }

    /**
     * An amount in the locale's own pattern for one, with `$currency`'s text where the symbol
     * stands, for a currency the formatter has no code for. Two decimals, or more up to
     * `$maxPrecision`, as the text without intl keeps them: the currency's own are not known.
     */
    private static function withTheTextAsSymbol(float $amount, string $currency, ?string $locale, ?int $maxPrecision): string|false
    {
        $formatter = new \NumberFormatter(IcuLocale::usable($locale), \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, max(2, $maxPrecision ?? 2));
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $currency);
        $formatter->setSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL, $currency);
        self::markWithTheApplicationsMarks($formatter);

        return self::unsignedZero($amount, static fn (float $number): string|false => $formatter->format($number));
    }

    /**
     * An amount in a currency without intl: two decimals, or up to `$maxPrecision` with the zeros
     * after the second dropped, and the ISO code after the number. `number_format()` rounds half
     * away from zero, as the formatter above is set to.
     */
    private static function currencyWithoutIntl(float $amount, string $currency, ?int $maxPrecision): string
    {
        $digits = max(2, $maxPrecision ?? 2);
        $formatted = number_format($amount, $digits);

        if ($digits > 2) {
            $formatted = (string) preg_replace('/(\.\d{2}\d*?)0+$/', '$1', $formatted);
        }

        return $formatted.' '.strtoupper($currency);
    }

    /**
     * A value that is already in percent, the way the active locale writes a percentage:
     * `12.5` becomes `12,5 %` in German and French and `12.5%` in English, with at most
     * `$maxPrecision` decimals and trailing zeros dropped.
     *
     * A negative value keeps the locale's minus. A plus sign is the caller's to put in front,
     * because whether a rise is shown with one is a decision about the figure, not about the
     * language it is written in.
     *
     * Without intl the number is written the way `format()` writes it without intl, with `%`
     * after it. Where the application gave its own formatter, the grouping mark and the decimal
     * mark are the ones it writes, as for an amount in `currency()`.
     */
    public static function percent(
        float $value,
        int $maxPrecision = 2,
        ?string $locale = null,
        ?bool $intlAvailable = null,
    ): string {
        $intlAvailable ??= extension_loaded('intl');
        $plain = self::plainWithTheApplicationsMarks(self::withoutIntl($value, null, $maxPrecision)).'%';

        if (! $intlAvailable) {
            return $plain;
        }

        $formatter = new \NumberFormatter(IcuLocale::usable($locale), \NumberFormatter::PERCENT);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        self::markWithTheApplicationsMarks($formatter);
        $formatted = self::unsignedZero($value, static fn (float $number): string|false => $formatter->format($number / 100));

        return $formatted === false ? $plain : $formatted;
    }

    /**
     * The grouping mark and the decimal mark of the application's own formatter, set on a formatter
     * of the locale: a percentage and an amount keep the locale's pattern, where the sign stands and
     * the space and the minus beside it, and take the marks every other number on the page is
     * written with. Nothing changes without an application formatter, or where `separators()` cannot
     * read its marks.
     */
    private static function markWithTheApplicationsMarks(\NumberFormatter $formatter): void
    {
        $marks = self::separators();

        if ($marks === null) {
            return;
        }

        $formatter->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $marks['decimal']);
        $formatter->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $marks['group']);
        $formatter->setSymbol(\NumberFormatter::MONETARY_SEPARATOR_SYMBOL, $marks['decimal']);
        $formatter->setSymbol(\NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL, $marks['group']);
    }

    /**
     * The same two marks in a text written without intl, which `number_format()` groups with a comma
     * and marks the decimals with a point. Both are swapped in one pass, so a mark that is the
     * other's is not swapped twice.
     */
    private static function plainWithTheApplicationsMarks(string $text): string
    {
        $marks = self::separators();

        return $marks === null ? $text : strtr($text, [',' => $marks['group'], '.' => $marks['decimal']]);
    }

    /**
     * What `$write` writes for `$value`, with no sign on a zero.
     *
     * ICU writes the sign of a negative zero: `-0` for -0.4 at no decimals and for `-0.0` itself,
     * `-0 %` and `-0,00 €` the same way, where the text without intl writes `0`. So a value whose
     * size the formatter writes as zero is written as zero, by the formatter's own rounding and
     * digits, a currency's included. The browser does the same in `formatDecimal()`.
     *
     * @param  callable(float): (string|false)  $write
     */
    private static function unsignedZero(float $value, callable $write): string|false
    {
        if ($value <= 0.0 && $value > -1.0 && $write(abs($value)) === $write(0.0)) {
            return $write(0.0);
        }

        return $write($value);
    }

    /**
     * The formatting for an environment without intl: `number_format()`, grouped the English way.
     */
    private static function withoutIntl(float $value, ?int $precision, ?int $maxPrecision): string
    {
        // A figure that asks for no decimals keeps its own, up to three, as the locale's way does.
        if ($precision === null && $maxPrecision === null) {
            $maxPrecision = 3;
        }

        if ($maxPrecision !== null) {
            $formatted = number_format($value, $maxPrecision);

            // Match Number::format()'s maxPrecision semantics: 4.0 renders as 4.
            return str_contains($formatted, '.')
                ? rtrim(rtrim($formatted, '0'), '.')
                : $formatted;
        }

        return number_format($value, $precision ?? 0);
    }
}
