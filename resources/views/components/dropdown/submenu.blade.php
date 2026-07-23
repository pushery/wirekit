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

    // Parent item classes — reuse the dropdown.item token key so a submenu
    // parent is visually identical to a regular item AND inherits the same
    // per-scope overrides a developer may have set for dropdown.item.
    $itemClasses = WireKit::resolveClasses('dropdown.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
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

    // Child panel classes — identical surface to dropdown.panel.
    $panelClasses = WireKit::resolveClasses('dropdown.panel', 'base', implode(' ', [
        'fixed',
        'z-[var(--z-wk-dropdown)]',
        'min-w-[12rem]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-hidden',
    ]), $scope);
@endphp

{{-- Nested submenu flyout. The parent item carries aria-haspopup="menu" +
     aria-expanded; the child panel is a role="menu" positioned beside the
     parent by Floating UI (right-start, collision-flip handled by the shared
     position() util). Purely additive — a flat menu never renders this.

     x-effect="open || closeSub()" resets the submenu when the OUTER dropdown
     closes (the outer scope's `open`), so a reopened dropdown never shows a
     stale-open submenu. The parent item's @click.stop keeps a click on the
     parent from bubbling to the dropdown root's auto-close delegated handler
     (which would otherwise dismiss the whole menu). --}}
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
        {{-- Optional leading icon (decorative — the label is the accessible name) --}}
        @if($icon)
            <span class="shrink-0 w-5 h-5" aria-hidden="true">
                @if(function_exists('svg'))
                    {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
                @endif
            </span>
        @endif

        {{-- Label: a <x-slot:label> renders its content; otherwise the prop text. --}}
        <span>{{ $label }}</span>

        {{-- Trailing chevron-right indicator — points toward the flyout side.
             Decorative; aria-haspopup/aria-expanded carry the semantics. --}}
        <span class="ms-auto ps-[var(--padding-wk-x-md)] shrink-0" aria-hidden="true">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
    </button>

    {{-- Child panel — the nested menu items. data-wk-submenu-panel scopes both
         this submenu's own roving focus AND the parent menu's _getItems() (which
         excludes items inside any submenu panel so parent-level nav stays flat). --}}
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
