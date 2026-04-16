@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Empty state shown when no command items match the search query.
    $classes = WireKit::resolveClasses('command-palette.empty', 'base', implode(' ', [
        'py-[var(--padding-wk-y-xl)]',
        'text-center',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
