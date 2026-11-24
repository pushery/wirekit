<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Where a panel stands that the overlay around it may hold back until it is needed (`lazy`).
 *
 * A panel that is a view of its own, as a dropdown's panel or a menubar's menu is, renders before
 * the overlay that holds it in the explicit form, where the caller writes the panel into the
 * overlay's slot. It cannot know whether that overlay is lazy, and a value it read from the
 * components around it through `@aware` would be the first one any of them was given: a dropdown
 * inside a lazy popover would hold its panel back while the dropdown itself builds nothing. So the
 * panel marks where the template that holds it back opens and closes, and the overlay writes that
 * template there, or nothing, from its own `lazy` (resolve()). The browser keeps a template's
 * content out of the page until `utils/lazy-panel.js` builds it.
 *
 * The marks of the template are comments, the mark of the element it stands in an attribute, and
 * resolve() replaces every one in the markup it is given: a nested overlay has resolved the marks
 * of its own panels as it rendered, before the overlay around it reads its slot, so every mark left
 * belongs to a panel of this overlay.
 */
final class LazyPanel
{
    /** Where the template that holds a panel back opens. */
    public const string OPEN = '<!--wk-lazy-panel-->';

    /** Where it closes. */
    public const string CLOSE = '<!--/wk-lazy-panel-->';

    /**
     * An attribute of the element the template stands in, written after a space: it becomes
     * `data-wk-lazy-panel-host`, by which the render hook of `utils/lazy-entries.js` finds the
     * template, or goes.
     */
    public const string HOST = 'data-wk-lazy-host-mark';

    /**
     * The markup with each mark replaced by the template's tag, or the host's attribute, when
     * `$lazy`, and by nothing otherwise.
     */
    public static function resolve(string $html, bool $lazy): string
    {
        return str_replace(
            [self::OPEN, self::CLOSE, ' '.self::HOST],
            $lazy ? ['<template data-wk-lazy-panel>', '</template>', ' data-wk-lazy-panel-host'] : ['', '', ''],
            $html,
        );
    }
}
