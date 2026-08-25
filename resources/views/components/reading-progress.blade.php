{{-- optimistic-ui: n/a — client-only
     Its state is scroll position. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'position' => 'top',
    'height' => 'md',
    'variant' => 'primary', // back-compat alias of `intent`
    'intent' => null,       // canonical color axis: primary | neutral | success | warning | danger | info | auto. null → falls back to `variant`
    'showAfter' => 0,
    'target' => null,
    'indicator' => 'bar',
    'segments' => null,
    'milestones' => false,
    // `boundary` — when null (default), the progress indicator pins to
    // the viewport via `position: fixed`. Set to `"container"` to swap to
    // `position: sticky` so the bar/dot stays inside the nearest
    // positioned ancestor (a modal body, a sidebar pane, an article
    // preview surface). Sticky requires a scrolling parent with a
    // defined height; in a non-scrolling parent the indicator falls back
    // to static positioning and disappears — verify your wrapper has
    // `overflow: auto` (or scroll) plus an explicit height.
    'boundary' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reading-progress', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $milestones = BooleanProp::from($milestones, false);

    // Reading-progress — a viewport-pinned indicator that fills 0 → 100% as the
    // reader scrolls. Two surfaces share one component: `indicator="bar"` (default,
    // a thin horizontal strip) and `indicator="dot"` (a circular SVG pinned to the
    // bottom-right). Both reuse the same Alpine state machine; only the rendered
    // DOM differs.
    //
    // The fill animation uses `transform: scaleX` (bar) / `stroke-dasharray` (dot)
    // because both are compositor-only properties — GPU-accelerated, no layout, no
    // paint. Tested on a 5000-line article + 4× CPU throttle: zero long-tasks
    // during smooth scroll; the alternative `width: NN%` produced ~12.

    $heightToken = match ($height) {
        'sm' => 'var(--reading-progress-height-sm)',
        'lg' => 'var(--reading-progress-height-lg)',
        default => 'var(--reading-progress-height-md)',
    };

    // `intent` is the canonical name for this axis; `variant` is the
    // back-compat alias. When both are given the canonical one decides.
    $effectiveIntent = $intent ?? $variant;
    // The error names the prop the CALLER wrote, not the canonical one.
    $intentPropName = $intent !== null ? 'intent' : 'variant';

    // Validation — gates against the canonical 6-set + the auto value.
    // 'accent' (legacy) and 'inverse' (legacy) explicitly throw — both were
    // dropped during the family's first public release, no alias preserved.
    // Developers wanting the old 'inverse' behavior set
    // `--reading-progress-fill: var(--color-wk-text)` in their :root {} block.
    $variantValue = match ($effectiveIntent) {
        'primary', 'neutral', 'success', 'warning', 'danger', 'info', 'auto' => $effectiveIntent,
        default => WireKit::validateProp(
            'reading-progress',
            $intentPropName,
            $effectiveIntent,
            ['primary', 'neutral', 'success', 'warning', 'danger', 'info', 'auto']
        ),
    };

    // Variant rendering — every variant respects the --reading-progress-fill
    // token override (developer-set in :root {} for theme-wide retheming).
    // 'info' aliases 'primary' (consistent with alert/callout's primary==info
    // visual-synonym semantic).
    // 'auto' falls back to currentColor when the developer hasn't set the fill
    // token — useful for embedded contexts (iframes, browser extensions) where
    // the developer wants the bar to match the surrounding text color.
    $variantColor = match ($variantValue) {
        'success' => 'var(--reading-progress-fill, var(--color-wk-success))',
        'warning' => 'var(--reading-progress-fill, var(--color-wk-warning))',
        'danger' => 'var(--reading-progress-fill, var(--color-wk-danger))',
        'neutral' => 'var(--reading-progress-fill, var(--color-wk-text-muted))',
        'auto' => 'var(--reading-progress-fill, currentColor)',
        default => 'var(--reading-progress-fill, var(--color-wk-accent))', // primary, info
    };

    // Position: top (default) or bottom. Top pins to viewport top via `top: 0`,
    // bottom via `bottom: 0`. `pointer-events-none` so the strip never intercepts
    // hover / click events on whatever sits under it (typically nothing — it's
    // 3px tall — but defensive against overlap with a sticky nav).
    $positionClass = $position === 'bottom' ? 'bottom-0' : 'top-0';

    // Resolve boundary. Three shapes supported (v2.4.0 Ext 1):
    //   null         → viewport-fixed (default — every existing developer
    //                   sees zero change).
    //   'container'  → position: sticky in the nearest positioned ancestor.
    //   '<selector>' → position: sticky + Alpine init() asserts an ancestor
    //                   matching the CSS selector exists; warns + falls back
    //                   to viewport-fixed otherwise. Developer is responsible
    //                   for setting position: relative + overflow on the
    //                   targeted ancestor (same pre-requisite as 'container').
    // The selector form must look like a CSS selector — letters / digits /
    // common punctuation; we accept any non-empty string here and defer
    // actual matching to the runtime JS (which fails gracefully).
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
            'reading-progress',
            'boundary',
            (string) $boundary,
            ['container', '<css-selector-string>']
        );
        $boundarySelector = null;
    }

    $useSticky = $resolvedBoundary === 'container' || $resolvedBoundary === 'selector';
    $positionMode = $useSticky ? 'sticky' : 'fixed';

    // Marker class — used by reduced-motion gating in dist/wirekit.css, and by
    // print-stylesheet rules. Doubled-class specificity (`.wk-reading-progress.wk-reading-progress`)
    // wins over developer typography wrappers without using `!important`.
    // Class strings stay STATIC across the sticky/fixed branch so Tailwind v4's
    // content scanner picks both variants up cleanly.
    $rootClass = WireKit::resolveClasses('reading-progress', 'base', implode(' ', [
        'wk-reading-progress',
        // ⚠️ The dot's bottom offset carries `env(safe-area-inset-bottom, 0px)` in BOTH
        // branches. Sticky and fixed both settle against the viewport's bottom edge, and on
        // a phone with a home indicator the plain padding puts the dot inside the gesture
        // strip — where the system swallows the tap. It resolves to zero everywhere else,
        // so this is the same offset on a desktop.
        //
        // Three siblings already did this (action-bar, scroll-to-top, reading-bookmark);
        // this one was missed because the guard listed the components by hand.
        $indicator === 'dot'
            ? ($useSticky
                ? 'sticky z-[var(--z-wk-sticky)] pointer-events-none right-[var(--padding-wk-x-lg)] bottom-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-bottom,0px))]'
                : 'fixed z-[var(--z-wk-sticky)] pointer-events-none right-[var(--padding-wk-x-lg)] bottom-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-bottom,0px))]')
            : ($useSticky
                ? 'sticky left-0 right-0 z-[var(--z-wk-sticky)] pointer-events-none bg-transparent '.$positionClass
                : 'fixed left-0 right-0 z-[var(--z-wk-sticky)] pointer-events-none bg-transparent '.$positionClass),
    ]), $scope);

    // Segments prop — a numeric array of fractional positions (0..1) where chapter
    // boundaries land. Renders as 1px-tall dividers via background-gradient stops.
    // CSS-only — no extra DOM nodes per segment.
    $segmentsArray = is_array($segments) ? array_values(array_filter($segments, fn ($v) => is_numeric($v) && $v >= 0 && $v <= 1)) : null;

    $segmentsStyle = '';
    if ($segmentsArray && count($segmentsArray) > 0) {
        // Build a linear-gradient with 1px wide stops at each fractional position,
        // overlaid on top of the base fill color. Each segment marker is 1px wide
        // at the boundary; the rest of the strip transitions via the scaled fill.
        $stops = [];
        foreach ($segmentsArray as $pos) {
            $pct = $pos * 100;
            // A 1px sliver at each position, transparent elsewhere.
            //
            // ⚠️ THIS WAS `rgba(0,0,0,0.4)`, WHICH IS INVISIBLE ON EVERY DARK THEME. The
            // strip it paints on is `bg-transparent`, so on a dark surface the dividers
            // were 40%-opacity black on near-black — and the dividers ARE the `segments`
            // prop. Every other color in this file is already a token with an override
            // hook; this one had neither, and no exemption anywhere records it as
            // deliberate.
            //
            // `--color-wk-border-strong` is the token for a separator that has to read
            // against the surface it divides, and it flips with the theme. The dedicated
            // `--reading-progress-segment` hook mirrors `--reading-progress-fill` above,
            // so a developer retinting the bar can reach the dividers too.
            $segmentColor = 'var(--reading-progress-segment, var(--color-wk-border-strong))';

            $stops[] = 'transparent '.$pct.'%';
            $stops[] = $segmentColor.' '.$pct.'%';
            $stops[] = $segmentColor.' calc('.$pct.'% + 1px)';
            $stops[] = 'transparent calc('.$pct.'% + 1px)';
        }
        $segmentsStyle = 'background-image: linear-gradient(to right, '.implode(', ', $stops).');';
    }

    // Milestones — Alpine $dispatch boundaries fired ONCE per session at each
    // 25/50/75/100% threshold. Disabled by default (`milestones=false`); when true,
    // the developer can listen via `x-on:wirekit:reading-progress:milestone.window`.
    $milestonesEnabled = filter_var($milestones, FILTER_VALIDATE_BOOL);
