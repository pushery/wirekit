<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * A caller's `x-ref` on a component whose attributes land on an Alpine root of its own.
 *
 * Alpine files an `x-ref` under the nearest `x-data` root, and an element that carries `x-data`
 * is its own nearest root, so the ref would stay inside the component where the caller's
 * `$refs` never reads it. The `x-wk-ref` directive (resources/js/utils/caller-ref.js) files the
 * element on the root above the nearest `data-wk-ref-scope` instead. A view that renders its bag
 * on its root passes the bag through here; a view whose bag lands on an element inside a root
 * of its own marks its outermost element itself.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class CallerRef
{
    /**
     * The bag with a caller's `x-ref` moved to `x-wk-ref`, and the element it lands on marked
     * as its own boundary. A bag without an `x-ref` comes back unchanged.
     */
    public static function onRoot(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        $name = trim((string) $attributes->get('x-ref', ''));

        if ($name === '') {
            return $attributes;
        }

        return $attributes->except('x-ref')->merge([
            'x-wk-ref' => $name,
            'data-wk-ref-scope' => true,
        ]);
    }
}
