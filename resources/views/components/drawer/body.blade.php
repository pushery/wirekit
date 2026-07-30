{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Body classes — scrollable content area that fills available space
    $classes = WireKit::resolveClasses('drawer.body', 'base', implode(' ', [
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'wk-scrollbar flex-1 overflow-y-auto',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- Drawer body — main scrollable content area --}}
<div data-wk-drawer-body {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
