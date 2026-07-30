{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('dropdown.separator', 'base', implode(' ', [
        'my-[var(--padding-wk-y-xs)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

{{-- Visual separator between dropdown item groups --}}
<div role="separator" {{ $attributes->class([$classes]) }}></div>
