{{-- optimistic-ui: n/a — client-only
     A scroll region, keyboard-reachable by contract. Nothing here talks to a server. --}}
@props([
    'orientation' => 'vertical', // vertical | horizontal | both
    'maxHeight' => null,
    // Edge fade: false (none) | 'both' | 'start' | 'end' | 'auto'. Masks the
    // overflow edge(s) along the scroll axis so the content itself dissolves
    // into the background — a background-agnostic "there's more to scroll" hint
    // (mask-image, not a colored overlay). Removed on :focus-within so a
    // keyboard-focused child near an edge is never clipped by the mask.
    //
    // The named edges are pure CSS and unconditional, which is also their limit:
    // they fade an edge the content does not continue past — at the very top,
    // at the very bottom, and on an area whose content fits and cannot scroll
    // at all. 'auto' opts into an Alpine plugin that measures instead and fades
    // only where there really is more. Without JavaScript it renders no mask,
    // which is the right way round: a missing hint beats dissolved text.
    'fade' => config('wirekit.components.scroll-area.fade', false),
    // Accessible name for the scroll region, and the switch that makes it a LANDMARK.
    //
    // `role="region"` plus a name is a landmark, so ten unnamed scroll areas on one page were
    // ten rotor entries all called "Scrollable content" — axe reports it as `landmark-unique`,
    // and the name meant to tell them apart is what made them identical. Without a name the
    // element keeps `tabindex="0"` and no role: still keyboard-reachable (WCAG 2.1.1), just
    // deliberately not a destination. Name it after the CONTENT ("Release notes"), never after
    // the mechanism.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('scroll-area', $attributes->getAttributes());

    // Scroll Area — themed scrollbar container wrapping the existing .wk-scrollbar utility.
    // Provides a component API around the CSS scrollbar utility with configurable orientation.
    $overflowClass = match ($orientation) {
        'horizontal' => 'overflow-x-auto overflow-y-hidden',
        'both' => 'overflow-auto',
        default => 'overflow-y-auto overflow-x-hidden',
    };

    // Normalize the fade prop. Falsy / 'none' → no fade; otherwise validate to
    // the allowed edge set so an invalid value resolves to a real one.
    $fadeValue = ($fade === false || $fade === null || $fade === '' || $fade === 'none')
        ? null
        : (in_array($fade, ['both', 'start', 'end', 'auto'], true)
            ? $fade
            : WireKit::validateProp('scroll-area', 'fade', $fade, ['both', 'start', 'end', 'auto']));

    // The fade masks the SCROLL axis: x for horizontal, y for vertical / both.
    $fadeAxis = $orientation === 'horizontal' ? 'x' : 'y';

    // 'auto' emits no data-fade at all — the plugin writes it once it has
    // measured. Rendering a value here first would paint the very mask the
    // measurement exists to avoid, for one frame or forever if scripts fail.
    $fadeIsAuto = $fadeValue === 'auto';

    $classes = WireKit::resolveClasses('scroll-area', 'base', implode(' ', array_filter([
        'wk-scrollbar',
        $overflowClass,
        'font-[family-name:var(--font-wk-sans)]',
        $fadeValue ? 'wk-scroll-fade' : '',
    ])), $scope);

    // Inline style for max-height — common pattern for scroll containers
    $inlineStyle = $maxHeight ? "max-height: {$maxHeight};" : '';
@endphp

{{-- Scroll container — focusable for keyboard scrolling (a11y: WCAG 2.1.1).

     `tabindex` is UNCONDITIONAL and the landmark is not. Reachability is what WCAG 2.1.1 and
     axe's `scrollable-region-focusable` ask for, and `tabindex` alone satisfies both; the role
     adds a rotor destination, which is only worth having when the destination has a name the
     reader can tell from its neighbors. `filled()` rather than `??`: an interpolated caller
     value can arrive empty, and `role="region"` with an empty name is not exposed as a landmark
     at all — a focusable region nobody can identify is the worst of the three outcomes. --}}
<div
    tabindex="0"
    @if(filled($label)) role="region" aria-label="{{ $label }}" @endif
    @if($fadeValue) data-fade-axis="{{ $fadeAxis }}" @endif
    @if($fadeIsAuto) x-data="wirekitScrollFade" @elseif($fadeValue) data-fade="{{ $fadeValue }}" @endif
    {{ $attributes->merge($inlineStyle ? ['style' => $inlineStyle] : [])->class([$classes]) }}
>
    {{ $slot }}
</div>
