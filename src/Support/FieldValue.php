<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Illuminate\View\ComponentAttributeBag;
use Traversable;
use UnitEnum;

/**
 * The value of a form control, read where a request decides its shape.
 *
 * `old('email')` gives a list when a request sent `email[]`. A validation rule such as `string`
 * fails on it and sends the reader back to the form with it, and written into a control that holds
 * one value, a list is a PHP error: the page meant to show the validation error answers with one.
 * A list where one value belongs is reported through the strictness gate instead, and the control
 * renders without it.
 *
 * An enum is read as `e()` writes it: a backed enum as its value, a pure one as its name. A model
 * attribute cast to an enum hands one over, and the controls compare and write a value as text,
 * where an enum does not cast.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class FieldValue
{
    /** What a report says the control expected. */
    private const EXPECTED = 'one value, not a list';

    /**
     * A prop's value, or null for a list: an array, or a Collection or another object that is
     * iterated as one, which the control would otherwise write as its JSON text.
     *
     * @param  array<array-key, mixed>|object|string|int|float|bool|null  $value
     */
    public static function one(array|object|string|int|float|bool|null $value, string $component, string $prop = 'value'): object|string|int|float|bool|null
    {
        if ($value instanceof UnitEnum) {
            return self::enumValue($value);
        }

        if (! is_array($value) && ! $value instanceof Traversable) {
            return $value;
        }

        StrictnessGate::reject($component, $prop, is_array($value) ? 'array' : class_basename($value), self::EXPECTED, '');

        return null;
    }

    /**
     * The bag without an attribute that holds a list, for a control that writes the attribute onto
     * a native field, where the bag would cast it to a string. An enum in such an attribute is
     * written as its value, since the bag casts it the same way and an enum does not cast.
     *
     * @param  list<string>  $names  the attributes that take one value: `value`, `checked`
     */
    public static function withoutLists(ComponentAttributeBag $attributes, string $component, array $names = ['value']): ComponentAttributeBag
    {
        foreach ($names as $name) {
            $value = $attributes->get($name);

            if (is_array($value)) {
                StrictnessGate::reject($component, $name, 'array', self::EXPECTED, '');
                $attributes = $attributes->except($name);
            } elseif ($value instanceof UnitEnum) {
                $attributes = new ComponentAttributeBag(array_replace($attributes->getAttributes(), [$name => self::enumValue($value)]));
            }
        }

        return $attributes;
    }

    /**
     * The entries of a list prop that are one value each. An entry that is a list itself is
     * reported and left out, and an enum is read as its value.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<array-key, mixed>
     */
    public static function items(array $items, string $component, string $prop = 'value'): array
    {
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                StrictnessGate::reject($component, $prop, 'a list in the list', 'a list of single values', '');
                unset($items[$key]);
            } elseif ($item instanceof UnitEnum) {
                $items[$key] = self::enumValue($item);
            }
        }

        return $items;
    }

    /** An enum as `e()` writes it: a backed enum's value, a pure enum's name. */
    private static function enumValue(UnitEnum $enum): string|int
    {
        return $enum instanceof BackedEnum ? $enum->value : $enum->name;
    }
}
