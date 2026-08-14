{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('mark', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('mark', 'base', implode(' ', [
        'bg-[var(--color-wk-warning-bg,oklch(0.905 0.093 102.1))]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-0.5',
    ]), $scope);
@endphp

<mark {{ $attributes->class([$classes]) }}>{{ $slot }}</mark>