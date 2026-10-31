<?php

declare(strict_types=1);

namespace Pushery\WireKit\Values;

use DateTimeInterface;
use InvalidArgumentException;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

/**
 * Carries a DateRange through Livewire, so a property typed with one binds with `wire:model`.
 *
 * Written into the snapshot as its two ends, `{start, end}`, the shape a `date-picker` with
 * `range` binds as `stay.start` and `stay.end`. Read back from that shape, or from the text a
 * `calendar` sends, `start/end`. It is deliberately no array-shaped synthesizer: Livewire hands a
 * text update for such a property on as text, which a property typed with a range does not take.
 *
 * The class comes from the snapshot, which Livewire signs, and is held to a range before it is
 * built.
 */
class DateRangeSynth extends Synth
{
    public static string $key = 'wkrng';

    /**
     * Whether a value is a range. `mixed` because Livewire hands every value of a component here
     * and declares the parameter without a type.
     */
    public static function match(mixed $target): bool
    {
        return $target instanceof DateRange;
    }

    /**
     * Whether a declared type is a range, for a property that holds none yet. `mixed` for the
     * reason match() gives.
     */
    public static function matchByType(mixed $type): bool
    {
        return is_string($type) && is_a($type, DateRange::class, true);
    }

    /**
     * The two ends a validation rule reads, `stay.start` and `stay.end`, and `stay.preset` for a
     * range chosen by a preset.
     *
     * @return array{start: ?string, end: ?string, preset?: string}
     */
    public static function unwrapForValidation(DateRange $target): array
    {
        return $target->toFields();
    }

    /**
     * A property typed with a range that held none, set from the value the browser sent.
     *
     * @param  array<array-key, DateTimeInterface|string|null>|string|int|float|bool|null  $value
     */
    public static function hydrateFromType(string $type, array|string|int|float|bool|null $value): DateRange
    {
        return self::rangeClass($type)::from(is_array($value) || is_string($value) ? $value : null);
    }

    /**
     * @return array{0: array{start: ?string, end: ?string, preset?: string}, 1: array{class: class-string<DateRange>}}
     */
    public function dehydrate(DateRange $target): array
    {
        return [$target->toFields(), ['class' => $target::class]];
    }

    /**
     * @param  array<array-key, DateTimeInterface|string|null>|string|int|float|bool|null  $value
     * @param  array{class?: string}  $meta
     */
    public function hydrate(array|string|int|float|bool|null $value, array $meta): DateRange
    {
        return self::rangeClass($meta['class'] ?? '')::from(is_array($value) || is_string($value) ? $value : null);
    }

    /**
     * One end set through its path, `stay.start` or `stay.end`, as a `date-picker` with `range`
     * binds them, or the preset through `stay.preset`. The range is replaced, as a range does not
     * change. A new end drops the preset; the date picker sends the preset after its two ends.
     *
     * The date picker sends the start first, so a start after the old end begins the range anew
     * (DateRange::startingAt()) and the end that follows completes it: ordered against the old
     * end, a range picked wholly after the old one would keep the old end as its start.
     *
     * @param  array<array-key, mixed>|string|int|float|bool|null  $value
     */
    public function set(DateRange &$target, string $key, array|string|int|float|bool|null $value): void
    {
        $text = is_string($value) && $value !== '' ? $value : null;

        $target = match ($key) {
            'start' => $target->startingAt($text),
            'end' => $target->withEnd($text),
            'preset' => $target->withPreset($text),
            default => $target,
        };
    }

    /** @return class-string<DateRange> */
    private static function rangeClass(string $class): string
    {
        if (! is_a($class, DateRange::class, true)) {
            throw new InvalidArgumentException("WireKit: [{$class}] is no date range.");
        }

        return $class;
    }
}
