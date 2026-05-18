@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Menubar — horizontal menu bar with dropdown menus (File, Edit, View pattern).
    // Follows WAI-ARIA menubar pattern with arrow key navigation between menus.
    $classes = WireKit::resolveClasses('menubar', 'base', implode(' ', [
        'flex items-center gap-1',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'p-[var(--padding-wk-y-xs)]',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitMenubar()"
    x-on:keydown="handleKeydown"
    x-on:click.outside="closeAll()"
    role="menubar"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
