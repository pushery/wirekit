{{-- optimistic-ui: n/a — client-only
     Its state is open state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'label' => null,        // Parent item text (or pass a <x-slot:label> for rich content)
    'icon' => null,         // Optional leading icon (WireKit icon system)
    'placement' => 'right-start', // Floating UI placement of the child panel
    'offset' => 0,          // Gap between the parent item and the child panel
    'disabled' => false,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('context-menu.submenu', $attributes->getAttributes());

    // A caller's `x-ref` is filed above the parent component, whose root is the boundary.
    $attributes = \Pushery\WireKit\Support\CallerRef::forward($attributes);

    // One of the twelve placements Floating UI takes (Support\FloatingPlacement). A word it does not
    // know would put the panel on the top-left corner of its trigger: debug refuses it, production logs it and uses the default.
    // No placement at all, `null` or empty, is the default, as the script reads it.
    // The default names a physical side of the trigger, as every placement word does. The
    // direction ratchet counts the word with the utilities, although it is none.
    $placement = \Pushery\WireKit\Support\StrictnessGate::enforce('context-menu.submenu', 'placement', (string) ($placement ?: 'right-start'), \Pushery\WireKit\Support\FloatingPlacement::ALL, 'right-start');

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false, 'context-menu.submenu', 'disabled');

    // The panel's accessible name, carried by the trigger's own id.
    //
    // A `role="menu"` with no name is announced as a bare "menu": a reader who has just
    // opened "Export As" hears nothing confirming which flyout they are in, and with two
    // submenus open in sequence nothing tells them apart on re-entry. The sibling
    // `menubar/menu.blade.php` states the same rule and honors it; the docs promise it for
    // every panel ("Menu panels: role=\"menu\", aria-label from the label prop"), which is
    // what made this the sharpest instance: the page said it was already done.
    //
    // `aria-labelledby` at the TRIGGER rather than `aria-label` from the prop, because the
    // label reaches this component two ways — as a prop and as `<x-slot:label>` — and only
    // the rendered trigger has both. It also tracks the visible text instead of restating
    // it. The icon and the chevron inside the trigger are `aria-hidden`, so the computed
    // name is the label and nothing else.
    //
    // `DomId::unique`, never a random id: this markup sits inside Livewire morphs, and a
    // value minted per render makes the two halves stop naming each other after an update
    // while both stay well-formed. The counted form survives a re-render.
    $subTriggerId = \Pushery\WireKit\Support\DomId::unique(null, 'wk-context-submenu-');

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
        'hover:bg-[var(--color-wk-bg-hover)]',
        'whitespace-nowrap',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        // `focus-visible` + a ring, per dropdown/item.blade.php — and this block is
        // the same `context-menu.item` name the row component resolves, so the two
        // defaults have to agree. The background is the hover's shade, one step
        // from the panel behind this row, and too faint to mark focus on its own.
        'focus:outline-hidden',
        'focus-visible:bg-[var(--color-wk-bg-hover)]',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-inset',
        'focus-visible:ring-[var(--color-wk-ring)]',
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
        // fitViewport caps the height to the window; the panel scrolls its excess instead of
        // clipping it.
        'overflow-y-auto overscroll-contain',
    ]), $scope);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // submenu, so they go on its outermost element; the rest of the bag stays on the trigger
    // (Support\OuterAttributes).
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);
@endphp

{{-- Nested submenu flyout for context-menu. Mirrors the dropdown
     submenu: parent menuitem (aria-haspopup="menu" + aria-expanded) opening a
     child role="menu" panel positioned by Floating UI. Purely additive.

     x-effect="isOpen || closeSub()" resets the submenu when the OUTER context
     menu closes — the context-menu's `open` is still in scope after the panel
     teleports out of the document flow (x-teleport preserves x-data scope). The parent's
     click is stopPropagation'd; leaf child items keep their own close(). --}}
<div {{ $outerAttributes }}
    x-data="wirekitSubmenu({ placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }}, offset: {{ \Pushery\WireKit\Support\NumericProp::int($offset, 0, 'context-menu.submenu', 'offset') }} })"
    x-effect="isOpen || closeSub()"
    data-wk-submenu
    class="block w-full"
>
    <button
        type="button"
        x-ref="subTrigger"
        id="{{ $subTriggerId }}"
        role="menuitem"
        tabindex="-1"
        aria-haspopup="menu"
        aria-expanded="false"
        x-bind:aria-expanded="subOpen ? 'true' : 'false'"
        @if($disabled) aria-disabled="true" @endif
        x-on:click.stop="openSub(true)"
        x-on:mouseenter="scheduleOpen()"
        x-on:mouseleave="scheduleClose()"
        x-on:keydown="onTriggerKey"
        x-bind:class="subOpen ? 'bg-[var(--color-wk-bg-hover)]' : ''"
        {{ $attributes->except(['role', 'tabindex', 'aria-expanded', 'type'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['wk-touch-row', $itemClasses, $disabledClasses])) }}
    >
        @if($icon)
            <span class="shrink-0 w-5 h-5" aria-hidden="true">
                {{ \Pushery\WireKit\Support\IconSvg::html($icon, ['class' => 'w-5 h-5']) }}
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
        aria-labelledby="{{ $subTriggerId }}"
        data-wk-submenu-panel
        x-on:mouseenter="_clearCloseTimer()"
        x-on:mouseleave="scheduleClose()"
        x-on:keydown="onSubKey"
        @class([$panelClasses])
    >
        {{ $slot }}
    </div>
</div>
