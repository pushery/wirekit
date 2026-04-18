@props([
    'visible' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Action Bar — floating toolbar for bulk actions (shown when items are selected).
    // Uses role="toolbar" + aria-live announcement for screen readers.
    $classes = WireKit::resolveClasses('action-bar', 'base', implode(' ', [
        'fixed bottom-[var(--padding-wk-y-lg)] left-1/2 -translate-x-1/2',
        'z-[var(--z-wk-sticky)]',
        'flex items-center gap-[var(--gap-wk-md)]',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-sm)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

<div
    @if(!$visible) style="display: none;" @endif
    role="toolbar"
    aria-label="Bulk actions"
    {{ $attributes->class([$classes]) }}
>
    {{-- Live region announces bar appearance --}}
    <div aria-live="polite" class="sr-only">
        @if($visible) Bulk actions available @endif
    </div>

    {{ $slot }}
</div>
