<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Facades\App;

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
     * Argument order mirrors `Number::format()` so the two read the same at a
     * call site.
     *
     * @param  int|null  $precision  Exactly this many decimals, trailing zeros KEPT
     *                               (`2.0` stays `2.0` — a file size reads as "2.0 KB").
     * @param  int|null  $maxPrecision  At most this many decimals, trailing zeros DROPPED
     *                                  (`4.0` becomes `4` — a rating reads as "4", not "4.0").
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
        $intlAvailable ??= extension_loaded('intl');

        if (! $intlAvailable) {
            return self::withoutIntl($value, $precision, $maxPrecision);
        }

        // `Number::format()`, with one difference: a tie rounds half away from zero, as the text
        // without intl and the browser's `Intl.NumberFormat` round it. ICU's own default, half to
        // even, would write `2` for 2.5 where both of them write `3`.
        $formatter = new \NumberFormatter($locale ?? App::getLocale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        if ($maxPrecision !== null) {
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        } elseif ($precision !== null) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $precision);
        }

        $formatted = $formatter->format($value);

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
     * to `$maxPrecision`. The same text stands in when the formatter refuses an amount or a code.
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
        $plain = self::currencyWithoutIntl($amount, $currency, $maxPrecision);

        if (! $intlAvailable) {
            return $plain;
        }

        $formatter = new \NumberFormatter($locale ?? App::getLocale(), \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        // The currency's own digits, two for the euro and none for the yen, are the formatter's once
        // it is set to that currency. The precision asked for adds to them and never takes away.
        if ($maxPrecision !== null && $formatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $currency)) {
            $digits = (int) $formatter->getAttribute(\NumberFormatter::FRACTION_DIGITS);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $digits);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, max($digits, $maxPrecision));
        }

        $formatted = $formatter->formatCurrency($amount, $currency);

        return $formatted === false ? $plain : $formatted;
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
     * after it.
     */
    public static function percent(
        float $value,
        int $maxPrecision = 2,
        ?string $locale = null,
        ?bool $intlAvailable = null,
    ): string {
        $intlAvailable ??= extension_loaded('intl');
        $plain = self::withoutIntl($value, null, $maxPrecision).'%';

        if (! $intlAvailable) {
            return $plain;
        }

        $formatter = new \NumberFormatter($locale ?? App::getLocale(), \NumberFormatter::PERCENT);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        $formatted = $formatter->format($value / 100);

        return $formatted === false ? $plain : $formatted;
    }

    /**
     * The formatting for an environment without intl: `number_format()`, grouped the English way.
     */
    private static function withoutIntl(float $value, ?int $precision, ?int $maxPrecision): string
    {
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
