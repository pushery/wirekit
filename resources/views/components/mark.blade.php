@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('mark', 'base', implode(' ', [
        'bg-[var(--color-wk-warning-bg,oklch(0.905 0.093 102.1))]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-0.5',
    ]), $scope);
@endphp

<mark {{ $attributes->class([$classes]) }}>{{ $slot }}</mark>