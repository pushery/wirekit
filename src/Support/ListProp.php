<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Enumerable;
use Traversable;
use UnitEnum;

/**
 * Reads a Blade component prop that is meant as a list: items, steps, levels, selected values.
 *
 * In a Laravel application a list usually arrives as a Collection, such as
 * `$user->skills->pluck('id')`, a relation or a query result, and only sometimes as an array
 * literal. A component that tests the prop with `is_array()` reads a Collection as "nothing
 * passed" and renders its empty state without a word, and one that hands it to
 * `array_key_last()` or a parameter typed `array` throws. Neither shows in an example written
 * with an array literal; both show on the first render of a real page.
 *
 * Only a list-like object is converted. Every other value, a string, a number, null, comes back
 * exactly as it was passed, so the branches a component already has for those keep working.
 * A reader given the names of the component and the prop reads a text, a number or a switch as
 * an empty list instead and reports it through the strictness gate: `options="Red,Green"`
 * written without its colon, or a JSON column read without its cast, would otherwise end the
 * page at the first loop over it. An empty text is no value and is not reported. An object is
 * left as it is, since a component loops over one as well.
 *
 * Keys are a second question. `filter()`, `where()`, `reject()`, `unique()` and `sortBy()` keep
 * the keys of what they return, so a Collection that has been through one of them is a list whose
 * keys are not 0 to n-1. `from()` keeps those keys, which is right for a map of values to labels.
 * A component that reads its prop as a list asks `renumbered()` instead: a position computed from
 * a key is wrong for such a list, and `json_encode()` writes it as an object, in which a script
 * finds no array and integer keys come back in ascending order whatever order they were sorted in.
 */
final class ListProp
{
    /**
     * The value as an array when it is a Collection, another Traversable or an Arrayable, and
     * unchanged otherwise.
     *
     * `mixed` in and out because the prop is whatever the application passed, and a value that
     * is not a list is handed back untouched for the component's own handling. Keys are kept:
     * a filtered Collection keeps its keys the way the array it replaces would, and a component
     * that renumbers its list does so itself.
     *
     * The order of the checks matters for a paginator, which is both Traversable and Arrayable:
     * walking it yields the items of the page, while `toArray()` yields the pagination metadata
     * with the items nested under `data`.
     */
    public static function from(mixed $value, ?string $component = null, ?string $prop = null): mixed
    {
        if ($value instanceof Enumerable) {
            return $value->all();
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($component !== null && $prop !== null && is_scalar($value)) {
            if (! is_string($value) || trim($value) !== '') {
                StrictnessGate::reject($component, $prop, is_string($value) ? trim($value) : get_debug_type($value), 'a list, such as an array or a Collection', '[]');
            }

            return [];
        }

        return $value;
    }

    /**
     * The value as `from()` gives it, with each item that is a model, a Collection or another
     * Arrayable read as the array it holds, and each enum in it read as its text.
     *
     * The result of a query is a Collection of models. A view that tests an item with
     * `is_array()` takes a model for a plain text, and cast to a string a model is its JSON: the
     * whole record, where the component meant to print one field of it. A model is read through
     * `toArray()`, which leaves out what it hides.
     *
     * An enum is read as `e()` writes it, as the value of a field is (Support\FieldValue): a backed
     * enum as its value, a pure one as its name. That holds for an item, such as one of
     * `Status::cases()`, and for an enum anywhere inside an item, such as the `value` of an option
     * or the options of a group, since the views write them as text and an enum does not cast.
     * Any other item, a string, an array, a date, is left as it is. Keys are kept; pair it with
     * `renumbered()` where they mean nothing. `mixed` for the reason `from()` gives.
     */
    public static function records(mixed $value, ?string $component = null, ?string $prop = null): mixed
    {
        $value = self::from($value, $component, $prop);

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::enumsAsText(is_object($item) ? self::from($item) : $item);
        }

        return $value;
    }

    /**
     * The item with each enum read as its text, in the arrays inside it as well. `mixed` because
     * an item is whatever the list held.
     */
    private static function enumsAsText(mixed $item): mixed
    {
        if ($item instanceof UnitEnum) {
            return $item instanceof BackedEnum ? $item->value : $item->name;
        }

        if (is_array($item)) {
            foreach ($item as $key => $field) {
                if ($field instanceof UnitEnum || is_array($field)) {
                    $item[$key] = self::enumsAsText($field);
                }
            }
        }

        return $item;
    }

    /**
     * The records of a list, each with the named keys and nothing else of it.
     *
     * For a list a component hands to its script. The script reads the keys the component
     * documents, and the whole list is written into the page for it, so a record built from a
     * query would put every one of its attributes into the page's source: a table that shows a
     * name and an address would carry each customer's other columns as well. With this, what the
     * component does not read does not leave the server.
     *
     * The list is read through `records()`, so a model counts as the array it holds. A record is
     * an array with names for keys; an item that is none, a string, a number, a plain list, is
     * left as it is, and so is the value under a key that is kept. Keys of the list itself are
     * kept. `mixed` for the reason `from()` gives.
     *
     * @param  list<string>  $keys
     */
    public static function only(mixed $value, array $keys, ?string $component = null, ?string $prop = null): mixed
    {
        $value = self::records($value, $component, $prop);

        if (! is_array($value)) {
            return $value;
        }

        $kept = array_flip($keys);

        foreach ($value as $key => $item) {
            if (is_array($item) && ! array_is_list($item)) {
                $value[$key] = array_intersect_key($item, $kept);
            }
        }

        return $value;
    }

    /**
     * The value as `from()` gives it, with an array renumbered 0 to n-1 in the order it is walked.
     *
     * For a prop whose keys mean nothing: labels, steps, the options of a field. The order is
     * the Collection's own, so a sorted list stays sorted. `mixed` for the reason `from()` gives:
     * a value that is not a list is handed back for the component's own handling.
     */
    public static function renumbered(mixed $value, ?string $component = null, ?string $prop = null): mixed
    {
        $value = self::from($value, $component, $prop);

        return is_array($value) ? array_values($value) : $value;
    }

    /**
     * `renumbered()`, unless a key is a name.
     *
     * For a prop that is a list by default and may be a map by intent, such as the data of a
     * chart series: `['January' => 10, 'February' => 20]` reaches the chart as the object it is,
     * while the integer keys a filter or a sort leaves behind are renumbered. `mixed` for the
     * reason `from()` gives.
     */
    public static function renumberedUnlessNamed(mixed $value, ?string $component = null, ?string $prop = null): mixed
    {
        $value = self::from($value, $component, $prop);

        if (! is_array($value)) {
            return $value;
        }

        foreach (array_keys($value) as $key) {
            if (! is_int($key)) {
                return $value;
            }
        }

        return array_values($value);
    }
}
