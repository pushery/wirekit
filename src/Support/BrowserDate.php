<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;

/**
 * A date written for a script that reads it with `new Date()`.
 *
 * ECMAScript defines one form that `Date` reads alike in every engine: ISO 8601 as
 * `YYYY-MM-DDTHH:mm:ss.sssZ`, with the time, the seconds, the milliseconds and the offset each
 * optional. Any other text is read as each engine decides, and a number is read as milliseconds
 * where PHP counts seconds. A date the server reads (Support\Moment::of()) is written in that form,
 * so the script reads the date the server read.
 */
final class BrowserDate
{
    /** The date time string format of ECMAScript, which every engine reads. */
    private const ECMA = '/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2}(?:\.\d{3})?)?(?:Z|[+-]\d{2}:\d{2})?)?$/';

    private const DAY = '/^\d{4}-\d{2}-\d{2}$/';

    /**
     * `$value` as a moment the script reads as the one it names.
     *
     * A date object is written as the instant it holds, and text in the form ECMAScript defines is
     * left as it is. A Unix timestamp and text with an offset in another form name an instant and
     * are written as it. Other text names a wall-clock time, which the script reads in the reader's
     * zone, as it reads ISO 8601 without an offset: it is read in UTC, whose clock has no gap, so
     * the time written is the time the text names. A value that names no moment is returned as it
     * came.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     * @return array<array-key, mixed>|object|string|int|float|bool|null
     */
    public static function moment(array|object|string|int|float|bool|null $value): array|object|string|int|float|bool|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_string($value) && preg_match(self::ECMA, trim($value)) === 1) {
            return trim($value);
        }

        if (self::namesAnInstant($value)) {
            return self::read($value, null)?->format(DateTimeInterface::ATOM) ?? $value;
        }

        return self::read($value, 'UTC')?->format('Y-m-d\TH:i:s') ?? $value;
    }

    /**
     * `$value` as a day the script reads as the one it names, `YYYY-MM-DD`.
     *
     * A date object and a date and time written as text are written as the day Support\DateModel
     * writes for a date field, and a day is left as it is. A Unix timestamp and text with an offset
     * in another form fall on their day in the application's zone, other text on the day it names.
     * A value that names no day is returned as it came.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     * @return array<array-key, mixed>|object|string|int|float|bool|null
     */
    public static function day(array|object|string|int|float|bool|null $value): array|object|string|int|float|bool|null
    {
        $written = DateModel::written('date', $value);

        if (is_string($written) && preg_match(self::DAY, trim($written)) === 1) {
            return trim($written);
        }

        if (self::namesAnInstant($written)) {
            return self::read($written, null)?->setTimezone(date_default_timezone_get())->format('Y-m-d') ?? $written;
        }

        return self::read($written, 'UTC')?->format('Y-m-d') ?? $written;
    }

    /**
     * Whether `$value` names an instant rather than a wall-clock time: a number, which is a Unix
     * timestamp, or text that names a zone or an offset.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    private static function namesAnInstant(array|object|string|int|float|bool|null $value): bool
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $parsed = date_parse(trim($value));

        return ($parsed['is_localtime'] ?? false) === true;
    }

    /**
     * The moment Support\Moment::of() reads in `$value`, read in `$zone` when the value names none
     * of its own, or null for a value it reads as no date and for one it cannot read.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    private static function read(array|object|string|int|float|bool|null $value, ?string $zone): ?CarbonImmutable
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        try {
            return Moment::of($value, $zone);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
