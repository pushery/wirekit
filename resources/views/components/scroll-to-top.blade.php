@props([
    'threshold' => 1.5,
    'size' => config('wirekit.components.scroll-to-top.size', 'md'),
    'position' => 'bottom-right',
    'forceVisible' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Scroll-to-top button — appears after scrolling past a configurable
    // viewport multiplier (default: 1.5x viewport height). Uses Alpine.js
    // scroll listener with requestAnimationFrame for smooth performance.
    $buttonClasses = WireKit::resolveClasses('scroll-to-top', 'base', implode(' ', [
        'fixed z-[var(--z-wk-sticky)]',
        'inline-flex items-center justify-center',
        'rounded-full',
        'bg-[var(--color-wk-accent)]',
        'text-[color:var(--color-wk-accent-fg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'cursor-pointer',
        'transition-all',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:bg-[var(--color-wk-accent-hover)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
    ]), $scope);

    // Size classes: icon and button dimensions
    $sizeClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] w-[var(--size-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] w-[var(--size-wk-lg)]',
        default => 'h-[var(--size-wk-md)] w-[var(--size-wk-md)]',
    };

    $iconSize = match ($size) {
        'sm' => 'h-3.5 w-3.5',
        'lg' => 'h-6 w-6',
        default => 'h-5 w-5',
    };

    // Position: four corners + custom via attributes
    $positionClasses = match ($position) {
        'bottom-left' => 'bottom-[var(--padding-wk-x-lg)] left-[var(--padding-wk-x-lg)]',
        'top-right' => 'top-[var(--padding-wk-x-lg)] right-[var(--padding-wk-x-lg)]',
        'top-left' => 'top-[var(--padding-wk-x-lg)] left-[var(--padding-wk-x-lg)]',
        default => 'bottom-[var(--padding-wk-x-lg)] right-[var(--padding-wk-x-lg)]',
    };
@endphp

{{-- Alpine: listens to scroll events and shows button after threshold.
     Uses requestAnimationFrame to avoid jank on frequent scroll events. --}}
<button
    type="button"
    x-data="{
        visible: {{ $forceVisible ? 'true' : 'false' }},
        threshold: {{ (float) $threshold }},
        _ticking: false,
        init() {
            if ({{ $forceVisible ? 'true' : 'false' }}) return;
            this._onScroll = () => {
                if (!this._ticking) {
                    window.requestAnimationFrame(() => {
                        this.visible = window.scrollY > (window.innerHeight * this.threshold);
                        this._ticking = false;
                    });
                    this._ticking = true;
                }
            };
            window.addEventListener('scroll', this._onScroll, { passive: true });
        },
        destroy() {
            window.removeEventListener('scroll', this._onScroll);
        },
        scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }"
    x-show="visible"
    @unless($forceVisible) x-cloak @endunless
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    @click="scrollToTop()"
    type="button"
    {{-- aria-label via merge so a caller can override the default — a
         hardcoded attribute plus a separate $attributes bag renders a
         duplicate aria-label that the browser ignores (first wins). --}}
    {{ $attributes->merge(['aria-label' => 'Scroll to top'])->class([$buttonClasses, $sizeClasses, $positionClasses]) }}
>
    {{-- Chevron up icon — decorative, label is on the button --}}
    <svg aria-hidden="true" class="{{ $iconSize }}" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 17a.75.75 0 01-.75-.75V5.612L5.29 9.77a.75.75 0 01-1.08-1.04l5.25-5.5a.75.75 0 011.08 0l5.25 5.5a.75.75 0 11-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0110 17z" clip-rule="evenodd"/>
    </svg>
</button>
