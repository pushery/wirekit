@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Row styling: striped + hoverable modes are driven by parent <table>
    // data attributes (see table.blade.php). The odd-child rule provides the
    // stripe color; the hover rule lights the whole row.
    $classes = WireKit::resolveClasses('table.row', 'base', implode(' ', [
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        // Striped: every odd child row in a data-wk-striped table gets a subtle tint
        '[table[data-wk-striped]_&:nth-child(odd)]:bg-[var(--color-wk-bg-subtle)]',
        // Hoverable: any row inside a data-wk-hoverable table reacts to hover
        '[table[data-wk-hoverable]_&:hover]:bg-[var(--color-wk-bg-muted)]',
        // Striped + hoverable: hover must override the stripe on odd rows (higher specificity)
        '[table[data-wk-striped][data-wk-hoverable]_&:nth-child(odd):hover]:bg-[var(--color-wk-bg-muted)]',
    ]), $scope);
@endphp

<tr data-wk-table-row {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</tr>
