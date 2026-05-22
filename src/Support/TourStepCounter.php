<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Per-render sequential counter for `<x-wirekit::tour.step>` children.
 *
 * Solves the bug class where every `tour.step` defaulted to
 * `index=0`, so all steps rendered with `data-wk-tour-step="0"` and
 * the tour's `next()` JS could only locate the first step.
 *
 * Mechanism: `<x-wirekit::tour>` calls `reset()` at the top of its
 * `@php` block; each `<x-wirekit::tour.step>` calls `next()` when
 * its own `$index` prop is null (the default), getting the next
 * sequential integer.
 *
 * Scope: per-PHP-process, single counter. Multiple tours rendered on
 * the same page work correctly because Blade evaluates slots inline
 * top-to-bottom — Tour A renders all its steps before Tour B starts,
 * and Tour B's `reset()` zeroes the counter when its parent block
 * runs. Each tour's Alpine scope is isolated via `x-data="wirekitTour(...)"`,
 * so per-tour `currentStep === N` matches that tour's own steps
 * even though numbering restarts.
 *
 * Edge case: developer-supplied `:index="N"` on a step bypasses the
 * counter entirely (the step's `@php` block only calls `next()` when
 * `$index === null`). Mixing explicit and implicit indexes within
 * the same tour is supported but discouraged — the resulting numbering
 * is the developer's responsibility.
 */
final class TourStepCounter
{
    private static int $counter = 0;

    /**
     * Reset the counter to zero. Called by `<x-wirekit::tour>`'s
     * `@php` block before the slot renders.
     */
    public static function reset(): void
    {
        self::$counter = 0;
    }

    /**
     * Return the current counter value AND increment it. Called by
     * `<x-wirekit::tour.step>`'s `@php` block when `$index` is null.
     */
    public static function next(): int
    {
        return self::$counter++;
    }
}
