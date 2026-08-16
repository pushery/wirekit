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
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.empty', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Empty state shown when no command items match the search query.
    $classes = WireKit::resolveClasses('command-palette.empty', 'base', implode(' ', [
        'py-[var(--padding-wk-y-xl)]',
        'text-center',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
