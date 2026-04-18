@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Sidebar root: a semantic <nav> landmark that holds grouped navigation
    // items. The sidebar is visually a vertical column with consistent padding.
    $classes = WireKit::resolveClasses('sidebar', 'base', implode(' ', [
        'flex flex-col',
        'gap-[var(--padding-wk-y-sm)]',
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
    ]), $scope);
@endphp

{{-- <nav aria-label="Sidebar"> — named so AT distinguishes it from the main nav. --}}
<nav aria-label="Sidebar" {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</nav>
