<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonInterface;
use IntlDateFormatter;
use IntlDatePatternGenerator;

/**
 * A date whose ORDER follows the locale, not only its words.
 *
 * `Carbon::translatedFormat('M j')` translates the month NAME and keeps the English
 * arrangement, so a German page rendered "Sep. 7" where German writes "7. Sep.", and a
 * full date came out as "Sonntag, September 7, 2026". The words were right and the
 * sentence was not — which reads worse than an untranslated date, because it looks like
 * the locale was applied and got it wrong.
 *
 * The fix is a CLDR SKELETON: a description of which fields are wanted (`MMMd` = abbreviated
 * month plus day) that ICU turns into the pattern that locale actually uses. The order is
 * never spelled out here, which is the point — spelling it out is the bug.
 *
 * Two things this deliberately does NOT do, both mirroring {@see LocalizedNumber}:
 *
 * 1. It does not require `ext-intl`. The extension is not a WireKit dependency, and making
 *    it one would break every application without it. Without intl the output is exactly
 *    what it was before this class existed — `translatedFormat()` with the caller's format.
 * 2. It does not read the locale from the formatter's own default. `IntlDateFormatter`
 *    falls back to the ICU default locale, which has nothing to do with `App::getLocale()`,
 *    so the locale is passed explicitly. Omitting it would have "fixed" the bug while
 *    changing nothing on a page whose locale is set by Laravel.
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
        $locale ??= app()->getLocale();
        $intlAvailable ??= class_exists(IntlDatePatternGenerator::class) && class_exists(IntlDateFormatter::class);

        if (! $intlAvailable) {
            return $date->translatedFormat($fallbackFormat);
        }

        $pattern = (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);

        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone()->getName(),
        );
        $formatter->setPattern($pattern);

        $formatted = $formatter->format($date);

        // ICU returns `false` on a pattern it cannot apply. Falling back rather than
        // returning an empty string: a separator with no date is worse than an
        // English-ordered one, and it would be invisible in a green test run.
        return $formatted === false ? $date->translatedFormat($fallbackFormat) : $formatted;
    }
}
