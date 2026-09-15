<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

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
 */
final class SlotContent
{
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
     */
    public static function text(Htmlable|string|null $slot): string
    {
        $html = $slot instanceof Htmlable ? $slot->toHtml() : (string) $slot;

        return trim((string) preg_replace('/<!--[\s\S]*?-->/', '', $html));
    }
}
