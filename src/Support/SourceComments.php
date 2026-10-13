<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Remove comments from a source file before scanning it for anything else.
 *
 * ## Why this is its own class
 *
 * A scanner that reads raw text cannot tell a comment from code, and the two
 * failure directions are both real and both silent:
 *
 *   A reference INSIDE a comment surfaces as a live one. `var(--color-wk-X)` in
 *   a docblock reads as a token reference and reports a Tier-1 violation that
 *   does not exist, and a class inventory lists utilities only a comment names.
 *   Tailwind's own scanner shows the same class from the outside: it compiles a
 *   utility a comment names, so the stylesheet carries a selector no source
 *   emits.
 *
 *   And a stripper that is too eager destroys code. The line-comment rule below
 *   has to survive `https://`, or a `class="… https://x.io …"` attribute is cut
 *   at the colon and every utility to its right disappears from the inventory.
 *
 * It is a class of its own because more than one scanner needs exactly these
 * rules. Two copies of a stripper this delicate drift apart: one of them gains a
 * fix the other never sees, and neither reports anything when they disagree.
 *
 * Conservative on purpose, and it handles the four shapes this codebase mixes in
 * one file: block, line, HTML and Blade.
 */
final class SourceComments
{
    /**
     * Every comment replaced by its own newlines.
     *
     * Newlines survive, and that is not tidiness. Callers report findings as
     * `file:line`; a multi-line comment removed outright shifts every line after
     * it, so the one piece of information a reader uses to go and look points at
     * the wrong place — in exactly the files that carry the most prose.
     */
    public static function strip(string $contents): string
    {
        $keepLines = static fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n"));

        $stripped = preg_replace_callback('!/\*.*?\*/!s', $keepLines, $contents) ?? $contents;

        // The negative lookbehind for `:` excludes `https://`-style URLs; the one
        // for `/` excludes the second slash of an already-consumed `//`. Both are
        // load-bearing — see the class docblock for the attribute this saved.
        $stripped = preg_replace('~(?<![:/])//[^\n]*~', '', $stripped) ?? $stripped;
        $stripped = preg_replace_callback('/<!--.*?-->/s', $keepLines, $stripped) ?? $stripped;

        return preg_replace_callback('/\{\{--.*?--\}\}/s', $keepLines, $stripped) ?? $stripped;
    }
}
