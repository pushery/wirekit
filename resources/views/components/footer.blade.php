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

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'footer');

    $max ??= config('wirekit.components.footer.max', 'xl');

    // Inner-container max-width resolution — reads the same
    // `--size-wk-container-*` token family as `<x-wirekit::container>`.
    // No hardcoded fallback values: the token IS the source of truth.
    $innerMaxClass = match ($max) {
        'sm' => 'max-w-[var(--size-wk-container-sm)]',
        'md' => 'max-w-[var(--size-wk-container-md)]',
        'lg' => 'max-w-[var(--size-wk-container-lg)]',
        'xl' => 'max-w-[var(--size-wk-container-xl)]',
        '2xl' => 'max-w-[var(--size-wk-container-2xl)]',
        'full' => 'max-w-full',
        default => WireKit::validateProp('footer', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full']),
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
        'border-t border-[var(--color-wk-border)]',
        'py-[var(--space-wk-section-sm)]',
        'px-[var(--padding-wk-x-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text-muted)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);
@endphp

<footer {{ $attributes->class([$classes]) }} @if($animateAttr) {!! $animateAttr !!} @endif>
    <div class="{{ $innerMaxClass }} mx-auto">
        @isset($columns)
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-[var(--space-wk-xl)] mb-[var(--space-wk-xl)]">
                {{ $columns }}
            </div>
        @endisset
    </div>

    {{-- Bottom-strip divider sits OUTSIDE the constrained container so it
         spans the full footer-content-area width (footer's px-lg padding,
         not the max-w-container-xl content boundary). Real-world footer
         pattern: legal/brand row separated from columns by a line that
         visually crosses the entire footer, not just the inner content. --}}
    @if(isset($brand) || isset($legal))
        <div class="my-[var(--space-wk-lg)] border-t border-[var(--color-wk-border-subtle)]"></div>

        <div class="{{ $innerMaxClass }} mx-auto">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-[var(--space-wk-md)]">
                @isset($brand)
                    <div>{{ $brand }}</div>
                @endisset

                @isset($legal)
                    <div class="flex flex-wrap gap-[var(--gap-wk-md)] text-[length:var(--text-wk-xs)]">
                        {{ $legal }}
                    </div>
                @endisset
            </div>
        </div>
    @endif

    @if($slot->isNotEmpty())
        <div class="{{ $innerMaxClass }} mx-auto">
            {{ $slot }}
        </div>
    @endif
</footer>