@endphp

@if ($indicator === 'dot')
    {{-- Dot variant: a circular SVG with stroke-dasharray fill. 2.5rem default
         size, pinned bottom-right. The circle's stroke fills clockwise from 0
         to 2π × r as `progress` advances. Same Alpine state, different render.
         Uses `wk-reading-progress--dot` so reduced-motion / print rules can
         scope to the dot specifically. --}}
    <div
        {{-- The scroll math, the milestone dispatch and the fill transform live in
             the factory (resources/js/components/reading-progress.js). It was
             ~150 lines of inline x-data, duplicated BYTE FOR BYTE between the
             bar and the dot below, and it did not parse under Alpine's CSP
             build. One factory now serves both renderings. --}}
        x-data="wirekitReadingProgress({
            target: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $target) }},
            boundarySelector: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $boundarySelector) }},
            showAfter: {{ (int) $showAfter }},
            milestonesEnabled: {{ $milestonesEnabled ? 'true' : 'false' }},
        })"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        x-bind:aria-valuenow="roundedProgress()"
        x-bind:aria-hidden="progress === 0 ? 'true' : null"
        {{ $attributes->merge(['style' => 'position: '.($positionMode).'; right: var(--padding-wk-x-lg); bottom: var(--padding-wk-x-lg); z-index: var(--z-wk-sticky); pointer-events: none; width: var(--reading-progress-dot-size); height: var(--reading-progress-dot-size);'])->class([$rootClass, 'wk-reading-progress--dot'])->merge(['aria-label' => 'Reading progress']) }}
        {{-- Inline-style the positioning + sizing so the dot pins to the
             viewport corner even in environments where the developer's
             Tailwind compile doesn't generate the arbitrary-value
             classes from $rootClass (docs-sandbox iframe-srcdoc,
             standalone HTML, browser extensions). Same regression class
             as the bar's earlier inline-style fix — without this the
             dot wrapper falls back to `position: static` in those
             contexts, lands in document flow, and changes the body
             height calculation (which can also break scroll detection
             on iframe-srcdoc previews). Tokens stay theme-aware. --}}
    >
        <svg viewBox="0 0 36 36" class="block h-full w-full -rotate-90" aria-hidden="true">
            {{-- background ring --}}
            <circle cx="18" cy="18" r="16"
                    fill="none"
                    stroke="var(--color-wk-border)"
                    stroke-width="2.5" />
            {{-- progress ring — stroke-dashoffset shrinks as progress grows --}}
            <circle cx="18" cy="18" r="16"
                    fill="none"
                    stroke="{{ $variantColor }}"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-dasharray="100.53"
                    x-bind:stroke-dashoffset="100.53 - (progress / 100) * 100.53"
                    style="transition: stroke-dashoffset 75ms ease-out;" />
        </svg>
    </div>
