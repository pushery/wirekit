{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Footer classes — bottom section with top border and right-aligned buttons
    $classes = WireKit::resolveClasses('drawer.footer', 'base', implode(' ', [
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
        'flex items-center justify-end gap-x-[var(--gap-wk-md)]',
    ]), $scope);
@endphp

{{-- Drawer footer — action buttons area --}}
<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
