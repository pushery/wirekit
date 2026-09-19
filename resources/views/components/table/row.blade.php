{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
    /*
     * A SECOND row under this one, carrying what `hide-below[-container]` took off the
     * remaining columns.
     *
     * ⚠️ IT IS A SLOT ON THIS COMPONENT RATHER THAN A SIBLING `table.details`, AND THAT WAS
     * THE WHOLE DESIGN QUESTION. The reporting ticket proposed a sibling component, which
     * reads better at the call site and cannot work: Tailwind's `divide-y` compiles to
     * `:where(.divide-y > :not(:last-child))` with `border-BOTTOM-width`, so the line between
     * a record and its details belongs to the RECORD row. A sibling component cannot reach
     * backwards to switch it off -- CSS has no previous-sibling combinator -- so the reporter
     * had to write both halves by hand, which is the thing being fixed. One component
     * emitting both rows owns the border, the `:last-child` arithmetic and the stripe.
     */
    'detailsBelowContainer' => null,
    'detailsBelow' => null,
    'detailsColspan' => null,
    'detailsFor' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.row', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $hasDetails = isset($details) && trim((string) $details) !== '';

    $detailsBelowContainer = filled($detailsBelowContainer) ? (string) $detailsBelowContainer : null;
    if ($detailsBelowContainer !== null && ! in_array($detailsBelowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl'], true)) {
        WireKit::validateProp('table.row', 'detailsBelowContainer', $detailsBelowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']);
        $detailsBelowContainer = null;
    }

    $detailsBelow = filled($detailsBelow) ? (string) $detailsBelow : null;
    if ($detailsBelow !== null && ! in_array($detailsBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.row', 'detailsBelow', $detailsBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
        $detailsBelow = null;
    }

    /*
     * The details row is the INVERSE of a column's `hide-below`: a column disappears below a
     * size, this appears there. Written out per size because Tailwind reads a literal class
     * name and never an assembled one -- the same reason `table/td.blade.php` spells its
     * ladder out.
     */
    $detailsHiddenFromContainer = match ($detailsBelowContainer) {
        'sm' => '@sm/wk-table:hidden',
        'md' => '@md/wk-table:hidden',
        'lg' => '@lg/wk-table:hidden',
        'xl' => '@xl/wk-table:hidden',
        '2xl' => '@2xl/wk-table:hidden',
        '3xl' => '@3xl/wk-table:hidden',
        '4xl' => '@4xl/wk-table:hidden',
        '5xl' => '@5xl/wk-table:hidden',
        default => '',
    };
    $detailsHiddenFromViewport = match ($detailsBelow) {
        'sm' => 'sm:hidden',
        'md' => 'md:hidden',
        'lg' => 'lg:hidden',
        'xl' => 'xl:hidden',
        '2xl' => '2xl:hidden',
        default => '',
    };
    $detailsHiddenFrom = trim($detailsHiddenFromContainer.' '.$detailsHiddenFromViewport);

    /*
     * The two border corrections the reporter had to write by hand, now emitted here.
     *
     * 1. While the details row is VISIBLE it continues its record, so the line between the two
     *    has to go -- and that line is the record's own `border-bottom` from `divide-y`.
     * 2. While it is HIDDEN, `divide-y`'s `:not(:last-child)` still counts it: a `display: none`
     *    row is a child like any other, so the last RECORD is `:nth-last-child(2)` and keeps a
     *    bottom border with nothing under it. `nth-last-2` is exactly that row.
     *
     * Both are conditioned on the ladder rather than applied flat, so a row whose details are
     * always visible does not silently lose its separator at every width.
     */
    $detailsBorderFixContainer = match ($detailsBelowContainer) {
        'sm' => '@max-sm/wk-table:border-b-0 @sm/wk-table:nth-last-2:border-b-0',
        'md' => '@max-md/wk-table:border-b-0 @md/wk-table:nth-last-2:border-b-0',
        'lg' => '@max-lg/wk-table:border-b-0 @lg/wk-table:nth-last-2:border-b-0',
        'xl' => '@max-xl/wk-table:border-b-0 @xl/wk-table:nth-last-2:border-b-0',
        '2xl' => '@max-2xl/wk-table:border-b-0 @2xl/wk-table:nth-last-2:border-b-0',
        '3xl' => '@max-3xl/wk-table:border-b-0 @3xl/wk-table:nth-last-2:border-b-0',
        '4xl' => '@max-4xl/wk-table:border-b-0 @4xl/wk-table:nth-last-2:border-b-0',
        '5xl' => '@max-5xl/wk-table:border-b-0 @5xl/wk-table:nth-last-2:border-b-0',
        default => '',
    };
    $detailsBorderFixViewport = match ($detailsBelow) {
        'sm' => 'max-sm:border-b-0 sm:nth-last-2:border-b-0',
        'md' => 'max-md:border-b-0 md:nth-last-2:border-b-0',
        'lg' => 'max-lg:border-b-0 lg:nth-last-2:border-b-0',
        'xl' => 'max-xl:border-b-0 xl:nth-last-2:border-b-0',
        '2xl' => 'max-2xl:border-b-0 2xl:nth-last-2:border-b-0',
        default => '',
    };
    // Neither ladder given: the details row is visible at every width, so the separator between
    // the two halves of one record is always wrong, and the last record is always the
    // second-last child.
    $detailsBorderFixAlways = ($detailsBelowContainer === null && $detailsBelow === null)
        ? 'border-b-0 nth-last-2:border-b-0'
        : '';
    $detailsBorderFix = ! $hasDetails
        ? ''
        : trim($detailsBorderFixContainer.' '.$detailsBorderFixViewport.' '.$detailsBorderFixAlways);

    // Row styling: striped + hoverable modes are driven by parent <table>
    // data attributes (see table.blade.php). The odd-child rule provides the
    // stripe color; the hover rule lights the whole row.
    //
    // ⚠️ `odd of [data-wk-table-row]` RATHER THAN A BARE `odd`, because a details row is a
    // child of the same tbody and a bare `nth-child` counts it. With one details row in the
    // table every stripe below it lands on the wrong record. The `of S` form counts only
    // record rows, is a no-op for a table that has none, and sits ON the support baseline
    // rather than above it: Chrome 111 (the floor itself), Safari 9, Firefox 113.
    $classes = WireKit::resolveClasses('table.row', 'base', implode(' ', [
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        // Striped: every odd RECORD row in a data-wk-striped table gets a subtle tint
        '[table[data-wk-striped]_&:nth-child(odd_of_[data-wk-table-row])]:bg-[var(--color-wk-bg-subtle)]',
        // Hoverable: any row inside a data-wk-hoverable table reacts to hover
        '[table[data-wk-hoverable]_&:hover]:bg-[var(--color-wk-bg-muted)]',
        // Striped + hoverable: hover must override the stripe on odd rows (higher specificity)
        '[table[data-wk-striped][data-wk-hoverable]_&:nth-child(odd_of_[data-wk-table-row]):hover]:bg-[var(--color-wk-bg-muted)]',
    ]), $scope);

    // The details row takes the tint of the record it belongs to, or it reads as a separate
    // band under a tinted row. Selected by adjacency rather than by its own position: the
    // record before it is what decides, and only that record's parity is knowable here.
    $detailsClasses = WireKit::resolveClasses('table.row', 'details', implode(' ', [
        $detailsHiddenFrom,
        '[table[data-wk-striped]_[data-wk-table-row]:nth-child(odd_of_[data-wk-table-row])+&]:bg-[var(--color-wk-bg-subtle)]',
    ]), $scope);
@endphp

<tr data-wk-table-row {{ $attributes->class([$classes, $detailsBorderFix]) }}>
    {{ $slot }}
</tr>

@if($hasDetails)
    {{-- `headers` points at the record's row-header cell, so a screen reader reaching this row
         is told which record it continues. Without it the row is an orphan announcing values
         with no subject. --}}
    <tr data-wk-table-details class="{{ $detailsClasses }}">
        <td
            data-wk-prose-skip
            @if($detailsColspan !== null) colspan="{{ (int) $detailsColspan }}" @endif
            @if(filled($detailsFor)) headers="{{ $detailsFor }}" @endif
            class="px-[var(--padding-wk-x-md)] pb-[var(--padding-wk-y-md)] pt-0 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]"
        >{{ $details }}</td>
    </tr>
@endif
