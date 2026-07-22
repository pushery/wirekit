@props([
    'visible' => false,
    'mode' => 'floating',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Action Bar — toolbar for bulk actions (shown when items are selected).
    // Uses role="toolbar" + aria-live announcement for screen readers.
    //
    // Two layout modes:
    //   - 'floating' (default) — pinned to bottom-center of the viewport via
    //     `position: fixed`. Best for list pages where the bar should hover
    //     over the content while the user scrolls.
    //   - 'static' — flows inline with the surrounding content. Useful when
    //     the bar is part of a card / panel / dashboard rather than a
    //     viewport-floating overlay. Drops the fixed positioning + the
    //     viewport-centring transforms; keeps the same visual chrome.
    $isFloating = $mode !== 'static';

    $positioningClasses = $isFloating
        ? 'fixed bottom-[var(--padding-wk-y-lg)] left-1/2 -translate-x-1/2 z-[var(--z-wk-sticky)]'
        : 'inline-flex';

    $classes = WireKit::resolveClasses('action-bar', 'base', implode(' ', [
        $positioningClasses,
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
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<div
    @if(!$visible) style="display: none;" @endif
    role="toolbar"
    aria-label="{{ __('Bulk actions') }}"
    {{ $attributes->class([$classes]) }}
>
    {{-- Live region announces bar appearance --}}
    <div aria-live="polite" class="sr-only">
        @if($visible) Bulk actions available @endif
    </div>

    {{ $slot }}
</div>
