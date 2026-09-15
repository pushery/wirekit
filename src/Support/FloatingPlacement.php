<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The placements an overlay can open at against its trigger.
 *
 * The set Floating UI's `computePosition` accepts, which is what every WireKit overlay hands
 * the value to. One list, so a component that validates its `placement` prop and the page that
 * documents it read the same twelve words. `bottom-start` comes first because a validation
 * failure falls back to the first allowed value, and that is where a field's panel opens.
 */
final class FloatingPlacement
{
    /** @var list<string> */
    public const ALL = [
        'bottom-start',
        'bottom',
        'bottom-end',
        'top-start',
        'top',
        'top-end',
        'left-start',
        'left',
        'left-end',
        'right-start',
        'right',
        'right-end',
    ];
}
