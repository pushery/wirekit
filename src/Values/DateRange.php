<?php

declare(strict_types=1);

namespace Pushery\WireKit\Values;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use DatePeriod;
use DateTimeInterface;
use Illuminate\Support\Enumerable;
use InvalidArgumentException;
use Pushery\WireKit\Support\CalendarLimits;
use Pushery\WireKit\Support\DatePresets;
use Pushery\WireKit\Support\LocalizedDate;

/**
 * A range of days: the value of a `calendar` or a `date-picker` with `range`, and of a Livewire
 * property typed with it that either one binds to.
 *
 * It is a period of Carbon, so it goes where one goes: a loop over it runs a day at a time from
 * the start, the last day included, and `whereBetween()` reads its two ends. The period reaches to
 * the end of the last day, so a query over a column with a time of day takes the whole of it, while
 * `end()` names the day.
 *
 * A range a form is still filling in is a range too: the start chosen and the end not yet, the
 * end typed first, or neither. `hasStart()` and `hasEnd()` say which ends there are, `start()`
 * and `end()` return null for a missing one, and nothing here throws on one. The period behind a
 * range without both ends holds one day, the end or the start, or today for neither, so a loop
 * over it stops; check `isComplete()` before a query that needs both.
 *
 * Every end is read as the day it names in the application's time zone, as the components read
 * it: a date object is converted to that zone, and a date and time is cut to its day.
 *
 * A range chosen by a preset keeps its name, `preset()`, so a filter set to "last 7 days" is still
 * "last 7 days" when it is read again tomorrow: `fromPreset()` computes the range for today. Each
 * preset is a static constructor as well, `DateRange::last7Days()` (Support\DatePresets). A range
 * whose ends change loses its preset.
 *
 * @phpstan-consistent-constructor
 */
class DateRange extends CarbonPeriodImmutable
{
    /** How an end is written into the value of a component, and read back from it. */
    protected const string FORMAT = 'Y-m-d';

    /** Whether the period's start stands in for a start the range does not have. */
    protected bool $startless = false;

    /** Whether the range has no end: the period then counts one occurrence instead. */
    protected bool $endless = false;

    /** The preset the range was chosen by, or null for one picked day by day. */
    protected ?string $preset = null;

    /** A range without either end, as a form starts one. */
    public static function empty(): static
    {
        return static::between(null, null);
    }

    /**
     * The range a preset names, from today in the application's time zone, with a week that starts
     * on the locale's first day unless one is given. `allTime` runs from `$min` to today.
     *
     * @throws InvalidArgumentException for a key that names no preset, or an `allTime` without a first day before today
     */
    public static function fromPreset(string $preset, DateTimeInterface|string|null $min = null, ?int $weekStartsOn = null): static
    {
        $days = DatePresets::range($preset, CarbonImmutable::today(), $weekStartsOn ?? LocalizedDate::firstDayOfWeek(), static::read($min));

        if ($days === null) {
            throw new InvalidArgumentException("WireKit: [{$preset}] names no range for today. A preset is one of ".implode(', ', DatePresets::KEYS).', and allTime needs a first day on or before today.');
        }

        // The end of the last day, which a range of days reads as the day and a range of moments
        // as its last minute.
        return static::between($days[0], $days[1]->endOfDay())->withPreset($preset);
    }

    public static function today(): static
    {
        return static::fromPreset('today');
    }

    public static function yesterday(): static
    {
        return static::fromPreset('yesterday');
    }

    public static function tomorrow(): static
    {
        return static::fromPreset('tomorrow');
    }

    public static function thisWeek(?int $weekStartsOn = null): static
    {
        return static::fromPreset('thisWeek', null, $weekStartsOn);
    }

    public static function lastWeek(?int $weekStartsOn = null): static
    {
        return static::fromPreset('lastWeek', null, $weekStartsOn);
    }

    public static function nextWeek(?int $weekStartsOn = null): static
    {
        return static::fromPreset('nextWeek', null, $weekStartsOn);
    }

    public static function last7Days(): static
    {
        return static::fromPreset('last7Days');
    }

    public static function last14Days(): static
    {
        return static::fromPreset('last14Days');
    }

    public static function last30Days(): static
    {
        return static::fromPreset('last30Days');
    }

    public static function next7Days(): static
    {
        return static::fromPreset('next7Days');
    }

    public static function next14Days(): static
    {
        return static::fromPreset('next14Days');
    }

