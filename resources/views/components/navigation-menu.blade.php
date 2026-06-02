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
    {{-- Outside-click close is handled in wirekitNavigationMenu()'s
         document-level pointerdown listener, not here: the flyout panels
         teleport to <body>, so a Blade x-on:click.outside on this root would
         fire when clicking inside an open panel (no longer a DOM descendant)
         and close it before an in-panel click registered. --}}
    aria-label="Main navigation"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</nav>
