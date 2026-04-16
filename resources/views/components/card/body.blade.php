@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Body section — main content area with comfortable padding
    $classes = WireKit::resolveClasses('card.body', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
