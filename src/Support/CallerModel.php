<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * A caller's `x-model` on a component that renders a root of its own where the binding lands.
 *
 * Alpine reads an `x-model` in the nearest `x-data` at or above the element it stands on, so on a
 * root of the component's, or on a field inside one, the caller's expression would find the
 * component's names first: a page property named like one of them binds the control to the
 * component. Two places, two ways:
 *
 *  - on a root that answers the binding through `x-modelable`, with nothing of the component's
 *    around it, the `.parent` modifier has Alpine read it in the scope around the root
 *    (aroundRoot());
 *  - on a field inside a root of the component's own, or on a root under a layer of its own,
 *    `.parent` would still find the component's names, so the binding becomes `x-wk-model`, read
 *    above the boundary the component's outermost element marks (onField(), binds(),
 *    resources/js/utils/caller-model.js).
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class CallerModel
{
    /**
     * The bag with every `x-model` read in the scope around the root: `.parent` among its
     * modifiers, the caller's own modifiers kept, and every other attribute as it was, in its place.
     */
    public static function aroundRoot(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        $read = [];

        foreach ($attributes->getAttributes() as $name => $value) {
            $read[self::aroundName((string) $name)] = $value;
        }

        return new ComponentAttributeBag($read);
    }

    /**
     * The bag with every `x-model` renamed to `x-wk-model`, its modifiers kept, for a field that
     * stands inside a root of the component's own, where `.parent` would still find the
     * component's names. The directive reads the caller's expression in the scope above the
     * boundary the view marks on its outermost element with `data-wk-ref-scope` whenever
     * binds() answers yes (resources/js/utils/caller-model.js).
     */
    public static function onField(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        $read = [];

        foreach ($attributes->getAttributes() as $name => $value) {
            $name = (string) $name;
            $read[preg_match('/^x-model(?:\.|$)/', $name) === 1 ? 'x-wk-'.substr($name, 2) : $name] = $value;
        }

        return new ComponentAttributeBag($read);
    }

    /**
     * Whether a bag carries a caller's `x-model`, before onField() or after it, so the view marks
     * the boundary its field's binding is read above.
     */
    public static function binds(ComponentAttributeBag $attributes): bool
    {
        foreach (array_keys($attributes->getAttributes()) as $name) {
            if (preg_match('/^x-(?:wk-)?model(?:\.|$)/', (string) $name) === 1) {
                return true;
            }
        }

        return false;
    }

    /** The name of an `x-model` with `.parent` among its modifiers; any other name as it is. */
    private static function aroundName(string $name): string
    {
        if (preg_match('/^x-model(?:\.|$)/', $name) !== 1) {
            return $name;
        }

        return in_array('parent', array_slice(explode('.', $name), 1), true) ? $name : $name.'.parent';
    }
}
