@props([
    'variant' => 'default',
    'gradient' => false,
    'layout' => 'balanced',
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
        'accent' => 'bg-[var(--color-wk-accent)] text-[var(--color-wk-accent-fg)]',
        'muted' => 'bg-[var(--color-wk-bg-muted)] text-[var(--color-wk-text)]',
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

    // Alignment-dependent classes for lede + actions. Centered keeps mobile + desktop centered;
    // every other layout keeps the original "centered on mobile, left-aligned at lg+" behavior.
    $isCentered = $validLayout === 'centered';
    $ledeAlignClasses = $isCentered ? 'mx-auto' : 'lg:mx-0 mx-auto';
    $actionsAlignClasses = $isCentered ? 'justify-center' : 'justify-center lg:justify-start';
    // items-center on the outer flex pulls aside up and centers it next to the copy in row layouts;
    // for column layouts (centered/stacked) the natural flow is fine — items-center centers them in the cross-axis.
    $outerItemsClasses = $outerFlexClasses === 'flex-col' ? 'items-stretch' : 'items-center';

    $gradientClasses = $gradient ? 'bg-gradient-to-br from-transparent to-black/10' : '';
@endphp

<section {{ $attributes->class([$classes, $variantClasses]) }}>
    @if($gradient)
        <div class="absolute inset-0 {{ $gradientClasses }}" aria-hidden="true"></div>
    @endif

    <div class="relative max-w-[var(--size-wk-container-xl,80rem)] mx-auto">
        <div class="flex {{ $outerFlexClasses }} {{ $outerItemsClasses }} gap-[var(--space-wk-xl,2.5rem)]">
            <div class="{{ $copyColClasses }} {{ $textAlignClasses }}">
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
                <div class="{{ $asideColClasses }}">{{ $aside }}</div>
            @endisset
        </div>
    </div>
</section>
