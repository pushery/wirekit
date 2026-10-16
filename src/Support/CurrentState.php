<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * What `x-wk-current` needs to move a navigation entry between current and idle in the browser.
 *
 * A navigation inside Livewire's `@persist` is not rendered again after a `wire:navigate` visit,
 * so the entry it marked as current stays the page the reader left. With `track-current` the
 * navigation carries `x-wk-current`, and each entry carries the difference between the two ways
 * it can render: the classes only a current entry has, and the classes only an idle one has. The
 * directive moves an entry by that difference alone, so whatever the two renders share, and
 * whatever the developer added, stays where it is.
 */
final class CurrentState
{
    /**
     * The data attributes of one entry, from the classes it renders as current and as idle.
     *
     * @return array<string, string>
     */
    public static function attributes(string $whenCurrent, string $whenIdle, string $ariaCurrent = 'page'): array
    {
        $current = self::words($whenCurrent);
        $idle = self::words($whenIdle);

        // Under the names the element itself is written with, so the directive moves the classes the
        // element carries (Support\CompactClasses).
        $attributes = [
            'data-wk-current-classes' => CompactClasses::map(implode(' ', array_values(array_diff($current, $idle)))),
            'data-wk-idle-classes' => CompactClasses::map(implode(' ', array_values(array_diff($idle, $current)))),
        ];

        // "page" is what the directive writes without a word of its own.
        if ($ariaCurrent !== 'page') {
            $attributes['data-wk-current-aria'] = $ariaCurrent;
        }

        return $attributes;
    }

    /**
     * The classes of a class string, each once.
     *
     * @return list<string>
     */
    private static function words(string $classes): array
    {
        return array_values(array_unique(preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }
}
