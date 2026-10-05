<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * A caller's `x-model` on a component whose own Alpine root takes the caller's attributes and
 * holds no value the caller can bind.
 *
 * Alpine's `x-model` on an element that is not a field listens to the `input` events that bubble
 * up from inside it and takes the value of whichever field sent them. On such a root the binding
 * received the values of fields inside the component (the month select of a calendar, one of the
 * two dates of a range) or nothing at all, never the component's value. A component that has a
 * value to hand over declares `x-modelable`, and Alpine then drops those listeners. On the others
 * the caller's `x-model` is removed, and a warning says so where WireKit reports an unknown prop:
 * with `APP_DEBUG` on, or in the console. It never throws, because the page renders the same
 * either way.
 *
 * A caller's `x-model` fares the same on a component that renders the caller's attributes beside a
 * value it does not hand over: on a wrapper around the hidden fields it sends (`otp-input`,
 * `range-slider`) it takes whichever of them sent `input` last, and on a field that binds a value
 * of the component's own (the number box of `phone`) it is a second `x-model` the parser drops.
 * Those views remove it here as well.
 *
 * @internal Called from the view composer the service provider registers, from the views that
 *           render the caller's attributes on such a root in one of their modes only, and from
 *           the views above.
 */
final class UnboundModel
{
    /**
     * The components whose own Alpine root takes the caller's attributes in every mode and carries
     * no `x-modelable`. `UnboundModelTest` derives the set from the rendered examples, so an entry
     * whose attributes land on a field, and a root missing here, both fail there.
     *
     * @var list<string>
     */
    public const ROOTS = [
        'accordion',
        'app-rail',
        'app-shell',
        'assistant-message',
        'bottom-nav',
        'calendar',
        'carousel',
        'clipboard-button',
        'collapsible',
        'command-palette',
        'context-menu',
        'conversation',
        'data-table',
        'dropdown',
        'event-calendar',
        'fab',
        'file-upload',
        'filter-builder',
        'hover-card',
        'image-compare',
        'inline-edit',
        'lightbox',
        'map',
        'menubar',
        'modal',
        'navbar',
        'navigation-menu',
        'notification-center',
        'overflow-nav',
        'page-progress',
        'popover',
        'quick-replies',
        'rating',
        'reveal',
        'scope-switcher',
        'segmented-control',
        'status-matrix',
        'stream',
        'tabs',
        'tooltip',
        'tree-view',
        'wizard',
    ];

    /**
     * Remove a caller's `x-model` from the bag of a view that renders one of the components above,
     * in place. Any other view is left alone.
     */
    public static function dropFromView(string $viewName, ComponentAttributeBag $attributes): void
    {
        $component = self::componentOf($viewName);

        if ($component !== null && in_array($component, self::ROOTS, true)) {
            self::drop($component, $attributes);
        }
    }

    /** Remove a caller's `x-model`, with any modifiers, from the bag in place, and say so. */
    public static function drop(string $component, ComponentAttributeBag $attributes): void
    {
        $all = $attributes->getAttributes();
        $models = array_values(array_filter(
            array_keys($all),
            static fn (string|int $name): bool => $name === 'x-model' || str_starts_with((string) $name, 'x-model.'),
        ));

        if ($models === []) {
            return;
        }

        $attributes->setAttributes(array_diff_key($all, array_flip($models)));

        if (! (bool) config('app.debug') && ! app()->runningInConsole()) {
            return;
        }

        logger()->warning(sprintf(
            'WireKit [%s]: `%s` has nothing to bind here and was removed. The component\'s own Alpine '
            .'root takes the attributes written on its tag and holds no value for it, so the binding '
            .'would receive the values of fields inside the component. Where the component holds a '
            .'value, bind it with `wire:model`.',
            $component,
            $models[0],
        ));
    }

    /**
     * The component a view renders, from its name: `wirekit::components.calendar` and the
     * namespace Laravel derives from a custom prefix (`{hash}::calendar`) both give `calendar`.
     * A sub-component such as `tabs.tab` is returned as written and matches no entry above.
     */
    public static function componentOf(string $viewName): ?string
    {
        $separator = strpos($viewName, '::');

        if ($separator === false) {
            return null;
        }

        $name = substr($viewName, $separator + 2);

        return str_starts_with($name, 'components.') ? substr($name, strlen('components.')) : $name;
    }
}
