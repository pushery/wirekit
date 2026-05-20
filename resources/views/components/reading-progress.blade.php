@props([
    'position' => 'top',
    'height' => 'md',
    'variant' => 'primary',
    'showAfter' => 0,
    'target' => null,
    'indicator' => 'bar',
    'segments' => null,
    'milestones' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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

    // Variant validation — gates against the canonical 6-set + the auto value.
    // 'accent' (legacy) and 'inverse' (legacy) explicitly throw — both were
    // dropped during the family's first public release, no alias preserved
    // (the family was visibility:admin so no public developers existed to
    // break). Developers wanting the old 'inverse' behaviour set
    // `--reading-progress-fill: var(--color-wk-text)` in their :root {} block.
    $variantValue = match ($variant) {
        'primary', 'neutral', 'success', 'warning', 'danger', 'info', 'auto' => $variant,
        default => WireKit::validateProp(
            'reading-progress',
            'variant',
            $variant,
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

    // Marker class — used by reduced-motion gating in dist/wirekit.css, and by
    // print-stylesheet rules. Doubled-class specificity (`.wk-reading-progress.wk-reading-progress`)
    // wins over developer typography wrappers without using `!important`.
    $rootClass = WireKit::resolveClasses('reading-progress', 'base', implode(' ', [
        'wk-reading-progress',
        $indicator === 'dot'
            ? 'fixed z-[var(--z-wk-sticky)] pointer-events-none right-[var(--padding-wk-x-lg)] bottom-[var(--padding-wk-x-lg)]'
            : 'fixed left-0 right-0 z-[var(--z-wk-sticky)] pointer-events-none bg-transparent '.$positionClass,
    ]), $scope);

    // Segments prop — a numeric array of fractional positions (0..1) where chapter
    // boundaries land. Renders as 1px-tall dividers via background-gradient stops.
    // CSS-only — no extra DOM nodes per segment.
    $segmentsArray = is_array($segments) ? array_values(array_filter($segments, fn ($v) => is_numeric($v) && $v >= 0 && $v <= 1)) : null;

    $segmentsStyle = '';
    if ($segmentsArray && count($segmentsArray) > 0) {
        // Build a linear-gradient with 1px wide stops at each fractional position,
        // overlaid on top of the base fill colour. Each segment marker is 1px wide
        // at the boundary; the rest of the strip transitions via the scaled fill.
        $stops = [];
        foreach ($segmentsArray as $pos) {
            $pct = $pos * 100;
            // Tiny dark sliver at each position, transparent elsewhere.
            $stops[] = 'transparent '.$pct.'%';
            $stops[] = 'rgba(0,0,0,0.4) '.$pct.'%';
            $stops[] = 'rgba(0,0,0,0.4) calc('.$pct.'% + 1px)';
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
        x-data="{
            progress: 0,
            _ticking: false,
            _onScroll: null,
            _milestonesFired: { 25: false, 50: false, 75: false, 100: false },
            _milestonesEnabled: {{ $milestonesEnabled ? 'true' : 'false' }},
            init() {
                const target = '{{ $target }}' || null;
                const showAfter = {{ (int) $showAfter }};
                const update = () => {
                    const scope = target ? document.querySelector(target) : null;
                    let scrollTop, scrollHeight, clientHeight;
                    if (scope) {
                        const rect = scope.getBoundingClientRect();
                        scrollTop = Math.max(0, -rect.top);
                        scrollHeight = scope.scrollHeight;
                        clientHeight = window.innerHeight;
                    } else {
                        // Read scroll metrics from a SINGLE element consistently
                        // — picking whichever candidate actually has scrollable
                        // content. Reading scrollTop from one element and
                        // scrollHeight from another mixes scroll roots and caps
                        // the proportional math below 100% (the failure mode
                        // that broke the bar's saturation while the dot stayed
                        // visually 'close enough').
                        //
                        // Candidates in priority order:
                        //   1. document.scrollingElement — the CSSOM scroll root
                        //   2. document.documentElement  — html
                        //   3. document.body
                        //
                        // We pick the FIRST candidate whose scrollHeight exceeds
                        // its clientHeight (i.e. the one with overflow). In the
                        // standard iframe-srcdoc case (html-scroll) that's html;
                        // in body-scroll contexts (where body has overflow:auto
                        // and html fills the viewport) it's body. Spec quirk:
                        // document.scrollingElement always returns html in
                        // standards mode regardless of which element actually
                        // scrolls, so we can't rely on it alone — the
                        // scrollHeight-vs-clientHeight probe is the deciding
                        // factor.
                        const candidates = [
                            document.scrollingElement,
                            document.documentElement,
                            document.body,
                        ].filter(Boolean);
                        let root = candidates[0];
                        for (const c of candidates) {
                            if (c.scrollHeight > c.clientHeight) {
                                root = c;
                                break;
                            }
                        }
                        scrollTop = root.scrollTop;
                        scrollHeight = root.scrollHeight;
                        clientHeight = root.clientHeight || window.innerHeight;
                    }
                    const max = Math.max(1, scrollHeight - clientHeight);
                    // At-bottom override — saturates fill at 100 when the
                    // reader is within `bottomTolerance` of the math-max
                    // scroll position OR proportional math already crossed
                    // 99%. The generous 32px tolerance covers the common
                    // failure cases:
                    //   - body { padding-bottom: 24px } inflates scrollHeight
                    //     past reachable scrollTop (proportional math caps
                    //     ~96% even when the scrollbar visually rests at end)
                    //   - browser sub-pixel rounding leaves scrollTop a
                    //     fraction short of `max`
                    //   - iframe-srcdoc shapes report scrollHeight slightly
                    //     larger than the actual scrollable distance
                    // 32px is below one line-height of typical body text, so
                    // it won't saturate prematurely while the user is still
                    // reading a paragraph mid-article.
                    //
                    // (An earlier draft used `document.body.lastElementChild
                    // .getBoundingClientRect().bottom` as a visual-bottom
                    // detector — that backfired because the reading-progress
                    // wrapper IS itself a child of body with position:fixed
                    // top:0 height:3px, so its bottom edge is always near
                    // the top of viewport, making the strategy return true
                    // unconditionally and pinning the bar to 100% on load.)
                    const bottomTolerance = 32;
                    const atBottomMath = (scrollTop + clientHeight) >= (scrollHeight - bottomTolerance);
                    const raw = Math.min(100, Math.max(0, (scrollTop / max) * 100));
                    const next = atBottomMath || raw >= 99 ? 100 : raw;
                    this.progress = (showAfter > 0 && scrollTop < showAfter) ? 0 : next;
                    this._maybeFireMilestones(scrollTop, scrollHeight);
                };
                this._onScroll = () => {
                    if (this._ticking) return;
                    requestAnimationFrame(() => { update(); this._ticking = false; });
                    this._ticking = true;
                };
                update();
                // Listen on window AND document — some browsers (notably in
                // iframe-srcdoc contexts where <body> is the scrollingElement)
                // fire scroll events on document but not always reliably on
                // window. The capture-phase document listener catches both.
                window.addEventListener('scroll', this._onScroll, { passive: true });
                window.addEventListener('resize', this._onScroll, { passive: true });
                document.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
            },
            _maybeFireMilestones(scrollTop, scrollHeight) {
                if (!this._milestonesEnabled) return;
                const p = Math.round(this.progress);
                [25, 50, 75, 100].forEach((threshold) => {
                    if (!this._milestonesFired[threshold] && p >= threshold) {
                        this._milestonesFired[threshold] = true;
                        this.$dispatch('wirekit:reading-progress:milestone', {
                            percent: threshold,
                            scrollTop,
                            scrollHeight,
                        });
                    }
                });
            },
            destroy() {
                window.removeEventListener('scroll', this._onScroll);
                window.removeEventListener('resize', this._onScroll);
                document.removeEventListener('scroll', this._onScroll, { capture: true });
            },
        }"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        x-bind:aria-valuenow="Math.round(progress)"
        x-bind:aria-hidden="progress === 0 ? 'true' : null"
        {{ $attributes->class([$rootClass, 'wk-reading-progress--dot'])->merge(['aria-label' => 'Reading progress']) }}
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
        style="position: fixed; right: var(--padding-wk-x-lg); bottom: var(--padding-wk-x-lg); z-index: var(--z-wk-sticky); pointer-events: none; width: var(--reading-progress-dot-size); height: var(--reading-progress-dot-size);"
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
        x-data="{
            progress: 0,
            _ticking: false,
            _onScroll: null,
            _milestonesFired: { 25: false, 50: false, 75: false, 100: false },
            _milestonesEnabled: {{ $milestonesEnabled ? 'true' : 'false' }},
            init() {
                const target = '{{ $target }}' || null;
                const showAfter = {{ (int) $showAfter }};
                const update = () => {
                    const scope = target ? document.querySelector(target) : null;
                    let scrollTop, scrollHeight, clientHeight;
                    if (scope) {
                        const rect = scope.getBoundingClientRect();
                        scrollTop = Math.max(0, -rect.top);
                        scrollHeight = scope.scrollHeight;
                        clientHeight = window.innerHeight;
                    } else {
                        // Read scroll metrics from a SINGLE element consistently
                        // — picking whichever candidate actually has scrollable
                        // content. Reading scrollTop from one element and
                        // scrollHeight from another mixes scroll roots and caps
                        // the proportional math below 100% (the failure mode
                        // that broke the bar's saturation while the dot stayed
                        // visually 'close enough').
                        //
                        // Candidates in priority order:
                        //   1. document.scrollingElement — the CSSOM scroll root
                        //   2. document.documentElement  — html
                        //   3. document.body
                        //
                        // We pick the FIRST candidate whose scrollHeight exceeds
                        // its clientHeight (i.e. the one with overflow). In the
                        // standard iframe-srcdoc case (html-scroll) that's html;
                        // in body-scroll contexts (where body has overflow:auto
                        // and html fills the viewport) it's body. Spec quirk:
                        // document.scrollingElement always returns html in
                        // standards mode regardless of which element actually
                        // scrolls, so we can't rely on it alone — the
                        // scrollHeight-vs-clientHeight probe is the deciding
                        // factor.
                        const candidates = [
                            document.scrollingElement,
                            document.documentElement,
                            document.body,
                        ].filter(Boolean);
                        let root = candidates[0];
                        for (const c of candidates) {
                            if (c.scrollHeight > c.clientHeight) {
                                root = c;
                                break;
                            }
                        }
                        scrollTop = root.scrollTop;
                        scrollHeight = root.scrollHeight;
                        clientHeight = root.clientHeight || window.innerHeight;
                    }
                    const max = Math.max(1, scrollHeight - clientHeight);
                    // At-bottom override — saturates fill at 100 when the
                    // reader is within `bottomTolerance` of the math-max
                    // scroll position OR proportional math already crossed
                    // 99%. The generous 32px tolerance covers the common
                    // failure cases:
                    //   - body { padding-bottom: 24px } inflates scrollHeight
                    //     past reachable scrollTop (proportional math caps
                    //     ~96% even when the scrollbar visually rests at end)
                    //   - browser sub-pixel rounding leaves scrollTop a
                    //     fraction short of `max`
                    //   - iframe-srcdoc shapes report scrollHeight slightly
                    //     larger than the actual scrollable distance
                    // 32px is below one line-height of typical body text, so
                    // it won't saturate prematurely while the user is still
                    // reading a paragraph mid-article.
                    //
                    // (An earlier draft used `document.body.lastElementChild
                    // .getBoundingClientRect().bottom` as a visual-bottom
                    // detector — that backfired because the reading-progress
                    // wrapper IS itself a child of body with position:fixed
                    // top:0 height:3px, so its bottom edge is always near
                    // the top of viewport, making the strategy return true
                    // unconditionally and pinning the bar to 100% on load.)
                    const bottomTolerance = 32;
                    const atBottomMath = (scrollTop + clientHeight) >= (scrollHeight - bottomTolerance);
                    const raw = Math.min(100, Math.max(0, (scrollTop / max) * 100));
                    const next = atBottomMath || raw >= 99 ? 100 : raw;
                    this.progress = (showAfter > 0 && scrollTop < showAfter) ? 0 : next;
                    this._maybeFireMilestones(scrollTop, scrollHeight);
                };
                this._onScroll = () => {
                    if (this._ticking) return;
                    requestAnimationFrame(() => { update(); this._ticking = false; });
                    this._ticking = true;
                };
                update();
                // Listen on window AND document — some browsers (notably in
                // iframe-srcdoc contexts where <body> is the scrollingElement)
                // fire scroll events on document but not always reliably on
                // window. The capture-phase document listener catches both.
                window.addEventListener('scroll', this._onScroll, { passive: true });
                window.addEventListener('resize', this._onScroll, { passive: true });
                document.addEventListener('scroll', this._onScroll, { passive: true, capture: true });
            },
            _maybeFireMilestones(scrollTop, scrollHeight) {
                if (!this._milestonesEnabled) return;
                const p = Math.round(this.progress);
                [25, 50, 75, 100].forEach((threshold) => {
                    if (!this._milestonesFired[threshold] && p >= threshold) {
                        this._milestonesFired[threshold] = true;
                        this.$dispatch('wirekit:reading-progress:milestone', {
                            percent: threshold,
                            scrollTop,
                            scrollHeight,
                        });
                    }
                });
            },
            destroy() {
                window.removeEventListener('scroll', this._onScroll);
                window.removeEventListener('resize', this._onScroll);
                document.removeEventListener('scroll', this._onScroll, { capture: true });
            },
        }"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        x-bind:aria-valuenow="Math.round(progress)"
        x-bind:aria-hidden="progress === 0 ? 'true' : null"
        {{ $attributes->class([$rootClass])->merge(['aria-label' => 'Reading progress']) }}
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
        style="position: fixed; {{ $position === 'bottom' ? 'bottom: 0' : 'top: 0' }}; left: 0; right: 0; max-width: none; z-index: var(--z-wk-sticky); pointer-events: none; height: {{ $heightToken }};{{ $segmentsStyle ? ' '.$segmentsStyle : '' }}"
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
            x-bind:style="{ transform: `scaleX(${progress / 100})` }"
            class="wk-reading-progress__fill h-full w-full origin-left"
            style="height: 100%; width: 100%; transform-origin: left center; background-color: {{ $variantColor }}; transition: transform 75ms ease-out;"
        ></div>
    </div>
@endif
