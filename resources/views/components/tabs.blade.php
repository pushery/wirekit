@props([
    'items' => [],
    'default' => null,
    'variant' => config('wirekit.components.tabs.variant', 'underline'),
    'label' => 'Tabs',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Resolve the initial active tab: explicit default, otherwise first key
    $activeTab = $default ?? (array_key_first($items) ?? '');

    // Unique instance id — needed so multiple Tabs components on the same page
    // don't clash on `aria-controls`/`id` attributes when mounted together.
    $uid = 'wk-tabs-' . \Illuminate\Support\Str::random(6);

    // Tablist container — horizontal row of tab buttons. Variant controls bottom
    // border (underline), background pill track (pills), or bordered segments.
    $tablistBase = match ($variant) {
        'pills' => 'inline-flex items-center gap-1 p-1 rounded-[var(--radius-wk-lg)] bg-[var(--color-wk-bg-muted)]',
        'bordered' => 'inline-flex items-center border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] overflow-hidden',
        default => 'inline-flex items-center gap-2 border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
    };

    $tablistClasses = WireKit::resolveClasses('tabs', 'tablist', $tablistBase, $scope);

    // Per-tab button classes. active/inactive state toggled via :class="..."
    // Bound to Alpine's `active === key` expression in the loop below.
    $tabBase = 'inline-flex items-center gap-2 font-[number:var(--font-wk-body-weight)] text-[length:var(--text-wk-sm)] transition-colors duration-[var(--transition-wk-duration)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed cursor-pointer';

    $tabVariantClasses = match ($variant) {
        'pills' => 'p-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-md)]',
        'bordered' => 'p-[var(--padding-wk-x-sm)] border-r-[length:var(--border-wk-width)] border-[var(--color-wk-border)] last:border-r-0',
        default => 'p-[var(--padding-wk-x-sm)] -mb-[length:var(--border-wk-width)] border-b-[3px] border-transparent',
    };

    $tabClasses = WireKit::resolveClasses('tabs', 'tab', $tabBase . ' ' . $tabVariantClasses, $scope);

    // Active-state classes applied conditionally via Alpine :class binding.
    $tabActiveClasses = match ($variant) {
        'pills' => 'bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-sm)]',
        'bordered' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        default => 'border-[var(--color-wk-accent)] text-[color:var(--color-wk-text)]',
    };

    $tabInactiveClasses = 'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]';

    // Panel container — the area below the tablist that shows the active panel.
    $panelClasses = WireKit::resolveClasses('tabs', 'panel', 'pt-[var(--padding-wk-y-md)]', $scope);
@endphp

{{-- Tabs root — holds shared Alpine state and ARIA wiring.
     Arrow-key navigation is handled inline (roving tabindex pattern): ArrowLeft/ArrowRight
     move focus between tab buttons, Home/End jump to first/last, Space/Enter activate. --}}
<div
    x-data="{
        active: @js($activeTab),
        focusTab(direction) {
            const buttons = $el.querySelectorAll('[role=tab]:not([disabled])');
            const current = document.activeElement;
            const idx = Array.from(buttons).indexOf(current);
            if (idx === -1) return;
            let next;
            if (direction === 'next') next = (idx + 1) % buttons.length;
            else if (direction === 'prev') next = (idx - 1 + buttons.length) % buttons.length;
            else if (direction === 'first') next = 0;
            else if (direction === 'last') next = buttons.length - 1;
            buttons[next]?.focus();
        }
    }"
    {{ $attributes }}
>
    {{-- Tablist — horizontal list of tab buttons.
         role="tablist" groups the tab buttons as a single keyboard navigation unit. --}}
    <div role="tablist" aria-label="{{ $label }}" class="{{ $tablistClasses }}">
        @foreach($items as $key => $label)
            <button
                type="button"
                role="tab"
                id="{{ $uid }}-tab-{{ $key }}"
                aria-controls="{{ $uid }}-panel-{{ $key }}"
                :aria-selected="active === @js($key) ? 'true' : 'false'"
                :tabindex="active === @js($key) ? '0' : '-1'"
                @click="active = @js($key)"
                @keydown.arrow-right.prevent="focusTab('next')"
                @keydown.arrow-left.prevent="focusTab('prev')"
                @keydown.home.prevent="focusTab('first')"
                @keydown.end.prevent="focusTab('last')"
                :class="active === @js($key) ? '{{ $tabActiveClasses }}' : '{{ $tabInactiveClasses }}'"
                class="{{ $tabClasses }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Tab panels — one per item. Each pulls its content from a named slot
         matching the item key (`<x-slot:{key}>...</x-slot:{key}>`). Only the
         active panel is visible; others stay in the DOM but are hidden via x-show. --}}
    <div class="{{ $panelClasses }}">
        @foreach($items as $key => $label)
            <div
                role="tabpanel"
                id="{{ $uid }}-panel-{{ $key }}"
                aria-labelledby="{{ $uid }}-tab-{{ $key }}"
                tabindex="0"
                x-show="active === @js($key)"
                x-cloak
            >
                {{-- Dynamic slot lookup: render the named slot whose name matches
                     the item key. Falls back to empty string if no slot was provided. --}}
                {{ ${$key} ?? '' }}
            </div>
        @endforeach
    </div>
</div>
