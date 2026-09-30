{{-- optimistic-ui: n/a — client-only
     Its state is the current heading. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'target' => null,
    'levels' => '2',
    'position' => 'top',
    'offset' => '0',
    'hideBelow' => 'sm',
    // `flush` — when true, the strip runs viewport-edge-to-viewport-edge:
    // zero horizontal padding on the nav AND the inner list AND the
    // first / last link. The strip's chrome (background + bottom border)
    // and the visible "Features" / "Pricing" / "…" text both run flush
    // to the viewport boundary. Use when the TOC sits below an
    // edge-to-edge marketing-page chrome (brand-bar with no `padding="lg"`
    // setting, or sandwiched between a hero and the article body). The
    // hover-state background keeps its internal padding on the opposite
    // edge so the rounded background still has breathing room around
    // the rendered text. Default `false` keeps backward-compatible
    // rendering with every link symmetrically padded.
    'flush' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reading-toc', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $flush = BooleanProp::from($flush, false);

    // Reading-toc — horizontal sticky-strip TOC variant of the reading family.
    // Same data source as reading-spine (auto-built from page headings); different
    // rendered shape (flat horizontal nav across the top/bottom of the viewport)
    // and different responsive behavior (mobile-hidden by default since narrow
    // viewports cannot host a 3-4-link horizontal strip without overflow).
    //
    // Use case: marketing landing pages with 3-4 anchored sections (Hero,
    // Features, Pricing, FAQ). Sidebar spine feels excessive; a strip across
    // the top with active-section highlighting is the right shape.
    //
    // Drives the wirekitReadingToc Alpine plugin (resources/js/components/
    // reading-toc.js — bundled into dist/wirekit.js).

    // levels: comma-separated string -> int[] for the plugin. Default '2' for
    // landing-page flat structure (Hero / Features / Pricing / FAQ all <h2>);
    // developers opt into nested levels via levels="2,3" explicitly.
    $levelsArray = collect(explode(',', (string) $levels))
        ->map(fn ($v) => (int) trim($v))
        ->filter(fn ($v) => $v >= 1 && $v <= 6)
        ->values()
        ->all();

    // hideBelow controls a Tailwind responsive prefix that toggles display.
    // Mobile (< breakpoint) hides the strip — narrow viewports can't host
    // a horizontal 3-4-link strip without overflow. Mitigation note: use
    // <x-wirekit::reading-spine hideBelow="none"> if a TOC is needed on
    // mobile (its collapsed-ticks mode works at narrow widths).
    $hideBelowClass = match ($hideBelow) {
        'sm' => 'hidden sm:block',
        'md' => 'hidden md:block',
        'lg' => 'hidden lg:block',
        'xl' => 'hidden xl:block',
        'none' => '',
        default => 'hidden sm:block',
    };

    // Sticky positioning is owned by the `.wk-reading-toc` rule in
    // `dist/wirekit.css` (load-bearing layout — see the rule comment
    // for the rationale). The `data-position` attribute on the <nav>
    // selects between top-sticky and bottom-sticky variants in the CSS.

    // Convert `offset` (CSS string like '4rem') to px for the plugin.
    // Same parser as reading-spine for symmetry. Used for the IO rootMargin
    // and scrollTo offset math; the CSS positioning reads from --reading-toc-offset
    // directly via inline style below.
    $offsetPx = 0;
    if (preg_match('/^([\d.]+)\s*(px|rem|em)?$/', (string) $offset, $m)) {
        $val = (float) $m[1];
        $unit = $m[2] ?? 'px';
        $offsetPx = (int) round($unit === 'px' ? $val : $val * 16);
    }

    // CSS-side offset string passed through as-is so `'4rem'`, `'72px'`, etc.
    // all work. Validated above to be a numeric+unit shape; defensively
    // fall back to '0' if the developer passed garbage.
    $offsetCss = preg_match('/^([\d.]+)\s*(px|rem|em)?$/', (string) $offset)
        ? (string) $offset
        : '0';

    // Marker class — drives the print-stylesheet hide rule + reduced-motion
    // gating.
    // Sticky positioning + top/bottom offset are owned by the
    // `.wk-reading-toc` rule in `dist/wirekit.css` (selected per
    // `data-position` attribute below).
    $rootClass = 'wk-reading-toc '.WireKit::resolveClasses('reading-toc', 'base', implode(' ', array_filter([
        filter_var($flush, FILTER_VALIDATE_BOOL) ? 'wk-reading-toc--flush' : '',
        $hideBelowClass,
    ])), $scope);

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Documented under "Family contracts → target prop convention" in
    // docs/components/reading.md.
    $resolvedTarget = $target ?? 'main, article';

    // Plugin options as a JS literal for the x-data initializer.
    //
    // AlpinePayload, not json_encode: `target` is a developer-supplied CSS selector, so a
    // heading id such as `#überschrift` is ordinary input here. A plain encode escapes it
    // as `ü`, and Alpine's CSP tokenizer drops that backslash and keeps the letters —
    // the selector arrives as `#u00fcberschrift`, matches nothing, and the list stays empty
    // behind its own `x-show="items.length > 0"`, so nothing on the page says why.
    $alpineOptions = \Pushery\WireKit\Support\AlpinePayload::from([
        'target' => $resolvedTarget,
        'levels' => $levelsArray,
        'offset' => $offsetPx,
    ]);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // The list hides itself with an `x-show` of its own while the page has no headings, so a
    // caller's `x-show` or `wire:show` cannot sit on it as well: the parser drops a second
    // attribute of one name, and a bound `wire:show` would toggle the list independently of it.
    // They go on a wrapper that generates no box, with the rest of what is about the whole
    // component (Support\OuterAttributes).
    [$outerAttributes, $innerAttributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);
    $showScope = \Pushery\WireKit\Support\OuterAttributes::shows($outerAttributes);

    if ($showScope) {
        $attributes = $innerAttributes;
    }
@endphp

@if($showScope)
<div class="contents" {{ $outerAttributes }}>
@endif
<nav
    x-data="wirekitReadingToc({{ $alpineOptions }})"
    x-show="items.length > 0"
    x-cloak
    data-position="{{ $position }}"
    {{-- The landmark's name is the one string this component writes on the
         developer's behalf, so it asks the catalog rather than freezing English
         into the markup. `merge()` still keeps it a DEFAULT: a caller passing
         `aria-label="…"` wins, exactly as before. The key ships in every locale
         file. reading-minimap names its own landmark differently, because two
         navigation landmarks with one name cannot be told apart. --}}
    {{-- The offset goes into the bag's `style` rather than an attribute of its own beside it: a
         caller's `style` would stand in the tag a second time, and the parser keeps the first
         one, so the offset would be gone. Merged, the caller's declarations follow ours. --}}
    {{ $attributes->class([$rootClass])->merge(['aria-label' => __('wirekit::Page sections'), 'style' => '--reading-toc-offset: '.$offsetCss.';']) }}
>
    {{--
        Inline-style the load-bearing list primitives. The Tailwind utility
        classes (`list-none`, `flex flex-row`, the spacing values) exist only
        where a Tailwind build scanned this view; in a page whose stylesheet
        did not, the browser falls back to `<ol>`'s defaults (`list-style:
        decimal`, `padding-inline-start: 40px`, block layout) and the TOC strip
        renders as a vertical numbered list with deep indentation instead of a
        horizontal pill row: visible "1. 2. 3." markers, staggered link text,
        whitespace above the content. Utility classes for decoration, inline
        style for load-bearing layout primitives.
    --}}
    <ol data-wk-prose-skip role="list"
        class="wk-reading-toc__list wk-scrollbar flex flex-row items-center gap-[var(--reading-toc-gap)] py-[var(--reading-toc-padding-y)] px-[var(--reading-toc-padding-x)] overflow-x-auto"
        style="list-style: none; margin: 0; display: flex; flex-direction: row; align-items: center;"
    >
        <template x-for="item in items" :key="item.id">
            <li data-wk-prose-skip class="wk-reading-toc__item shrink-0">
                <a data-wk-prose-skip
                    :href="'#' + item.id"
                    :data-active="item.index === activeIndex ? 'true' : 'false'"
                    :data-level="item.level"
                    :aria-current="item.index === activeIndex ? 'location' : null"
                    class="wk-reading-toc__link inline-block max-w-[var(--reading-toc-link-max-width)] truncate text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    x-text="item.text"
                    @click="scrollTo(item.id, $event)"
                ></a>
            </li>
        </template>
    </ol>
</nav>
@if($showScope)
</div>
@endif
