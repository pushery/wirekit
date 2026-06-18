@props([
    'label' => null,        // Parent item text (or pass a <x-slot:label> for rich content)
    'icon' => null,         // Optional leading icon (WireKit icon system)
    'placement' => 'right-start', // Floating UI placement of the child panel
    'offset' => 0,          // Gap between the parent item and the child panel
    'disabled' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Parent item classes — reuse the context-menu.item token key so a submenu
    // parent is visually identical to a regular item AND inherits the same
    // per-scope overrides set for context-menu.item.
    $itemClasses = WireKit::resolveClasses('context-menu.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'whitespace-nowrap',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        'focus:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    // Child panel classes — identical surface to the context-menu panel.
    $panelClasses = WireKit::resolveClasses('context-menu.panel', 'base', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'min-w-48 max-w-[calc(100vw-1rem)] w-max',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-hidden',
    ]), $scope);
@endphp

{{-- Nested submenu flyout for context-menu. Mirrors the dropdown
     submenu: parent menuitem (aria-haspopup="menu" + aria-expanded) opening a
     child role="menu" panel positioned by Floating UI. Purely additive.

     x-effect="open || closeSub()" resets the submenu when the OUTER context
     menu closes — the context-menu's `open` is still in scope after the panel
     teleports to <body> (x-teleport preserves x-data scope). The parent's
     click is stopPropagation'd; leaf child items keep their own close(). --}}
<div
    x-data="wirekitSubmenu({ placement: '{{ $placement }}', offset: {{ (int) $offset }} })"
    x-effect="open || closeSub()"
    data-wk-submenu
    class="block w-full"
>
    <button
        type="button"
        x-ref="subTrigger"
        role="menuitem"
        tabindex="-1"
        aria-haspopup="menu"
        x-bind:aria-expanded="subOpen ? 'true' : 'false'"
        @if($disabled) aria-disabled="true" @endif
        x-on:click.stop="openSub(true)"
        x-on:mouseenter="scheduleOpen()"
        x-on:mouseleave="scheduleClose()"
        x-on:keydown="onTriggerKey"
        x-bind:class="subOpen ? 'bg-[var(--color-wk-bg-subtle)]' : ''"
        {{ $attributes->class([$itemClasses, $disabledClasses]) }}
    >
        @if($icon)
            <span class="shrink-0 w-5 h-5" aria-hidden="true">
                @if(function_exists('svg'))
                    {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
                @endif
            </span>
        @endif

        {{-- Label: a <x-slot:label> renders its content; otherwise the prop text. --}}
        <span>{{ $label }}</span>

        {{-- Trailing chevron-right indicator. Decorative; aria-* carry semantics. --}}
        <span class="ms-auto ps-[var(--padding-wk-x-md)] shrink-0" aria-hidden="true">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
    </button>

    {{-- Child panel — data-wk-submenu-panel scopes both this submenu's roving
         focus AND the parent menu's flat _getItems(). --}}
    <div
        x-ref="subPanel"
        x-show="subOpen"
        x-cloak
        role="menu"
        data-wk-submenu-panel
        x-on:mouseenter="_clearCloseTimer()"
        x-on:mouseleave="scheduleClose()"
        x-on:keydown="onSubKey"
        @class([$panelClasses])
    >
        {{ $slot }}
    </div>
</div>
