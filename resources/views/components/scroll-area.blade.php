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
    // `role="region"` plus a name is a landmark, so a built-in name would make ten scroll areas
    // on one page ten rotor entries all called "Scrollable content" — axe reports it as
    // `landmark-unique`, and the name meant to tell them apart would make them identical.
    // Without a name the element keeps `tabindex="0"` and no role: still keyboard-reachable
    // (WCAG 2.1.1), just deliberately not a destination. Name it after the CONTENT ("Release
    // notes"), never after the mechanism.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('scroll-area', $attributes->getAttributes());

    // An unknown `orientation` is reported (Support\StrictnessGate) and renders as `vertical`; a
    // bound `null` or empty string is `vertical` without a word.
    $orientation = \Pushery\WireKit\WireKit::validateProp('scroll-area', 'orientation', \Pushery\WireKit\Support\NumericProp::text($orientation ?: 'vertical'), ['vertical', 'horizontal', 'both']);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // Scroll Area — themed scrollbar container wrapping the existing .wk-scrollbar utility.
    // Provides a component API around the CSS scrollbar utility with configurable orientation.
    $overflowClass = match ($orientation) {
        'horizontal' => 'overflow-x-auto overflow-y-hidden',
        'both' => 'overflow-auto',
        default => 'overflow-y-auto overflow-x-hidden',
    };

    // Normalize the fade prop. Falsy / 'none' → no fade; `true` (a bare `fade`) is both edges;
    // otherwise validate to the allowed edge set so an invalid value resolves to a real one.
    if ($fade === true) {
        $fade = 'both';
    }
    $fadeValue = ($fade === false || $fade === null || $fade === '' || $fade === 'none')
        ? null
        : (in_array($fade, ['both', 'start', 'end', 'auto'], true)
            ? $fade
            : WireKit::validateProp('scroll-area', 'fade', $fade, ['both', 'start', 'end', 'auto']));

    // The fade masks the SCROLL axis: x for horizontal, y for vertical / both.
    $fadeAxis = $orientation === 'horizontal' ? 'x' : 'y';

    // 'auto' emits no data-fade at all — the plugin writes it once it has
    // read the overflow. Rendering a value here first would paint the very mask
    // that reading exists to avoid, for one frame or forever if scripts fail.
    $fadeIsAuto = $fadeValue === 'auto';

    $classes = 'wk-scrollbar '.($fadeValue ? 'wk-scroll-fade ' : '').WireKit::resolveClasses('scroll-area', 'base', implode(' ', array_filter([
        'relative',
        $overflowClass,
        'font-[family-name:var(--font-wk-sans)]',
        // A tab stop shows that it has the focus, in the kit's ring and its tokens. Drawn
        // outside the area, where nothing the area holds can cover it and nothing it scrolls
        // moves it.
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ])), $scope);

    // Inline style for max-height — common pattern for scroll containers
    $maxHeight = \Pushery\WireKit\Support\CssValue::prop($maxHeight, 'scroll-area', 'maxHeight');
    $inlineStyle = $maxHeight ? "max-height: {$maxHeight};" : '';

    // A caller's `aria-labelledby` or `aria-label` names the area as `label` does, and makes it the
    // same landmark; left in the bag, it named a `div` with no role, which ARIA prohibits
    // (Support\CallerName).
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
    $named = $callerLabelledBy !== null || $callerLabel !== null || filled($label);
@endphp

{{-- Scroll container — focusable for keyboard scrolling (a11y: WCAG 2.1.1).

     `tabindex` is UNCONDITIONAL and the landmark is not. Reachability is what WCAG 2.1.1 and
     axe's `scrollable-region-focusable` ask for, and `tabindex` alone satisfies both; the role
     adds a rotor destination, which is only worth having when the destination has a name the
     reader can tell from its neighbors. `filled()` rather than `??`: an interpolated caller
     value can arrive empty, and `role="region"` with an empty name is not exposed as a landmark
     at all — a focusable region nobody can identify is the worst of the three outcomes. --}}
{{-- `data-wk-print-expand`: paper does not scroll, so on paper this box grows to all it holds. --}}
<div
    data-wk-print-expand
    tabindex="0"
    @if($named) @unless(\Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'role')) role="region" @endunless {{ \Pushery\WireKit\Support\CallerName::attribute($callerLabelledBy, $callerLabel, $label) }} @endif
    @if($fadeValue) data-fade-axis="{{ $fadeAxis }}" @endif
    @if($fadeIsAuto) x-data="wirekitScrollFade" @elseif($fadeValue) data-fade="{{ $fadeValue }}" @endif
    {{ $attributes->except('tabindex')->merge($inlineStyle ? ['style' => $inlineStyle] : [])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{ $slot }}
</div>
