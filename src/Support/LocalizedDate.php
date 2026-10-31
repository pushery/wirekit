<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use IntlCalendar;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use IntlTimeZone;

/**
 * A date whose ORDER follows the locale, not only its words.
 *
 * `Carbon::translatedFormat('M j')` translates the month NAME and keeps the English
 * arrangement, so a German page would render "Sep. 7" where German writes "7. Sep.", and a
 * full date would come out as "Sonntag, September 7, 2026": the words right and the
 * sentence not — which reads worse than an untranslated date, because it looks like the
 * locale was applied and got it wrong.
 *
 * The answer is a CLDR SKELETON: a description of which fields are wanted (`MMMd` =
 * abbreviated month plus day) that ICU turns into the pattern that locale actually uses. The
 * order is never spelled out here, which is the point — spelling it out would be the bug.
 *
 * Two things this deliberately does NOT do, both mirroring {@see LocalizedNumber}:
 *
 * 1. It does not require `ext-intl`. The extension is not a WireKit dependency, and making
 *    it one would break every application without it. Without intl the output is
 *    `translatedFormat()` with the caller's format.
 * 2. It does not read the locale from the formatter's own default. `IntlDateFormatter`
 *    falls back to the ICU default locale, which has nothing to do with `App::getLocale()`,
 *    so the locale is passed explicitly. Omitted, it would change nothing on a page whose
 *    locale is set by Laravel.
 */
final class LocalizedDate
{
    /**
     * @param  string  $skeleton  CLDR field skeleton, e.g. `MMMd` or `yMMMMEEEEd`.
     * @param  string  $fallbackFormat  PHP date() format used when intl is unavailable.
     *                                  It carries the English order on purpose: without
     *                                  ICU there is nothing to derive a better one from,
     *                                  and inventing one per locale here would be a second
     *                                  hardcoded arrangement.
     * @param  bool|null  $intlAvailable  Test seam — production callers omit it. The
     *                                    intl-less path is a real shipped behavior, so it
     *                                    has to be provable without unloading an extension.
     */
    public static function bySkeleton(
        CarbonInterface $date,
        string $skeleton,
        string $fallbackFormat,
        ?string $locale = null,
        ?bool $intlAvailable = null,
    ): string {
        $locale = IcuLocale::usable($locale);
        $intlAvailable ??= class_exists(IntlDatePatternGenerator::class) && class_exists(IntlDateFormatter::class) && class_exists(IntlTimeZone::class);

        if (! $intlAvailable) {
            // A copy carries the locale, so the caller's date keeps its own.
            return CarbonImmutable::instance($date)->locale(str_replace('-', '_', $locale))->translatedFormat($fallbackFormat);
        }

        $pattern = (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);

        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            self::zoneOf($date),
        );
        $formatter->setPattern($pattern);

        $formatted = $formatter->format($date);

        // ICU returns `false` on a pattern it cannot apply. Falling back rather than
        // returning an empty string: a separator with no date is worse than an
        // English-ordered one, and it would be invisible in a green test run.
        return $formatted === false ? $date->translatedFormat($fallbackFormat) : $formatted;
    }

    /**
     * The first day of the week in a locale, 0 for Sunday to 6 for Saturday, from the locale data
     * ICU carries: Monday in German, Sunday in American English, Saturday in some Arabic locales.
     * Without `ext-intl` it is Monday, the kit's own default.
     *
     * @param  bool|null  $intlAvailable  Test seam, as on bySkeleton().
     */
    public static function firstDayOfWeek(?string $locale = null, ?bool $intlAvailable = null): int
    {
        $locale = IcuLocale::usable($locale);
        $intlAvailable ??= class_exists(IntlCalendar::class);

        if (! $intlAvailable) {
            return 1;
        }

        $first = IntlCalendar::createInstance(null, str_replace('-', '_', $locale))?->getFirstDayOfWeek();

        // ICU counts Sunday as 1 and Saturday as 7.
        return is_int($first) && $first >= 1 && $first <= 7 ? $first - 1 : 1;
    }

    /**
     * The zone ICU writes `$date` in.
     *
     * ICU knows a zone by its identifier, such as `Europe/Berlin` or `UTC`. A date read from a
     * Unix timestamp or from a string that names an offset or an abbreviation keeps that in PHP
     * (`+00:00`, `+02:00`, `Z`, `CEST`), and ICU refuses each of them with an exception. Such a
     * date is written in the offset it has at this instant, which is the same wall-clock time.
     */
    private static function zoneOf(CarbonInterface $date): string
    {
        $name = $date->getTimezone()->getName();
        $known = IntlTimeZone::createTimeZone($name);

        return $known === null || $known->getID() === IntlTimeZone::getUnknown()->getID()
            ? 'GMT'.$date->format('P')
            : $name;
    }
}
