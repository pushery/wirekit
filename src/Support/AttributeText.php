<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * The text of an attribute a caller wrote on a component, read out of its attribute bag.
 *
 * The bag holds a value as HTML: a static value as it was written in the tag, entities included, and a
 * bound value already escaped by Blade when it put it there. Written back through the bag, that HTML
 * is what the page receives. A view that reads a value out of the bag to write it itself, with `{{ }}`
 * or into a script's payload, escapes it a second time, and a name such as `Tom's orders & returns`
 * reaches assistive technology as `Tom&#039;s orders &amp; returns`. This reads the value as the text
 * the bag stands for, for the view to escape once.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class AttributeText
{
    /**
     * The attribute's text, or the default where the bag has no such attribute.
     *
     * A value that is not a string, a boolean attribute written without a value for one, comes back
     * as it is.
     */
    public static function get(ComponentAttributeBag $attributes, string $key, mixed $default = null): mixed
    {
        $value = $attributes->get($key, $default);

        if (! is_string($value) || ! $attributes->has($key)) {
            return $value;
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
