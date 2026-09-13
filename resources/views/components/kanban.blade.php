{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'orientation' => 'horizontal',
    'sortable' => false,
    // Cards may move BETWEEN sortable columns: dragged onto another one, or moved with
    // ArrowLeft/ArrowRight while lifted. Opt-in, because it changes what those two keys do
    // on this board — without it they move a lifted card within its own column, which is
    // what every board shipped so far has taught its users.
    'crossColumn' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('kanban', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sortable = BooleanProp::from($sortable, false);
    $crossColumn = BooleanProp::from($crossColumn, false);

    $orientationValue = match ($orientation) {
        'horizontal', 'vertical' => $orientation,
        default => WireKit::validateProp('kanban', 'orientation', $orientation, ['horizontal', 'vertical']),
    };

    /*
     * A `match` rather than a ternary, and the reason is the Drift inventory: its Blade
     * class extractor harvests match ARMS and `implode([...])` arrays, not the branches of
     * a ternary assignment. `contain-paint` below is the one class in this file that
     * appears nowhere else in the library, so it was the first to expose the gap — the
     * reverse diff reported a compiled selector it could not trace to any source.
     */
    $layoutClasses = match ($orientationValue) {
        // `contain-paint` is what keeps the board's scrollable width off the PAGE.
        //
        // A horizontal scroller clips its own painting, but its scrollable overflow still
        // counted toward the document's, so on a phone the whole page could be panned
        // sideways past the board. Measured on a 393px viewport: the kanban blueprint's
        // document was 926px wide, 533px of it off-screen, and the reader panned the page
        // instead of the columns. Six candidate fixes were tried live in the browser
        // (max-width, width, flex-none, overflow-x:hidden on the card body and on body);
        // `contain: paint` is the only one that changed anything, and it took the document
        // straight back to 393.
        //
        // Safe below the support floor — Chrome 52 / Safari 15.4 / Firefox 69, well under
        // the Tailwind v4 baseline this library pins to — and visually a no-op, because a
        // scroll container already clips what it paints. It is NOT applied to the vertical
        // orientation, which does not scroll and would only gain a clip it never wanted.
        //
        // `snap-x snap-mandatory`, which is what Tailwind actually ships. This read
        // `scroll-snap-x-mandatory` — the CSS PROPERTY name with a dash in front, not a
        // utility — so Tailwind compiled nothing for it and the container had no snap type
        // at all. The `snap-start` on every kanban-column then had nothing to align against
        // and did nothing either: a two-file feature, silently absent, with both halves
        // looking present in the source. attachment-group and carousel write the real pair.
        'horizontal' => 'wk-scrollbar flex flex-row overflow-x-auto contain-paint snap-x snap-mandatory gap-[var(--space-wk-md,1rem)]',
        default => 'flex flex-col gap-[var(--space-wk-md,1rem)]',
    };

    $baseClasses = WireKit::resolveClasses('kanban', 'base', implode(' ', [
        $layoutClasses,
        'font-[family-name:var(--font-wk-sans)]',
        'min-h-0',
        '-mx-[var(--space-wk-sm,0.5rem)] px-[var(--space-wk-sm,0.5rem)]',
        'pb-[var(--space-wk-sm,0.5rem)]',
    ]), $scope);
@endphp

<div
    role="list"
    @if($sortable) data-sortable @endif
    {{-- The marker every column's sortable looks for before it lets a card leave. Only on a
         sortable board: a board that does not sort has nothing to connect. --}}
    @if($sortable && $crossColumn) data-sortable-connected @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{ $slot }}
</div>
