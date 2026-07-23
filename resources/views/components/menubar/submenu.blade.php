@props([
    'label' => null,        // Parent item text (or pass a <x-slot:label> for rich content)
    'icon' => null,         // Optional leading icon (WireKit icon system)
    'placement' => 'right-start', // Floating UI placement of the child panel
    'offset' => 0,          // Gap between the parent item and the child panel
    'disabled' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);

    // Parent item classes — reuse the menubar.item token key so a submenu
    // parent is visually identical to a regular item AND inherits the same
    // per-scope overrides set for menubar.item.
    $itemClasses = WireKit::resolveClasses('menubar.item', 'base', implode(' ', [
        'flex items-center justify-between gap-x-[var(--gap-wk-md)] w-full',
        'p-[var(--padding-wk-x-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'focus:outline-none',
        'focus:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    // Child panel classes — identical surface to the menubar menu panel.
    $panelClasses = WireKit::resolveClasses('menubar.menu', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'min-w-[12rem]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-hidden',
    ]), $scope);
@endphp

{{-- Nested submenu flyout for menubar. Mirrors the dropdown /
     context-menu submenu. Purely additive.

     The reset reads the menubar's reactive `activeMenu` and compares it to the
     name of the containing menu panel (discovered from the DOM via the nearest
     [data-wk-menubar-panel] — no prop needed, and it survives the panel's
     teleport to <body> since the whole subtree moves together). When this
     menu is no longer the active one, the submenu resets.

     The menubar's ArrowRight moves between TOP-LEVEL menus; onTriggerKey here
     handles ArrowRight on a submenu PARENT and stopPropagation's it, so the
     menubar root's handler does not also fire — the WAI-ARIA disambiguation
     (ArrowRight opens a submenu when on a parent, else moves menus). --}}
<div
    x-data="wirekitSubmenu({ placement: '{{ $placement }}', offset: {{ (int) $offset }} })"
    x-effect="(activeMenu === ($el.closest('[data-wk-menubar-panel]')?.dataset.wkMenubarPanel)) || closeSub()"
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
        <span class="flex items-center gap-x-[var(--gap-wk-sm)]">
            @if($icon)
                <span class="shrink-0 w-5 h-5" aria-hidden="true">
                    @if(function_exists('svg'))
                        {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
                    @endif
                </span>
            @endif

            {{-- Label: a <x-slot:label> renders its content; otherwise the prop text. --}}
            <span>{{ $label }}</span>
        </span>

        {{-- Trailing chevron-right indicator. Decorative; aria-* carry semantics. --}}
        <span class="shrink-0" aria-hidden="true">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
    </button>

    {{-- Child panel — data-wk-submenu-panel scopes both this submenu's roving
         focus AND the menubar's flat _getActiveItems(). --}}
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
