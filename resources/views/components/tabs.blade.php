{{-- optimistic-ui: n/a — client-only
     Its state is which tab is active. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'items' => [],
    // Which tab opens on first paint. Initial only — Alpine reads a seed once, so
    // a later value here never reaches a rendered tablist.
    'default' => null,
    // Which tab is active, as the SERVER sees it. Distinct from `default` on
    // purpose: this one keeps arriving. Set it and the tablist follows the server
    // on every round trip — a tab restored from a URL, a validation error whose
    // field lives in another panel, a permission that just changed. Leave it unset
    // and nothing about the rendered output moves.
    'active' => null,
    'variant' => config('wirekit.components.tabs.variant', 'underline'),
    'orientation' => 'horizontal', // horizontal (default) | vertical
    'label' => __('Tabs'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tabs', $attributes->getAttributes());

    $orientationValue = match ($orientation) {
        'horizontal', 'vertical' => $orientation,
        default => WireKit::validateProp('tabs', 'orientation', $orientation, ['horizontal', 'vertical']),
    };
    $isVertical = $orientationValue === 'vertical';

    // Normalize $items at the template edge to a keyed map of per-tab metadata:
    //   $tabs[key] = ['label' => string, 'icon' => ?string, 'badge' => ?scalar]
    // Accepts BOTH historical input shapes:
    //   - Keyed-assoc (legacy):  ['profile' => 'Profile', 'billing' => 'Billing']
    //   - Array-of-objects:      [['key' => 'profile', 'label' => 'Profile',
    //                              'icon' => 'user', 'badge' => 3], ...]
    // Only the array-of-objects shape can carry per-tab `icon` / `badge`.
    //
    // Detection: PHP 8.1+ array_is_list() returns true for a zero-indexed
    // sequential array (the shape of array-of-objects). Keyed-assoc returns false.
    $tabs = [];
    if (is_array($items) && array_is_list($items)) {
        foreach ($items as $item) {
            if (is_array($item) && isset($item['key'])) {
                $tabs[$item['key']] = [
                    'label' => $item['label'] ?? $item['key'],
                    'icon' => $item['icon'] ?? null,
                    'badge' => $item['badge'] ?? null,
                ];
            }
        }
    } else {
        foreach ($items as $key => $val) {
            $tabs[$key] = ['label' => $val, 'icon' => null, 'badge' => null];
        }
    }

    // Resolve the initial active tab: explicit default, otherwise first key
    $activeTab = $active ?? $default ?? (array_key_first($tabs) ?? '');

    // Key→label map exposed to Alpine so the `wirekit:tab-changed` event payload
    // can carry the human label alongside the key (detail = { tab, label }).
    $tabLabels = array_map(fn ($t) => $t['label'], $tabs);

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
    // The bar's appearance lives in Support\TablistStyles, shared with the panel-less
    // `tabs.list` / `tabs.tab` pair. Two bars with the same surface and different
    // behavior must not carry two class ladders — they drift a border-radius apart over
    // a few releases and nothing goes red while they do.
    $tablistClasses = WireKit::resolveClasses('tabs', 'tablist', \Pushery\WireKit\Support\TablistStyles::list($variant, $isVertical), $scope);
    $tabClasses = WireKit::resolveClasses('tabs', 'tab', \Pushery\WireKit\Support\TablistStyles::tab($variant, $isVertical), $scope);
    $tabActiveClasses = \Pushery\WireKit\Support\TablistStyles::tabActive($variant);
    $tabInactiveClasses = \Pushery\WireKit\Support\TablistStyles::tabInactive();

    // Root layout — vertical places the tablist beside the panels (flex row);
    // horizontal stacks them (the panel sits below the tablist).
    $rootClasses = $isVertical ? 'flex gap-[var(--padding-wk-x-lg)] items-start' : '';

    // Panel container — vertical takes the remaining inline space; horizontal pads the top.
    $panelClasses = WireKit::resolveClasses('tabs', 'panel',
        $isVertical ? 'flex-1 min-w-0' : 'pt-[var(--padding-wk-y-md)]',
        $scope);

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
    {{-- Selection, the change event and the roving-focus model live in the
         factory (resources/js/components/tabs.js). Alpine's CSP build parses
         neither the method shorthand nor the arrow-function $watch callback, so
         the whole object failed to build and the tablist stopped responding to
         clicks AND arrow keys. --}}
    {{-- The debug-only wire:model warning rides in the factory's config rather
         than its own x-init: an element carries one Alpine component, and an
         inline console.warn throws under the CSP build while BUILDING the
         component — taking the tablist down with it. --}}
    @php
        // Composed in PHP, not inline in the directive: Blade's component-tag
        // compiler rewrites an `<x-slot:…>` wherever it appears in TEMPLATE
        // text — including inside a string literal in a `{{ }}` echo, where it
        // is a parse error rather than a bad string. Inside @php it is ordinary
        // PHP and survives whole, which also keeps it a single literal that the
        // drift scanner recognizes as a warning rather than a class list.
        $wireModelWarning = $warnWireModelInDebug
            ? '[wirekit] tabs: wire:model dropped — tabs are client-only Alpine state, not a Livewire input. Use the `active` prop to drive the tablist from the server, and named slots for content (<x-slot:keyname>...</x-slot:keyname>) per items[key]. See https://docs.wirekit.app/components/tabs for the contract.'
            : null;
    @endphp
    x-data="wirekitTabs({
        active: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $activeTab) }},
        labels: {{ \Pushery\WireKit\Support\AlpinePayload::from((object) $tabLabels) }},
        warning: {{ \Pushery\WireKit\Support\AlpinePayload::from($wireModelWarning) }},
    })"
    @if($active !== null)
        {{-- The channel, and only when it is used: an unchanged call site renders
             byte for byte what it did before. The attribute is written by the
             server on every render and bound by nobody, which is what makes "the
             server changed it" a fact the DOM can state rather than something to
             infer. See utils/server-value.js for why a dedicated attribute rather
             than the component's own state. --}}
        data-wk-server-value="{{ $activeTab }}"
    @endif
    {{ $attributes->class([$rootClasses]) }}
>
    {{-- Tablist — the row (or column, when vertical) of tab buttons.
         role="tablist" groups the tab buttons as a single keyboard navigation unit. --}}
    <div role="tablist" aria-label="{{ $label }}" aria-orientation="{{ $orientationValue }}" class="{{ $tablistClasses }}">
        @foreach($tabs as $key => $tab)
            <button
                type="button"
                role="tab"
                id="{{ $uid }}-tab-{{ $key }}"
                aria-controls="{{ $uid }}-panel-{{ $key }}"
                :aria-selected="active === {{ \Pushery\WireKit\Support\AlpinePayload::from($key) }} ? 'true' : 'false'"
                :tabindex="active === {{ \Pushery\WireKit\Support\AlpinePayload::from($key) }} ? '0' : '-1'"
                @click="active = {{ \Pushery\WireKit\Support\AlpinePayload::from($key) }}"
                @if($isVertical)
                    @keydown.arrow-down.prevent="focusTab('next')"
                    @keydown.arrow-up.prevent="focusTab('prev')"
                @else
                    @keydown.arrow-right.prevent="focusTab('next')"
                    @keydown.arrow-left.prevent="focusTab('prev')"
                @endif
                @keydown.home.prevent="focusTab('first')"
                @keydown.end.prevent="focusTab('last')"
                :class="active === {{ \Pushery\WireKit\Support\AlpinePayload::from($key) }} ? '{{ $tabActiveClasses }}' : '{{ $tabInactiveClasses }}'"
                class="{{ $tabClasses }}"
            >
                @if($tab['icon'])
                    <x-wirekit::icon :name="$tab['icon']" class="h-4 w-4 shrink-0" />
                @endif
                <span>{{ $tab['label'] }}</span>
                @if($tab['badge'] !== null && $tab['badge'] !== '')
                    <x-wirekit::badge size="sm">{{ $tab['badge'] }}</x-wirekit::badge>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Tab panels — one per item. Each pulls its content from a named slot
         matching the item key (`<x-slot:{key}>...</x-slot:{key}>`). Only the
         active panel is visible; others stay in the DOM but are hidden via x-show. --}}
    <div class="{{ $panelClasses }}">
        @foreach($tabs as $key => $tab)
            <div
                role="tabpanel"
                id="{{ $uid }}-panel-{{ $key }}"
                aria-labelledby="{{ $uid }}-tab-{{ $key }}"
                tabindex="0"
                x-show="active === {{ \Pushery\WireKit\Support\AlpinePayload::from($key) }}"
                x-cloak
            >
                {{-- Dynamic slot lookup: render the named slot whose name matches
                     the item key. Falls back to empty string if no slot was provided. --}}
                {{ ${$key} ?? '' }}
            </div>
        @endforeach
    </div>
</div>
