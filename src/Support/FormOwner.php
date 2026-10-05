<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * The form a caller joins a component to with `form="…"`, read out of its attribute bag.
 *
 * `form` names the form a field belongs to when it stands outside that form's markup, in a table
 * row or a sidebar. Only a field the form submits can carry it: on any other element it is not a
 * valid attribute and submits nothing. A component that submits through a field of its own (a
 * hidden field, a file input, an editor's textarea) hands this to that field and keeps it off its
 * wrapper.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class FormOwner
{
    /**
     * The id of the form the caller named, or null where they named none.
     *
     * A blank value and a bare `form` written without one name no form, so they come back as null.
     */
    public static function of(ComponentAttributeBag $attributes): ?string
    {
        $form = AttributeText::get($attributes, 'form');

        return is_string($form) && trim($form) !== '' ? trim($form) : null;
    }
}
