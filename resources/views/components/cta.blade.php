{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'variant' => 'default',
    // Optional reveal animation when the CTA scrolls into view.
    // Null = no animation (default — CTA renders without an entrance reveal).
    'animateIn' => null,
    // `size` — vertical-rhythm tier (mirrors hero's prop). One of `sm` /
    // `md` / `lg`. Default `md` (= `--space-wk-section-md` at sm+
    // viewports). Mobile viewport (< sm breakpoint) drops one tier so
    // the cta never overshoots vertical-padding budget on small screens.
    'size' => 'md',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'cta');

    // Validate `size` against the three-tier enum (debug-mode validation,
    // production silent fallback to `md`).
    $validSize = in_array($size, ['sm', 'md', 'lg'], true)
        ? $size
        : WireKit::validateProp('cta', 'size', $size, ['sm', 'md', 'lg']);

    // Responsive vertical-padding (same shape as hero). Static class
    // strings keep Tailwind source-detection happy.
    $ctaVerticalPadding = match ($validSize) {
        'sm' => 'py-[var(--space-wk-section-sm)] sm:py-[var(--space-wk-section-sm)]',
        'md' => 'py-[var(--space-wk-section-sm)] sm:py-[var(--space-wk-section-md)]',
        'lg' => 'py-[var(--space-wk-section-md)] sm:py-[var(--space-wk-section-lg)]',
    };

    // CTA — call-to-action banner section.
    // `wk-cta` marker — load-bearing against developer prose
    // `max-width: 75ch` clamps (see footer.blade.php for the full
    // rationale).
    $classes = WireKit::resolveClasses('cta', 'base', implode(' ', [
        'wk-cta',
        // `w-full` keeps the CTA full-width inside docs.wirekit.app
        // flex-row preview wrapper (see footer.blade.php for rationale).
        'w-full',
        $ctaVerticalPadding,
        'px-[var(--padding-wk-x-lg)]',
        'text-center',
    ]), $scope);

    // `dark` establishes a real dark TOKEN CONTEXT (the bare `dark` class), not
    // just a dark band — every --color-wk-* token resolves to its `.dark` value
    // for the subtree, so token-surfaced children (code-block, card, input)
    // render dark surfaces instead of light (white-on-white bug class). Regular
    // --color-wk-bg / --color-wk-text are dark / light under `.dark`, so the band
    // is identical in light mode and stays genuinely dark in dark mode (the old
    // `bg-inverse` flipped it to near-white in dark mode). See hero.blade.php for
    // the full rationale; kept in lock-step.
    $variantClasses = match ($variant) {
        'default' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]',
        'dark' => 'dark bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text)]',
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        default => WireKit::validateProp('cta', 'variant', $variant, ['default', 'dark', 'accent']),
    };
@endphp

<section data-variant="{{ $variant }}" {{ $attributes->class([$classes, $variantClasses]) }} @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif>
    <div class="max-w-[var(--size-wk-container-md)] mx-auto">
        @isset($title)
            <h2 class="text-[length:var(--text-wk-2xl,1.5rem)] sm:text-[length:var(--font-wk-heading-xl,2.5rem)] font-[number:var(--font-wk-heading-weight)] leading-[var(--font-wk-heading-line-height,1.25)] mb-[var(--space-wk-md,1rem)]">
                {{ $title }}
            </h2>
        @endisset

        @isset($description)
            <p class="text-[length:var(--text-wk-lg)] opacity-80 mb-[var(--space-wk-lg,1.5rem)]">
                {{ $description }}
            </p>
        @endisset

        @isset($actions)
            <div class="flex flex-wrap gap-[var(--gap-wk-sm)] justify-center">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
