<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Pushery\WireKit\Support\FieldControl;
use Stringable;

/**
 * The class behind `field`, registered under the same tag as the anonymous file.
 *
 * Everything the field renders stays in `field.blade.php`, and so does its `@props` block, which
 * is what every catalog, manifest and guard reads. The class exists for one reason: a slot renders
 * before the view of the component that holds it, so the control inside cannot learn what the
 * field's label is, and the field cannot learn the id of the control. A constructor runs before
 * the slot. It builds a FieldControl, `@aware` hands it to the control, and the control records
 * the element the label should name.
 */
final class Field extends Component
{
    /**
     * Read by the control in the slot through `@aware`, which matches on the name alone. A name no
     * other component declares, so no prop of an ancestor can answer in its place.
     */
    public FieldControl $wkField;

    /**
     * Every prop is a parameter, and that is for their VALUES. A class component hands an attribute
     * that is not a constructor parameter to its view through the attribute bag, and Blade escapes
     * a bound string on its way in there: `@props` would read a label `A & B` back escaped and the
     * view's `{{ }}` would escape it again. A parameter is matched in camel case, so
     * `announce-error` arrives as `$announceError`. A named slot still arrives as the slot: it is
     * handed to the view after this data.
     */
    public function __construct(
        private readonly Htmlable|Stringable|string|int|float|null $label = null,
        private readonly Stringable|string|null $name = null,
        private readonly Htmlable|Stringable|string|int|float|null $hint = null,
        private readonly Htmlable|Stringable|string|int|float|null $error = null,
        private readonly bool|string|null $announceError = null,
        private readonly bool|string|null $required = false,
        private readonly Stringable|string|null $for = null,
        private readonly ?string $orientation = 'vertical',
        private readonly ?string $scope = null,
        // Last, so a call that passes the others by position keeps them where they were.
        private readonly Htmlable|Stringable|string|int|float|null $help = null,
    ) {
        $this->wkField = FieldControl::open($for, $name, $label instanceof Stringable && ! $label instanceof Htmlable ? (string) $label : $label);
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'wirekit::components.field';

        // Handed to the view as data rather than held in public properties: a public property is
        // part of what `@aware` searches, and these belong to the field alone.
        return view($view, [
            'label' => $this->label,
            'name' => $this->name,
            'hint' => $this->hint,
            'help' => $this->help,
            'error' => $this->error,
            'announceError' => $this->announceError,
            'required' => $this->required,
            'for' => $this->for,
            'orientation' => $this->orientation,
            'scope' => $this->scope,
        ]);
    }
}
