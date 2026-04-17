@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Navigation Menu — top-level nav with rich flyout panels (mega menu).
    // Uses disclosure pattern: hover or click to reveal content panels.
    $classes = WireKit::resolveClasses('navigation-menu', 'base', implode(' ', [
        'relative flex items-center gap-1',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<nav
    x-data="wirekitNavigationMenu()"
    x-on:click.outside="closeAll()"
    aria-label="Main navigation"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</nav>
