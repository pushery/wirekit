<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\View\ComponentAttributeBag;
use Pushery\WireKit\Values\DateRange;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * A date or time field's value in the form the native field reads, and a caller's `wire:model` on
 * one that binds a date object, which the field cannot show.
 *
 * A native date or time field drops a value that is not written in its own form, so the field
 * stays empty and its form sends it empty. A view writes the values it renders through written().
 * A binding is another matter: Livewire sends a date object to the browser as a full timestamp,
 * `2026-10-05T00:00:00+00:00`, and after a choice the server answers with a timestamp again and
 * empties the field once more. A `string` property in the field's own form binds as expected, so a
 * view warns about the bindings that are not one while the application runs in debug mode.
 */
final class DateModel
{
    /**
     * The form each native field writes its value in, as `DateTimeInterface::format()` takes it. A
     * week is numbered by ISO 8601 and so is its year (`o`): 1 January 2027 lies in the 53rd week
     * of 2026, and `Y` would write a week that does not exist.
     */
    public const FORMATS = [
        'date' => 'Y-m-d',
        'time' => 'H:i',
        'datetime-local' => 'Y-m-d\TH:i',
        'month' => 'Y-m',
        'week' => 'o-\WW',
    ];

    /**
     * A date and a time written as text, in ISO 8601 with a `T` or a space between them, the hour
     * in one digit or two, with seconds, a fraction and a zone optional: `2026-03-15 14:30:00`, as
     * a `datetime` column reads without a cast, and `2026-03-15T14:30:00.000000Z`, as Laravel
     * writes a date into JSON. A date alone and a time alone are not in it.
     */
    private const DATE_AND_TIME = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{1,2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?$/';

    /** A time of day written as text, the hour in one digit or two, with seconds and their fraction optional, as a `time` column reads. */
    private const TIME = '/^(\d{1,2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/';

    /**
     * A value as the native field of a type reads it: `date`, `time`, `datetime-local`, `month` or
     * `week`.
     *
     * A date object is written in the field's form in its own zone. A date and a time written as
     * text are read in the application's zone, which makes a date Laravel wrote into JSON in UTC
     * the application's day again. A time of day written as text, `9:30` or `09:30:45`, is written
     * in a time field's form, with two digits for the hour: a field reads no other. Seconds are
     * written only where the field's `step` counts them: without a step a time field counts
     * minutes, and a value with seconds would be a step mismatch the form refuses to send, and a
     * `min` with seconds a base no whole minute is a step from. Any other value is returned as it
     * came, a string in the field's own form included.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $step  the field's `step` attribute: seconds, or `any`
     * @return array<array-key, mixed>|object|string|int|float|bool|null
     */
    public static function written(string $type, array|object|string|int|float|bool|null $value, array|object|string|int|float|bool|null $step = null): array|object|string|int|float|bool|null
    {
        if (! isset(self::FORMATS[$type])) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            $moment = CarbonImmutable::instance($value);
        } elseif (is_string($value) && preg_match(self::DATE_AND_TIME, trim($value), $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            && (int) $parts[4] < 24 && (int) $parts[5] < 60 && (int) ($parts[6] ?? 0) < 60) {
            $zone = date_default_timezone_get();
            $moment = CarbonImmutable::parse(trim($value), $zone)->setTimezone($zone);
        } elseif ($type === 'time' && is_string($value) && preg_match(self::TIME, trim($value), $parts) === 1
            && (int) $parts[1] < 24 && (int) $parts[2] < 60 && (int) ($parts[3] ?? 0) < 60) {
            $seconds = (int) ($parts[3] ?? 0);

            return sprintf('%02d:%02d', (int) $parts[1], (int) $parts[2]).($seconds !== 0 && self::countsSeconds($step) ? sprintf(':%02d', $seconds) : '');
        } else {
            return $value;
        }

        $format = self::FORMATS[$type];

        if (in_array($type, ['time', 'datetime-local'], true) && $moment->format('s') !== '00' && self::countsSeconds($step)) {
            $format .= ':s';
        }

        return $moment->format($format);
    }

    /**
     * A native date or time field's bag with `value`, `min` and `max` written as the field reads
     * them (see written()), for a view whose field takes them from its bag.
     */
    public static function writtenIn(ComponentAttributeBag $attributes, string $type): ComponentAttributeBag
    {
        $written = [];

        foreach (['value', 'min', 'max'] as $name) {
            if ($attributes->has($name)) {
                $written[$name] = self::written($type, $attributes->get($name), $attributes->get('step'));
            }
        }

        return $written === [] ? $attributes : new ComponentAttributeBag(array_replace($attributes->getAttributes(), $written));
    }

    /**
     * Each `wire:model` of the bag whose path ends on a date object, as written with that path:
     * `wire:model.live="due"`. A view whose field binds a path of its own passes the suffixes it
     * adds, as the two ends of a date range add `.start` and `.end`. The verdict alone, without a
     * log and without asking the environment, so a test can ask for it.
     *
     * @param  list<string>  $suffixes
     * @return list<string>
     */
    public static function datesBound(ComponentAttributeBag $attributes, ?object $component = null, array $suffixes = ['']): array
    {
        $component ??= BoundProperty::rendering();

        if ($component === null) {
            return [];
        }

        $found = [];

        foreach ($attributes->getAttributes() as $key => $value) {
            if (! is_string($key) || ! is_string($value) || ! str_starts_with($key, 'wire:model')) {
                continue;
            }

            foreach ($suffixes as $suffix) {
                if (self::bindsADate($component, $value.$suffix)) {
                    $found[] = $key.'="'.$value.$suffix.'"';
                }
            }
        }

        return $found;
    }

    /**
     * Whether a path ends on a property declared as a date object, or one that holds a date object
     * whatever it is declared as, the way a property filled from a model attribute with a date
     * cast does.
     */
    public static function bindsADate(object $component, string $path): bool
    {
        $found = BoundProperty::find($component, $path);

        if ($found === null) {
            return false;
        }

        [$owner, $property] = $found;

        // The ends of a range travel as text, whatever the period behind it declares them as:
        // Values\DateRangeSynth writes `stay.start` and `stay.end` as days.
        if ($owner instanceof DateRange) {
            return false;
        }

        if (self::declaresADate($property->getType())) {
            return true;
        }

        return $property->isInitialized($owner) && $property->getValue($owner) instanceof DateTimeInterface;
    }

    /**
     * Log a warning for each binding the bag makes to a date object, in debug mode and in the
     * console, the way the warning about an unknown prop is logged.
     *
     * @param  string  $context  the component the warning names
     * @param  string  $type  the field's native type, a key of FORMATS
     * @param  list<string>  $suffixes
     */
    public static function warn(string $context, string $type, ComponentAttributeBag $attributes, array $suffixes = ['']): void
    {
        if (! (bool) config('app.debug') && ! app()->runningInConsole()) {
            return;
        }

        $format = self::FORMATS[$type] ?? self::FORMATS['date'];

        foreach (self::datesBound($attributes, null, $suffixes) as $binding) {
            logger()->warning(sprintf(
                'WireKit [%s]: %s binds a date object. Livewire sends it to the browser as a full timestamp, '
                .'which a %s field cannot show, so the field stays empty and every answer of the server empties it again. '
                .'Bind a string property in the form `%s` and convert it when you save.',
                $context,
                $binding,
                $type,
                $format,
            ));
        }
    }

    /**
     * Whether a field's `step` counts seconds: `any`, or a number of seconds that is not a whole
     * number of minutes. Without a step a time field counts minutes.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $step
     */
    private static function countsSeconds(array|object|string|int|float|bool|null $step): bool
    {
        if (is_string($step) && strtolower(trim($step)) === 'any') {
            return true;
        }

        return is_numeric($step) && (float) $step > 0 && fmod((float) $step, 60.0) !== 0.0;
    }

    private static function declaresADate(?ReflectionType $type): bool
    {
        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($members as $member) {
            if ($member instanceof ReflectionNamedType && ! $member->isBuiltin() && is_a($member->getName(), DateTimeInterface::class, true)) {
                return true;
            }
        }

        return false;
    }
}
