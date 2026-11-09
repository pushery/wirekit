<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Closure;
use Illuminate\Support\HtmlString;

/**
 * The numbered links of a pagination, written as one string.
 *
 * Each entry of a paginator's link collection is one element: a gap, the current page or a link
 * to a page. Written by a loop of Blade blocks, every entry carried two of Livewire's morph
 * markers and the loop two more, and every update sent them again. The entries are elements of
 * one kind in one row, which the morph matches in order, and in a Livewire pagination by their
 * `wire:key`, so they need no markers to keep their place.
 *
 * Every value goes through `e()`, as the template's `{{ }}` did. A label is written as it comes,
 * as the template wrote it with `{!! !!}`: Laravel's paginator builds it from the page number, or
 * as a pre-encoded entity for a gap, never from a request. An extension of the paginator that lets
 * a request reach the label has to escape it before it gets here.
 */
final class PaginationLinks
{
    /**
     * @param  iterable<int, array{url: string|null, label: string, active: bool}>  $links  the paginator's links, without its arrows
     * @param  Closure(string|null): (string|null)  $href  the absolute URL of a page
     * @param  (Closure(int): string)|null  $action  the Livewire action that opens a page, in a Livewire pagination
     * @param  string|null  $pageName  the paginator's page name, in a Livewire pagination
     * @param  string|null  $landedHandler  the Alpine expression the current page runs as it arrives
     * @param  string|null  $scrollHandler  the Alpine expression a link runs when it is clicked
     */
    public static function numbered(
        iterable $links,
        Closure $href,
        string $linkClass,
        string $gapClass,
        string $currentClass,
        ?Closure $action,
        ?string $pageName,
        ?string $landedHandler,
        ?string $scrollHandler,
    ): HtmlString {
        $html = [];
        $index = 0;

        foreach ($links as $link) {
            $label = $link['label'];

            if ($link['url'] === null) {
                $html[] = '<span class="'.e($gapClass).'" aria-hidden="true"'
                    .($action !== null ? ' wire:key="'.e('paginator-'.$pageName.'-gap'.$index).'"' : '')
                    .'>'.$label.'</span>';
            } elseif ($link['active']) {
                $html[] = '<span class="'.e($currentClass).'" aria-current="page"'
                    .($action !== null ? ' wire:key="'.e('paginator-'.$pageName.'-page'.$label).'" tabindex="-1"' : '')
                    .($landedHandler !== null ? ' x-init="'.e($landedHandler).'"' : '')
                    .'>'.$label.'</span>';
            } else {
                $html[] = '<a data-wk-prose-skip href="'.e($href($link['url'])).'" class="'.e($linkClass).'"'
                    .' aria-label="'.e(__('wirekit::Go to page :page', ['page' => $label])).'"'
                    .($action !== null ? ' wire:click.prevent="'.e($action((int) $label)).'" wire:key="'.e('paginator-'.$pageName.'-page'.$label).'"' : '')
                    .($scrollHandler !== null ? ' x-on:click="'.e($scrollHandler).'"' : '')
                    .'>'.$label.'</a>';
            }

            $index++;
        }

        return new HtmlString(implode("\n", $html));
    }
}
