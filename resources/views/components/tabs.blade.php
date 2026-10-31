{{-- optimistic-ui: n/a — client-only
     Its state is which tab is active. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@php
    // A panel arrives as a slot named after its tab's key, and Blade sets each slot as a variable
    // of that name before the props below are read, where it takes the place of the attribute: a
    // tab keyed `active` or `items` would replace that prop with its panel. The panels are read
    // from `$__laravel_slots` (Support\KeyedSlot), so the slot variables are let go first.
    foreach (array_keys($__laravel_slots ?? []) as $__wkSlotName) {
        if (is_string($__wkSlotName) && ! in_array($__wkSlotName, ['__default', 'slot', 'attributes', '__laravel_slots'], true)) {
            unset($$__wkSlotName);
        }
    }
    unset($__wkSlotName);
@endphp
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
    'label' => __('wirekit::Tabs'),
    // Inactive panels become findable by the browser's find in page, which switches to the tab a
    // match lands in. Off by default: an inactive tab is often a view of its own, and a search
    // that changes the reader's view unasked is a surprise. Only where the engine supports it.
    'findable' => config('wirekit.components.tabs.findable', false),
    // A horizontal bar too long for its row wraps its tabs onto further lines instead of
    // scrolling: `wrap` at every width, or `wrap="md"` (or `sm`, `lg`, `xl`) from that width up,
    // so a phone keeps the bar that scrolls. The arrow keys move along the tabs in either form.
    'wrap' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // `findable="false"` on an unbound tag is the string "false", which is truthy.
    $findable = BooleanProp::from($findable, false, 'tabs', 'findable');

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
    //   $tabs[key] = ['label' => string, 'icon' => ?string, 'badge' => ?scalar, 'badgeIntent' => ?string]
    // Accepts BOTH historical input shapes:
    //   - Keyed-assoc (legacy):  ['profile' => 'Profile', 'billing' => 'Billing']
    //   - Array-of-objects:      [['key' => 'profile', 'label' => 'Profile',
    //                              'icon' => 'user', 'badge' => 3, 'badgeIntent' => 'danger'], ...]
    // Only the array-of-objects shape can carry per-tab `icon` / `badge`. `badgeIntent` is the
    // badge's tone, one of the badge's own intents: a count of errors in a hidden panel reads as a
    // neutral counter otherwise. Null keeps the badge's default.
    //
    // Detection: PHP 8.1+ array_is_list() returns true for a zero-indexed
    // sequential array (the shape of array-of-objects). Keyed-assoc returns false.
    //
    // The items may arrive as a Collection. One that was filtered keeps the keys it had, so a
    // list of tab records is also recognized by its records: every entry an array with a `key`.
    $tabs = [];
    $items = \Pushery\WireKit\Support\ListProp::records($items, 'tabs', 'items');
    $itemsAreRecords = is_array($items) && $items !== []
        && array_all($items, static fn ($item): bool => is_array($item) && isset($item['key']));
    if (is_array($items) && (array_is_list($items) || $itemsAreRecords)) {
        foreach ($items as $item) {
            if (is_array($item) && isset($item['key'])) {
                $tabs[$item['key']] = [
                    'label' => $item['label'] ?? $item['key'],
                    'icon' => $item['icon'] ?? null,
                    'badge' => $item['badge'] ?? null,
                    'badgeIntent' => $item['badgeIntent'] ?? null,
                ];
            }
        }
    } else {
        foreach ($items as $key => $val) {
            $tabs[$key] = ['label' => $val, 'icon' => null, 'badge' => null, 'badgeIntent' => null];
        }
    }

    // A tab named by an enum, as a model attribute cast to one hands it over, is the tab of its
    // value (Support\FieldValue).
    $active = \Pushery\WireKit\Support\FieldValue::one($active, 'tabs', 'active');
    $default = \Pushery\WireKit\Support\FieldValue::one($default, 'tabs', 'default');

    // Resolve the initial active tab: explicit default, otherwise first key
    $activeTab = $active ?? $default ?? (array_key_first($tabs) ?? '');

    // The Alpine seed, deliberately WITHOUT the `$active` term. `$activeTab`
    // changes whenever the server changes the tab, and a changing value in
    // `x-data` is rewritten by every Livewire morph — which re-initializes the
    // scope, after which an effect queued against the old one writes the old
    // value last. This one is derived from props alone, so the attribute renders
    // byte-identical every time and the scope survives. When the server drives
    // the tab it arrives through `data-wk-server-value`, which the factory reads
    // at init and the observer watches afterwards.
    $seedTab = $default ?? (array_key_first($tabs) ?? '');

    // Key→label map exposed to Alpine so the `wirekit:tab-changed` event payload
    // can carry the human label alongside the key (detail = { tab, label }).
    $tabLabels = array_map(fn ($t) => $t['label'], $tabs);

    // Unique instance id — needed so multiple Tabs components on the same page
    // don't clash on `aria-controls`/`id` attributes when mounted together.
    // Same morph defect as collapsible, multiplied by the number of tabs: every button
    // and every panel carries this prefix in its id, so a random one has Livewire remove
    // and re-clone all 2N of them on every round trip. A keyboard user loses focus to
    // <body> mid-interaction (the roving-tabindex position goes with the node), and the
    // active panel's slot — the developer's own markup, which is where forms live — is
    // destroyed and rebuilt.
    //
    // The tab KEYS are the seed: they are the caller's own vocabulary and stable across
    // renders by construction. Two tabsets with the same keys on one page are told apart
    // by DomId: the first keeps the derived id and the second gets `-2`, so every
    // `aria-controls` and `aria-labelledby` stays inside its own tabset. A caller-supplied
    // id still wins, verbatim, because the root element carries it as given.
    $uid = $attributes->get('id') ?: \Pushery\WireKit\Support\DomId::unique(
        \Pushery\WireKit\WireKit::stableId('wk-tabs', implode('-', array_keys($tabs))),
        'wk-tabs-'
    );

    // The ids of each tab and its panel, made once, so that both loops below name the same pair.
    // An id holds no whitespace (Support\DomId::valid()): the key `new york` makes
    // `…-tab-new-york`, and a given id `my tabs` the prefix `my-tabs`, while the root keeps the id
    // as written. Two keys that read alike then (`a b`, `a-b`) keep apart through
    // DomId::distinct(), which leaves an id that is new to the page as it is.
    $idPrefix = \Pushery\WireKit\Support\DomId::valid((string) $uid) ?? (string) $uid;
    $pairIds = [];
    foreach (array_keys($tabs) as $key) {
        $pairIds[$key] = [
            'tab' => \Pushery\WireKit\Support\DomId::distinct($idPrefix.'-tab-'.$key),
            'panel' => \Pushery\WireKit\Support\DomId::distinct($idPrefix.'-panel-'.$key),
        ];
    }

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
    // Beside the class seam, so a scope that replaces the bar keeps the wrap asked for here.
    $wrapClasses = \Pushery\WireKit\Support\TablistStyles::wrap(\Pushery\WireKit\Support\TablistStyles::wrapValue($wrap, 'tabs'), $isVertical);
    $tabClasses = WireKit::resolveClasses('tabs', 'tab', \Pushery\WireKit\Support\TablistStyles::tab($variant, $isVertical), $scope);
    $tabActiveClasses = \Pushery\WireKit\Support\TablistStyles::tabActive($variant);
    $tabInactiveClasses = \Pushery\WireKit\Support\TablistStyles::tabInactive();

    // Root layout — vertical places the tablist beside the panels (flex row);
    // horizontal stacks them (the panel sits below the tablist).
    $rootClasses = $isVertical ? 'flex gap-[var(--padding-wk-x-lg)] items-start' : '';

    // Panel container — vertical takes the remaining inline space; horizontal pads the top. The
    // distance below the tab bar is `--wk-tabs-panel-gap` when a page or a theme sets it, so the
    // first card under the bar can sit as far from it as two cards sit from each other, and the
    // panel block stays WireKit's. Unset, it is `--padding-wk-y-md`, resolved where the panel is.
    $panelClasses = WireKit::resolveClasses('tabs', 'panel',
        $isVertical ? 'flex-1 min-w-0' : 'pt-[var(--wk-tabs-panel-gap,var(--padding-wk-y-md))]',
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
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes, holdsParts: true);
    // A caller's `aria-labelledby` or `aria-label` names the element a reader meets, below; on this
    // wrapper, which has no role, ARIA prohibits a name (Support\CallerName).
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
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
        active: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $seedTab) }},
        labels: {{ \Pushery\WireKit\Support\AlpinePayload::from((object) $tabLabels) }},
        warning: {{ \Pushery\WireKit\Support\AlpinePayload::from($wireModelWarning) }},
    })"
    @if($active !== null)
        {{-- The channel, and only when it is used: a call site without `active`
             renders no attribute for it. The attribute is written by the
             server on every render and bound by nobody, which is what makes "the
             server changed it" a fact the DOM can state rather than something to
             infer. See utils/server-value.js for why a dedicated attribute rather
             than the component's own state. --}}
        data-wk-server-value="{{ $activeTab }}"
    @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}
>
    {{-- Tablist — the row (or column, when vertical) of tab buttons.
         role="tablist" groups the tab buttons as a single keyboard navigation unit. Without
         tabs the row carries no role: a tablist that owns no tab reads out as an empty control. --}}
    <div @if($tabs !== []) role="tablist" {{ \Pushery\WireKit\Support\CallerName::attribute($callerLabelledBy, $callerLabel, $label) }} aria-orientation="{{ $orientationValue }}" @endif class="{{ $tablistClasses }}{{ $wrapClasses !== '' ? ' '.$wrapClasses : '' }}">
        @foreach($tabs as $key => $tab)
            <button
                type="button"
                role="tab"
                id="{{ $pairIds[$key]['tab'] }}"
                aria-controls="{{ $pairIds[$key]['panel'] }}"
                {{-- Before the script, the tab of the panel the server renders open: the seed. --}}
                aria-selected="{{ (string) $key === (string) $seedTab ? 'true' : 'false' }}"
                {{-- Every comparison and every write takes the key as text, the form the seed and
                     the server's attribute arrive in: a key PHP holds as a number, an id or a
                     year, is the text of its digits on both sides. --}}
                :aria-selected="active === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }} ? 'true' : 'false'"
                :tabindex="active === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }} ? '0' : '-1'"
                @click="active = {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }}"
                @if($isVertical)
                    @keydown.arrow-down.prevent="focusTab('next')"
                    @keydown.arrow-up.prevent="focusTab('prev')"
                @else
                    @keydown.arrow-right.prevent="focusTab('next')"
                    @keydown.arrow-left.prevent="focusTab('prev')"
                @endif
                @keydown.home.prevent="focusTab('first')"
                @keydown.end.prevent="focusTab('last')"
                :class="active === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }} ? {{ \Pushery\WireKit\Support\AlpinePayload::string($tabActiveClasses) }} : {{ \Pushery\WireKit\Support\AlpinePayload::string($tabInactiveClasses) }}"
                class="wk-tab wk-touch-row {{ $tabClasses }}"
            >
                @if($tab['icon'])
                    <x-wirekit::icon :name="$tab['icon']" size="sm" />
                @endif
                <span>{{ $tab['label'] }}</span>
                @php($badgeText = \Pushery\WireKit\Support\LocalizedNumber::countOrNull($tab['badge']))
                @if($badgeText !== null)
                    <x-wirekit::badge size="sm" :intent="$tab['badgeIntent']">{{ $badgeText }}</x-wirekit::badge>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Tab panels — one per item. Each pulls its content from a named slot
         matching the item key (`<x-slot:{key}>...</x-slot:{key}>`). Only the
         active panel is visible; others stay in the DOM but are hidden via x-show. --}}
    <div class="{{ $panelClasses }}">
        @foreach($tabs as $key => $tab)
            {{-- One panel element in both modes, its attributes branched inside the tag. An `@if`
                 around an opening tag alone gets Livewire's block markers on both sides of it, the
                 start beside the panel and the end inside it, and a morph that adds, drops or
                 reorders panels then finds no end to the block and throws.

                 FINDABLE PANEL. An inactive one is `hidden="until-found"` where the engine supports
                 it, and a match activates its tab through `x-on:beforematch`, so `aria-selected`
                 and the roving tab stop follow. The tab stop is BOUND: an element that is itself
                 until-found still takes focus, by script and by Tab, in Chromium and WebKit, so
                 an inactive panel carries no `tabindex` at all. See utils/findable.js. The panel of
                 the seed tab is drawn open and says so with `data-wk-rendered-open`: the server can
                 name another tab, which the start then shows, and without the mark the seed's panel
                 would read as one found before the script ran, and take the tab back.

                 Otherwise every panel is a tab stop, shown by `x-show`. --}}
            <div
                role="tabpanel"
                id="{{ $pairIds[$key]['panel'] }}"
                aria-labelledby="{{ $pairIds[$key]['tab'] }}"
                @if(! $findable) tabindex="0" @elseif((string) $key === (string) $seedTab) tabindex="0" data-wk-rendered-open @else hidden="until-found" @endif
                class="rounded-[var(--radius-wk-sm)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                @if($findable)
                    :tabindex="active === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }} ? '0' : null"
                    x-wk-findable="active === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }}"
                    x-on:beforematch="active = {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }}"
                @else
                    x-show="active === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $key) }}"
                    x-cloak
                @endif
            >
                {{-- The slot the item key names, as written or, for a hyphenated key, in the camel
                     form Blade gives `<x-slot:api-keys>`. Empty when no slot names it. --}}
                {{ \Pushery\WireKit\Support\KeyedSlot::find($__laravel_slots ?? [], $key) ?? '' }}
            </div>
        @endforeach
    </div>
</div>
