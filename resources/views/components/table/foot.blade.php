{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tfoot styling — same subtle background as thead + top border
    $classes = WireKit::resolveClasses('table.foot', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-subtle)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'font-[number:var(--font-wk-heading-weight)]',
    ]), $scope);
@endphp

<tfoot {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</tfoot>
