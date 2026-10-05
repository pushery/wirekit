<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;
use ReflectionNamedType;

/**
 * A caller's `wire:model` on a single checkbox that binds a number, which the box takes for its
 * value rather than its state.
 *
 * Alpine sets a checkbox from its model by type: a boolean checks or clears the box, while a number
 * becomes the box's `value` and leaves it as it is. A box bound to a property that holds 1, such as
 * a `tinyint` column without a `boolean` cast, therefore shows unchecked when the page loads and
 * does not follow a value the server sets; only a click puts it right. A box with a `value` of its
 * own adds that value to an array or sets it, and is left alone here.
 */
final class CheckboxModel
{
    /**
     * Each `wire:model` of a single box whose path ends on a number, as written with that path:
     * `wire:model.live="active"`. The verdict alone, without a log and without asking the
     * environment, so a test can ask for it.
     *
     * @return list<string>
     */
    public static function numbersBound(ComponentAttributeBag $attributes, ?object $component = null): array
    {
        if ($attributes->has('value')) {
            return [];
        }

        $component ??= BoundProperty::rendering();

        if ($component === null) {
            return [];
        }

        $found = [];

        foreach ($attributes->getAttributes() as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'wire:model') && self::bindsANumber($component, $value)) {
                $found[] = $key.'="'.$value.'"';
            }
        }

        return $found;
    }

    /**
     * Whether a path ends on a property declared `int`, nullable or not, or on one without a type
     * that holds an int.
     */
    public static function bindsANumber(object $component, string $path): bool
    {
        $found = BoundProperty::find($component, $path);

        if ($found === null) {
            return false;
        }

        [$owner, $property] = $found;
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'int';
        }

        return $type === null && $property->isInitialized($owner) && is_int($property->getValue($owner));
    }

    /**
     * Log a warning for each binding the bag makes to a number, in debug mode and in the console,
     * the way the warning about an unknown prop is logged.
     *
     * @param  string  $context  the component the warning names
     */
    public static function warn(string $context, ComponentAttributeBag $attributes): void
    {
        if (! (bool) config('app.debug') && ! app()->runningInConsole()) {
            return;
        }

        foreach (self::numbersBound($attributes) as $binding) {
            logger()->warning(sprintf(
                'WireKit [%s]: %s binds a number. A checkbox takes a number for its value rather than its state, '
                .'so the box shows unchecked whatever the property holds and does not follow the server. '
                .'Declare the property `bool`, or give the model attribute a `boolean` cast.',
                $context,
                $binding,
            ));
        }
    }
}
