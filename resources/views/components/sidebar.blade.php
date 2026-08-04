{{-- optimistic-ui: n/a — client-only
     Its state is rail collapse state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Collapse-to-icon rail. When true, the sidebar can collapse to a narrow
    // icon-only rail: an auto-rendered toggle flips the state, item/group labels
    // become sr-only (accessible names preserved), and only icons stay visible.
    'collapsible' => false,
    // Initial collapsed state (only meaningful with `collapsible`).
    'collapsed' => false,
    // Surface shape. `card` (default) is the self-contained panel: background,
    // border on all four sides, rounded, meant to sit inside a padded column.
    // `flush` is the full-bleed navigation COLUMN of the common admin layout —
    // no radius, no surrounding border, the surface inherited from the host, and
    // a single inline-end edge separating it from the content.
    //
    // Two variants rather than a set of booleans, because the halves are not
    // independent: a rounded panel with no border reads as a rendering fault, and
    // a full-bleed column with a radius shows a sliver of the page at each corner.
    // Default stays `card` so no existing sidebar changes.
    'variant' => 'card',
    // Where the auto-rendered collapse toggle sits, or `none` to omit it and
    // supply your own. `none` keeps the state machinery here — a developer who
    // wanted the trigger elsewhere previously had to switch `collapsible` off and
    // rebuild the rail's Alpine state, the persisted flag and the marker the
    // descendant items read, which is vendor mechanics reimplemented in an app.
    // An outside trigger reaches this sidebar through the `wirekit:sidebar:toggle`
    // window event instead; see the docs page.
    'toggle' => 'end',
    // Optional localStorage key — persists the collapsed state across reloads.
    'persist' => null,
    // Accessible name for the navigation landmark. Defaults to "Sidebar" so
    // assistive tech can tell it apart from the main <nav>; override it when the
    // page carries more than one navigation landmark. Passing aria-label OR
    // aria-labelledby directly on the component also wins over this default
    // (and suppresses it, so the <nav> never gets a duplicate/conflicting name).
    'label' => __('Sidebar'),
    // The edge shadows on the scrolling middle. On by default, because a
    // scrollbar alone is easy to miss on a track that fades when idle.
    //
    // It has to be separable from the zones, and that is the point of the prop:
    // the zones and the shadows arrived as one thing, so a developer with their
    // own edge treatment could only be rid of ours by dropping `header` and
    // `footer` entirely — which also gives up the sticky head and foot, a
    // completely unrelated capability. Two decisions were being made by one
    // answer.
    'scrollShadows' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $collapsible = BooleanProp::from($collapsible, false);
    $collapsed = BooleanProp::from($collapsed, false);

    // Landmark accessible name. Only emit our default `aria-label` when the
    // developer supplied NEITHER aria-label NOR aria-labelledby directly on the
    // component — a raw aria-label / aria-labelledby always wins and suppresses
    // the default, so the <nav> never carries a duplicate or conflicting name.
    // Merging (vs a hardcoded literal) also keeps every other passed-through
    // attribute (id, data-*) intact instead of dropping it.
    $navLabelAttrs = ($attributes->has('aria-label') || $attributes->has('aria-labelledby'))
        ? []
        : ['aria-label' => $label];

    // Sidebar root: a semantic <nav> landmark that holds grouped navigation
    // items. Uniform `p-[var(--padding-wk-y-sm)]` (= 0.375rem all four
    // sides) so the OUTER gap between the sidebar border and each item's
    // hover/active highlight matches the INNER gap between the item edge
    // and the label text. With asymmetric `px-x-sm py-y-sm` (0.625rem
    // horizontal, 0.375rem vertical) the outer gap looked 67% larger
    // than the inner — visually unbalanced.
    $variant = in_array($variant, ['card', 'flush'], true) ? $variant : 'card';

    // The surface half, split out so the two shapes read as alternatives rather
    // than as a base with exceptions bolted on. `border-e` is the LOGICAL
    // inline-end edge, so a right-to-left document gets the separator on the side
    // that faces its content instead of the side that faces the page margin.
    $surface = $variant === 'flush'
        ? [
            'border-e-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
            // Fills its column. Only the browser showed why this is needed: the edge
            // is drawn on THIS element, so a nav sized to its content ends the
            // separator wherever the last item happens to fall — measured 200px of
            // line in a 340px column, which reads as a rendering fault rather than
            // as a short list. The card variant must NOT have it, because a card is
            // supposed to be as tall as its content.
            'h-full',
        ]
        : [
            'bg-[var(--color-wk-bg-elevated)]',
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
            'rounded-[var(--radius-wk-lg)]',
        ];

    $classes = WireKit::resolveClasses('sidebar', 'base', implode(' ', [
        'flex flex-col',
        'gap-[var(--space-wk-sm)]',
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)]',
        ...$surface,
    ]), $scope);

    $toggle = in_array($toggle, ['start', 'end', 'none'], true) ? $toggle : 'end';

    // Collapse toggle button — only rendered (and only styled) for a collapsible
    // sidebar. Resolvable rather than a local variable: as a local it could not be
    // themed, moved or replaced, so the only way to restyle the one chrome control
    // in the component was a CSS rule against `> button:first-child` — a selector
    // that depends on the vendor's child order and breaks the moment anything is
    // rendered before it.
    $collapseBtnClasses = WireKit::resolveClasses('sidebar', 'toggle', implode(' ', [
        $toggle === 'start' ? 'self-start' : 'self-end',
        'inline-flex items-center justify-center shrink-0',
        'p-1 rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)] cursor-pointer',
        // In the collapsed rail the button centers with the icons.
        'group-data-[collapsed]/wk-sidebar:self-center',
    ]), $scope);

    // Three zones — fixed head, scrolling middle, fixed foot — but ONLY when a
    // head or foot is actually supplied. A sidebar with neither keeps rendering
    // its slot bare, so nothing about an existing call site changes.
    //
    // The zones exist because every developer building this layout writes the same
    // three lines and makes the same mistake in them: without `min-h-0` on the
    // middle, a flex item refuses to shrink below its content, so the head and
    // foot are pushed out of view and scroll away with the list instead of staying
    // put. The bug looks like the scroll region is missing when in fact it is the
    // whole column that scrolled.
    $hasZones = isset($header) || isset($footer);
    $scrollShadows = BooleanProp::from($scrollShadows, true);
