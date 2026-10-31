<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * The slot a key names, for a component that takes one slot per key: the panel of a tab, a part
 * of a card stack.
 *
 * Blade hands a view its slots twice: each as a variable of its name, and all of them in the list
 * `$__laravel_slots`. Only the list holds every slot under the name it was given. A variable
 * cannot be called `api-keys`, so `<x-slot name="api-keys">` reaches the list alone, and
 * `<x-slot:api-keys>` reaches both as `apiKeys`, the form Blade gives a hyphenated name written
 * after the colon. A variable also does not tell a slot from the view's own variables, so a key
 * read as one finds the component's props and loop variables as well.
 *
 * A view that reads its slots here also lets go of the slot variables before its props are read:
 * Blade sets them first, and a prop takes a variable that is already set over its attribute, so
 * a slot named like a prop would take the prop's place.
 */
final class KeyedSlot
{
    /**
     * The name under which the list holds the slot for this key, or null when no slot names it.
     *
     * The key as written comes first, then the camel form of a hyphenated key. A number names no
     * slot: the list is put together with `array_merge()`, which numbers the keys that are
     * numbers anew, so a slot written as `7` stands under another number.
     *
     * @param  array<array-key, mixed>  $slots  the view's `$__laravel_slots`
     */
    public static function nameFor(array $slots, int|string $key): ?string
    {
        if (is_int($key) || $key === '' || $key === '__default' || (string) (int) $key === $key) {
            return null;
        }

        if (array_key_exists($key, $slots)) {
            return $key;
        }

        $camel = Str::camel($key);

        return str_contains($key, '-') && array_key_exists($camel, $slots) ? $camel : null;
    }

    /**
     * The slot for this key, or null when no slot names it.
     *
     * A slot can arrive as a string where its closing tag touches the text after it; it is
     * returned as it came.
     *
     * @param  array<array-key, mixed>  $slots  the view's `$__laravel_slots`
     */
    public static function find(array $slots, int|string $key): Htmlable|string|null
    {
        $name = self::nameFor($slots, $key);
        $slot = $name === null ? null : $slots[$name];

        return $slot instanceof Htmlable || is_string($slot) ? $slot : null;
    }
}
