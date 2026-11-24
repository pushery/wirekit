<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The option lists of lazy selects that share one list through `options-key`, written once per
 * render.
 *
 * The first lazy field of a render with a key writes the list in a template after it; every later
 * field with that key points to it and writes none (resources/js/utils/lazy-select-options.js finds
 * the list by its key). A Livewire update renders its component without the page around it, and as
 * a request of its own it starts this register empty, so the component's first field writes the
 * list again inside the component.
 */
final class SelectOptionLists
{
    /** @var array<string, string> the hash of the list each key was written with in this render */
    private static array $written = [];

    /**
     * Whether this field writes the list of its key: true for the first field of the render with
     * the key, false for every later one. A later field with another list under the same key is
     * reported in debug, since it builds from the first list.
     */
    public static function claim(string $key, string $list): bool
    {
        $hash = hash('xxh128', $list);

        if (! array_key_exists($key, self::$written)) {
            self::$written[$key] = $hash;

            return true;
        }

        if (self::$written[$key] !== $hash && (bool) config('app.debug')) {
            logger()->warning(sprintf('[wirekit] select: the `options-key` "%s" names two different lists on one page. A field builds from the first list written under its key.', $key));
        }

        return false;
    }

    /** Forget the lists written so far, with the other counts of a render. */
    public static function reset(): void
    {
        self::$written = [];
    }
}
