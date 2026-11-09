<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Break opportunities at the seams of an address, for `break="address"` on `text`, `heading`
 * and `code`.
 *
 * An email address, a host name or a URL has no space in it, so a browser wraps it wherever the
 * line ends, and `overflow-wrap: anywhere` cuts it inside a part: `example.or` over a lone `g`.
 * CSS cannot prefer one character over another as a break point, since under the Unicode line
 * breaking rules a full stop or an at sign between letters is no break opportunity. A `<wbr>` is
 * one, and it adds no character, so the text a reader selects and copies is still the address.
 *
 * The seams are the ones an address is set at in print: before an at sign, a full stop, a
 * question mark, a number sign, an ampersand and an equals sign, before a slash that follows
 * neither a colon nor another slash, and after the double slash of a scheme. A seam opens only
 * between two characters that are no space, and not after a seam of its own, so a full stop that
 * ends a sentence never starts a line, and `//` stays together.
 *
 * Only text is marked. A tag with its attributes, a comment and the content of a `script` or
 * `style` element pass through as they are, so a link's `href` keeps its value. A character
 * reference counts as the character it stands for, and the text is read across the tags in it,
 * so `ada@<b>example</b>.org` has its seam before the full stop all the same.
 *
 * @internal Called by the component views; the prop is the API.
 */
final class AddressBreaks
{
    /** A break may come before each of these. A slash is decided apart, see seamBefore(). */
    private const SEAMS = ['@', '.', '?', '#', '&', '=', '/'];

    public static function mark(Htmlable|string $content): HtmlString
    {
        $html = $content instanceof Htmlable ? $content->toHtml() : e($content);

        // A tag ends at the first `>` outside a quoted attribute value, so `title="1 > 0.5"` stays
        // inside its tag.
        $parts = preg_split('~(<!--.*?-->|<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return new HtmlString($html);
        }

        // Every character of the text as one unit, a character reference included, with the part
        // it stands in; the seams are decided over all of them, so a tag between two characters
        // does not hide the one before.
        $units = [];
        $raw = null;

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                if ($raw === null && preg_match('~^<(script|style)\b~i', $part, $open) === 1) {
                    $raw = strtolower($open[1]);
                } elseif ($raw !== null && preg_match('~^</'.$raw.'\b~i', $part) === 1) {
                    $raw = null;
                }

                continue;
            }

            if ($raw !== null || $part === '') {
                continue;
            }

            if (preg_match_all('~&(?:#[0-9]+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);|.~su', $part, $found) === false) {
                continue;
            }

            foreach ($found[0] as $unit) {
                $character = $unit[0] === '&' && strlen($unit) > 1
                    ? html_entity_decode($unit, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    : $unit;

                $units[] = ['part' => $index, 'unit' => $unit, 'character' => $character];
            }
        }

        $characters = array_column($units, 'character');
        $marked = array_fill_keys(array_keys($parts), null);

        foreach ($units as $position => $unit) {
            $marked[$unit['part']] = ($marked[$unit['part']] ?? '')
                .(self::seamBefore($characters, $position) ? '<wbr>' : '')
                .$unit['unit'];
        }

        $html = '';

        foreach ($parts as $index => $part) {
            $html .= $marked[$index] ?? $part;
        }

        return new HtmlString($html);
    }

    /**
     * Whether a break may come before the character at this position.
     *
     * @param  array<int, string>  $characters
     */
    private static function seamBefore(array $characters, int $position): bool
    {
        $character = $characters[$position];
        $before = $characters[$position - 1] ?? null;

        if ($before === null || self::isSpace($before)) {
            return false;
        }

        if (in_array($character, self::SEAMS, true)) {
            $after = $characters[$position + 1] ?? null;

            return $after !== null
                && ! self::isSpace($after)
                && ! in_array($before, self::SEAMS, true)
                && ! ($character === '/' && $before === ':');
        }

        // After the double slash of a scheme: `https://` and the host on the next line.
        return $before === '/'
            && ($characters[$position - 2] ?? null) === '/'
            && ($characters[$position - 3] ?? null) === ':'
            && ! self::isSpace($character);
    }

    private static function isSpace(string $character): bool
    {
        return preg_match('~^[\s\x{00A0}]$~u', $character) === 1;
    }
}
