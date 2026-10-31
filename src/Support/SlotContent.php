<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Stringable;

/**
 * A slot read somewhere its HTML comments are not comments.
 *
 * Livewire wraps every `@if` and `@foreach` in `<!--[if BLOCK]><![endif]-->` and
 * `<!--[if ENDBLOCK]><![endif]-->` while a component renders, so a caller who fills a
 * slot conditionally hands the component those comments along with the text. Rendered
 * into the page as markup they belong there: they are real comments, and Livewire's
 * morph aligns the conditional block by them. Read anywhere else they are text. An
 * attribute value escapes them into the accessible name, the RCDATA of a `<textarea>`
 * shows them as the field's value, and structured data records them as part of the
 * answer.
 *
 * So this is for those places only, and never for a slot echoed as markup. The pattern
 * is the one `ComponentSlot::hasActualContent()` uses, so "is there content?" and "what
 * is the content?" agree on what a comment is.
 *
 * given() answers the first of those questions for a view that decides whether to draw a
 * slot at all: a slot of nothing but those comments renders as the same call without it.
 */
final class SlotContent
{
    /**
     * Whether the caller gave the slot anything to show.
     *
     * A view asks `given($footer ?? null)`, since a slot nobody passed is no variable at all.
     * Markup counts without its comments, so the markers a caller's `@if` leaves behind when
     * it is false are no slot. A plain string is text, and anything in it but white space shows.
     */
    public static function given(Htmlable|Stringable|string|null $slot): bool
    {
        if ($slot instanceof Htmlable) {
            return self::text($slot) !== '';
        }

        return $slot !== null && trim((string) $slot) !== '';
    }

    /**
     * The slot's markup with every HTML comment removed, trimmed.
     *
     * An `HtmlString`, because the markup keeps the provenance of the slot itself:
     * whatever the caller interpolated was escaped by Blade on the way in, exactly as
     * it is when the slot is echoed with `{{ $slot }}`.
     */
    public static function withoutComments(Htmlable|string|null $slot): HtmlString
    {
        return new HtmlString(self::text($slot));
    }

    /**
     * The same, as a plain string for an attribute or a value.
     *
     * Still markup: a name the caller interpolated arrives as `Tom&#039;s &amp; Jerry`, which is
     * right where the string is written as HTML, as in a `<textarea>`, and wrong anywhere that
     * escapes it again. Text for a name, an attribute or a comparison is plain().
     */
    public static function text(Htmlable|string|null $slot): string
    {
        $html = $slot instanceof Htmlable ? $slot->toHtml() : (string) $slot;

        return trim((string) preg_replace('/<!--[\s\S]*?-->/', '', $html));
    }

    /**
     * The slot's text as a reader gets it: tags and comments gone, the entities in it resolved,
     * white space collapsed as the browser collapses it.
     *
     * For a name, an attribute value or a comparison, which take text and escape it once
     * themselves. Read as text() reads it, a name the caller interpolated reaches the reader as
     * `Tom&#039;s &amp; Jerry`.
     */
    public static function plain(Htmlable|string|null $slot): string
    {
        $text = html_entity_decode(strip_tags(self::text($slot)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // A no-break space stays, as it does in the text: it holds a figure such as `1 234 €` together.
        return trim((string) preg_replace('/[ \t\n\f\r]+/', ' ', $text), " \t\n\f\r");
    }

    /**
     * The text of a prop that takes text or markup, such as a `label`.
     *
     * An Htmlable is markup, read as plain() reads a slot. A string is text already: its tags, if it
     * has any, are what the reader sees, since the view escapes the string where it shows it. A
     * boolean is no text.
     */
    public static function textOf(Htmlable|Stringable|string|int|float|bool|null $value): string
    {
        if ($value instanceof Htmlable) {
            return self::plain($value);
        }

        return is_bool($value) ? '' : trim((string) $value);
    }
}
