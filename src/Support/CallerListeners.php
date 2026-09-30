<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * A caller's Alpine listener for an event the view already listens to on the element the bag lands
 * on.
 *
 * A listener is an attribute, so a caller's `x-on:click` beside the view's own `x-on:click` puts
 * the name in the tag twice, and the parser keeps the first: either the caller's listener is gone,
 * or the component's, and with it what the component does on that event. Alpine registers
 * `x-on:click` and `@click` as two listeners, since they are two attribute names. So a caller's
 * listener that collides with one the view writes is renamed to the other spelling, and both run.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class CallerListeners
{
    /**
     * The bag with each caller listener that the view writes itself moved to the other spelling.
     *
     * @param  list<string>  $own  the listener attributes the view writes on that element, as written there
     */
    public static function beside(ComponentAttributeBag $attributes, array $own): ComponentAttributeBag
    {
        $all = $attributes->getAttributes();
        $renamed = [];
        $changed = false;

        foreach ($all as $name => $value) {
            $name = (string) $name;
            $other = in_array($name, $own, true) ? self::otherSpelling($name) : null;

            // The other spelling is taken only when nothing else holds it, the caller or the view.
            if ($other !== null && ! array_key_exists($other, $all) && ! in_array($other, $own, true)) {
                $renamed[$other] = $value;
                $changed = true;

                continue;
            }

            $renamed[$name] = $value;
        }

        return $changed ? new ComponentAttributeBag($renamed) : $attributes;
    }

    private static function otherSpelling(string $name): ?string
    {
        if (str_starts_with($name, '@')) {
            return 'x-on:'.substr($name, 1);
        }

        if (str_starts_with($name, 'x-on:')) {
            return '@'.substr($name, strlen('x-on:'));
        }

        return null;
    }
}
