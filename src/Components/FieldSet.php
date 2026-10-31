<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Pushery\WireKit\Support\FieldError;
use Pushery\WireKit\Support\FieldGroup;
use Pushery\WireKit\Support\PropText;
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

        $data = PropText::forConstructor($data, 'field.set', self::class);

        // The legend of a set is text, and a boolean is no legend. `legend` is a switch on a status
        // tile and a status matrix, so PropText's list leaves it out, and the constructor, typed for
        // text and numbers, would take `false` as `0`.
        if (is_bool($data['legend'] ?? null)) {
            $data['legend'] = null;
        }

        return parent::resolve($data);
    }

    /**
     * `announceError` is taken here for its SPELLING, not because the controls need it. A class
     * component hands every attribute that is not a constructor parameter to its view under the
     * name it was written with, so `announce-error` reached `@props` in kebab case and never
     * became `$announceError`. A constructor parameter is matched in camel case.
     *
     * `legend`, `help` and `scope` are taken for their VALUES. An attribute that is not a constructor
     * parameter reaches the view through the attribute bag, and Blade escapes a bound string on
     * its way in there; `@props` reads it back escaped and the view's `{{ }}` escapes it again,
     * so a legend `A & B` would print as `A &amp;amp; B`. A named `legend` slot still arrives as
     * the slot: it is handed to the view after this data.
     *
     * A value echoed into the tag arrives escaped as a parameter too, so the text values are read
     * through PropText wherever they are handed on.
     *
     * `asField` is taken for its spelling, as `announceError` is: written `as-field`, it would
     * reach the view in kebab case otherwise.
     *
     * `required` is taken because every prop the view declares is a parameter here, so each one
     * reaches the view as written, whatever its type. So is `helpField`, which a kebab-case
     * `help-field` would otherwise not reach.
     */
    public function __construct(
        string|Stringable|null $name = null,
        string|Stringable|false|null $error = null,
        string|Stringable|null $hint = null,
        private readonly bool|string|null $announceError = null,
        private readonly Htmlable|string|int|float|null $legend = null,
        private readonly ?string $scope = null,
        private readonly string|Stringable|null $help = null,
        private readonly bool|string|null $asField = null,
        private readonly bool|string|null $required = null,
        private readonly string|Stringable|null $helpField = null,
    ) {
        $this->wkFieldSet = FieldGroup::open(PropText::text($name), PropText::text($error), PropText::text($hint));
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'wirekit::components.field.set';

        // Handed to the view as data rather than held in a public property: a public property is
        // part of what `@aware` searches, and this setting belongs to the set alone.
        return view($view, [
            'announceError' => $this->announceError,
            'legend' => PropText::text($this->legend),
            'help' => PropText::text($this->help),
            'scope' => $this->scope,
            'asField' => $this->asField,
            'required' => $this->required,
            'helpField' => PropText::text($this->helpField),
        ]);
    }
}
