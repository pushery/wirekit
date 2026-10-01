<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
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
 * It is read a second time with its character references decoded. `{{ }}` escapes an ampersand,
 * and an application that has called `Blade::withoutDoubleEncoding()` tells it to leave one that
 * begins a character reference as it is. The browser then reads `&#106;avascript:` in the attribute
 * as `javascript:`, after a check on the text took it for a relative URL. A URL is kept only when
 * both readings are allowed, so the link is the same whichever way the page echoes it.
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
     * The named character references that decode to a character a scheme can hold, or one the URL
     * parser removes before it reads the scheme. Every other name decodes to a character that ends
     * the scheme, which is what the ampersand it begins with does as well.
     */
    private const SCHEME_REFERENCES = ['Tab' => "\t", 'NewLine' => "\n", 'colon' => ':', 'plus' => '+', 'period' => '.', 'fjlig' => 'fj'];

    /**
     * The URL without surrounding whitespace, or '' when its scheme is not one of `$schemes`.
     *
     * '' is also what an empty or absent URL gives, so a caller has one value to test for "no link".
     *
     * @param  list<string>  $schemes  lowercase
     */
    public static function href(string|Stringable|BackedEnum|null $url, array $schemes = self::LINK_SCHEMES): string
    {
        // A backed enum is its value, as it is to Blade's own echo.
        $href = self::trim((string) ($url instanceof BackedEnum ? $url->value : $url));

        return self::allows($href, $schemes) ? $href : '';
    }

    /**
     * Whether the browser would read no scheme from the URL, or one of `$schemes`, both from the
     * text and from the text with its character references decoded.
     *
     * @param  list<string>  $schemes  lowercase
     */
    public static function allows(string $url, array $schemes = self::LINK_SCHEMES): bool
    {
        if (! self::isOneOf(self::scheme($url), $schemes)) {
            return false;
        }

        // Only an ampersand can begin a character reference.
        if (! str_contains($url, '&')) {
            return true;
        }

        $decoded = self::withReferencesDecoded($url);

        return $decoded !== null && self::isOneOf(self::scheme($decoded), $schemes);
    }

    /**
     * The scheme the browser reads from the URL, lowercase, or null for a relative URL.
     */
    public static function scheme(string $url): ?string
    {
        // Control characters and spaces before the URL, then tabs and newlines anywhere in it.
        $url = str_replace(["\t", "\n", "\r"], '', ltrim($url, "\x00..\x20"));

        // The class is written out rather than matched without regard to case: that flag takes its
        // letter pairs from the process's locale, and `strtolower()` is ASCII-only.
        return preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $url, $match) === 1 ? strtolower($match[1]) : null;
    }

    /**
     * Whether a scheme is none, which is a relative URL, or one of `$schemes`.
     *
     * @param  list<string>  $schemes  lowercase
     */
    private static function isOneOf(?string $scheme, array $schemes): bool
    {
        return $scheme === null || in_array($scheme, $schemes, true);
    }

    /**
     * The URL as an HTML parser reads it from an attribute whose character references were left
     * standing: every numeric reference, with or without its semicolon, and the named ones that
     * matter to a scheme. Null when the pattern could not be applied.
     */
    private static function withReferencesDecoded(string $url): ?string
    {
        return preg_replace_callback(
            '/&(?:#(?:[xX]([0-9A-Fa-f]+)|([0-9]+));?|(Tab|NewLine|colon|plus|period|fjlig);)/',
            static function (array $reference): string {
                $name = $reference[3] ?? '';

                if ($name !== '') {
                    return self::SCHEME_REFERENCES[$name];
                }

                $hexadecimal = $reference[1] ?? '';
                $digits = ltrim($hexadecimal !== '' ? $hexadecimal : ($reference[2] ?? ''), '0');

                // U+10FFFF is the last code point: six hexadecimal digits, seven decimal ones.
                $point = strlen($digits) > 7 ? 0 : intval($digits, $hexadecimal !== '' ? 16 : 10);

                // The parser puts U+FFFD in place of a reference to U+0000, to a surrogate or to a
                // number past the last code point.
                if ($point === 0 || $point > 0x10FFFF || ($point >= 0xD800 && $point <= 0xDFFF)) {
                    return "\u{FFFD}";
                }

                $character = mb_chr($point, 'UTF-8');

                return $character === false ? "\u{FFFD}" : $character;
            },
            $url,
        );
    }

    /**
     * Whitespace off both ends: the set `String.prototype.trim()` removes, so the value a link
     * carries is the same whether the server or the browser prepared it.
     *
     * A string that is not valid UTF-8 gives '', which every caller reads as "no link".
     */
    private static function trim(string $url): string
    {
        return BrowserTrim::trim($url) ?? '';
    }
}