    public static function next30Days(): static
    {
        return static::fromPreset('next30Days');
    }

    public static function thisMonth(): static
    {
        return static::fromPreset('thisMonth');
    }

    public static function lastMonth(): static
    {
        return static::fromPreset('lastMonth');
    }

    public static function nextMonth(): static
    {
        return static::fromPreset('nextMonth');
    }

    public static function last3Months(): static
    {
        return static::fromPreset('last3Months');
    }

    public static function last6Months(): static
    {
        return static::fromPreset('last6Months');
    }

    public static function next3Months(): static
    {
        return static::fromPreset('next3Months');
    }

    public static function next6Months(): static
    {
        return static::fromPreset('next6Months');
    }

    public static function thisQuarter(): static
    {
        return static::fromPreset('thisQuarter');
    }

    public static function lastQuarter(): static
    {
        return static::fromPreset('lastQuarter');
    }

    public static function nextQuarter(): static
    {
        return static::fromPreset('nextQuarter');
    }

    public static function thisYear(): static
    {
        return static::fromPreset('thisYear');
    }

    public static function lastYear(): static
    {
        return static::fromPreset('lastYear');
    }

    public static function nextYear(): static
    {
        return static::fromPreset('nextYear');
    }

    public static function yearToDate(): static
    {
        return static::fromPreset('yearToDate');
    }

    /** From the first day there is to today. */
    public static function allTime(DateTimeInterface|string $min): static
    {
        return static::fromPreset('allTime', $min);
    }

    /**
     * A range from any value a component takes for one: `start/end` text, as a `calendar` writes
     * it, or a start alone; an array with `start` and `end`, or the two ends as a list; a
     * Collection of them; a date object, which starts a range; or a period, whose two ends it takes.
     * Anything that names no day leaves its end missing.
     *
     * @param  DatePeriod<DateTimeInterface, ?DateTimeInterface, ?int>|DateTimeInterface|Enumerable<array-key, DateTimeInterface|string|null>|array<array-key, DateTimeInterface|string|null>|string|null  $value
     */
    public static function from(DatePeriod|DateTimeInterface|Enumerable|array|string|null $value): static
    {
        if ($value instanceof self) {
            return static::between($value->start(), $value->end())->withPreset($value->preset());
        }

        if ($value instanceof DatePeriod) {
            return static::between($value->getStartDate(), $value->getEndDate());
        }

        if ($value instanceof DateTimeInterface) {
            return static::between($value, null);
        }

        if ($value instanceof Enumerable) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $preset = $value['preset'] ?? null;

            return static::between(self::entry($value['start'] ?? ($value[0] ?? null)), self::entry($value['end'] ?? ($value[1] ?? null)))
                ->withPreset(is_string($preset) ? $preset : null);
        }

        [$start, $end] = array_pad(explode('/', trim((string) $value), 2), 2, null);

