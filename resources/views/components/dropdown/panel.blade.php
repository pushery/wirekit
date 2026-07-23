@props([
    'width' => config('wirekit.components.dropdown.panel.width', 'auto'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Panel classes — elevated surface with shadow and border
    // Uses `fixed` positioning so the panel escapes ancestor `overflow: hidden` containers
    // (cards, scroll panels, docs preview boxes). Floating UI uses strategy: 'fixed' to match.
    $classes = WireKit::resolveClasses('dropdown.panel', 'base', implode(' ', [
        'fixed',
        'z-[var(--z-wk-dropdown)]',
        'min-w-[12rem]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-y-auto',  // fitViewport caps the height; the panel scrolls its excess instead of clipping
    ]), $scope);

    // Width handling — 'auto' uses min-width only, 'trigger' matches trigger width
    $widthStyle = match ($width) {
        'auto' => '',
        'trigger' => 'min-width: 100%;',
        default => "width: {$width};",
    };
@endphp

{{-- Dropdown panel — positioned by Floating UI, shown/hidden via Alpine.
     The id is bound dynamically from the parent's data-wk-panel-id for aria-controls.
     Only the ENTER transition is animated — the panel disappears instantly on close.
     This matches common UX patterns (GitHub, Linear, Stripe — dropdowns close instantly)
     and avoids a race where Alpine's ~150ms leave transition leaves the panel visible
     long enough to break synchronous browser test assertions like `assertDontSee`. --}}
<div
    x-ref="panel"
    x-show="open"
    x-bind:id="$el.closest('[data-wk-panel-id]')?.dataset.wkPanelId"
    x-transition:enter="transition ease-out"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-cloak
    role="menu"
    @if($widthStyle) style="{{ $widthStyle }}" @endif
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
