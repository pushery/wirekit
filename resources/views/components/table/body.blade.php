@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tbody dividers — separates rows visually
    $classes = WireKit::resolveClasses('table.body', 'base', implode(' ', [
        'divide-y-[length:var(--border-wk-width)]',
        'divide-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<tbody data-wk-table-body {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</tbody>
