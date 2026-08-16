{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.body', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Tbody dividers — separates rows visually
    $classes = WireKit::resolveClasses('table.body', 'base', implode(' ', [
        'divide-y-[length:var(--border-wk-width)]',
        'divide-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<tbody data-wk-table-body {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</tbody>
