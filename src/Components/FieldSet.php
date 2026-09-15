<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Pushery\WireKit\Support\FieldGroup;
use Stringable;

/**
 * The class behind `field.set`, registered under the same tag as the anonymous file.
 *
 * Everything the set renders stays in `field/set.blade.php`, and so does its `@props` block,
 * which is what every catalog, manifest and guard reads. The class exists for one reason: a slot
 * renders before the view of the component that holds it, so the controls inside cannot read
 * anything that view computes. A constructor runs before the slot. It turns the three props the
 * controls need into a FieldGroup, and `@aware` hands that to them.
 */
final class FieldSet extends Component
{
    /**
     * Read by the controls in the slot through `@aware`, which matches on the name alone. A
     * name no other component declares, so no prop of an ancestor can answer in its place.
     */
    public FieldGroup $wkFieldSet;

    /**
     * `announceError` is taken here for its SPELLING, not because the controls need it. A class
     * component hands every attribute that is not a constructor parameter to its view under the
     * name it was written with, so `announce-error` reached `@props` in kebab case and never
     * became `$announceError`. A constructor parameter is matched in camel case.
     */
    public function __construct(
        string|Stringable|null $name = null,
        string|Stringable|null $error = null,
        string|Stringable|null $hint = null,
        private readonly bool|string|null $announceError = null,
    ) {
        $this->wkFieldSet = FieldGroup::open($name, $error, $hint);
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'wirekit::components.field.set';

        // Handed to the view as data rather than held in a public property: a public property is
        // part of what `@aware` searches, and this setting belongs to the set alone.
        return view($view, ['announceError' => $this->announceError]);
    }
}
