@props([
    // How much room this cell claims once the grid is wide enough (the '@2xl'
    // container width). In a narrow grid every cell is full width regardless —
    // see below.
    //   '1x1' → one column  (the default)
    //   '2x1' → two columns wide
    //   '1x2' → two rows tall
    //   '2x2' → the hero cell
    'span' => '1x1',
    // Lift this cell visually. A border and a background, never a tint or a
    // gradient: the emphasis has to survive a reader who cannot separate the
    // accent hue from the surface.
    'emphasis' => false,
    // Let content run to the cell's edge — for imagery that should bleed rather
    // than sit in a padded box. Turns off the cell's own padding.
    'bleed' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $emphasis = BooleanProp::from($emphasis, false);
    $bleed = BooleanProp::from($bleed, false);

    // Match on the RESULT, not the raw prop: validateProp throws in strict mode
    // and otherwise returns the first allowed value — '1x1' here, which is both
    // the default and the only span that is safe at any grid width.
    $span = WireKit::validateProp('bento-cell', 'span', $span, ['1x1', '2x1', '1x2', '2x2']);

    // Full literal classes per arm — the drift auditor reads these statically and
    // Tailwind only emits a class it can see written out.
    //
    // Every cell spans all three columns on a narrow container (a single stacked
    // column), then claims its real span from the '@2xl/bento' container width up —
    // the reflow tracks the GRID's own width (see bento-grid's @container/bento),
    // not the viewport, so a bento in a resizable region collapses when it is small.
    $spanClass = match ($span) {
        '2x1' => 'col-span-3 @2xl/bento:col-span-2 @2xl/bento:row-span-1',
        '1x2' => 'col-span-3 @2xl/bento:col-span-1 @2xl/bento:row-span-2',
        '2x2' => 'col-span-3 @2xl/bento:col-span-2 @2xl/bento:row-span-2',
        default => 'col-span-3 @2xl/bento:col-span-1 @2xl/bento:row-span-1',
    };

    $classes = WireKit::resolveClasses('bento-cell', 'base', implode(' ', [
        'relative flex flex-col overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)]',
        $emphasis ? 'border-[var(--color-wk-accent)]' : 'border-[var(--color-wk-border)]',
        $emphasis ? 'bg-[var(--color-wk-bg-elevated)]' : 'bg-[var(--color-wk-bg-subtle)]',
        $bleed ? 'p-0' : 'p-[var(--padding-wk-x-lg)]',
        $spanClass,
    ]), $scope);
@endphp

{{-- Every cell is full width when the grid is narrow: a two-column cell in a
     cramped grid only squeezes its neighbors into unreadable slivers, which is the
     failure the span-collapse exists to prevent. The span re-engages once the grid
     itself is wide enough (@2xl/bento), where there are columns to claim. --}}
<div data-wk-bento-cell data-span="{{ $span }}" {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
