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
    \Pushery\WireKit\WireKit::warnUnknownProps('table.caption', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Caption styling — muted text below the table, aligned start
    $classes = WireKit::resolveClasses('table.caption', 'base', implode(' ', [
        'caption-bottom',
        'mt-2',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'text-left',
    ]), $scope);
@endphp

<caption {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</caption>
