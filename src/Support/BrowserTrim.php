<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * `String.prototype.trim()`, for a value the server and the browser both prepare.
 *
 * A link target or a color is checked on the server and again in the browser, and the two only
 * agree when they strip the same characters first. The set is the one JavaScript's `\s` matches,
 * and it is written out character by character, for two reasons a shorthand does not survive.
 *
 * `\s` asks the process's locale which characters are spaces: on macOS it takes U+0085, which the
 * browser keeps, and under two legacy locales a pattern that used `\p{Z}` beside it did not
 * compile at all. A class of literal code points reads the same everywhere.
 *
 * And the trailing run is matched only from its first character. Tried from every position of a
 * long run inside the string, the same pattern costs quadratic time on a PHP without the PCRE JIT:
 * 1.6 s for 16,000 spaces, against under a millisecond this way.
 *
 * @internal
 */
final class BrowserTrim
{
    /** A character class of everything JavaScript's `\s` matches. For a pattern with the `u` flag. */
    public const SPACE = '[\x09-\x0D\x20\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    /**
     * The value without the whitespace at its ends, or null when it is not valid UTF-8.
     */
    public static function trim(string $value): ?string
    {
        $space = self::SPACE;

        return preg_replace("/^{$space}+|(?<!{$space}){$space}+$/u", '', $value);
    }
}
