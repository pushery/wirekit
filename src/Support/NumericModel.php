<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Illuminate\View\ComponentAttributeBag;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * A caller's `wire:model` on a field that gives its value as text, read as a number where it binds
 * one.
 *
 * A native range, number, select or radio field gives its value as text, and so does the hidden
 * input a rating or a segmented control writes, so a binding sends `"17"`, the server casts it to
 * the property's `int` and answers `17`. Livewire compares what a request sent with what
 * came back through `JSON.stringify`, takes `"17"` and `17` for a change made on the server, and
 * writes it into the client. When the answer arrives after the reader has moved on, it overwrites
 * the newer value, and the commit for that value then finds nothing to send: a slider dragged with
 * pauses over a slow connection jumps back, and its last value never reaches the server.
 *
 * Livewire hands the modifiers before `.live` to Alpine's `x-model`, and `x-model.number` reads the
 * field as a number. So where the bound property is declared `int`, `float` or an enum backed by
 * `int`, which Livewire sends as its number, `.number` goes in right after `wire:model`:
 * `wire:model.live` becomes `wire:model.number.live`, and one written after `.live` moves to the
 * front. A property without a type, a `string` one or a string-backed enum keeps the text its
 * field sends: the server answers with the text it got, so nothing is written back, and a number
 * would change the value that property holds.
 */
final class NumericModel
{
    /**
     * The attributes with each `wire:model` that binds a number reading its field as one, in the
     * order they came. The component is the one whose view renders the field, the one Livewire is
     * rendering when none is given; with neither, the attributes stay as they are.
     */
    public static function number(ComponentAttributeBag $attributes, ?object $component = null): ComponentAttributeBag
    {
        $component ??= BoundProperty::rendering();

        if ($component === null) {
            return $attributes;
        }

        $out = [];

        foreach ($attributes->getAttributes() as $key => $value) {
            $out[is_string($key) && is_string($value) ? self::name($key, $value, $component) : $key] = $value;
        }

        return new ComponentAttributeBag($out);
    }

    /**
     * The name an attribute takes for the path it binds: a `wire:model` whose path ends on a number
     * reads it as one, any other attribute stays as written. For a view that writes a binding of its
     * own, such as one per bound end of a range.
     */
    public static function name(string $attribute, string $path, ?object $component = null): string
    {
        $component ??= BoundProperty::rendering();

        if ($component === null || ! str_starts_with($attribute, 'wire:model') || ! self::bindsANumber($component, $path)) {
            return $attribute;
        }

        return self::withNumber($attribute);
    }

    /**
     * Whether a `wire:model` path ends on a property declared `int`, `float`, an enum backed by
     * `int`, or a union of them, nullable or not. The path is followed through the objects it names (a form object's `form.amount`); an
     * array on the way, a property that does not exist or one not yet set ends it with no.
     */
    public static function bindsANumber(object $component, string $path): bool
    {
        $found = BoundProperty::find($component, $path);

        return $found !== null && self::isNumeric($found[1]->getType());
    }

    /**
     * One attribute name with `.number` right after `wire:model`, and no other `.number` in it.
     */
    public static function withNumber(string $name): string
    {
        $parts = explode('.', $name);

        if ($parts[0] !== 'wire:model') {
            return $name;
        }

        $modifiers = array_values(array_filter(array_slice($parts, 1), static fn (string $part): bool => $part !== 'number'));

        return implode('.', ['wire:model', 'number', ...$modifiers]);
    }

    private static function isNumeric(?ReflectionType $type): bool
    {
        $names = match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_map(
                static fn (ReflectionType $member): string => $member instanceof ReflectionNamedType ? $member->getName() : '',
                $type->getTypes(),
            ),
            default => [],
        };

        $names = array_values(array_diff($names, ['null']));

        return $names !== [] && array_filter($names, static fn (string $name): bool => ! self::isNumber($name)) === [];
    }

    /**
     * Whether a type holds a number on the wire: `int`, `float`, or an enum backed by `int`, which
     * Livewire sends as its backing value and builds again from it.
     */
    private static function isNumber(string $name): bool
    {
        if ($name === 'int' || $name === 'float') {
            return true;
        }

        if (! enum_exists($name) || ! is_a($name, BackedEnum::class, true)) {
            return false;
        }

        return (string) (new ReflectionEnum($name))->getBackingType() === 'int';
    }
}
