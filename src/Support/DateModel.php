<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use DateTimeInterface;
use Illuminate\View\ComponentAttributeBag;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * A caller's `wire:model` on a date or time field that binds a date object, which the field cannot
 * show.
 *
 * Livewire sends a date object to the browser as a full timestamp, `2026-10-05T00:00:00+00:00`, and
 * a native date or time field drops a value that is not written in its own form, so the field stays
 * empty. After a choice the server answers with a timestamp again and empties it once more. A
 * `string` property in the field's own form binds as expected, so a view warns about the bindings
 * that are not one while the application runs in debug mode.
 */
final class DateModel
{
    /** The form each native field writes its value in, as `DateTimeInterface::format()` takes it. */
    public const FORMATS = [
        'date' => 'Y-m-d',
        'time' => 'H:i',
        'datetime-local' => 'Y-m-d\TH:i',
    ];

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
     * @param  string  $type  the field's native type: `date`, `time` or `datetime-local`
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
