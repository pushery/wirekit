<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Facades\App;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use NumberFormatter;
use Throwable;

/**
 * A locale the intl formatters take, for the numbers, dates, prices and names the kit writes.
 *
 * The formatters refuse some locales outright: a POSIX name such as `C` or `POSIX`, a language
 * ICU does not know such as `xx`, a modifier such as `de_DE@euro` (the date patterns refuse it),
 * anything with a space. Building one throws, so a page that writes a number or a date would fail
 * on an application locale set from a request or a profile. A refused locale reads as the nearest
 * one they take: the same without its charset and modifier, then its language alone, then the
 * application's fallback locale, then English. A locale they take stays exactly as it was given.
 *
 * Without the intl extension there is nothing to refuse a locale, and the callers write their
 * English fallback; the locale is handed back as it came.
 *
 * @internal Called from the formatters and the component views; not a developer-facing API.
 */
final class IcuLocale
{
    /** @var array<string, bool> Whether the formatters take a locale, asked once per locale. */
    private static array $taken = [];

    /** The locale given, or the application's where none or an empty one is, in the nearest form the formatters take. */
    public static function usable(?string $locale = null): string
    {
        $given = trim((string) $locale);
        $given = $given !== '' ? $given : trim(App::getLocale());
        $plain = (string) preg_replace('/[.@].*$/', '', $given);
        $fallback = config('app.fallback_locale');
        $fallback = is_string($fallback) ? trim($fallback) : '';

        foreach ([$given, $plain, (string) preg_replace('/[-_].*$/', '', $plain), $fallback] as $candidate) {
            if ($candidate !== '' && self::takes($candidate)) {
                return $candidate;
            }
        }

        return 'en';
    }

    /**
     * The locale as a script's `Intl` takes it: a BCP 47 tag such as `de-AT` or `zh-Hant-TW`.
     *
     * The formatters read `en_US.UTF-8` where a browser refuses `en-US.UTF-8`, so the tag leaves
     * out the charset and the modifier of the usable locale, and keeps the rest as it was written.
     */
    public static function tag(?string $locale = null): string
    {
        return str_replace('_', '-', (string) preg_replace('/[.@].*$/', '', self::usable($locale)));
    }

    /** Whether every formatter the kit builds takes the locale. */
    private static function takes(string $locale): bool
    {
        if (! class_exists(NumberFormatter::class) || ! class_exists(IntlDateFormatter::class)) {
            return true;
        }

        if (! array_key_exists($locale, self::$taken)) {
            // A factory answers a locale it cannot build with null, and throws, as a constructor
            // does, for one PHP refuses before ICU sees it.
            try {
                self::$taken[$locale] = NumberFormatter::create($locale, NumberFormatter::DECIMAL) !== null
                    && IntlDateFormatter::create($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE) !== null
                    && (! class_exists(IntlDatePatternGenerator::class) || IntlDatePatternGenerator::create($locale) !== null);
            } catch (Throwable) {
                self::$taken[$locale] = false;
            }
        }

        return self::$taken[$locale];
    }
}
