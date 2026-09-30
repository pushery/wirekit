<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * The attributes a caller writes for a component as a whole, on a component whose other attributes
 * land inside its outermost element.
 *
 * A form control renders a wrapper around its field and hands its bag to the field. A few
 * attributes are about the whole component rather than the field, and on the field they do half
 * of what the caller wrote:
 *
 * - `wire:key`. Livewire's morph compares the direct children of an element by `wire:id`,
 *   `wire:key` or `id`, so in a loop it compares the outermost element of each iteration. A key on
 *   the field leaves the wrappers unkeyed and matched by position, and after a row is removed from
 *   the middle every field below it is rebuilt, losing its focus and its state.
 * - `x-show` and `wire:show`, with `x-transition` and `wire:transition`. On the field they hide and
 *   animate the field alone, and its label, description and error stay on the page.
 * - `x-cloak` and `wire:cloak`, when a show directive comes with them, so the whole component stays
 *   hidden until Alpine has decided. Without one they stay in the bag: several of these components
 *   run an Alpine component of their own inside a wrapper that has none, and on a page with no
 *   Alpine root around them a cloak moved to that wrapper would never be removed. A caller who
 *   writes `x-show` has that root already, since the expression needs one to be read.
 *
 * A view like that takes them out of its bag here and writes them on its outermost element.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class OuterAttributes
{
    /**
     * The attributes that belong on the outermost element, and the bag without them.
     *
     * @return array{0: ComponentAttributeBag, 1: ComponentAttributeBag}
     */
    public static function split(ComponentAttributeBag $attributes): array
    {
        $names = array_map('strval', array_keys($attributes->getAttributes()));
        $shows = array_filter($names, self::isShow(...)) !== [];

        $outer = array_values(array_filter(
            $names,
            static fn (string $name): bool => $name === 'wire:key'
                || self::isShow($name)
                || self::isDirective($name, 'x-transition')
                || self::isDirective($name, 'wire:transition')
                || ($shows && in_array($name, ['x-cloak', 'wire:cloak'], true)),
        ));

        return [$attributes->only($outer), $attributes->except($outer)];
    }

    /**
     * Whether the bag carries an `x-show` or a `wire:show`.
     *
     * A component that shows and hides itself with an `x-show` of its own on its outermost
     * element cannot take a caller's there too: the second attribute of one name is dropped by
     * the parser, and a bound `wire:show` beside it toggles the same element independently. Such
     * a component wraps itself in an element for them when this says there is one.
     */
    public static function shows(ComponentAttributeBag $attributes): bool
    {
        foreach (array_keys($attributes->getAttributes()) as $name) {
            if (self::isShow((string) $name)) {
                return true;
            }
        }

        return false;
    }

    private static function isShow(string $name): bool
    {
        return self::isDirective($name, 'x-show') || self::isDirective($name, 'wire:show');
    }

    /**
     * The directive itself, or it with modifiers (`x-transition.opacity`) or a value
     * (`x-transition:enter`).
     */
    private static function isDirective(string $name, string $directive): bool
    {
        return $name === $directive
            || str_starts_with($name, $directive.'.')
            || str_starts_with($name, $directive.':');
    }
}
