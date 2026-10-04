<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Stringable;

/**
 * A URL a component took as a prop, read as the URL it stands for.
 *
 * Blade escapes a value echoed into a component tag before the component receives it:
 * `href="{{ $url }}"` arrives as `/x?a=1&amp;b=2`, and so does a value another component passes on
 * through its attribute bag. A bound value, `:href="$url"`, arrives as written. A view that writes
 * the first with `{{ }}` escapes it a second time, `&amp;amp;b=2`, and the browser follows
 * `/x?a=1&amp;b=2`: the server reads a parameter named `amp;b`, and a signed link loses its
 * signature. Read here, both arrive as the same URL, which the view escapes once.
 *
 * Only what Blade's escape writes is undone: `&amp;`, `&lt;`, `&gt;`, `&quot;` and `&#039;`. A
 * character reference Blade never writes, `&#106;` or `&colon;`, stays text, because decoding one
 * could turn `&#106;avascript:` in a bound value from data into a script URL. None of the five
 * characters the undo restores can begin a scheme, so the URL keeps the scheme it was read with.
 *
 * @internal Called from the component views; not a developer-facing API.
 */
final class UrlProp
{
    /**
     * The URL with Blade's escape undone, or null for no URL.
     */
    public static function text(string|Stringable|BackedEnum|null $url): ?string
    {
        if ($url === null) {
            return null;
        }

        // A backed enum is its value, as it is to Blade's own echo.
        $text = (string) ($url instanceof BackedEnum ? $url->value : $url);

        // Every one of the five references begins with an ampersand.
        return str_contains($text, '&') ? htmlspecialchars_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : $text;
    }
}
