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
 * Mechanism: each `<x-wirekit::tour.step>` calls `next()` when its own
 * `$index` prop is null (the default), getting the next sequential
 * integer. Blade renders a component's slot BEFORE its view, so a
 * tour's steps have drawn their numbers by the time `<x-wirekit::tour>`'s
 * own `@php` block runs; its `reset()` readies the counter for the next
 * tour on the page, not for its own steps.
 *
 * Scope: per-PHP-process, single counter, so the first tour of a render
 * relies on starting at zero. The service provider resets it after
 * each request and before each queued job, and `WireKit::flush()` in
 * tests; without that, a render that stopped between a step and its
 * tour left the counter mid-count, and the next tour in a long-running
 * process numbered its steps from there while `tour.js` looks for step 0.
 * Each tour's Alpine scope is isolated via `x-data="wirekitTour(...)"`,
 * so per-tour `currentStep === N` matches that tour's own steps even
 * though numbering restarts.
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
     * Reset the counter to zero. Called by `<x-wirekit::tour>`'s `@php` block, which
     * runs after its steps, and between renders by the service provider.
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
