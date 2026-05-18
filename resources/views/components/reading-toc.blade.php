@props([
    'target' => null,
    'levels' => '2',
    'position' => 'top',
    'offset' => '0',
    'hideBelow' => 'sm',
    // `flush` — when true, the first link's left-edge padding and the
    // last link's right-edge padding are zeroed so the visible text
    // aligns flush with the strip's content edges. Use when the TOC is
    // nested directly under a main wrapper / brand-bar and the consumer
    // wants the strip's first link to sit on the same vertical content-
    // edge spine as the brand logo and the article h2 headings. The
    // hover-state background keeps its internal padding on the opposite
    // edge so the rounded background still has breathing room around
    // the rendered text. Default `false` keeps backward-compatible
    // rendering with every link symmetrically padded.
    'flush' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Reading-toc — horizontal sticky-strip TOC variant of the reading family.
    // Same data source as reading-spine (auto-built from page headings); different
    // rendered shape (flat horizontal nav across the top/bottom of the viewport)
    // and different responsive behaviour (mobile-hidden by default since narrow
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
    // consumers opt into nested levels via levels="2,3" explicitly.
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
    // fall back to '0' if the consumer passed garbage.
    $offsetCss = preg_match('/^([\d.]+)\s*(px|rem|em)?$/', (string) $offset)
        ? (string) $offset
        : '0';

    // Marker class — drives the print-stylesheet hide rule + reduced-motion
    // gating + doubled-class specificity for any consumer overrides.
    // Sticky positioning + top/bottom offset are owned by the
    // `.wk-reading-toc` rule in `dist/wirekit.css` (selected per
    // `data-position` attribute below).
    $rootClass = WireKit::resolveClasses('reading-toc', 'base', implode(' ', array_filter([
        'wk-reading-toc',
        filter_var($flush, FILTER_VALIDATE_BOOL) ? 'wk-reading-toc--flush' : '',
        $hideBelowClass,
    ])), $scope);

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Documented under "Family contracts → target prop convention" in
    // docs/components/reading.md.
    $resolvedTarget = $target ?? 'main, article';

    // Plugin options as JSON for the x-data initialiser.
    $alpineOptions = json_encode([
        'target' => $resolvedTarget,
        'levels' => $levelsArray,
        'offset' => $offsetPx,
    ], JSON_THROW_ON_ERROR);
@endphp

<nav
    x-data="wirekitReadingToc({{ $alpineOptions }})"
    x-show="items.length > 0"
    x-cloak
    data-position="{{ $position }}"
    {{ $attributes->class([$rootClass])->merge(['aria-label' => 'Page sections']) }}
    style="--reading-toc-offset: {{ $offsetCss }};"
>
    {{--
        Inline-style the load-bearing list primitives because the docs-site
        sandbox iframe renders previews WITHOUT consumer Tailwind. The
        Tailwind utility classes (`list-none` via preflight, `flex flex-row`,
        the spacing values) only apply when the consumer's compiled CSS is in
        scope — without it, the browser falls back to `<ol>`'s defaults
        (`list-style: decimal`, `padding-inline-start: 40px`, block layout)
        and the TOC strip renders as a vertical numbered list with massive
        indentation instead of a horizontal pill row. /recipes/marketing-
        landing-toc surfaced the bug shape: visible "1. 2. 3." markers,
        offset/staggered link text, gaping whitespace above the content.
        Same lesson as the sparkline-inline + chart-wrapper-width fixes:
        utility classes for decoration, inline style for load-bearing
        layout primitives.
    --}}
    <ol
        class="wk-reading-toc__list flex flex-row items-center gap-[var(--reading-toc-gap)] py-[var(--reading-toc-padding-y)] px-[var(--reading-toc-padding-x)] overflow-x-auto"
        style="list-style: none; margin: 0; display: flex; flex-direction: row; align-items: center;"
    >
        <template x-for="item in items" :key="item.id">
            <li class="wk-reading-toc__item shrink-0">
                <a
                    :href="`#${item.id}`"
                    :data-active="item.index === activeIndex ? 'true' : 'false'"
                    :data-level="item.level"
                    :aria-current="item.index === activeIndex ? 'location' : null"
                    class="wk-reading-toc__link inline-block max-w-[var(--reading-toc-link-max-width)] truncate text-sm rounded-[var(--radius-wk-sm)] px-2 py-1 focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    x-text="item.text"
                    @click="scrollTo(item.id, $event)"
                ></a>
            </li>
        </template>
    </ol>
</nav>
