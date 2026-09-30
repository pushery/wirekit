<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;

/**
 * Formats a number for the active application locale.
 *
 * Components used PHP's `number_format()`, which always groups the
 * English way: `1,000`. In every locale that groups with a period or a space
 * (de `1.000`, fr `1 000`, it/es/pt `1.000`) the readout came out wrong —
 * directly beside labels the same component had just translated correctly.
 * Half-localized output reads as a defect rather than a missing locale.
 *
 * Two things this deliberately does NOT do:
 *
 * 1. It does not reach for `Number::format()` unconditionally. That helper
 *    throws when `ext-intl` is absent, and WireKit does not require the
 *    extension — making it a hard dependency would break every application
 *    without it. Without intl the output stays exactly what it was.
 * 2. It does not rely on `Number::format()` picking up the locale by itself.
 *    Its locale is a static on the class, defaulting to `en`, and the framework
 *    never wires it to `App::getLocale()` — so the locale is passed explicitly.
 *    Omitting it would have "fixed" the bug while changing nothing.
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

        return (string) Number::format(
            $value,
            precision: $precision,
            maxPrecision: $maxPrecision,
            locale: $locale ?? App::getLocale(),
        );
    }

    /**
     * An amount in a currency, the way the active locale writes it: `1.234,56 €` in German,
     * `€1,234.56` in English.
     *
     * Without intl there is no table of currency symbols and their positions to read, so the
     * ISO code follows the number, grouped the way `format()` groups without intl:
     * `1,234.56 EUR`. The amount stays exact and the currency unambiguous, which is what a price
     * has to be; the symbol and where it goes are the part the extension adds. The same text
     * stands in when the formatter refuses an amount or a code.
     *
     * @param  string  $currency  An ISO 4217 code, `EUR`.
     */
    public static function currency(
        float $amount,
        string $currency,
        ?string $locale = null,
        ?bool $intlAvailable = null,
    ): string {
        $intlAvailable ??= extension_loaded('intl');
        $plain = number_format($amount, 2).' '.strtoupper($currency);

        if (! $intlAvailable) {
            return $plain;
        }

        $formatted = (new \NumberFormatter($locale ?? App::getLocale(), \NumberFormatter::CURRENCY))
            ->formatCurrency($amount, $currency);

        return $formatted === false ? $plain : $formatted;
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
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        $formatted = $formatter->format($value / 100);

        return $formatted === false ? $plain : $formatted;
    }

    /**
     * The pre-existing behavior, kept verbatim for environments without intl.
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
