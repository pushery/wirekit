<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;

/**
 * The name a caller gives a widget whose attributes land on a wrapper around the element a reader
 * meets: a tablist, a menu, a dialog, a meter.
 *
 * On that wrapper, a `div` with no role, ARIA prohibits a name, and assistive technology reads
 * none. A view like that takes `aria-labelledby` and `aria-label` out of its bag here and writes
 * them on the element itself, ahead of the name the widget gives it on its own: the caller's
 * reference first, as in the accessible name computation, then the caller's name, then the
 * widget's.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class CallerName
{
    /**
     * The caller's `aria-labelledby` and `aria-label` as text, each null when absent or blank, and
     * the bag without either.
     *
     * @return array{0: ?string, 1: ?string, 2: ComponentAttributeBag}
     */
    public static function split(ComponentAttributeBag $attributes): array
    {
        $labelledBy = AttributeText::get($attributes, 'aria-labelledby');
        $label = AttributeText::get($attributes, 'aria-label');

        return [
            is_string($labelledBy) && trim($labelledBy) !== '' ? trim($labelledBy) : null,
            is_string($label) && trim($label) !== '' ? $label : null,
            $attributes->except(['aria-labelledby', 'aria-label']),
        ];
    }

    /**
     * The naming attribute for the element a reader meets, escaped once: the caller's reference,
     * else the caller's name, else the widget's own reference, else its own name, else nothing.
     */
    public static function attribute(?string $callerLabelledBy, ?string $callerLabel, ?string $ownLabel = null, ?string $ownLabelledBy = null): HtmlString
    {
        foreach ([['aria-labelledby', $callerLabelledBy], ['aria-label', $callerLabel], ['aria-labelledby', $ownLabelledBy], ['aria-label', $ownLabel]] as [$name, $value]) {
            if (is_string($value) && trim($value) !== '') {
                return new HtmlString($name.'="'.e($value).'"');
            }
        }

        return new HtmlString('');
    }
}
