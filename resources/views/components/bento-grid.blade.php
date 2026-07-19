@props([
    // Accessible name for the showcase. A bento is a set of related feature
    // claims, so a screen reader should get a boundary and a name for it rather
    // than a wall of loose headings.
    'label' => null,
    'gap' => config('wirekit.components.bento-grid.gap', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Match on the RESULT, not the raw prop: validateProp throws in strict mode
    // and otherwise returns the fallback, so an invalid value can never reach the
    // class map. 'md' leads the list because validateProp falls back to the first
    // allowed value — a bad gap should land on the normal gap, not on none.
    $gap = WireKit::validateProp('bento-grid', 'gap', $gap, ['md', 'none', 'xs', 'sm', 'lg', 'xl', '2xl']);

    // The same --space-wk-* ladder (and the same inline fallbacks) the grid uses.
    // This component extends that grid, so a second spacing vocabulary here would
    // mean two gap ladders that drift apart. Static strings, one per arm: the
    // drift auditor harvests class names out of match arms and cannot follow an
    // interpolated token.
    $gapClass = match ($gap) {
        'none' => 'gap-0',
        'xs' => 'gap-[var(--space-wk-xs,0.25rem)]',
        'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
        'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'gap-[var(--space-wk-2xl,4rem)]',
        default => 'gap-[var(--space-wk-md,1rem)]',
    };

    $classes = WireKit::resolveClasses('bento-grid', 'base', implode(' ', [
        'grid',
        // The grid is a NAMED query container ('bento'), so its cells reflow on the
        // grid's OWN inline width, not the viewport. A bento inside a narrow column
        // — or a resizable region a reader drags — collapses to a single stack when
        // IT is small, independent of the window size. Container queries are in the
        // Tailwind browser baseline (Chrome 111 / Safari 16.4 / Firefox 128).
        '@container/bento',
        // Fixed three-column track. On a narrow container every cell spans all three
        // (see bento-cell), which reads as a single stacked column; from @2xl the
        // cells claim their real spans and the asymmetric bento appears. The count
        // stays fixed here because an element cannot container-query ITSELF — only
        // the descendant cells can, so the collapse lives on them.
        'grid-cols-3',
        // Rows size to content rather than to a fixed track, so a tall cell can
        // never crop the text inside it.
        'auto-rows-[minmax(12rem,auto)]',
        $gapClass,
    ]), $scope);
@endphp

{{-- The asymmetry IS the component: cells claim different amounts of space, so
     the eye gets a hierarchy instead of a uniform matrix. feature-grid already
     covers the uniform case and stays the right answer when every feature
     carries the same weight.

     Reading order is DOM order, deliberately. There is no prop to place a cell
     at an arbitrary track — that would let the visual order and the order a
     screen reader announces drift apart, and the drift is invisible to the
     person authoring it. --}}
<div
    @if($label) role="group" aria-label="{{ $label }}" @endif
    data-wk-bento-grid
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
