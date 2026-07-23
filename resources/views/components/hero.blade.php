{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'variant' => 'default',
    'gradient' => false,
    'layout' => 'balanced',
    // Only valid when layout="balanced". Tunes the copy:aside ratio from
    // the default 50/50 to one of: 1/3 | 2/5 | 3/5 | 2/3. Under any
    // non-balanced layout this prop throws via validateProp (debug) or is
    // silently ignored (production).
    'asideWidth' => null,
    // Optional reveal animation. Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    // `size` — vertical-rhythm tier. One of `sm` / `md` / `lg`. Default
    // `lg` (= `--space-wk-section-lg` at `sm+` viewports). Mobile viewport
    // (< sm breakpoint) automatically drops one tier — `lg` becomes
    // `md` (= 5rem each side) so the bottom-empty-area-with-gradient
    // class of bug (variant="dark" hero with gradient overlay produces
    // a visually empty dark band below the content on narrow viewports
    // when the section padding is too generous) doesn't recur.
    // Pass `size="sm"` for the tightest spacing across every viewport.
    'size' => 'lg',
    // `tightOnMobile` — when true, drop an ADDITIONAL tier on mobile
    // viewports for `size="lg"` heroes. Useful for dark-variant heroes
    // with gradient overlays where even 5 rem of mobile bottom padding
    // reads as a visible dead-zone below the content on 390 px-wide
    // viewports (iPhone 12-class). Opt-in (default `false`) — does NOT
    // change the rendering of any existing hero. For `size="sm"` and
    // `size="md"`, the prop is a no-op (mobile already runs at the
    // tightest tier).
    'tightOnMobile' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $gradient = BooleanProp::from($gradient, false);
    $tightOnMobile = BooleanProp::from($tightOnMobile, false);

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'hero');

    // Validate `size` against the three-tier enum. Off-enum values
    // surface debug-mode validation; production silently falls back
    // to `lg` (the safest default).
    $validSize = in_array($size, ['sm', 'md', 'lg'], true)
        ? $size
        : WireKit::validateProp('hero', 'size', $size, ['sm', 'md', 'lg']);

    // Responsive vertical-padding utility — combines per-component opt-in
    // responsive utility with the `size` prop for explicit override.
    // Mobile (below sm breakpoint) uses one tier smaller than the named
    // size; `sm+` uses the named size directly. This collapses the
    // historical "14rem vertical padding on every viewport" footgun into
    // a viewport-aware contract.
    //
    // The class strings below are STATIC (no string interpolation) so
    // Tailwind's source-detection picks them up cleanly. Dynamically-
    // constructed arbitrary values (Blade-interpolated into the class
    // attribute) would be invisible to Tailwind and the drift-audit
    // would flag them. NOTE: do not write the literal pattern here as
    // an example, even in a comment — Tailwind's source scanner reads
    // raw .blade.php files and would emit invalid CSS for any
    // bracket-wrapped value containing a PHP-style variable token.
    // Class strings stay STATIC (no string interpolation) so Tailwind v4's
    // source-detection picks them up. The 6-way match exhausts every
    // (size × tightOnMobile) combination — emitting the literal strings
    // here is the only shape that keeps every variant visible to the
    // content scanner. tightOnMobile is a no-op for `sm` / `md` because
    // those sizes already run at the tightest mobile tier; the boolean
    // only shrinks the `lg` mobile axis from section-md (5 rem) to
    // section-sm (3 rem).
    $tightMobile = (bool) $tightOnMobile;
    $heroVerticalPadding = match (true) {
        $validSize === 'sm' => 'py-[var(--space-wk-section-sm)] sm:py-[var(--space-wk-section-sm)]',
        $validSize === 'md' => 'py-[var(--space-wk-section-sm)] sm:py-[var(--space-wk-section-md)]',
        $validSize === 'lg' && $tightMobile => 'py-[var(--space-wk-section-sm)] sm:py-[var(--space-wk-section-lg)]',
        $validSize === 'lg' => 'py-[var(--space-wk-section-md)] sm:py-[var(--space-wk-section-lg)]',
    };

    // Hero — landing page hero section with title, lede, actions, and optional aside.
    // `wk-hero` marker — load-bearing against developer prose
    // `max-width: 75ch` clamps (see footer.blade.php for the full
    // rationale on this defensive pattern).
    $classes = WireKit::resolveClasses('hero', 'base', implode(' ', [
        'wk-hero',
        // `w-full` keeps the hero full-width inside docs.wirekit.app
        // flex-row preview wrapper (see footer.blade.php for rationale).
        'w-full',
        'relative overflow-hidden',
        $heroVerticalPadding,
        'px-[var(--padding-wk-x-lg)]',
    ]), $scope);

    // `dark` establishes a real dark TOKEN CONTEXT (the bare `dark` class), not
    // just a dark band: every --color-wk-* token resolves to its `.dark` value
    // for the whole subtree, so a token-surfaced child (<x-wirekit::code-block>,
    // card, input, table) renders a DARK surface instead of a light one — fixing
    // the white-on-white bug class (a code-block in a dark hero). Painting from
    // the regular --color-wk-bg / --color-wk-text (which are dark / light under
    // `.dark`) keeps the band identical in light mode AND keeps the hero
    // genuinely dark in dark mode — the old `bg-inverse` flipped the band to
    // near-white in dark mode. `accent` stays a colored surface (no `.dark`,
    // it isn't dark); its token-surfaced children read the page mode, which is
    // fine because the accent surface is colored, not near-white.
    $variantClasses = match ($variant) {
        'default' => 'bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text)]',
        'dark' => 'dark bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text)]',
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'muted' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]',
        default => WireKit::validateProp('hero', 'variant', $variant, ['default', 'dark', 'accent', 'muted']),
    };

    // Layout map: drives outer flex direction, copy/aside column flex factors, and text alignment.
    // — replaces the previous hardcoded 50/50 (flex-1 + flex-1 lg:flex-row) split.
    //   balanced — 50/50 split at lg+, identical to v1.2.x behavior.
    //   lead     — 60/40 split (copy slightly favored).
    //   centered — single column, max-w-md container, text-center.
    //   stacked  — both rows full-width, copy on top, aside below; like centered for copy alignment
    //              but the aside is preserved (centered layout also keeps the aside, just renders it below).
    $layoutMap = [
        'balanced' => ['flex-1', 'flex-1', 'flex-col lg:flex-row', 'text-center lg:text-left'],
        'lead' => ['flex-[3]', 'flex-[2]', 'flex-col lg:flex-row', 'text-center lg:text-left'],
        'centered' => ['max-w-[var(--size-wk-container-md,48rem)] mx-auto', 'w-full mt-[var(--space-wk-xl,2.5rem)]', 'flex-col', 'text-center'],
        'stacked' => ['w-full', 'w-full mt-[var(--space-wk-xl,2.5rem)]', 'flex-col', 'text-center lg:text-left'],
    ];
    $validLayout = isset($layoutMap[$layout])
        ? $layout
        : WireKit::validateProp('hero', 'layout', $layout, array_keys($layoutMap));
    [$copyColClasses, $asideColClasses, $outerFlexClasses, $textAlignClasses] = $layoutMap[$validLayout];

    // asideWidth refines the copy:aside ratio. Only valid under "balanced" —
    // the other layouts already prescribe their own column shapes (centered's
    // single column, lead's fixed 60/40, stacked's full-width rows). For non-
    // balanced layouts with asideWidth set, validateProp emits a debug-mode
    // hint and the prop is silently ignored at render time.
    if ($asideWidth !== null) {
        $asideWidthMap = [
            '1/3' => ['flex-[2]', 'flex-[1]'],
            '2/5' => ['flex-[3]', 'flex-[2]'],
            '1/2' => ['flex-1', 'flex-1'],
            '3/5' => ['flex-[2]', 'flex-[3]'],
            '2/3' => ['flex-[1]', 'flex-[2]'],
        ];
        if ($validLayout !== 'balanced') {
            // Non-balanced layout — fail-fast in debug, ignore silently otherwise.
            // Pass an empty allowed-values list so the message is "asideWidth is
            // only valid when layout=balanced; got {asideWidth} under {layout}".
            WireKit::validateProp(
                'hero',
                'asideWidth',
                $asideWidth.' (only valid when layout=balanced; current layout: '.$validLayout.')',
                ['1/3', '2/5', '1/2', '3/5', '2/3']
            );
        } elseif (! isset($asideWidthMap[$asideWidth])) {
            WireKit::validateProp('hero', 'asideWidth', $asideWidth, array_keys($asideWidthMap));
        } else {
            [$copyColClasses, $asideColClasses] = $asideWidthMap[$asideWidth];
        }
    }

    // Alignment-dependent classes for lede + actions. Centered keeps mobile + desktop centered;
    // every other layout keeps the original "centered on mobile, left-aligned at lg+" behavior.
    $isCentered = $validLayout === 'centered';
    $ledeAlignClasses = $isCentered ? 'mx-auto' : 'lg:mx-0 mx-auto';
    $actionsAlignClasses = $isCentered ? 'justify-center' : 'justify-center lg:justify-start';
    // items-center on the outer flex pulls aside up and centers it next to the copy in row layouts;
    // for column layouts (centered/stacked) the natural flow is fine — items-center centers them in the cross-axis.
    $outerItemsClasses = $outerFlexClasses === 'flex-col' ? 'items-stretch' : 'items-center';

    // Gradient overlay direction is variant-aware so the effect stays visible
    // regardless of the underlying background luminance:
    //   - default (light surface): 10 % BLACK at the bottom-right corner reads
    //     as a subtle vignette darkening.
    //   - muted (subtle-tinted surface): same as default — a touch of black
    //     adds depth without competing.
    //   - dark / accent (saturated or near-black surface in WireKit's neutral
    //     default theme): black-on-near-black is invisible. Switch to a 12 %
    //     WHITE overlay so the corner LIGHTENS instead, producing a
    //     comparable depth cue against dark backgrounds.
    $gradientOverlayClass = match ($variant) {
        'dark', 'accent' => 'bg-gradient-to-br from-transparent to-white/30',
        default => 'bg-gradient-to-br from-transparent to-black/20',
    };
    $gradientClasses = $gradient ? $gradientOverlayClass : '';
