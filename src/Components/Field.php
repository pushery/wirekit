<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Pushery\WireKit\Support\BooleanProp;
use Pushery\WireKit\Support\FieldControl;
use Pushery\WireKit\Support\FieldError;
use Pushery\WireKit\Support\FieldGroup;
use Pushery\WireKit\Support\PropText;
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
     * The tag's values with every enum read as its value or name before the constructor takes
     * them, as every other component reads its props (Support\PropText): the parameters are typed
     * for text, and an enum there would be an error before the component runs. A boolean on a text
     * prop reads as none, which a parameter typed for text and numbers would take as `0`. An
     * `error` given as a list of messages is read as its first, as the other form controls read one.
     * A list or an object another parameter's type refuses is reported and left out, so the
     * parameter takes its default.
     *
     * @param  array<array-key, mixed>  $data
     * @return static
     */
    public static function resolve($data)
    {
        // A list of messages, as `$errors->get()` hands one over, is its first (FieldError::one()).
        // Read before PropText, which spares a parameter a list its type refuses, and `error` is
        // typed for one message.
        if (is_iterable($data['error'] ?? null)) {
            $data['error'] = FieldError::one($data['error']);
        }

        $data = PropText::forConstructor($data, 'field', self::class);

        return parent::resolve($data);
    }

    /**
     * Every prop is a parameter, and that is for their VALUES. A class component hands an attribute
     * that is not a constructor parameter to its view through the attribute bag, and Blade escapes
     * a bound string on its way in there: `@props` would read a label `A & B` back escaped and the
     * view's `{{ }}` would escape it again. A parameter is matched in camel case, so
     * `announce-error` arrives as `$announceError`. A named slot still arrives as the slot: it is
     * handed to the view after this data.
     *
     * A value echoed into the tag (`label="{{ $name }}"`) arrives escaped as a parameter too, so
     * the text values are read through PropText wherever they are handed on.
     */
    public function __construct(
        private readonly Htmlable|Stringable|string|int|float|null $label = null,
        private readonly Stringable|string|null $name = null,
        private readonly Htmlable|Stringable|string|int|float|null $hint = null,
        // `false` turns the field's error off (FieldError::isOff); without it in the type, a bound
        // `false` arrived coerced to 0 and only the view's truthiness check kept "0" off the page.
        private readonly Htmlable|Stringable|string|int|float|false|null $error = null,
        private readonly bool|string|null $announceError = null,
        private readonly bool|string|null $required = false,
        private readonly Stringable|string|null $for = null,
        private readonly ?string $orientation = 'vertical',
        private readonly ?string $scope = null,
        // Last, so a call that passes the others by position keeps them where they were.
        private readonly Htmlable|Stringable|string|int|float|null $help = null,
    ) {
        $fieldName = PropText::text($name);
        $fieldName = filled($fieldName) ? (string) $fieldName : null;

        // Whether the view will show the hint is decided here, before the slot renders, so the
        // control inside can link it: the view shows it when it is set and no error of the field's
        // own takes its place, read the way the view reads both, through the set around the field
        // when there is one. `@aware` reads that set the same way.
        $ownError = FieldGroup::entryMessage(
            app('view')->getConsumableComponentData('wkFieldSet'),
            $fieldName !== null ? app('view')->shared('errors') : null,
            $fieldName,
            self::errorText($error),
        );

        // A field whose error is off turns it off for the control in its slot as well, which reads
        // the bag itself (FieldControl::errorOf()).
        $this->wkField = FieldControl::open(
            PropText::text($for),
            $fieldName,
            PropText::text($label instanceof Stringable && ! $label instanceof Htmlable ? (string) $label : $label),
            (bool) PropText::text($hint),
            (bool) $ownError,
            BooleanProp::from($required, false),
            filled(PropText::text($help)),
            FieldError::isOff(self::errorText($error)),
        );
    }

    /** The error as FieldError reads it: `false` stays the switch that turns it off, anything else is its text. */
    private static function errorText(Htmlable|Stringable|string|int|float|false|null $error): string|false|null
    {
        return match (true) {
            $error === null, $error === false => $error,
            $error instanceof Htmlable => $error->toHtml(),
            default => (string) $error,
        };
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'wirekit::components.field';

        // Handed to the view as data rather than held in public properties: a public property is
        // part of what `@aware` searches, and these belong to the field alone.
        return view($view, [
            'label' => PropText::text($this->label),
            'name' => PropText::text($this->name),
            'hint' => PropText::text($this->hint),
            'help' => PropText::text($this->help),
            'error' => PropText::text($this->error),
            'announceError' => $this->announceError,
            'required' => $this->required,
            'for' => PropText::text($this->for),
            'orientation' => $this->orientation,
            'scope' => $this->scope,
        ]);
    }
}
