@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Thead styling — subtle background + bottom border divider
    // Sticky header: when parent table has data-wk-sticky-header, thead sticks to top
    $classes = WireKit::resolveClasses('table.head', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-subtle)]',
        'border-b-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        '[table[data-wk-sticky-header]_&]:sticky [table[data-wk-sticky-header]_&]:top-0 [table[data-wk-sticky-header]_&]:z-10',
    ]), $scope);
@endphp

<thead {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</thead>
