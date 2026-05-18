@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Footer section — action area with top border and muted background
    $classes = WireKit::resolveClasses('card.footer', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'bg-[var(--color-wk-bg-subtle)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
