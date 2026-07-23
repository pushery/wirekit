@props([
    'target' => null,
    'levels' => '2,3',
    'position' => 'right',
    'expand' => 'hover',
    'offset' => '6rem',
    'hideBelow' => 'md',
    'numbered' => false,
    'fillSections' => false,
    'sectionEvents' => true,
    'backToTop' => false,
    // `boundary` — null (default) = viewport-pinned via Tailwind `fixed`.
    // `'container'` = scoped to the nearest positioned ancestor via
    // Tailwind `absolute`. Use `'container'` when embedding the spine
    // inside a contained reading surface (a modal body, a sidebar pane,
    // a docs preview frame) so it doesn't escape to the outer viewport.
    'boundary' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $numbered = BooleanProp::from($numbered, false);
    $fillSections = BooleanProp::from($fillSections, false);
    $sectionEvents = BooleanProp::from($sectionEvents, true);
    $backToTop = BooleanProp::from($backToTop, false);

    // Reading-spine — sidebar mini-TOC that auto-builds from page headings,
    // tracks scroll-position via IntersectionObserver, and expands on hover
    // or focus. Drives the wirekitReadingSpine Alpine plugin (resources/js/
    // components/reading-spine.js — bundled into dist/wirekit.js).
    //
    // The component opt-in story is the standard WireKit composition pattern:
    // place the Blade tag where you want a spine, the plugin only initializes
    // there. No global side effects, no auto-injection.

    // levels: comma-separated string -> int[] for the plugin
    $levelsArray = collect(explode(',', (string) $levels))
        ->map(fn ($v) => (int) trim($v))
        ->filter(fn ($v) => $v >= 1 && $v <= 6)
        ->values()
        ->all();

    // hideBelow controls a Tailwind responsive prefix that toggles display.
    // Mobile (< breakpoint) never sees the spine — hover doesn't exist on
    // touch and the fixed sidebar would crowd narrow viewports.
    $hideBelowClass = match ($hideBelow) {
        'sm' => 'hidden sm:block',
        'md' => 'hidden md:block',
        'lg' => 'hidden lg:block',
        'xl' => 'hidden xl:block',
        'none' => '',
        default => 'hidden md:block',
    };

    // Position class — left or right viewport edge. Both pin vertically
    // centered with translate-y, leaving a margin gutter to expand into.
    $positionClass = match ($position) {
        'left' => 'left-4',
        default => 'right-4',
    };

    // Expand mode — `always` and `always-md` (ES-1) force expanded state.
    // `always` is unconditionally expanded; `always-md` is expanded only at
    // md+ (mirrors hideBelow's mobile-hidden default for symmetry).
    // `hover` and `focus` rely on the plugin's hover/focus handlers.
    $forceExpanded = $expand === 'always';
    $forceExpandedMd = $expand === 'always-md';

    // Convert `offset` (CSS string like '6rem') to px for the plugin.
    // Cheap PHP-side conversion: parse the leading number, scale rem->px
    // assuming 16px root font-size. Not used for layout, only for the IO
    // rootMargin / scrollTo offset math in the plugin.
    $offsetPx = 96;
    if (preg_match('/^([\d.]+)\s*(px|rem|em)?$/', (string) $offset, $m)) {
        $val = (float) $m[1];
        $unit = $m[2] ?? 'px';
        $offsetPx = (int) round($unit === 'px' ? $val : $val * 16);
    }

    // Resolve boundary (v2.4.0 Ext 1 extended). null = viewport-pinned
    // via Tailwind `fixed`. 'container' = scoped via Tailwind `absolute`
    // to the nearest positioned ancestor. Any other non-empty string is
    // treated as a CSS selector — the spine renders Tailwind `absolute`
    // and the JS layer (where applicable) can verify the selector matches.
    if ($boundary === null) {
        $resolvedBoundary = null;
        $boundarySelector = null;
    } elseif ($boundary === 'container') {
        $resolvedBoundary = 'container';
        $boundarySelector = null;
    } elseif (is_string($boundary) && $boundary !== '') {
        $resolvedBoundary = 'selector';
        $boundarySelector = $boundary;
    } else {
        $resolvedBoundary = WireKit::validateProp(
            'reading-spine',
            'boundary',
            (string) $boundary,
            ['container', '<css-selector-string>']
        );
        $boundarySelector = null;
    }

    $useScoped = $resolvedBoundary === 'container' || $resolvedBoundary === 'selector';
    // Viewport mode: `fixed` + vertically centered. Scoped/container mode: the
    // aside is `absolute` at the top of a zero-height STICKY wrapper (rendered
    // below) so it PINS to the scroll container's viewport (offset by
    // --reading-spine-offset-top) instead of scrolling away with the content —
    // an `absolute` element alone is anchored to the scrolling box and scrolls
    // out of view with the article.
    $boundaryClass = $useScoped
        ? 'absolute top-0 z-[var(--z-wk-sticky)]'
        : 'fixed top-1/2 -translate-y-1/2 z-[var(--z-wk-sticky)]';

    // Marker class drives the print-stylesheet hide rule + reduced-motion
    // gating + doubled-class specificity for any developer overrides.
    // `tabindex="0"` + `focus-visible:` ring on the scroll-overflow
    // container satisfies WCAG 2.1.1 (keyboard operability) — when the
    // navigation links overflow vertically, the spine itself is reachable
    // via Tab so the keyboard user can scroll it (the inner <a> links
    // are tabbable too but Tab-skipping them when the user wants to scroll
    // the container is the canonical pattern). Focus-visible ring uses
    // the standard --ring-wk-* tokens; hidden on mouse-only focus.
    // `overflow-x-hidden` is explicit (not implied by `overflow-y-auto`):
    // per CSS spec, setting one axis to `auto` while leaving the other at
    // `visible` computes BOTH axes to `auto`. Long heading labels with
    // `white-space: nowrap` on `.wk-reading-spine__label` would then
    // produce a horizontal scrollbar inside the spine even though the
    // ellipsis truncation should clip them visually. Pinning x-axis to
    // hidden + y-axis to auto gives true vertical-only scroll.
    $rootClass = WireKit::resolveClasses('reading-spine', 'base', implode(' ', [
        'wk-reading-spine',
        $boundaryClass,
        'wk-scrollbar max-h-[calc(100vh-8rem)] overflow-x-hidden overflow-y-auto',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        $positionClass,
        $hideBelowClass,
    ]), $scope);

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Developer-passed selector wins over the default. Documented under
    // "Family contracts → target prop convention" in docs/components/reading.md.
    $resolvedTarget = $target ?? 'main, article';

    // Plugin options as JSON for the x-data initializer. Keep keys terse.
    $alpineOptions = json_encode([
        'target' => $resolvedTarget,
        'levels' => $levelsArray,
        'offset' => $offsetPx,
        'numbered' => filter_var($numbered, FILTER_VALIDATE_BOOL),
        'fillSections' => filter_var($fillSections, FILTER_VALIDATE_BOOL),
        'sectionEvents' => filter_var($sectionEvents, FILTER_VALIDATE_BOOL),
    ], JSON_THROW_ON_ERROR);
@endphp

@if ($useScoped)
{{-- Sticky pin wrapper (scoped/container mode only): a zero-height sticky
     positioning context. It sticks to the scroll container's viewport at
     --reading-spine-offset-top from the top, and the absolute spine inside it
     rides along — so the spine stays pinned instead of scrolling with content.
     height:0 keeps it out of the article's flow (no pushed-down content); the
     spine's hover-expand still overlays. Viewport mode skips this (the aside is
     `fixed`). Inline-styled so it works in the docs sandbox without Tailwind. --}}
<div style="position: sticky; top: var(--reading-spine-offset-top, 1rem); height: 0; z-index: var(--z-wk-sticky);">
@endif
<aside
    x-data="wirekitReadingSpine({{ $alpineOptions }})"
    x-init="
        @if ($forceExpanded) expanded = true;
        @elseif ($forceExpandedMd) if (window.matchMedia('(min-width: 768px)').matches) expanded = true;
        @endif
    "
    x-bind:data-expand="expanded ? 'true' : 'false'"
    data-side="{{ $position === 'left' ? 'left' : 'right' }}"
    @if (! $forceExpanded)
        @mouseenter="expandOnHover()"
        @mouseleave="collapseOnHover()"
        @focusin="expandOnFocus()"
        @focusout="collapseOnFocus()"
    @endif
    x-show="items.length > 0"
    x-cloak
    {{ $attributes->class([$rootClass])->merge(['aria-label' => 'Page contents', 'tabindex' => '0']) }}
>
    {{-- Optional developer-supplied filter input slot. Two-way-binds to
         `filter` Alpine state via `x-model` on the developer's input. --}}
    @if (isset($filter))
        <div class="wk-reading-spine__filter px-2 pb-2 border-b border-[var(--color-wk-border)]">
            {{ $filter }}
        </div>
    @endif

    <nav>
        {{--
            Inline-style the list primitives so the spine renders correctly
            in the docs sandbox iframe, where developer Tailwind isn't loaded
            — `<ol>` would otherwise show its UA decimal markers and 40px
            indent. See the sibling reading-toc note for the full rationale.
        --}}
        {{--
            Inline-style guard for the docs-sandbox iframe context (no Tailwind):
            `padding: var(--reading-spine-padding-y) var(--reading-spine-padding-x)`
            gives the ticks/labels a guttered inset from the aside's left+right
            edges (without it the ticks sit flush against the right edge — the
            "no padding between dot and edge" symptom). Gap is driven by the
            CSS variable so the list density tracks `--reading-spine-gap`.
        --}}
        <ol
            class="flex flex-col py-[var(--reading-spine-padding-y)] px-[var(--reading-spine-padding-x)]"
            style="list-style: none; margin: 0; padding-top: var(--reading-spine-padding-y); padding-right: var(--reading-spine-padding-x); padding-bottom: var(--reading-spine-padding-y); padding-left: var(--reading-spine-padding-x); display: flex; flex-direction: column; gap: var(--reading-spine-gap);"
        >
            <template x-for="item in items" :key="item.id">
                <li
                    class="wk-reading-spine__item relative"
                    x-show="matchesFilter(item)"
                >
                    {{--
                        `padding-left: calc((${item.level} - {{ min }}) * ...)` —
                        note the `${}` interpolation around `item.level`. Without
                        it the literal text "item.level" appears inside the CSS
                        calc(), which is invalid and the browser drops the rule
                        entirely — every heading rendered at the same indent,
                        H3-under-H2 nesting invisible. Same bug class as inline
                        Tailwind utilities not resolving in the sandbox iframe;
                        the fix is the same shape too (compute layout values via
                        inline style instead of relying on a class system that
                        only applies when Tailwind is loaded).
                    --}}
                    <a
                        :href="`#${item.id}`"
                        :data-active="item.index === activeIndex ? 'true' : 'false'"
                        :data-level="item.level"
                        :aria-current="item.index === activeIndex ? 'location' : null"
                        class="wk-reading-spine__link group flex items-center gap-2 cursor-pointer text-sm rounded-[var(--radius-wk-sm)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        :style="`padding-left: calc((${item.level} - {{ min($levelsArray) }}) * 0.5rem); padding-top: 0.125rem; padding-bottom: 0.125rem; text-decoration: none;`"
                        @click="scrollTo(item.id, $event)"
                    >
                        {{--
                            Tick — visual indicator that the spine menu exists
                            and can be hovered to expand. Width depends on
                            heading level (declared via `[data-level]` CSS
                            rules in dist/wirekit.css, since the `tickWidthClass`
                            Tailwind utilities don't resolve in the docs-
                            sandbox iframe). `display: block` is inline-styled
                            so the width rule actually paints — without it,
                            a default `<span>` stays `display: inline`,
                            ignores `width`, and renders 0 px wide → the
                            "no affordance visible, can't tell the menu is
                            there" symptom on every /components/reading page.
                            `height` reads from the canonical tick-height token.
                            `fillSections` paints a per-section progress fill
                            via background-gradient, gated by the prop.
                        --}}
                        <span
                            class="wk-reading-spine__tick rounded-[var(--radius-wk-full,9999px)]"
                            :class="tickWidthClass(item.level)"
                            :style="`display: block; height: var(--reading-spine-tick-height); @if (filter_var($fillSections, FILTER_VALIDATE_BOOL)) background: linear-gradient(to right, var(--reading-spine-color-active) ${item.fill * 100}%, var(--reading-spine-color-idle) ${item.fill * 100}%); @endif`"
                            aria-hidden="true"
                        ></span>

                        {{-- Optional numeric label (ES-3). Renders only when
                             numbered=true; takes precedence over an empty
                             label slot (collapsed-state covered by CSS). --}}
                        @if (filter_var($numbered, FILTER_VALIDATE_BOOL))
                            <span
                                class="wk-reading-spine__number tabular-nums text-xs text-[var(--reading-spine-color-idle)]"
                                x-text="item.label"
                                aria-hidden="true"
                            ></span>
                        @endif

                        {{-- Expanded label — fades in when expanded=true via
                             CSS in dist/wirekit.css. text-current inherits
                             the color state from the link's data-active. --}}
                        <span
                            class="wk-reading-spine__label text-[var(--reading-spine-color-idle)]"
                            x-text="item.text"
                        ></span>
                    </a>
                </li>
            </template>
        </ol>

        @if (filter_var($backToTop, FILTER_VALIDATE_BOOL))
            {{-- ES-5 back-to-top pill — fixed at the spine bottom, scrolls
                 to top on click. Reduced-motion respected via the same
                 matchMedia check as scrollTo. Visible only when expanded
                 OR when scrollY > 0 (you're past the top). --}}
            <button
                type="button"
                class="wk-reading-spine__back-to-top mt-2 ml-2 inline-flex items-center justify-center w-8 h-8 rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                aria-label="{{ __('Back to top') }}"
                @click="window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V5.612L5.29 9.77a.75.75 0 01-1.08-1.04l5.25-5.5a.75.75 0 011.08 0l5.25 5.5a.75.75 0 11-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0110 17z" clip-rule="evenodd"/>
                </svg>
            </button>
        @endif
    </nav>
</aside>
@if ($useScoped)
</div>
@endif