@endphp

@if($collapsible)
    {{-- Collapsible rail. The `collapsed` state lives here; `group/wk-sidebar` +
         the `data-collapsed` marker let descendant labels/indicators react via
         pure CSS (`group-data-[collapsed]/wk-sidebar:*`) — no per-item Alpine.
         3.5rem is the structural icon-rail width (icon + padding), not a theme
         value, so it stays a literal like the structural w-9 day cells. --}}
    <nav
        {{-- The rail's folded state lives in resources/js/components/sidebar-rail.js.
             It cannot live here: an inline object literal cannot declare methods
             under Alpine's CSP build, so the fold button did nothing at all under
             a strict policy. The key is emitted as a JSON literal rather than
             through {{ \Pushery\WireKit\Support\AlpinePayload::from() }}, because {{ \Pushery\WireKit\Support\AlpinePayload::from() }} renders a non-empty payload as
             JSON.parse(…) and JSON is a global the CSP evaluator cannot resolve. --}}
        x-data="wirekitSidebarRail({ collapsed: {{ $collapsed ? 'true' : 'false' }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }} })"
        :data-collapsed="collapsed ? '' : null"
        :class="collapsed ? 'w-[3.5rem]' : 'w-[var(--wk-sidebar-w,16rem)]'"
        {{ $attributes->class([$classes, 'group/wk-sidebar transition-[width] duration-[var(--transition-wk-duration)]'])->merge($navLabelAttrs) }}
    >
        @if($toggle !== 'none')
        <button
            type="button"
            x-on:click="toggle()"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :aria-label="collapsed ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('Expand sidebar')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('Collapse sidebar')) }}"
            class="{{ $collapseBtnClasses }}"
        >
            <svg class="h-5 w-5 transition-transform duration-[var(--transition-wk-duration)]" :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 4.5 11.25 12l7.5 7.5m-7.5-15L3.75 12l7.5 7.5" />
            </svg>
        </button>
        @endif
        @include('wirekit::components.partials.sidebar-zones')
    </nav>
@else
    {{-- <nav> carries a default aria-label so AT distinguishes it from the main
         nav; a developer-supplied aria-label / aria-labelledby / `label` prop overrides it. --}}
    <nav {{ $attributes->class([$classes])->merge($navLabelAttrs) }}>
        @include('wirekit::components.partials.sidebar-zones')
    </nav>
@endif
