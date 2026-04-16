@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Caption styling — muted text below the table, aligned start
    $classes = WireKit::resolveClasses('table.caption', 'base', implode(' ', [
        'caption-bottom',
        'mt-2',
        'text-[length:var(--text-wk-sm)]',
        'text-[var(--color-wk-text-muted)]',
        'text-left',
    ]), $scope);
@endphp

<caption {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</caption>
