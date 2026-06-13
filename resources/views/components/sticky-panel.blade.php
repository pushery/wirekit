@props([
    // Distance from the viewport top when pinned (any CSS length). Also drives the
    // default max-height: calc(100dvh - 2*offset), leaving the offset above + below.
    'offset' => '2rem',
    // Explicit max-height override (CSS length); null = the calc above.
    'maxHeight' => null,
    // Panel width on desktop (the developer's two-column layout supplies the gutter).
    'width' => '20rem',
    // Tailwind breakpoint at/above which the panel sticks. Below it, mobileBehavior applies.
    'hideBelow' => 'md',
    // Below the breakpoint: 'inline' (un-stick, render in flow) or 'hide' (display:none).
    'mobileBehavior' => 'inline',
    // Accessible name for the scrollable body region (role="region" needs a name).
    'label' => null,
    // Show top/bottom overflow shadows on the body. They render as OVERLAYS above
    // the content (.wk-scroll-shadow-top/-bottom + an IntersectionObserver over
    // edge sentinels), so hovered rows/buttons never cover them. For a plain-CSS
    // variant on your own containers, the .wk-scroll-shadow utility still exists.
    'scrollShadow' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $hideBelowValue = match ($hideBelow) {
        'sm', 'md', 'lg', 'xl' => $hideBelow,
        default => WireKit::validateProp('sticky-panel', 'hideBelow', $hideBelow, ['sm', 'md', 'lg', 'xl']),
    };
    $mobileBehaviorValue = match ($mobileBehavior) {
        'inline', 'hide' => $mobileBehavior,
        default => WireKit::validateProp('sticky-panel', 'mobileBehavior', $mobileBehavior, ['inline', 'hide']),
    };

    // `position: sticky` engages only at/above the breakpoint (literal classes so
    // Tailwind scans them); below it the <aside> falls back to static (mobile inline).
    $stickyClass = match ($hideBelowValue) {
        'sm' => 'sm:sticky',
        'lg' => 'lg:sticky',
        'xl' => 'xl:sticky',
        default => 'md:sticky',
    };
    // 'hide' mode: collapse the panel entirely below the breakpoint.
    $hideClass = $mobileBehaviorValue === 'hide'
        ? match ($hideBelowValue) {
            'sm' => 'max-sm:hidden',
            'lg' => 'max-lg:hidden',
            'xl' => 'max-xl:hidden',
            default => 'max-md:hidden',
        }
        : '';

    // dvh (dynamic viewport height) smooths the iOS/Android address-bar collapse jump.
    $resolvedMaxHeight = $maxHeight ?? "calc(100dvh - 2 * {$offset})";

    $asideClasses = WireKit::resolveClasses('sticky-panel', 'aside', implode(' ', [
        'self-start',
        $stickyClass,
        $hideClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Flex column (not a fixed 3-row grid) so an absent header / footer collapses
    // cleanly and the body always fills the remaining height + scrolls.
    $componentClasses = WireKit::resolveClasses('sticky-panel', 'component', implode(' ', [
        'flex flex-col overflow-hidden',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'shadow-[var(--shadow-wk-md)]',
    ]), $scope);
@endphp

<aside
    {{ $attributes->class([$asideClasses]) }}
    style="top: {{ $offset }}; width: {{ $width }}; max-width: 100%;"
    @if($label) aria-label="{{ $label }}" @endif
>
    <div class="{{ $componentClasses }}" style="max-height: {{ $resolvedMaxHeight }};">
        @if(isset($header))
            {{-- Non-scrolling header, pinned to the top of the panel. --}}
            <div class="shrink-0 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                {{ $header }}
            </div>
        @endif

        @if($scrollShadow)
            {{-- Scrollable body with OVERLAY scroll shadows. The shadows are absolute
                 siblings painted ABOVE the content (.wk-scroll-shadow-top/-bottom) —
                 the earlier background-based approach sat UNDER children, so a hovered
                 row/button at a scroll edge covered the affordance. An
                 IntersectionObserver over the two 1px edge sentinels drives each
                 side's visibility (a sentinel out of the scrollport = more content
                 that way); x-cloak keeps the overlays hidden without JS. The body
                 itself stays a keyboard-reachable region (WCAG 2.1.1) with
                 overscroll-contain. --}}
            {{-- The wrapper is itself a flex column so the scroller's height resolves
                 through the flex chain (flex-1 min-h-0 at every level). A plain
                 wrapper + h-full scroller does NOT constrain: a percentage height
                 can't resolve against a flex-grown (indefinite) parent, so the
                 scroller silently grew to its content and never scrolled. --}}
            <div class="relative flex flex-col flex-1 min-h-0" x-data="wirekitStickyPanelShadows()">
                <div
                    x-ref="scroller"
                    tabindex="0"
                    role="region"
                    aria-label="{{ $label ?? 'Panel content' }}"
                    class="flex-1 min-h-0 overflow-y-auto overscroll-contain wk-scrollbar px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset"
                >
                    <div x-ref="topSentinel" aria-hidden="true" class="h-px"></div>
                    {{ $slot }}
                    <div x-ref="bottomSentinel" aria-hidden="true" class="h-px"></div>
                </div>
                <div aria-hidden="true" x-cloak x-show="topShadow" x-transition.opacity class="wk-scroll-shadow-top"></div>
                <div aria-hidden="true" x-cloak x-show="bottomShadow" x-transition.opacity class="wk-scroll-shadow-bottom"></div>
            </div>
        @else
            {{-- Scrollable body, no shadow affordance (:scrollShadow="false"). --}}
            <div
                tabindex="0"
                role="region"
                aria-label="{{ $label ?? 'Panel content' }}"
                class="flex-1 min-h-0 overflow-y-auto overscroll-contain wk-scrollbar px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset"
            >
                {{ $slot }}
            </div>
        @endif

        @if(isset($footer))
            {{-- Non-scrolling footer, pinned to the bottom of the panel. --}}
            <div class="shrink-0 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                {{ $footer }}
            </div>
        @endif
    </div>
</aside>
