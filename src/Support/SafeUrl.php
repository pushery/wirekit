<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Stringable;

/**
 * A link target that came from data, reduced to one that cannot run script.
 *
 * Escaping does not stop a `javascript:` URL. `href="javascript:…"` is perfectly escaped HTML, and
 * it runs in the application's origin when the reader clicks. A URL a developer writes into a
 * template is their own decision; a URL from a table row, a notification, an assistant's citation
 * or a tenant list is data, often data somebody else typed, and every component that binds one to
 * an `href` passes it through here first.
 *
 * The scheme is read the way the browser reads it. Before it looks for a scheme, the URL parser
 * drops control characters and spaces from the start and removes tabs and newlines anywhere, so
 * `\x01javascript:` and `java\tscript:` are both `javascript:` to it, and a check on the raw string
 * lets both through. A scheme is a letter followed by letters, digits, `+`, `-` or `.`, ended by a
 * colon. Anything else before the first colon, a slash or a space, makes the URL relative, and a
 * relative URL stays on the page's own scheme.
 *
 * `resources/js/utils/safe-href.js` applies the same rule to values that arrive in the browser, and
 * both give the same answer for the same value.
 */
final class SafeUrl
{
    /** The schemes a link may use. A URL without a scheme is relative and always kept. */
    public const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** The schemes a frame or an image may load from. */
    public const LOAD_SCHEMES = ['http', 'https'];

    /**
     * The URL without surrounding whitespace, or '' when its scheme is not one of `$schemes`.
     *
     * '' is also what an empty or absent URL gives, so a caller has one value to test for "no link".
     *
     * @param  list<string>  $schemes  lowercase
     */
    public static function href(string|Stringable|null $url, array $schemes = self::LINK_SCHEMES): string
    {
        $href = self::trim((string) $url);

        return self::allows($href, $schemes) ? $href : '';
    }

    /**
     * Whether the browser would read no scheme from the URL, or one of `$schemes`.
     *
     * @param  list<string>  $schemes  lowercase
     */
    public static function allows(string $url, array $schemes = self::LINK_SCHEMES): bool
    {
        $scheme = self::scheme($url);

        return $scheme === null || in_array($scheme, $schemes, true);
    }

    /**
     * The scheme the browser reads from the URL, lowercase, or null for a relative URL.
     */
    public static function scheme(string $url): ?string
    {
        // Control characters and spaces before the URL, then tabs and newlines anywhere in it.
        $url = str_replace(["\t", "\n", "\r"], '', ltrim($url, "\x00..\x20"));

        return preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $match) === 1 ? strtolower($match[1]) : null;
    }

    /**
     * Whitespace off both ends: the set `String.prototype.trim()` removes, so the value a link
     * carries is the same whether the server or the browser prepared it.
     *
     * A string that is not valid UTF-8 gives '', which every caller reads as "no link".
     */
    private static function trim(string $url): string
    {
        return (string) preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $url);
    }
}
