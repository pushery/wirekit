@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Header section — title area with bottom border separator
    $classes = WireKit::resolveClasses('card.header', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'border-b-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-lg)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
