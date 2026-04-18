@props([
    'href' => '#',
    'active' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Navbar item — navigation link with active state indicator.
    $classes = WireKit::resolveClasses('navbar.item', 'base', implode(' ', [
        'inline-flex items-center',
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $colorClasses = $active
        ? 'text-[var(--color-wk-accent)] font-[number:var(--font-wk-heading-weight)]'
        : 'text-[var(--color-wk-text)]';
@endphp

<a
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    {{ $attributes->class([$classes, $colorClasses]) }}
>
    {{ $slot }}
</a>
