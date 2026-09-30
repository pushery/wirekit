<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * An Alpine directive a caller writes on a component's tag without a value.
 *
 * Blade hands such an attribute to the component as `true`, and the attribute bag writes `true`
 * as the attribute's own name, except for `x-data` and `wire:*`. So a bare `x-transition` renders
 * as `x-transition="x-transition"`. Alpine reads that as a class list for a stage that was never
 * named and throws while it starts the tree, and every directive after it in that tree stays dead.
 * A bare `x-init` or `x-intersect` becomes an expression naming itself, which fails when it runs.
 *
 * No directive means anything by its own name, so every `x-*` attribute that arrives as `true`
 * gets an empty value, the one Blade already gives `x-data`. A prop never starts with `x-`.
 *
 * @internal Called from the view composer the service provider registers; not a developer-facing API.
 */
final class ValuelessDirectives
{
    /** Give every `x-*` attribute of the bag that arrived without a value an empty one, in place. */
    public static function normalize(ComponentAttributeBag $attributes): void
    {
        $all = $attributes->getAttributes();
        $changed = false;

        foreach ($all as $name => $value) {
            if ($value === true && str_starts_with((string) $name, 'x-')) {
                $all[$name] = '';
                $changed = true;
            }
        }

        if ($changed) {
            $attributes->setAttributes($all);
        }
    }
}
