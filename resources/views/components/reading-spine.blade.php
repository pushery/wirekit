{{-- optimistic-ui: n/a — client-only
     Its state is the current section. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'target' => null,
    // A selector for subtrees whose headings are NOT this page's structure, such as live
    // component demos, whose accordion panel titles or carousel product names would otherwise
    // become entries. `target` cannot help there, because the demos sit INSIDE the article that
    // holds the real sections. Unset (the default), every heading in `target` is an entry.
    'exclude' => null,
    'levels' => '2,3',
    'position' => 'right',
    // How far in from the edge `position` chose. `position` picks WHICH edge; this
    // picks the distance, and `0` is expressible: a spine flush against the window.
    // An unlayered stylesheet override would reach every spine in the app rather than
    // the one call that wants it, and it wins only because Tailwind v4 emits its
    // utilities into a layer. Null keeps 1rem.
    'edgeOffset' => null,
    'expand' => 'hover',
    // NOT the distance from the edge — that is `edgeOffset` above. This is the
    // scroll/observer offset the plugin measures a section's arrival against.
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
    // `ticks` (the default) is the classic spine: pinned to an edge of the window or a
    // container, a column of ticks that expands on hover. `rail` is an outline in the page's own
    // flow, for a grid column beside the article: every entry stays visible on a line that bends
    // with the indent, and a band on that line slides to the current section. The rail places
    // itself where the page puts it, so `expand`, `boundary`, `position` and `edge-offset` do
    // nothing there.
    'layout' => 'ticks',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reading-spine', $attributes->getAttributes());

    // An unknown value of one of these props is reported (Support\StrictnessGate) and renders as
    // the default; a bound `null` or empty string is the default without a word.
    $position = \Pushery\WireKit\WireKit::validateProp('reading-spine', 'position', \Pushery\WireKit\Support\NumericProp::text($position ?: 'right'), ['right', 'left']);
    $hideBelow = \Pushery\WireKit\WireKit::validateProp('reading-spine', 'hideBelow', \Pushery\WireKit\Support\NumericProp::text($hideBelow ?: 'md'), ['md', 'sm', 'lg', 'xl', 'none']);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['@mouseenter', '@mouseleave', '@focusin', '@focusout', 'x-on:keydown.escape.window.capture']);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $numbered = BooleanProp::from($numbered, false, 'reading-spine', 'numbered');
    $fillSections = BooleanProp::from($fillSections, false, 'reading-spine', 'fillSections');
    $sectionEvents = BooleanProp::from($sectionEvents, true, 'reading-spine', 'sectionEvents');
    $backToTop = BooleanProp::from($backToTop, false, 'reading-spine', 'backToTop');

    // Reading-spine — sidebar mini-TOC that auto-builds from page headings,
    // tracks the active section as the page scrolls, and expands on hover
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

    // A `levels` string can filter down to NOTHING — `levels="0,7"` is entirely
    // out of range, and a value that arrived with its own quotes still attached
    // (`levels="'2'"`) casts to 0. The empty array would then reach `min()` at
    // the bottom of this block, and `min([])` is a ValueError: the whole page
    // carrying the spine would fail with a 500 rather than the spine degrading.
    //
    // Falling back to the `levels` default keeps the promise the prop makes — an
    // out-of-range level is FILTERED, not fatal — and it is the one outcome a
    // reader can act on: a default outline instead of a white screen. The fallback
    // spells the same `2,3` the prop declares as its default.
    $levelsArray = $levelsArray ?: [2, 3];

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
    // The fallback in the arbitrary value is 1rem, one spacing step. Same shape the
    // sidebar uses for its width: the property is
    // settable from a `style` attribute or from CSS, and the utility carries the
    // default rather than a second declaration that could drift away from it.
    //
    // The two step utilities are deliberately not named here: Tailwind scans the raw file,
    // comments included, so writing them would emit them into the compiled CSS as selectors
    // no element uses.
    $positionClass = match ($position) {
        'left' => 'left-[var(--reading-spine-edge-offset,1rem)]',
        default => 'right-[var(--reading-spine-edge-offset,1rem)]',
    };

    // A length or nothing. Blade escapes the interpolation, so a quote cannot break
    // out of the attribute — but an unparseable value would still reach the browser
    // and silently drop the whole declaration, taking the position with it. Rejecting
    // it here keeps a typo loud in debug instead of invisible in the layout.
    // `0` is deliberately valid without a unit; it is the whole point of the prop.
    $edgeOffsetStyle = null;
    if ($edgeOffset !== null && $edgeOffset !== '') {
        $edgeOffsetValue = trim(\Pushery\WireKit\Support\NumericProp::text($edgeOffset));

        if (preg_match('/^-?(\d*\.?\d+)(px|rem|em|%|vw|vh|ch)?$/', $edgeOffsetValue)) {
            // The declaration closes with its own semicolon: a caller's `style` is appended
            // after it, and without one the two would read as a single value.
            $edgeOffsetStyle = '--reading-spine-edge-offset: '.$edgeOffsetValue.';';
        } else {
            WireKit::validateProp('reading-spine', 'edge-offset', $edgeOffsetValue, ['0', '1rem', '12px', '2%']);
        }
    }

    // An unknown layout raises in debug and reads as the default in production.
    $layout = WireKit::validateProp('reading-spine', 'layout', (string) $layout, ['ticks', 'rail']);
    $rail = $layout === 'rail';

    // Expand mode — `always` and `always-md` force expanded state.
    // `always` is unconditionally expanded; `always-md` is expanded only at
    // md+ (mirrors hideBelow's mobile-hidden default for symmetry).
    // `hover` and `focus` rely on the plugin's hover/focus handlers.
    // `focus` reads as `hover`, which expands on focus as well; any other value is reported in
    // development and reads as `hover` too.
    $expand = WireKit::validateProp('reading-spine', 'expand', (string) $expand, ['hover', 'focus', 'always', 'always-md']);
    // The rail lists every entry with its title, so it is always expanded.
    $expand = $rail ? 'always' : $expand;
    $forceExpanded = $expand === 'always';
    $forceExpandedMd = $expand === 'always-md';

    // Convert `offset` (CSS string like '6rem') to px for the plugin.
    // Cheap PHP-side conversion: parse the leading number, scale rem->px
    // assuming 16px root font-size. Not used for layout, only for the plugin's
    // active-section line and its scrollTo offset math.
    $offsetPx = 96;
    if (preg_match('/^([\d.]+)\s*(px|rem|em)?$/', (string) $offset, $m)) {
        $val = (float) $m[1];
        $unit = $m[2] ?? 'px';
        $offsetPx = (int) round($unit === 'px' ? $val : $val * 16);
    }

    // Resolve boundary. null = viewport-pinned
    // via Tailwind `fixed`. 'container' = scoped via Tailwind `absolute`
    // to the nearest positioned ancestor. Any other non-empty string is
    // treated as a CSS selector — the spine renders Tailwind `absolute`
    // and the JS layer (where applicable) can verify the selector matches.
    if ($boundary === null || $rail) {
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

    // Marker class drives the print-stylesheet hide rule, the reduced-motion
    // gating and the component's base rules in the stylesheet.
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
    //
    // The rail is in the page's flow, so it carries no position and no edge. From `md` up its
    // outline scrolls inside a frame a window high, through its rules in the stylesheet: on a
    // phone it is an outline above the article, where a scroller inside the page would be the
    // wrong shape.
    $rootClass = 'wk-scrollbar '.WireKit::resolveClasses('reading-spine', 'base', implode(' ', array_filter([
        'wk-reading-spine',
        $rail ? null : $boundaryClass,
        $rail ? null : 'max-h-[calc(100vh-8rem)] overflow-x-hidden overflow-y-auto',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        $rail ? null : $positionClass,
        $hideBelowClass,
    ])), $scope);

    // In the rail the root is a `<div>`: inside `<main>`, an `<aside>` would be a complementary
    // landmark nested in the main one. The `<nav>` is the landmark there, named by a visible label,
    // and a `<div>` without a role carries no name, so a caller's `aria-label` becomes the label's
    // text and a caller's `aria-labelledby` names the `<nav>`, which then needs no label of its own.
    $rootTag = $rail ? 'div' : 'aside';
    $callerLabelledBy = $rail ? trim((string) $attributes->get('aria-labelledby', '')) : '';
    $railLabel = $rail ? (\Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label') ?: __('wirekit::Page contents')) : null;
    $railLabelId = $rail ? \Pushery\WireKit\Support\DomId::unique(null, 'wk-reading-spine-label-') : null;
    // The frame the phone's disclosure button controls.
    $railFrameId = $rail ? \Pushery\WireKit\Support\DomId::unique(null, 'wk-reading-spine-frame-') : null;

    // Outside the rail the `<aside>` is the complementary landmark, and the `<nav>` inside it is a
    // navigation landmark of its own that a screen reader lists beside a page's other navigations.
    // It carries the aside's name: a caller's `aria-labelledby`, else a caller's `aria-label`, else
    // the default the aside takes.
    $asideLabelledBy = $rail ? '' : trim((string) $attributes->get('aria-labelledby', ''));
    $asideLabel = $rail || $asideLabelledBy !== '' ? null : (\Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label') ?: __('wirekit::Page contents'));

    if ($rail) {
        $attributes = $attributes->except(['aria-label', 'aria-labelledby']);
    }

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Developer-passed selector wins over the default. Documented under
    // "Family contracts → target prop convention" in docs/components/reading.md.
    $resolvedTarget = $target ?? 'main, article';

    // Plugin options as JSON for the x-data initializer. Keep keys terse.
    // AlpinePayload, not json_encode: `target` and `exclude` are developer-supplied CSS
    // selectors, so a heading id such as `#überschrift` is ordinary input here. A plain
    // encode escapes it as `ü`, and Alpine's CSP tokenizer drops that backslash and
    // keeps the letters — the selector arrives as `#u00fcberschrift` and matches nothing.
    $alpineOptions = \Pushery\WireKit\Support\AlpinePayload::from([
        'target' => $resolvedTarget,
        'exclude' => $exclude,
        'levels' => $levelsArray,
        'offset' => $offsetPx,
        'numbered' => filter_var($numbered, FILTER_VALIDATE_BOOL),
        'fillSections' => filter_var($fillSections, FILTER_VALIDATE_BOOL),
        'sectionEvents' => filter_var($sectionEvents, FILTER_VALIDATE_BOOL),
        // Config rather than an `x-init` carrying Blade control flow: Alpine's CSP
        // build parses ONE expression, so a pair of statements wrapped in `@if`
        // would be refused outright — and a refused x-init leaves the element with
        // an empty scope, which takes every other directive on it down silently.
        'forceExpanded' => filter_var($forceExpanded, FILTER_VALIDATE_BOOL),
        'forceExpandedMd' => filter_var($forceExpandedMd, FILTER_VALIDATE_BOOL),
        // The shallowest level in the spine — the link indent counts from it,
        // and passing it here keeps the arithmetic in one place instead of
        // interpolated into a style string.
        'baseLevel' => min($levelsArray),
        'rail' => $rail,
    ]);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // What is about the whole component goes on its outermost element (Support\OuterAttributes).
    // In a container that is the pin wrapper below. In the viewport the aside is outermost, but
    // it hides itself with an `x-show` of its own while the page has no headings, so a caller's
    // `x-show` or `wire:show` cannot sit on it as well: the parser drops a second attribute of
    // one name, and a bound `wire:show` would toggle the aside independently of it. Only then
    // does the aside get a wrapper, one that generates no box.
    [$outerAttributes, $innerAttributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);
    $showScope = ! $useScoped && \Pushery\WireKit\Support\OuterAttributes::shows($outerAttributes);

    if ($useScoped || $showScope) {
        $attributes = $innerAttributes;
    }
@endphp

@if ($useScoped)
{{-- Sticky pin wrapper (scoped/container mode only): a zero-height sticky
     positioning context. It sticks to the scroll container's viewport at
     --reading-spine-offset-top from the top, and the absolute spine inside it
     rides along — so the spine stays pinned instead of scrolling with content.
     height:0 keeps it out of the article's flow (no pushed-down content); the
     spine's hover-expand still overlays. Viewport mode skips this (the aside is
     `fixed`). Inline-styled so it holds without a Tailwind build that scanned
     this view. --}}
<div style="position: sticky; top: var(--reading-spine-offset-top, 1rem); height: 0; z-index: var(--z-wk-sticky);" {{ $outerAttributes }}>
@elseif ($showScope)
<div class="contents" {{ $outerAttributes }}>
@endif
<{{ $rootTag }} data-wk-prose-skip
    x-data="wirekitReadingSpine({{ $alpineOptions }})"
    x-bind:data-expand="expanded ? 'true' : 'false'"
    data-side="{{ $position === 'left' ? 'left' : 'right' }}"
    {{-- Bound rather than set from the script: a Livewire morph removes every attribute the
         server's copy lacks, and a flag written with `dataset` would go with the first round
         trip, taking the rails' opacity and the band's transition with it. --}}
    @if ($rail)
        data-layout="rail"
        x-bind:data-rail-ready="railReady ? 'true' : null"
        x-bind:data-rail-open="railOpen ? 'true' : null"
    @endif
    @if (! $forceExpanded)
        @mouseenter="expandOnHover()"
        @mouseleave="collapseOnHover()"
        @focusin="expandOnFocus()"
        @focusout="collapseOnFocus($event)"
        x-on:keydown.escape.window.capture="keydownEscape($event)"
    @endif
    x-show="items.length > 0"
    x-cloak
    {{-- The default name goes through the catalog. This `<aside>` is a
         complementary landmark, so the string is what a screen reader reads out
         when it hops to it; the same file's back-to-top button already routes
         its name through `__()`, and a merge default is not a reason to skip it. --}}
    {{-- The edge offset goes into the bag's `style` rather than an attribute of its own beside
         it: a caller's `style` would stand in the tag a second time, and the parser keeps the
         first one, so the caller's declarations would be gone. Merged, they follow ours, and
         without an offset the default is `null`, which the bag leaves out. --}}
    {{-- The rail's root does not scroll and takes no focus: its `<nav>` scrolls, a named
         landmark whose links take the focus, and a stop on the root would come before them with
         nothing to do. --}}
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rootClass]))->merge(array_filter(['aria-label' => $rail ? null : __('wirekit::Page contents'), 'tabindex' => $rail ? null : '0', 'style' => $rail ? null : $edgeOffsetStyle], fn ($value) => $value !== null)) }}
>
    @if ($rail)
    {{-- On a phone, a rail you show there is a disclosure: this button carries the label and opens
         the frame under it, closed at first (the stylesheet hides the frame below md until it is
         open). From md the button is hidden and the outline always stands. --}}
    <button type="button"
        class="wk-reading-spine__toggle wk-touch-row md:hidden flex w-full items-center justify-between gap-[var(--gap-wk-sm)] cursor-pointer rounded-[var(--radius-wk-sm)] ps-[var(--reading-spine-padding-x)] py-[var(--padding-wk-y-sm)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-xs)] uppercase tracking-wider text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
        aria-expanded="false"
        x-bind:aria-expanded="railOpen ? 'true' : 'false'"
        aria-controls="{{ $railFrameId }}"
        @click="toggleRail()"
    >
        <span>{{ $railLabel }}</span>
        {{-- Chevron — rotates 180° when open. Decorative. --}}
        <svg class="h-4 w-4 shrink-0 transition-transform duration-[var(--transition-wk-duration)]" :class="railOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" focusable="false">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
    {{-- The rail holds its label, its filter and its outline in a frame at most a window high,
         in which only the outline scrolls, so the label and the filter stay in view. The frame
         is the column rather than the root, because the root's display is the one `hide-below`
         switches on and off. --}}
    <div id="{{ $railFrameId }}" class="wk-reading-spine__frame">
    @endif

    {{-- The rail's visible label, which names its `<nav>`. Below md the disclosure button shows
         the same text, so the label is hidden there and still names the `<nav>`. --}}
    @if ($rail && $callerLabelledBy === '')
        <p data-wk-prose-skip id="{{ $railLabelId }}" class="wk-reading-spine__heading hidden md:block mb-[var(--space-wk-sm)] ps-[var(--reading-spine-padding-x)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-xs)] uppercase tracking-wider text-[color:var(--color-wk-text-muted)]">{{ $railLabel }}</p>
    @endif

    {{-- Optional developer-supplied filter input slot. Two-way-binds to
         `filter` Alpine state via `x-model` on the developer's input. --}}
    @if (\Pushery\WireKit\Support\SlotContent::given($filter ?? null))
        <div class="wk-reading-spine__filter px-2 pb-2 border-b border-[var(--color-wk-border)]">
            {{ $filter }}
        </div>
    @endif

    @if ($rail)
    <nav class="wk-scrollbar" aria-labelledby="{{ $callerLabelledBy !== '' ? $callerLabelledBy : $railLabelId }}">
    @elseif ($asideLabelledBy !== '')
    <nav aria-labelledby="{{ $asideLabelledBy }}">
    @else
    <nav aria-label="{{ $asideLabel }}">
    @endif
        @if ($rail)
            {{-- The rail, drawn twice: muted, and in the active color clipped to a band on the
                 current entry. The script measures each link and writes one path through them
                 (railPath() in the plugin); the band is a clip-path whose edges the stylesheet
                 transitions, so it slides and nothing is drawn per frame. Before the list, since
                 an `<svg>` is not valid inside `<ol>`. --}}
            <svg class="wk-reading-spine__rail" aria-hidden="true" focusable="false"><path x-bind:d="railD"></path></svg>
            <svg class="wk-reading-spine__rail wk-reading-spine__rail--active" aria-hidden="true" focusable="false" x-bind:style="railBand()"><path x-bind:d="railD"></path></svg>
        @endif
        {{--
            Inline-style the list primitives so the spine renders correctly
            in a page whose stylesheet does not come from a Tailwind build that scanned this view,
            where `<ol>` would otherwise show its UA decimal markers and 40px
            indent. The sibling reading-toc note has the full rationale.
        --}}
        {{--
            Inline for the same reason:
            `padding: var(--reading-spine-padding-y) var(--reading-spine-padding-x)`
            gives the ticks/labels a guttered inset from the aside's left+right
            edges (without it the ticks would sit flush against the right edge).
            Gap is driven by the
            CSS variable so the list density tracks `--reading-spine-gap`.
        --}}
        <ol data-wk-prose-skip role="list" data-wk-reading-spine-list
            class="flex flex-col py-[var(--reading-spine-padding-y)] px-[var(--reading-spine-padding-x)]"
            style="list-style: none; margin: 0; padding-top: var(--reading-spine-padding-y); padding-right: var(--reading-spine-padding-x); padding-bottom: var(--reading-spine-padding-y); padding-left: var(--reading-spine-padding-x); display: flex; flex-direction: column; gap: var(--reading-spine-gap);"
        >
            <template x-for="item in items" :key="item.key">
                <li data-wk-prose-skip
                    class="wk-reading-spine__item relative"
                    x-show="matchesFilter(item)"
                >
                    {{--
                        The indent comes from linkStyle(item) in the plugin: half a
                        rem for each level below the shallowest heading on the page,
                        the level the numbering starts at as well. It is an inline
                        style, like the list styles above, rather than a class a
                        build would have to generate.
                    --}}
                    <a data-wk-prose-skip
                        :href="'#' + item.id"
                        {{-- The rail finds each link's box by it, whatever the filter hides. --}}
                        @if ($rail) :data-index="item.index" @endif
                        :data-active="item.index === activeIndex ? 'true' : 'false'"
                        :data-level="item.level"
                        :aria-current="item.index === activeIndex ? 'location' : null"
                        {{-- A row of the rail takes the touch floor. A tick of the spine does not: the
                             spacing of the ticks draws the outline of the page, and a tick 44px tall would
                             draw none. --}}
                        class="wk-reading-spine__link{{ $rail ? ' wk-touch-row' : '' }} group flex items-center gap-2 cursor-pointer text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-sm)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        :style="linkStyle(item)"
                        @click="scrollTo(item.key, $event)"
                    >
                        {{--
                            Tick — visual indicator that the spine menu exists
                            and can be hovered to expand. Width depends on
                            heading level (declared via `[data-level]` CSS
                            rules in dist/wirekit.css, since the `w-*` classes
                            `tickWidthClass` returns live in the JavaScript, which
                            a build scanning the views never reads). `display:
                            block` is inline-styled
                            so the width rule actually paints — without it,
                            a default `<span>` stays `display: inline`,
                            ignores `width`, and renders 0 px wide, and
                            nothing would show that the menu is there.
                            `height` reads from the canonical tick-height token.
                            `fillSections` paints a per-section progress fill
                            via background-gradient, gated by the prop.
                        --}}
                        {{-- Not in the rail, whose line is the mark: tickStyle() writes `display: block`
                             inline, which no rule in the stylesheet could hide. --}}
                        @unless ($rail)
                            <span
                                class="wk-reading-spine__tick rounded-[var(--radius-wk-full,9999px)]"
                                :class="tickWidthClass(item.level)"
                                :style="tickStyle(item)"
                                aria-hidden="true"
                            ></span>
                        @endunless

                        {{-- Optional numeric label. Renders only when
                             numbered=true; takes precedence over an empty
                             label slot (collapsed-state covered by CSS). --}}
                        @if (filter_var($numbered, FILTER_VALIDATE_BOOL))
                            <span
                                class="wk-reading-spine__number tabular-nums text-[length:var(--text-wk-xs)] text-[var(--reading-spine-color-idle)]"
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
            {{-- Back-to-top pill — fixed at the spine bottom, scrolls
                 to top on click. Reduced-motion respected via the same
                 matchMedia check as scrollTo. Visible only when expanded
                 OR when scrollY > 0 (you're past the top).

                 `cursor-pointer` is spelled out because nothing else supplies
                 it: Tailwind v4's preflight no longer sets `cursor: pointer` on
                 `button` (v3 did), and `.wk-reading-spine__back-to-top` has no declarations in
                 dist/wirekit.css — it is a styling hook, not a style. --}}
            <button
                type="button"
                class="wk-reading-spine__back-to-top mt-2 ml-2 inline-flex items-center justify-center cursor-pointer w-[var(--size-wk-sm)] h-[var(--size-wk-sm)] rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                aria-label="{{ __('wirekit::Back to top') }}"
                @click="scrollToTop()"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V5.612L5.29 9.77a.75.75 0 01-1.08-1.04l5.25-5.5a.75.75 0 011.08 0l5.25 5.5a.75.75 0 11-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0110 17z" clip-rule="evenodd"/>
                </svg>
            </button>
        @endif
    </nav>
    @if ($rail)
    </div>
    @endif
</{{ $rootTag }}>
@if ($useScoped || $showScope)
</div>
@endif
