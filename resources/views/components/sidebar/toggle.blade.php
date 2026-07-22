@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Sidebar Toggle — hamburger button that toggles sidebar visibility.
    // Reads `sidebarOpen` from app-shell's x-data.
    $classes = WireKit::resolveClasses('sidebar.toggle', 'base', implode(' ', [
        'inline-flex items-center justify-center',
        'p-2',
        'rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'cursor-pointer',
    ]), $scope);

    // Accessible name: translatable default, but a caller-supplied aria-label
    // wins — and is emitted exactly once. Rendering the default AND letting the
    // attribute bag add the caller's value produced two aria-label attributes,
    // the hardcoded English one winning.
    $ariaLabel = $attributes->get('aria-label', __('Toggle sidebar'));
@endphp

<button
    type="button"
    x-on:click="sidebarOpen = !sidebarOpen"
    :aria-expanded="sidebarOpen ? 'true' : 'false'"
    aria-label="{{ $ariaLabel }}"
    {{ $attributes->except('aria-label')->class([$classes]) }}
>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
    </svg>
</button>
