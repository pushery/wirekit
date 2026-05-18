@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Body classes — scrollable content area that fills available space
    $classes = WireKit::resolveClasses('drawer.body', 'base', implode(' ', [
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'flex-1 overflow-y-auto',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- Drawer body — main scrollable content area --}}
<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
