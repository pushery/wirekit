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
