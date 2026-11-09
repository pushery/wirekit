<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\HtmlString;

/**
 * The glyphs the kit draws itself in every instance of a component: the question mark of a field's
 * help, the check and the dash of a checkbox, the arrows of a select and a folding card, the
 * spinner of a busy button.
 *
 * Drawn inline, each instance carries its whole drawing, and a form of a hundred fields carries the
 * same paths a hundred times. With `wirekit.icons.sprite` on, `@wirekitScripts` writes one
 * `<symbol>` per glyph at the end of the page, outside every component, and each instance carries a
 * `<use>` that points there. The sprite always holds every glyph, so a component an update brings in
 * later finds its symbol too. Off by default: a page without `@wirekitScripts`, where a checkbox and a
 * select still work, would have no symbol to point at.
 *
 * A symbol carries its glyph's `viewBox` and presentation attributes, and `currentColor` resolves
 * against the instance's text color, in forced colors as well. The spinner's two shades are
 * `opacity` attributes rather than classes: a class from the page's stylesheet does not reliably
 * reach the copy a `<use>` draws.
 */
final class KitIcons
{
    /** @var array<string, array{viewBox: string, attributes: string, body: string}> */
    private const GLYPHS = [
        // A filled circle with the mark cut out, the same in every icon preset.
        'help' => [
            'viewBox' => '0 0 24 24',
            'attributes' => 'fill="currentColor"',
            'body' => '<path fill-rule="evenodd" clip-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm11.378-3.917c-.89-.777-2.366-.777-3.255 0a.75.75 0 0 1-.988-1.129c1.454-1.272 3.776-1.272 5.23 0 1.513 1.324 1.513 3.518 0 4.842a3.75 3.75 0 0 1-.837.552c-.676.328-1.028.774-1.028 1.152v.75a.75.75 0 0 1-1.5 0v-.75c0-1.279 1.06-2.107 1.875-2.502.182-.088.351-.199.503-.331.83-.727.83-1.857 0-2.584ZM12 18a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"/>',
        ],
        'check' => [
            'viewBox' => '0 0 24 24',
            'attributes' => 'fill="none" stroke="currentColor" stroke-width="3"',
            'body' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />',
        ],
        'dash' => [
            'viewBox' => '0 0 24 24',
            'attributes' => 'fill="none" stroke="currentColor" stroke-width="3"',
            'body' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />',
        ],
        'select-arrow' => [
            'viewBox' => '0 0 20 20',
            'attributes' => 'fill="currentColor"',
            'body' => '<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />',
        ],
        'card-arrow' => [
            'viewBox' => '0 0 20 20',
            'attributes' => 'fill="currentColor"',
            'body' => '<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />',
        ],
        'spinner' => [
            'viewBox' => '0 0 24 24',
            'attributes' => 'fill="none"',
            'body' => '<circle opacity="0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path opacity="0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>',
        ],
    ];

    /** The prefix of every symbol id, so no id of a page collides with one of these. */
    private const ID_PREFIX = 'wk-glyph-';

    /** Whether instances point into the sprite, read on every call so a middleware may set it. */
    public static function sprite(): bool
    {
        return BooleanProp::from(config('wirekit.icons.sprite', false), false);
    }

    /**
     * One glyph for one instance. The classes and the attribute text are the instance's own and
     * stay on its `<svg>`: what shows and hides it, its `aria-hidden`, its Alpine and Livewire
     * attributes. The classes go through the compact table like every class the kit writes; the
     * attribute text is written as given, so a caller escapes what it interpolates.
     */
    public static function svg(string $name, string $classes, string $attributes = 'aria-hidden="true"'): HtmlString
    {
        $glyph = self::GLYPHS[$name];
        $open = '<svg class="'.e(CompactClasses::map($classes)).'" '.$attributes;

        if (self::sprite()) {
            return new HtmlString($open.' viewBox="'.$glyph['viewBox'].'"><use href="#'.self::ID_PREFIX.$name.'"/></svg>');
        }

        return new HtmlString($open.' xmlns="http://www.w3.org/2000/svg" viewBox="'.$glyph['viewBox'].'" '.$glyph['attributes'].'>'.$glyph['body'].'</svg>');
    }

    /**
     * Every glyph as a symbol, once, while the sprite is on, and nothing while it is off. The zero
     * size and the place out of flow are the element's own attributes and two classes the views
     * spell, so a build that never scans this file still compiles them; `display: none` would cost a
     * symbol its rendering in some engines.
     */
    public static function spriteSheet(): HtmlString
    {
        if (! self::sprite()) {
            return new HtmlString('');
        }

        $symbols = '';

        foreach (self::GLYPHS as $name => $glyph) {
            $symbols .= '<symbol id="'.self::ID_PREFIX.$name.'" viewBox="'.$glyph['viewBox'].'" '.$glyph['attributes'].'>'.$glyph['body'].'</symbol>';
        }

        return new HtmlString('<svg aria-hidden="true" focusable="false" width="0" height="0" class="absolute overflow-hidden" data-wk-glyphs>'.$symbols.'</svg>');
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::GLYPHS);
    }
}
