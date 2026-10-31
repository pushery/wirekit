<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Stringable;

/**
 * A value for an element's `lang`, in the form HTML reads.
 *
 * A language a developer names is often a locale of the application: `app()->getLocale()` gives
 * `pt_BR`, a server's locale `de_DE.UTF-8`. HTML takes a BCP 47 tag, and one it does not recognize
 * stands for an unknown language distinct from every other, so a screen reader keeps its voice and
 * `:lang(pt)` matches nothing. The tag here is the one IcuLocale::tag() writes for a locale: without
 * its charset and modifier, and with hyphens.
 */
final class LanguageTag
{
    /**
     * The tag, or null where the value names no language: null, a blank text, or no text at all.
     *
     * `mixed`, because a prop is whatever the tag or the data carried.
     */
    public static function of(mixed $value): ?string
    {
        if ($value instanceof Stringable) {
            $value = $value->__toString();
        }

        if (! is_string($value)) {
            return null;
        }

        $tag = str_replace('_', '-', (string) preg_replace('/[.@].*$/', '', trim($value)));

        return $tag === '' ? null : $tag;
    }
}
