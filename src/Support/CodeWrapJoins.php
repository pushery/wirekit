<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The hyphens inside the tokens of code that wraps, each held to the character after it.
 *
 * A line may break after a hyphen-minus that no digit follows (Unicode line breaking, class HY),
 * and a browser filling a line takes the latest place it may. In code that splits an operator
 * such as `->` or `-->`, or an option such as `--no-dev`, across two lines, and a reader sees two
 * characters where the source has one token. So a run of hyphens and the character after it stand
 * in an element the stylesheet does not wrap (`[data-wk-join]`): the line breaks at a space,
 * or anywhere in a token longer than the line. A hyphen before a space or a digit is left alone,
 * since it ends a token there, or the line breaking rules give no break after it anyway.
 *
 * Reads the text between the tags of the markup it is given, which is the code a caller writes in
 * the slot or the markup a highlighter returns. A tag ends at the first `>` outside a quoted
 * attribute value, and a comment, a morph marker included, is passed over whole. Only elements are
 * added, so the text a copy reads (`textContent`) stays the same.
 *
 * A highlighter that runs in the browser writes a block anew from its text and takes these elements
 * with it; `resources/js/utils/code-joins.js` writes them again by the same rule
 * (tests/fixtures/code-join-cases.json holds both to the same answers).
 *
 * @internal Read by the code block's view in its `wrap` mode, by inline code, by prose for the code
 *           of its body, by `WireKit::codeJoins()`, the public way to the rule of `html()`, and by
 *           `WireKit::proseCodeJoins()`, the public way to the rules of a prose body.
 */
final class CodeWrapJoins
{
    /** A run of hyphens and the one character after it, an entity counting as one character. */
    private const JOIN = '/-+(?:&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);|[^\s\d<&-])/iu';

    /**
     * An operator alone: hyphens before a character that is no letter, digit or space (`->`,
     * `-=`, `$i--;`), or a run of two or more that starts a token (`--force`). A hyphen inside a
     * name (`Retry-After`, `x-data`, `block--modifier`) joins nothing: in running text it is a
     * place where the name may break.
     */
    private const OPERATOR = '/(?<![\p{L}\p{N}_])-{2,}(?:&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);|[^\s\d<&-])|-+(?:&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);|[^\s<&\-\p{L}\p{N}])/iu';

    /** A comment, or a tag up to the first `>` outside a quoted attribute value. */
    private const MARKUP = '~(<!--.*?-->|<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~s';

    /**
     * In a prose body: a comment and an element whose content is not markup, both matched to be
     * passed over, since a `<pre>` written inside them is text there, then a `<pre>` with its
     * content, then a `<code>` with its content.
     */
    private const PROSE = '~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>'
        .'|(<pre\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(.*?)(</pre\s*>)'
        .'|(<code\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(.*?)(</code\s*>)~is';

    /** Every run of hyphens inside a token, for code that wraps as a block. */
    public static function html(string $html): string
    {
        return self::join($html, self::JOIN);
    }

    /** The operators alone, for code that stands in running text, where a name may break at a hyphen. */
    public static function operators(string $html): string
    {
        return self::join($html, self::OPERATOR);
    }

    /**
     * The joins of a prose body: those of `html()` in each `<pre>`, which prose wraps as a block,
     * and those of `operators()` in each inline `<code>`.
     *
     * A `<pre>` with a `tabindex` scrolls instead, since its author gave it a keyboard model, and an
     * element with `data-wk-prose-skip` belongs to a component, which sets joins of its own. Both
     * stay as they came, and so do a `<pre>` that holds a `<code>` with that mark and an element that
     * already holds a join. A body without a `<pre>` or a `<code>` comes back unchanged.
     */
    public static function inProse(string $html): string
    {
        // Only a body that holds a code block or inline code is read any further.
        if (preg_match('~<(?:pre|code)\b~i', $html) !== 1) {
            return $html;
        }

        return preg_replace_callback(
            self::PROSE,
            static function (array $match): string {
                $block = ($match[2] ?? '') !== '';
                $open = $block ? $match[2] : ($match[5] ?? '');
                $content = $block ? ($match[3] ?? '') : ($match[6] ?? '');

                if ($open === '' || preg_match('/\s(?:tabindex|data-wk-prose-skip)\b/i', $open) === 1 || str_contains($content, 'data-wk-join')) {
                    return $match[0];
                }

                // A block is matched whole, so a component's `<code>` inside a `<pre>` without the
                // mark is found here rather than by the branch for inline code.
                if ($block && preg_match('/<code\b[^>]*\sdata-wk-prose-skip\b/i', $content) === 1) {
                    return $match[0];
                }

                $close = $block ? ($match[4] ?? '') : ($match[7] ?? '');

                return $open.($block ? self::html($content) : self::operators($content)).$close;
            },
            $html,
        ) ?? $html;
    }

    private static function join(string $html, string $pattern): string
    {
        if (! str_contains($html, '-')) {
            return $html;
        }

        $parts = preg_split(self::MARKUP, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $index => $part) {
            // The parts at odd places are the tags and comments themselves.
            if ($index % 2 === 1 || ! str_contains($part, '-')) {
                continue;
            }

            // Text that is not valid UTF-8 cannot be read by code point, and stays as it came.
            $parts[$index] = preg_replace($pattern, '<span data-wk-join>$0</span>', $part) ?? $part;
        }

        return implode('', $parts);
    }
}
