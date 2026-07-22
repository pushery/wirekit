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
