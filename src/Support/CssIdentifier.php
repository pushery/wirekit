<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * An `id` written into a CSS selector.
 *
 * An `id` may carry any character except whitespace, and a selector may not. `#form.email-error`
 * reads as the id `form` with the class `email-error`, so it matches nothing, and
 * `#settings[due]-error` is no selector at all, so `querySelector()` throws. A field named
 * `form.email`, the name under which a Livewire form object finds its validation messages, gets
 * exactly such an id.
 *
 * {@see self::escape()} is the CSSOM's "serialize an identifier", the algorithm behind the
 * browser's `CSS.escape()`, so a selector built here matches the element a browser matches.
 */
final class CssIdentifier
{
    /** The selector for the element with this `id`. */
    public static function idSelector(string $id): string
    {
        return '#'.self::escape($id);
    }

    /**
     * The identifier escaped for a selector, as `CSS.escape()` returns it.
     *
     * @see https://drafts.csswg.org/cssom/#serialize-an-identifier
     */
    public static function escape(string $identifier): string
    {
        $characters = mb_str_split($identifier, 1, 'UTF-8');
        $escaped = '';

        foreach ($characters as $index => $character) {
            $codePoint = mb_ord($character, 'UTF-8');

            // NUL, and a byte sequence that is not UTF-8, which an HTML parser decodes to the
            // same replacement character before any selector sees the id.
            if ($codePoint === false || $codePoint === 0) {
                $escaped .= "\u{FFFD}";

                continue;
            }

            $isDigit = $codePoint >= 0x30 && $codePoint <= 0x39;

            // Control characters, and a digit where an identifier may not start with one: first,
            // or second after a hyphen. These go as a code point, `\31 ` for a leading `1`; the
            // space ends the escape, so a hex digit after it is not read as part of it.
            if (($codePoint <= 0x1F || $codePoint === 0x7F)
                || ($index === 0 && $isDigit)
                || ($index === 1 && $isDigit && $characters[0] === '-')) {
                $escaped .= '\\'.dechex($codePoint).' ';

                continue;
            }

            // A hyphen alone is not an identifier.
            if ($index === 0 && $character === '-' && count($characters) === 1) {
                $escaped .= '\\-';

                continue;
            }

            if ($codePoint >= 0x80 || $character === '-' || $character === '_' || preg_match('/^[0-9A-Za-z]$/', $character) === 1) {
                $escaped .= $character;

                continue;
            }

            // Everything else a selector would read as syntax: `.`, `:`, `[`, `#`, a quote, a space.
            $escaped .= '\\'.$character;
        }

        return $escaped;
    }
}
