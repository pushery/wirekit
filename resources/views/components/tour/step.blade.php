@props([
    'target' => null,
    'placement' => 'bottom',
    'index' => 0,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tour step — individual tooltip-like popup positioned near a target element.
    // The initial off-screen position (left/top: -9999px) is set via a CSS rule
    // in dist/wirekit.css scoped to [data-wk-tour-step] — it prevents a visible
    // flicker at (0, 0) between Alpine's x-show flip and Floating UI's async
    // positioning. Floating UI's Object.assign(floating.style, { left, top })
    // writes inline style values that outrank the stylesheet rule once
    // computePosition() resolves, so the panel jumps straight to its final spot.
    $panelClasses = WireKit::resolveClasses('tour.step', 'base', implode(' ', [
        'fixed z-[var(--z-wk-modal)]',
        'w-80',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

<div
    x-show="currentStep === {{ (int) $index }}"
    data-wk-tour-step="{{ (int) $index }}"
    data-wk-target="{{ $target }}"
    data-wk-placement="{{ $placement }}"
    role="dialog"
    aria-label="Tour step {{ $index + 1 }}"
    {{ $attributes->class([$panelClasses]) }}
    x-cloak
>
    {{-- Step title --}}
    @isset($title)
        <h3 class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-lg)] mb-[var(--padding-wk-y-xs)]">{{ $title }}</h3>
    @endisset

    {{-- Step body --}}
    <div class="text-[var(--color-wk-text-muted)] mb-[var(--padding-wk-y-md)]">
        {{ $slot }}
    </div>

    {{-- Step footer — navigation + progress --}}
    <div class="flex items-center justify-between">
        <span class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)] tabular-nums" x-text="progressText"></span>
        <div class="flex items-center gap-[var(--gap-wk-sm)]">
            <button
                type="button"
                x-show="currentStep > 0"
                x-on:click="prev()"
                class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] cursor-pointer text-[var(--color-wk-text-muted)] hover:text-[var(--color-wk-text)] rounded-[var(--radius-wk-sm)] hover:bg-[var(--color-wk-bg-subtle)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >Back</button>
            <button
                type="button"
                x-on:click="next()"
                class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] cursor-pointer bg-[var(--color-wk-accent)] text-[var(--color-wk-accent-fg)] rounded-[var(--radius-wk-md)] hover:bg-[var(--color-wk-accent-hover)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                x-text="currentStep === totalSteps - 1 ? 'Finish' : 'Next'"
            ></button>
        </div>
    </div>
</div>
