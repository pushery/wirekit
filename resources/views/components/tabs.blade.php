@props([
    'items' => [],
    'default' => null,
    'variant' => config('wirekit.components.tabs.variant', 'underline'),
    'label' => 'Tabs',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Normalize $items at the template edge: accept BOTH historical shapes:
    //   - Keyed-assoc (legacy):    ['profile' => 'Profile', 'billing' => 'Billing']
    //   - Array-of-objects:        [['key' => 'profile', 'label' => 'Profile'], ...]
    // After this block, $items is always the keyed-assoc form so the
    // existing render loops + Alpine bindings stay byte-stable.
    //
    // Detection: PHP 8.1+ array_is_list() returns true for a zero-indexed
    // sequential array (the shape of array-of-objects). Keyed-assoc
    // returns false. Empty array stays empty.
    if (is_array($items) && array_is_list($items)) {
        $normalized = [];
        foreach ($items as $item) {
            if (is_array($item) && isset($item['key'])) {
                $normalized[$item['key']] = $item['label'] ?? $item['key'];
            }
        }
        $items = $normalized;
    }

    // Resolve the initial active tab: explicit default, otherwise first key
    $activeTab = $default ?? (array_key_first($items) ?? '');

    // Unique instance id — needed so multiple Tabs components on the same page
    // don't clash on `aria-controls`/`id` attributes when mounted together.
    $uid = 'wk-tabs-' . \Illuminate\Support\Str::random(6);

    // Tablist container — horizontal row of tab buttons. Variant controls bottom
    // border (underline), background pill track (pills), or bordered segments.
    //
    // `max-w-full overflow-x-auto overflow-y-hidden` makes the tab bar
    // horizontally scrollable when the labels exceed the available width (the
    // canonical mobile tab-bar pattern — Material / iOS both scroll). The
    // explicit `overflow-y-hidden` is REQUIRED: a bare `overflow-x-auto`
    // computes `overflow-y` to `auto` per CSS spec (a non-visible value on one
    // axis forces the other off `visible`), so the moment the horizontal
    // scrollbar consumes vertical space a phantom VERTICAL scrollbar appeared
    // over the tablist on mobile. Matches the canonical horizontal scroll-area
    // shape (`scroll-area` 'horizontal' variant). The `bordered` variant
    // already had effective `overflow-y:hidden` via its `overflow-hidden`.
    // Desktop is unchanged: `inline-flex`
    // still sizes to content when it fits; the cap + scroll only engage on
    // narrow viewports. WCAG 2.1.1 is satisfied because the container carries
    // role="tablist" with arrow-key navigation (a composite widget owning its
    // own keyboard model — scroll-region rule shape #1). Each tab also
    // carries a no-shrink + no-wrap rule (see $tabBase below) so the
    // rounded track doesn't squish its children below their label width.
    $tablistBase = match ($variant) {
        'pills' => 'inline-flex items-center gap-1 p-1 rounded-[var(--radius-wk-lg)] bg-[var(--color-wk-bg-muted)] max-w-full overflow-x-auto overflow-y-hidden',
        'bordered' => 'inline-flex items-center border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] overflow-hidden max-w-full overflow-x-auto',
        default => 'inline-flex items-center gap-2 border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] max-w-full overflow-x-auto overflow-y-hidden',
    };

    $tablistClasses = WireKit::resolveClasses('tabs', 'tablist', $tablistBase, $scope);

    // Per-tab button classes. active/inactive state toggled via :class="..."
    // Bound to Alpine's `active === key` expression in the loop below.
    // `shrink-0 whitespace-nowrap` keep each tab at its natural label width
    // inside the scrollable tablist — without them, a narrow viewport would
    // squish tabs and wrap/clip labels instead of letting the bar scroll.
    $tabBase = 'inline-flex items-center gap-2 shrink-0 whitespace-nowrap font-[number:var(--font-wk-body-weight)] text-[length:var(--text-wk-sm)] transition-colors duration-[var(--transition-wk-duration)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed cursor-pointer';

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

    // Dev-mode warning: tabs are client-only Alpine state — wire:model
    // passed on the component tag is silently dropped into the outer
    // div's attribute bag (Livewire only watches input/select/textarea).
    // Surface the silent-breakage at runtime via console.warn so the
    // developer doesn't waste time wondering why their tab state isn't
    // syncing to Livewire. Production stays silent.
    $hasWireModel = false;
    foreach ($attributes->getAttributes() as $key => $_) {
        if (is_string($key) && str_starts_with($key, 'wire:model')) {
            $hasWireModel = true;
            break;
        }
    }
    $warnWireModelInDebug = $hasWireModel && config('app.debug');
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
    @if($warnWireModelInDebug)
        x-init="console.warn('[wirekit] tabs: wire:model dropped — tabs are client-only Alpine state, not a Livewire input. Use named slots for content (<x-slot:keyname>...</x-slot:keyname>) per items[key]. See https://docs.wirekit.app/components/tabs for the contract.')"
    @endif
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
