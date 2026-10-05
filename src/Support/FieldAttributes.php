<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * The attributes a caller writes for the field of a form control whose other attributes land on a
 * wrapper around that field.
 *
 * They act on the element a reader types into or the keyboard focuses, and on the wrapper they do
 * nothing:
 *
 * - `inputmode`, `enterkeyhint` and `autocapitalize` are read by the on-screen keyboard on the
 *   field itself. No ancestor counts, and `autocapitalize` falls back only to the field's form.
 * - `spellcheck` may be inherited from an ancestor, but whether it is depends on the browser; on
 *   the field it holds in every one.
 * - `autofocus` needs an element that takes focus, and the wrapper takes none.
 * - `maxlength` limits typing only on a text field.
 *
 * A view like that takes the names that fit its field out of its bag here and writes them on the
 * field. Each view names its own set: a search field is not the value, so a `pattern` written
 * there would check the wrong text.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class FieldAttributes
{
    /**
     * What the on-screen keyboard and the spell checker read on a field the reader types into.
     *
     * @var list<string>
     */
    public const KEYBOARD = ['inputmode', 'enterkeyhint', 'autocapitalize', 'spellcheck'];

    /**
     * The named attributes as a bag of their own, and the bag without them.
     *
     * @param  list<string>  $names
     * @return array{0: ComponentAttributeBag, 1: ComponentAttributeBag}
     */
    public static function split(ComponentAttributeBag $attributes, array $names): array
    {
        return [$attributes->only($names), $attributes->except($names)];
    }

    /**
     * Whether the bag asks for `autofocus`. HTML reads the attribute by presence, so any value
     * counts except one that reads as false (`autofocus="false"`, `:autofocus="false"`).
     */
    public static function autofocus(ComponentAttributeBag $attributes): bool
    {
        return $attributes->has('autofocus') && BooleanProp::from($attributes->get('autofocus'), true);
    }
}
