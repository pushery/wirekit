<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\HtmlString;

/**
 * The named icons of navigation rows, drawn once per page.
 *
 * A navigation names a few icons in many rows, and drawn whole, every row carries every path of
 * its icon. With `wirekit.icons.navigation_sprite` on, a row of a sidebar or an app rail carries a
 * `<use>` that points at a symbol, and `@wirekitScripts` writes one `<symbol>` for each icon the
 * rows above it named, at the end of the page and outside every component.
 *
 * A row keeps the `<svg>` tag of its whole drawing, its classes and presentation attributes
 * included, and only the paths move into the symbol. What a `<use>` draws inherits from that tag,
 * so `currentColor`, an outline set's stroke and a class that changes the stroke of the row reach
 * the paths in the symbol as they reach the paths of a whole drawing. A rule of the page's
 * stylesheet that reaches into the drawing, such as `svg path`, does not reach that copy. The
 * symbol carries the drawing's `viewBox`, which every icon set starts at `0 0`; a drawing whose
 * `viewBox` starts elsewhere is drawn whole.
 *
 * A row is drawn whole where no symbol follows it: in a Livewire update, which renders a component
 * without the page around it and leaves the page's symbols as they are, and after the symbols have
 * been written. A symbol's id comes from the icon's name, so an icon keeps its id in every render.
 */
final class NavigationIcons
{
    /** @var array<string, string> the symbols the rows named since the last sheet, by id */
    private static array $symbols = [];

    /** Whether this render has written its sheet; a row after it is drawn whole. */
    private static bool $written = false;

    /** Whether rows point into the sheet, read on every call so a middleware may set it. */
    public static function sprite(): bool
    {
        return BooleanProp::from(config('wirekit.icons.navigation_sprite', false), false);
    }

    /**
     * The icon of a navigation row: a `<use>` that points at the symbol the next sheet writes, or
     * the whole drawing where no sheet follows.
     *
     * @param  string  $name  the icon's resolved name, which keys its symbol
     * @param  string  $whole  the drawing with the row's attributes, as `svg()->toHtml()` writes it
     * @param  string  $contents  the drawing as its file holds it, as `svg()->contents()` returns it
     */
    public static function row(string $name, string $whole, string $contents): HtmlString
    {
        // The header Livewire's update requests carry. A `wire:navigate` visit does not carry it:
        // it renders the whole page, the sheet included.
        if (! self::sprite() || self::$written || request()->hasHeader('X-Livewire')) {
            return new HtmlString($whole);
        }

        $parts = self::split($whole, $contents);

        if ($parts === null) {
            return new HtmlString($whole);
        }

        [$tag, $viewBox, $body] = $parts;
        $id = 'wk-icon-'.substr(md5($name), 0, 8);

        self::$symbols[$id] ??= '<symbol id="'.$id.'"'.($viewBox === null ? '' : ' viewBox="'.e($viewBox).'"').'>'.trim($body).'</symbol>';

        return new HtmlString($tag.'<use href="#'.$id.'"/></svg>');
    }

    /**
     * The symbols of the icons the rows named, once, and nothing where they named none. A row
     * rendered after it is drawn whole, since no second sheet would write its symbol.
     *
     * The zero size and the place out of flow are the element's own attributes and two classes
     * the views spell, so a build that never scans this file still compiles them; `display: none`
     * would cost a symbol its rendering in some engines.
     */
    public static function sheet(): HtmlString
    {
        $symbols = self::$symbols;
        self::$symbols = [];
        self::$written = true;

        if ($symbols === []) {
            return new HtmlString('');
        }

        return new HtmlString('<svg aria-hidden="true" focusable="false" width="0" height="0" class="absolute overflow-hidden" data-wk-icons>'.implode('', $symbols).'</svg>');
    }

    /** Forget the symbols of one render, and that its sheet was written. */
    public static function reset(): void
    {
        self::$symbols = [];
        self::$written = false;
    }

    /**
     * The tag of the whole drawing, its `viewBox` and its paths, or null where they cannot be told
     * apart.
     *
     * `svg()->toHtml()` writes the row's attributes into the file's own `<svg` and leaves the rest
     * of the file as it is, so the row's tag is the file's tag widened by what was written into it.
     * Its end is found that way rather than at its first `>`: a class such as `[&>path]:stroke-2`
     * carries one, and blade-icons writes attribute values as they come. The paths are what lies
     * between the file's tag and the `</svg>` that ends the file.
     *
     * @return array{0: string, 1: string|null, 2: string}|null
     */
    private static function split(string $whole, string $contents): ?array
    {
        $closed = rtrim($contents);

        if (substr_count($contents, '<svg') !== 1 || ! str_ends_with($closed, '</svg>') || preg_match('/<svg\b[^>]*>/', $contents, $file, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        [$fileTag, $start] = $file[0];
        $end = strlen($closed) - strlen('</svg>');
        $tag = substr($whole, $start, strlen($fileTag) + strlen($whole) - strlen($contents));

        // The reading holds only where its parts give the whole drawing back.
        if ($end < $start + strlen($fileTag) || substr($contents, 0, $start).$tag.substr($contents, $start + strlen($fileTag)) !== $whole) {
            return null;
        }

        $viewBox = null;

        if (preg_match('/\sviewBox\s*=\s*["\x27]([^"\x27]*)["\x27]/i', $fileTag, $box) === 1) {
            $numbers = preg_split('/[\s,]+/', trim($box[1])) ?: [];

            if (count($numbers) !== 4 || (float) $numbers[0] !== 0.0 || (float) $numbers[1] !== 0.0) {
                return null;
            }

            $viewBox = $box[1];
        }

        return [$tag, $viewBox, substr($contents, $start + strlen($fileTag), $end - $start - strlen($fileTag))];
    }
}
