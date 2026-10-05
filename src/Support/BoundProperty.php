<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Livewire\LivewireManager;
use ReflectionProperty;

/**
 * The property a caller's `wire:model` binds, read from the Livewire component that renders it.
 *
 * A `wire:model` names a path on the component: a property, or one inside an object it holds, such
 * as a form object's `form.amount`. A field that needs to know what the path ends on, a number or a
 * date, asks here, so every such question follows the path the same way.
 */
final class BoundProperty
{
    /**
     * The component Livewire is mounting or updating right now, whose view renders the field.
     */
    public static function rendering(): ?object
    {
        if (! app()->bound(LivewireManager::class)) {
            return null;
        }

        $component = app(LivewireManager::class)->current();

        return is_object($component) ? $component : null;
    }

    /**
     * The public property a path ends on, with the object that holds it, or null. The path is
     * followed through the objects it names; an array on the way, a property that does not exist
     * or one not yet set ends it.
     *
     * @return array{0: object, 1: ReflectionProperty}|null
     */
    public static function find(object $component, string $path): ?array
    {
        $segments = explode('.', trim($path));
        $last = (string) array_pop($segments);
        $target = $component;

        foreach ($segments as $segment) {
            $property = self::publicProperty($target, $segment);

            if ($property === null || ! $property->isInitialized($target)) {
                return null;
            }

            $next = $property->getValue($target);

            if (! is_object($next)) {
                return null;
            }

            $target = $next;
        }

        $property = self::publicProperty($target, $last);

        return $property === null ? null : [$target, $property];
    }

    private static function publicProperty(object $target, string $name): ?ReflectionProperty
    {
        if ($name === '' || ! property_exists($target, $name)) {
            return null;
        }

        $property = new ReflectionProperty($target, $name);

        return $property->isPublic() && ! $property->isStatic() ? $property : null;
    }
}
