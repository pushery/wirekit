<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;
use Illuminate\Support\HtmlString;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Icons\IconSetPackages;

/**
 * Icons for rows that Alpine renders, drawn once as `<symbol>`s and referenced from each row.
 *
 * `<x-wirekit::icon>` renders its SVG on the server, and a row stamped out by `x-for` in the
 * browser cannot call it. A `<symbol>` per icon with a `<use>` per row adds one small element to
 * each row. A `<template x-if>` per icon in the row would clone a branch for every icon the row
 * might show. Rows rendered on the server and filtered with `x-show` would multiply the HTML,
 * make every Livewire update morph every row, and replace the keyboard model of both
 * components, which indexes a filtered array. `x-html` is an injection sink and inert on the
 * CSP build.
 *
 * The symbol carries the icon's own `viewBox` and presentation attributes, so an outline set
 * keeps `fill="none"` and its stroke, and `currentColor` resolves against the row's text color.
 * Both engines were checked to paint it that way. Symbol ids are derived from the icon NAME,
 * not from its position, so a Livewire update that renders the same icons keeps the same ids.
 */
final class IconSprite
{
    /** Root attributes a symbol keeps; everything else on the icon's `<svg>` is dropped. */
    private const CARRIED = [
        'viewbox', 'preserveaspectratio', 'fill', 'fill-rule', 'fill-opacity', 'clip-rule',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
        'stroke-dasharray', 'stroke-opacity', 'opacity',
    ];

    /**
     * Replaces the icon NAME on each option that has one with a reference into the sprite, and
     * returns the sprite. An icon that cannot be drawn takes its option's medium with it, the
     * same way `<x-wirekit::icon>` degrades to an empty placeholder rather than failing the page.
     *
     * @param  list<array<string, mixed>>  $options
     * @return array{0: list<array<string, mixed>>, 1: HtmlString}
     */
    public static function attach(array $options, string $idPrefix): array
    {
        $refs = [];
        $symbols = [];

        foreach ($options as $index => $option) {
            if (($option['media'] ?? null) !== 'icon' || ! is_string($option['icon'] ?? null)) {
                continue;
            }

            $name = $option['icon'];

            if (! array_key_exists($name, $refs)) {
                $id = $idPrefix.'-icon-'.substr(md5($name), 0, 8);
                $symbol = self::symbol($name, $id);
                $refs[$name] = $symbol === null ? null : '#'.$id;

                if ($symbol !== null) {
                    $symbols[] = $symbol;
                }
            }

            unset($options[$index]['icon']);

            if ($refs[$name] === null) {
                unset($options[$index]['media']);
            } else {
                $options[$index]['iconRef'] = $refs[$name];
            }
        }

        // Not `display: none`: a symbol inside an undisplayed `<svg>` loses its gradients and
        // clip paths in some engines. A zero-size box out of flow keeps it rendered and invisible.
        //
        // The zero size is the element's own attributes, never a utility. No build
        // scans this file: `size-0` was a class no view names, so it was compiled nowhere, and the
        // sprite kept the 300 x 150 px of a replaced element, under the field and over the next
        // one. `absolute` and `overflow-hidden` stay because views spell them. A presentation
        // attribute is not a `style` attribute, so a CSP without inline styles leaves it alone.
        $sprite = $symbols === []
            ? ''
            : '<svg aria-hidden="true" focusable="false" width="0" height="0" class="absolute overflow-hidden">'.implode('', $symbols).'</svg>';

        return [$options, new HtmlString($sprite)];
    }

    private static function symbol(string $name, string $id): ?string
    {
        if (! function_exists('svg')) {
            return null;
        }

        $resolved = app(IconResolver::class)->resolve($name);

        if ($resolved === '') {
            return null;
        }

        try {
            $contents = svg($resolved)->contents();
        } catch (SvgNotFound $e) {
            // The same split `<x-wirekit::icon>` makes: loud where somebody can fix the build,
            // degraded in a request, where failing the page teaches the reader nothing.
            if (StrictnessGate::shouldThrowOnInvalid()) {
                throw $e;
            }

            IconSetPackages::reportMissingSetOnce($name, $resolved);

            return null;
        }

        if (preg_match('/<svg\b([^>]*)>(.*)<\/svg>/is', $contents, $svg) !== 1) {
            return null;
        }

        $attributes = '';
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*("[^"]*"|\'[^\']*\')/', $svg[1], $pairs, PREG_SET_ORDER);

        foreach ($pairs as $pair) {
            if (in_array(strtolower($pair[1]), self::CARRIED, true)) {
                $attributes .= ' '.$pair[1].'='.$pair[2];
            }
        }

        return '<symbol id="'.e($id).'"'.$attributes.'>'.trim($svg[2]).'</symbol>';
    }
}