        return static::between($start, $end);
    }

    /** A range between two ends, either of which may be missing. Ends written the wrong way round are put in order. */
    public static function between(DateTimeInterface|string|null $start, DateTimeInterface|string|null $end): static
    {
        $from = static::read($start);
        $to = static::read($end);

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // A period without an end or a number of occurrences never stops, so one occurrence
        // stands in for a missing end.
        $range = $to === null
            ? new static($from ?? CarbonImmutable::today(), 1)
            : new static($from ?? $to, static::through($to));

        $range->startless = $from === null;
        $range->endless = $to === null;

        return $range;
    }

    /** The last moment the period reaches for an end: the end of its day, so a query takes the whole day. */
    protected static function through(CarbonImmutable $end): CarbonImmutable
    {
        return $end->endOfDay();
    }

    /** An end read as the day it names in the application's time zone, or null for none. */
    protected static function read(DateTimeInterface|string|null $end): ?CarbonImmutable
    {
        $day = CalendarLimits::day($end);

        if ($day === null) {
            return null;
        }

        // The zone is named rather than left to the default: under a test clock, Carbon 3.8
        // builds a midnight given without a zone in UTC instead of the application's zone, and
        // the day read back in the application's zone is then the one before.
        return CarbonImmutable::createFromFormat('!Y-m-d', $day, date_default_timezone_get()) ?: null;
    }

    /**
     * An entry of an array as an end, or null for one that is no date and no text.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $entry
     */
    private static function entry(array|object|string|int|float|bool|null $entry): DateTimeInterface|string|null
    {
        return $entry instanceof DateTimeInterface || is_string($entry) ? $entry : null;
    }

    public function hasStart(): bool
    {
        return ! $this->startless;
    }

    public function hasEnd(): bool
    {
        return ! $this->endless && $this->getEndDate() !== null;
    }

    /** Both ends there. */
    public function isComplete(): bool
    {
        return $this->hasStart() && $this->hasEnd();
    }

    /** Neither end there. */
    public function isEmpty(): bool
    {
        return ! $this->hasStart() && ! $this->hasEnd();
    }

    /** The first day, or null without one. */
    public function start(): ?CarbonImmutable
    {
        return $this->hasStart() ? static::read($this->getStartDate()) : null;
    }

    /** The last day, or null without one. */
    public function end(): ?CarbonImmutable
    {
        return $this->hasEnd() ? static::read($this->getEndDate()) : null;
    }

    /** The range with another start, or none. */
    public function withStart(DateTimeInterface|string|null $start): static
    {
        return static::between($start, $this->end());
    }

    /**
     * The range from a new start, as a picker begins one. The end stays when it comes on or after
     * the start; a start after the end begins a range without one, which the end a picker sends
     * next completes. withStart() orders two ends written the wrong way round instead.
     */
    public function startingAt(DateTimeInterface|string|null $start): static
    {
        $begun = static::between($start, null);
        $end = $this->end();

        if ($end !== null && $begun->start()?->greaterThan($end) === true) {
            return $begun;
        }

        return static::between($start, $end);
    }

    /** The range with another end, or none. */
    public function withEnd(DateTimeInterface|string|null $end): static
    {
        return static::between($this->start(), $end);
    }

    /** The preset the range was chosen by, such as `last7Days`, or null for a range picked day by day. */
    public function preset(): ?string
    {
        return $this->preset;
    }

    /** The same ends, chosen by a preset, or by none; a key that names no preset is none. */
    public function withPreset(?string $preset): static
    {
        $range = clone $this;
        $range->preset = $preset !== null && in_array($preset, DatePresets::KEYS, true) ? $preset : null;

        return $range;
    }

    /**
     * The fields a form receives for the range: the two ends as a component writes them, a
     * missing one as null, and the preset the range was chosen by when there is one.
     *
     * Not `toArray()`, which a period of Carbon keeps for its days, one date per day.
     *
     * @return array{start: ?string, end: ?string, preset?: string}
     */
    public function toFields(): array
    {
        $ends = ['start' => $this->start()?->format(static::FORMAT), 'end' => $this->end()?->format(static::FORMAT)];

        return $this->preset === null ? $ends : [...$ends, 'preset' => $this->preset];
    }

    /** The value a component writes: `start/end`, the start alone while the end is missing, and nothing for neither. */
    public function toValue(): string
    {
        ['start' => $start, 'end' => $end] = $this->toFields();

        return $end === null ? (string) $start : (string) $start.'/'.$end;
    }

    /**
     * A new start. A date or a text is read as withStart() reads it; anything else Carbon sets
     * its own way, and the range then has a start.
     *
     * `mixed` because CarbonPeriod declares the parameter so, and a parameter narrower than the
     * parent's would break every caller that hands this range to code written for a period.
     */
    public function setStartDate(mixed $date, ?bool $inclusive = null): static
    {
        // Carbon builds a period through these two methods, and a range sets its own state once
        // the period stands (between()).
        if (! $this->constructed) {
            return parent::setStartDate($date, $inclusive);
        }

        if ($inclusive === null && ($date instanceof DateTimeInterface || is_string($date))) {
            return $this->withStart($date);
        }

        $range = parent::setStartDate($date, $inclusive);
        $range->startless = false;

        return $range;
    }

    /**
     * A new end; null takes it away. A date or a text is read as withEnd() reads it; anything else
     * Carbon sets its own way, and the one occurrence that stood in for a missing end gives way.
     *
     * `mixed` for the reason setStartDate() gives.
     */
    public function setEndDate(mixed $date, ?bool $inclusive = null): static
    {
        if (! $this->constructed) {
            return parent::setEndDate($date, $inclusive);
        }

        if ($inclusive === null && ($date === null || $date instanceof DateTimeInterface || is_string($date))) {
            return $this->withEnd($date);
        }

        $range = parent::setEndDate($date, $inclusive)->setRecurrences(null);
        $range->endless = false;

        return $range;
    }
}
