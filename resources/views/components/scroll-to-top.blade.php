{{-- optimistic-ui: n/a — client-only
     Its state is scroll position. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'threshold' => 1.5,
    'size' => config('wirekit.components.scroll-to-top.size', 'md'),
    'position' => 'bottom-right',
    'forceVisible' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('scroll-to-top', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $forceVisible = BooleanProp::from($forceVisible, false);

    // Scroll-to-top button — appears after scrolling past a configurable
    // viewport multiplier (default: 1.5x viewport height). Uses Alpine.js
    // scroll listener with requestAnimationFrame for smooth performance.
    $buttonClasses = WireKit::resolveClasses('scroll-to-top', 'base', implode(' ', [
        'fixed z-[var(--z-wk-sticky)]',
        // Expands the tap area to 44×44 on coarse pointers without changing the
        // painted box. It was left off here while theme-controller and
        // code-block carried it, so this button kept a 40×40 target — and it
        // could not simply be added by hand, because the class used to force
        // `position: relative` onto a host that is `fixed` by design and threw
        // the button off-screen. Both halves are fixed together, on purpose.
        'wk-touch-target',
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

    // Position: four corners + custom via attributes.
    //
    // ⚠️ The two BOTTOM corners carry `env(safe-area-inset-bottom, 0px)`; the top two do
    // not need it. On a notched iPhone the home-indicator inset is 34px and this button
    // sat 16px up, so the lower half of its 44px coarse-pointer hit area lay inside the
    // gesture strip — where, in the library's own words, "the bottom row of a swipe-up
    // gesture eats the taps". `.wk-fab` already ships this exact expression.
    //
    // `env()` is 0 in headless Playwright, so no browser test can see this either way.
    // ⚠️ WRITTEN OUT IN FULL, TWICE, AND NOT ASSEMBLED FROM A VARIABLE.
    //
    // Tailwind scans this file as TEXT, so a class assembled by string concatenation is
    // extracted WITH the PHP in it: the utility prefix, the opening bracket, a variable
    // reference and the closing bracket all become one candidate, and Tailwind emits a
    // rule for that nonsense selector while the class the component actually renders gets
    // none. The drift audit's reverse pass caught exactly that and named the expression as
    // an untraceable compiled selector.
    //
    // (And it caught it a second time, in the comment that first explained it — writing
    // the offending shape out verbatim re-emits the dead rule. Hence the prose.)
    //
    // Underscores rather than spaces for the same class of reason: an arbitrary value
    // containing a literal space is never compiled, so the offset silently falls back to
    // `auto`. `ArbitraryValueHasNoLiteralSpaceTest` caught this line's first draft.
    //
    // The two BOTTOM corners carry `env(safe-area-inset-bottom, 0px)`; the top two do not
    // need it. On a notched iPhone the home-indicator inset is 34px and this button sat
    // 16px up, so the lower half of its 44px coarse-pointer hit area lay inside the
    // gesture strip — where, in the library's own words, "the bottom row of a swipe-up
    // gesture eats the taps". `.wk-fab` already ships this expression.
    $positionClasses = match ($position) {
        'bottom-left' => 'bottom-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-bottom,0px))] left-[var(--padding-wk-x-lg)]',
        'top-right' => 'top-[var(--padding-wk-x-lg)] right-[var(--padding-wk-x-lg)]',
        'top-left' => 'top-[var(--padding-wk-x-lg)] left-[var(--padding-wk-x-lg)]',
        default => 'bottom-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-bottom,0px))] right-[var(--padding-wk-x-lg)]',
    };
@endphp

{{-- Alpine: listens to scroll events and shows button after threshold.
     Uses requestAnimationFrame to avoid jank on frequent scroll events. --}}
<button
    type="button"
    {{-- The scroll watching lives in resources/js/components/scroll-to-top.js.
         It cannot live here: three methods and two arrow functions, none of
         which Alpine's CSP build parses — under a strict policy the button
         never appeared, because nothing was listening to make it. --}}
    x-data="wirekitScrollToTop({ forceVisible: {{ $forceVisible ? 'true' : 'false' }}, threshold: {{ (float) $threshold }} })"
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