@endphp

<section data-variant="{{ $variant }}" {{ $attributes->class([$classes, $variantClasses]) }} @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif>
    @if($gradient)
        {{-- Gradient overlay is anchored to the outer <section> so it
             fills the section edge-to-edge (visually clean across the
             side padding as well as the content area). The
             empty-dark-area-below-content bug class on mobile under
             variant="dark" + gradient is mitigated separately by the
             responsive py- mapping above — mobile drops one tier so
             size="lg" gets the section-md padding tier (5rem) instead
             of lg (7rem), keeping the gradient-extension below content
             visually short enough that it reads as depth, not empty
             space. `pointer-events-none` so the overlay never
             intercepts clicks on the action buttons it visually
             covers. --}}
        <div class="absolute inset-0 pointer-events-none {{ $gradientClasses }}" aria-hidden="true"></div>
    @endif
    <div class="relative max-w-[var(--size-wk-container-xl,80rem)] mx-auto">
        <div class="flex {{ $outerFlexClasses }} {{ $outerItemsClasses }} gap-[var(--space-wk-xl,2.5rem)]">
            {{-- min-w-0 escapes flex-shrink's default min-content floor;
                 w-full lg:w-auto makes the column take parent's full
                 inline-size on flex-col, then revert to content-sized on
                 lg:flex-row. Without these, a wide unbreakable child (a
                 long URL in the lede, a wide ascii-art header) drives the
                 column's min-content past the section's edge on <lg
                 viewports, overflowing the page. Same shape applied to
                 the aside column below for symmetry. --}}
            <div class="{{ $copyColClasses }} {{ $textAlignClasses }} min-w-0 w-full lg:w-auto">
                @isset($eyebrow)
                    <div class="mb-[var(--space-wk-md,1rem)]">{{ $eyebrow }}</div>
                @endisset

                @isset($title)
                    <h1 class="text-[length:var(--text-wk-3xl,1.875rem)] sm:text-[length:var(--font-wk-heading-2xl,3.5rem)] font-[number:var(--font-wk-heading-weight)] leading-[var(--font-wk-heading-line-height,1.25)] tracking-tight mb-[var(--space-wk-md,1rem)]">
                        {{ $title }}
                    </h1>
                @endisset

                @isset($lede)
                    <p class="text-[length:var(--text-wk-lg)] opacity-80 max-w-[40rem] mb-[var(--space-wk-lg,1.5rem)] {{ $ledeAlignClasses }}">
                        {{ $lede }}
                    </p>
                @endisset

                @isset($actions)
                    <div class="flex flex-wrap gap-[var(--gap-wk-sm)] {{ $actionsAlignClasses }}">
                        {{ $actions }}
                    </div>
                @endisset
            </div>

            @isset($aside)
                {{-- min-w-0 w-full lg:w-auto — see rationale on the copy
                     column above. The aside is the higher-risk side
                     because developers commonly drop a
                     <x-wirekit::code-block> into the aside; <pre>'s
                     white-space: pre keeps the LONGEST line non-breakable,
                     which without min-w-0 escapes the column's intrinsic
                     min-content sizing and overflows the page on narrow
                     viewports. The code-block's own overflow-x-auto then
                     handles intra-line horizontal scrolling within the
                     column's now-constrained inline-size. --}}
                <div class="{{ $asideColClasses }} min-w-0 w-full lg:w-auto">{{ $aside }}</div>
            @endisset
        </div>
    </div>
</section>
