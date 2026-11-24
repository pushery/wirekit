{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    // Optional reveal animation when the footer scrolls into view.
    // Null = no animation (default — footer renders without an entrance reveal).
    'animateIn' => null,
    // `max` — container max-width tier for the inner content wrappers
    // (columns, brand+legal row, default slot). Defaults to `xl` (=
    // 80 rem). Same enum as the container primitive (sm/md/lg/xl/2xl/
    // full) so the footer aligns with the rest of the page chrome on
    // the same vertical content-edge spine.
    'max' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('footer', $attributes->getAttributes());

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'footer');

    // A width the caller passes marks the footer (`data-wk-footer-own-max`): inside a main region
    // its content spans the region's column, unless the caller chose a width here. The configured
    // default is no such choice, it is the width of a footer that spans the page.
    $ownMax = $max !== null;
    $max ??= config('wirekit.components.footer.max', 'xl');

    // Inner-container max-width resolution — reads the same
    // `--size-wk-container-*` token family as `<x-wirekit::container>`.
    // No hardcoded fallback values: the token is the source of truth.
    $innerMaxClass = match (WireKit::validateProp('footer', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full'])) {
        'sm' => 'max-w-[var(--size-wk-container-sm)]',
        'md' => 'max-w-[var(--size-wk-container-md)]',
        'lg' => 'max-w-[var(--size-wk-container-lg)]',
        'xl' => 'max-w-[var(--size-wk-container-xl)]',
        '2xl' => 'max-w-[var(--size-wk-container-2xl)]',
        'full' => 'max-w-full',
    };

    // Footer — landing page footer with brand, columns, and legal slots.
    // The `wk-footer` marker class is load-bearing for developer prose
    // wrappers that apply `max-width: 75ch` (or similar) to direct
    // descendants — those rules typically carve out `[class*="wk-"]`
    // (the same defensive pattern used for the brand-bar / reading-toc
    // / list components) so WireKit components bypass the typography
    // clamp and render at the parent's full content width. Without
    // the marker, the footer would render capped at 75 ch
    // (~600 px) regardless of the surrounding layout's available
    // width — visually narrower than a real website footer should be.
    //
    // It carries a second rule, one worth knowing before deleting the class: inside a
    // shell's main region the footer's own top border stops being an edge and becomes the
    // line between the content and the page chrome. `dist/wirekit.css` gives
    // `:where(.wk-main) :where(.wk-footer)` air above that line and less below it, so the
    // rule does not read as belonging to the card it touches. A standalone footer is
    // untouched, and so is a footer whose caller sets its own margin or padding at the top
    // (the first two markers below).
    $classes = WireKit::resolveClasses('footer', 'base', implode(' ', [
        'wk-footer',
        // `w-full` is load-bearing inside flex-row preview wrappers.
        // The docs.wirekit.app renders previews inside a
        // `.docs-preview__render-content` container that's `display: flex`
        // — without `width: 100%` the footer shrinks to its intrinsic
        // content width (typically ~235 px for a "© 2026 Company
        // Privacy  Terms" legal row). In a real developer app the footer
        // is a block child of <body> and would fill the line-box
        // naturally; the explicit width matches that intent.
        'w-full',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'py-[var(--space-wk-section-sm)]',
        'px-[var(--padding-wk-x-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text-muted)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);

    // The main-region rule stands outside every cascade layer, where it beats a Tailwind
    // utility whatever the specificity. A caller's margin or padding at the top, the auto
    // margin that holds a footer at the end of a growing column included, marks the footer,
    // and the rule leaves it out.
    $ownMarginAbove = \Pushery\WireKit\Support\UtilityClasses::callerSets($attributes, 'margin', ['t', 'bs']);
    $ownPaddingAbove = \Pushery\WireKit\Support\UtilityClasses::callerSets($attributes, 'padding', ['t', 'bs']);
    // A main region insets its column already, so the same rule takes the footer's inline padding
    // and its surface away there. A caller's own inline padding or background color marks the
    // footer, and the rule leaves that part to the caller.
    $ownPaddingInline = \Pushery\WireKit\Support\UtilityClasses::callerSets($attributes, 'padding', ['s', 'e', 'l', 'r']);
    $ownBackground = \Pushery\WireKit\Support\UtilityClasses::callerHas($attributes, \Pushery\WireKit\Support\UtilityClasses::BACKGROUND_COLOR);
@endphp

<footer {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }} @if($ownMarginAbove) data-wk-footer-own-margin @endif @if($ownPaddingAbove) data-wk-footer-own-padding @endif @if($ownPaddingInline) data-wk-footer-own-inline-padding @endif @if($ownBackground) data-wk-footer-own-background @endif @if($ownMax) data-wk-footer-own-max @endif @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif>
    <div class="{{ $innerMaxClass }} mx-auto">
        @wirekitLast(\Pushery\WireKit\Support\SlotContent::given($columns ?? null))
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-[var(--space-wk-xl)] mb-[var(--space-wk-xl)]">
                {{ $columns }}
            </div>
        @endwirekitLast
    </div>

    {{-- Bottom-strip divider sits outside the constrained container so it
         spans the full footer-content-area width (footer's px-lg padding,
         not the max-w-container-xl content boundary). Real-world footer
         pattern: legal/brand row separated from columns by a line that
         visually crosses the entire footer, not just the inner content. --}}
    @if(\Pushery\WireKit\Support\SlotContent::given($brand ?? null) || \Pushery\WireKit\Support\SlotContent::given($legal ?? null))
        <div class="my-[var(--space-wk-lg)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border-subtle)]"></div>

        <div class="{{ $innerMaxClass }} mx-auto">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-[var(--space-wk-md)]">
                @if(\Pushery\WireKit\Support\SlotContent::given($brand ?? null))
                    <div>{{ $brand }}</div>
                @endif

                @wirekitLast(\Pushery\WireKit\Support\SlotContent::given($legal ?? null))
                    <div class="flex flex-wrap gap-[var(--gap-wk-md)] text-[length:var(--text-wk-xs)]">
                        {{ $legal }}
                    </div>
                @endwirekitLast
            </div>
        </div>
    @endif

    @wirekitLast($slot->hasActualContent())
        <div class="{{ $innerMaxClass }} mx-auto">
            {{ $slot }}
        </div>
    @endwirekitLast
</footer>