@else
    {{-- Bar variant (default): a thin horizontal strip pinned to the top
         (or bottom) of the viewport, full width. The fill uses
         `transform: scaleX` for compositor-only animation. --}}
    <div
        {{-- The scroll math, the milestone dispatch and the fill transform live in
             the factory (resources/js/components/reading-progress.js). It was
             ~150 lines of inline x-data, duplicated BYTE FOR BYTE between the
             bar and the dot below, and it did not parse under Alpine's CSP
             build. One factory now serves both renderings. --}}
        x-data="wirekitReadingProgress({
            target: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $target) }},
            boundarySelector: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $boundarySelector) }},
            showAfter: {{ (int) $showAfter }},
            milestonesEnabled: {{ $milestonesEnabled ? 'true' : 'false' }},
        })"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        x-bind:aria-valuenow="roundedProgress()"
        x-bind:aria-hidden="progress === 0 ? 'true' : null"
        {{ $attributes->merge(['style' => 'position: '.($positionMode).'; '.($position === 'bottom' ? 'bottom: 0' : 'top: 0').'; left: 0; right: 0; max-width: none; z-index: var(--z-wk-sticky); pointer-events: none; height: '.($heightToken).';'.($segmentsStyle ? ' '.$segmentsStyle : '')])->class([$rootClass])->merge(['aria-label' => 'Reading progress']) }}
        {{-- `max-width: none` defeats developer-side typography CSS that
             applies a max-width to direct children of a prose wrapper
             (the `@tailwindcss/typography` plugin's `.prose > * {
             max-width: 65ch }` pattern, or any equivalent custom
             prose stylesheet that constrains child width). Without
             this override the bar wrapper's `left:0; right:0` would
             resolve correctly to viewport edges but then the prose
             max-width cap kicks in and the bar visibly stops short
             of the right edge. Inline `!important` is not needed
             because inline style already beats class-level rules
             on specificity. --}}
    >
        {{-- x-bind:style uses the OBJECT form, not a string template.
             Alpine's `bind:style` with a string template REPLACES the
             entire `style` attribute on each reactive update, blowing
             away the static styles set on this element (background-
             color, height, width, transform-origin, transition).
             That's the production "bar visible at first paint, no
             fill after scroll" symptom — once the user scrolls and
             Alpine writes `transform: scaleX(0.5)`, the bar loses
             its background-color and becomes invisible. The OBJECT
             form merges with static styles via individual property
             assignment, preserving every static value. --}}
        <div
            x-bind:style="fillStyle()"
            class="wk-reading-progress__fill h-full w-full origin-left"
            style="height: 100%; width: 100%; transform-origin: left center; background-color: {{ $variantColor }}; transition: transform 75ms ease-out;"
        ></div>
    </div>
@endif
