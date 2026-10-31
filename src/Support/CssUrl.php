<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * An address as a CSS `url()` that a `style` attribute can carry.
 *
 * A value written into a `style` attribute is CSS, and escaping the attribute does not stop a `;`
 * there. The address stands in a quoted string, so a `)` or a `;` in it stays part of the address,
 * and the characters that would end that string are percent-encoded: a double quote, a backslash
 * and every control character. A browser encodes the first two the same way when it fetches the
 * address, and an address does not carry the third. The scheme is the caller's to check, through
 * SafeUrl, before the address reaches this class.
 */
final class CssUrl
{
    /**
     * The address as `url("…")`.
     */
    public static function of(string $url): string
    {
        $encoded = (string) preg_replace_callback(
            '/["\\\\\x00-\x1F\x7F]/',
            static fn (array $character): string => '%'.strtoupper(bin2hex($character[0])),
            $url,
        );

        return 'url("'.$encoded.'")';
    }
}
