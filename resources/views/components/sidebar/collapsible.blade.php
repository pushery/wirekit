@props([
    'label' => '',
    'icon' => null,
    'open' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Collapsible sidebar group — a disclosure widget that toggles child items.
    // The trigger looks like a sidebar item but acts as an expand/collapse toggle.
    // Uses aria-expanded for AT, and indents child content by one level.
    $triggerClasses = WireKit::resolveClasses('sidebar.collapsible', 'trigger', implode(' ', [
        'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    // Child container — indented to show hierarchy.
    $childClasses = WireKit::resolveClasses('sidebar.collapsible', 'children', implode(' ', [
        'flex flex-col gap-[2px]',
        'pl-[var(--padding-wk-x-md)]',
    ]), $scope);
@endphp

<div
    x-data="{ open: @js($open) }"
    {{ $attributes }}
>
    {{-- Trigger button — toggles the child items. aria-expanded announces
         the current state to screen readers. --}}
    <button
        type="button"
        x-on:click="open = !open"
        :aria-expanded="open ? 'true' : 'false'"
        class="{{ $triggerClasses }}"
    >
        @if($icon)
            {{-- Icon — decorative, hidden from AT. A bare name string resolves
                 via the WireKit icon system (consistent with sidebar.item /
                 dropdown.item); a <x-slot:icon> or inline markup (non-string
                 ComponentSlot, Htmlable) renders verbatim. --}}
            <span class="shrink-0" aria-hidden="true">
                @if(is_string($icon) && ! str_contains($icon, '<') && function_exists('svg'))
                    {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
                @else
                    {{ $icon }}
                @endif
            </span>
        @endif
        <span class="flex-1 truncate text-left">{{ $label }}</span>
        {{-- Chevron indicator — rotates when open. --}}
        <svg
            class="w-3.5 h-3.5 shrink-0 transition-transform duration-[var(--transition-wk-duration)]"
            :class="open ? 'rotate-90' : ''"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    </button>

    {{-- Collapsible children — shown/hidden with Alpine. --}}
    <div x-show="open" x-collapse x-cloak class="{{ $childClasses }}">
        {{ $slot }}
    </div>
</div>
