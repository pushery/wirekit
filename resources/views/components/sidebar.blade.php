@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Sidebar root: a semantic <nav> landmark that holds grouped navigation
    // items. Uniform `p-[var(--padding-wk-y-sm)]` (= 0.375rem all four
    // sides) so the OUTER gap between the sidebar border and each item's
    // hover/active highlight matches the INNER gap between the item edge
    // and the label text. With asymmetric `px-x-sm py-y-sm` (0.625rem
    // horizontal, 0.375rem vertical) the outer gap looked 67% larger
    // than the inner — visually unbalanced.
    $classes = WireKit::resolveClasses('sidebar', 'base', implode(' ', [
        'flex flex-col',
        'gap-[var(--space-wk-sm)]',
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
