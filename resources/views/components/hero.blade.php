@props([
    'variant' => 'default',
    'gradient' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Hero — landing page hero section with title, lede, actions, and optional aside.
    $classes = WireKit::resolveClasses('hero', 'base', implode(' ', [
        'relative overflow-hidden',
        'py-[var(--space-wk-section-lg,7rem)]',
        'px-[var(--padding-wk-x-lg)]',
    ]), $scope);

    $variantClasses = match ($variant) {
        'default' => 'bg-[var(--color-wk-bg)] text-[var(--color-wk-text)]',
        'dark' => 'bg-[var(--color-wk-bg-inverse)] text-[var(--color-wk-text-inverse)]',
        'accent' => 'bg-[var(--color-wk-accent)] text-white',
        'muted' => 'bg-[var(--color-wk-bg-muted)] text-[var(--color-wk-text)]',
        default => WireKit::validateProp('hero', 'variant', $variant, ['default', 'dark', 'accent', 'muted']),
    };

    $gradientClasses = $gradient ? 'bg-gradient-to-br from-transparent to-black/10' : '';
@endphp

<section {{ $attributes->class([$classes, $variantClasses]) }}>
    @if($gradient)
        <div class="absolute inset-0 {{ $gradientClasses }}" aria-hidden="true"></div>
    @endif

    <div class="relative max-w-[var(--size-wk-container-xl,80rem)] mx-auto">
        <div class="flex flex-col lg:flex-row items-center gap-[var(--space-wk-xl,2.5rem)]">
            <div class="flex-1 text-center lg:text-left">
                @isset($eyebrow)
                    <div class="mb-[var(--space-wk-md,1rem)]">{{ $eyebrow }}</div>
                @endisset

                @isset($title)
                    <h1 class="text-[length:var(--text-wk-3xl,1.875rem)] sm:text-[length:var(--font-wk-heading-2xl,3.5rem)] font-[number:var(--font-wk-heading-weight)] leading-[var(--font-wk-heading-line-height,1.25)] tracking-tight mb-[var(--space-wk-md,1rem)]">
                        {{ $title }}
                    </h1>
                @endisset

                @isset($lede)
                    <p class="text-[length:var(--text-wk-lg)] opacity-80 max-w-[40rem] mb-[var(--space-wk-lg,1.5rem)] lg:mx-0 mx-auto">
                        {{ $lede }}
                    </p>
                @endisset

                @isset($actions)
                    <div class="flex flex-wrap gap-[var(--gap-wk-sm)] justify-center lg:justify-start">
                        {{ $actions }}
                    </div>
                @endisset
            </div>

            @isset($aside)
                <div class="flex-1 w-full">{{ $aside }}</div>
            @endisset
        </div>
    </div>
</section>
