@props([
    'variant' => 'default',
    // Optional reveal animation when the CTA scrolls into view.
    // Null = no animation (default — CTA renders without an entrance reveal).
    'animateIn' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'cta');

    // CTA — call-to-action banner section.
    // `wk-cta` marker — load-bearing against consumer prose
    // `max-width: 75ch` clamps (see footer.blade.php for the full
    // rationale).
    $classes = WireKit::resolveClasses('cta', 'base', implode(' ', [
        'wk-cta',
        // `w-full` keeps the CTA full-width inside the docs-site
        // flex-row preview wrapper (see footer.blade.php for rationale).
        'w-full',
        'py-[var(--space-wk-section-md,5rem)]',
        'px-[var(--padding-wk-x-lg)]',
        'text-center',
    ]), $scope);

    $variantClasses = match ($variant) {
        'default' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]',
        'dark' => 'bg-[var(--color-wk-bg-inverse)] text-[color:var(--color-wk-text-inverse)]',
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        default => WireKit::validateProp('cta', 'variant', $variant, ['default', 'dark', 'accent']),
    };
@endphp

<section {{ $attributes->class([$classes, $variantClasses]) }} @if($animateAttr) {!! $animateAttr !!} @endif>
    <div class="max-w-[var(--size-wk-container-md,48rem)] mx-auto">
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
