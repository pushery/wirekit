{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // Which corner the badge is anchored to. Logical, not physical: "end" is
    // the right edge in LTR and the left edge in RTL, so the anchor follows the
    // reading direction; the stylesheet flips the physical `transform` for RTL.
    'position' => 'top-end',
    // Nudge the badge further out (any CSS length). Default centers it on the
    // corner.
    'offset' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('indicator', $attributes->getAttributes());

    $positionValue = in_array($position, ['top-end', 'top-start', 'bottom-end', 'bottom-start'], true)
        ? $position
        : WireKit::validateProp('indicator', 'position', $position, ['top-end', 'top-start', 'bottom-end', 'bottom-start']);

    // The width is the target's in every container. An inline-level box is blockified as the
    // item of a flex column or a grid and stretched to the container's width, and the badge,
    // placed against this box, then sat at the container's far edge instead of on the target.
    $classes = WireKit::resolveClasses('indicator', 'base', 'relative inline-flex w-fit', $scope);

    // Per-instance nudge via the token custom property.
    $style = $offset !== null && $offset !== '' ? "--indicator-wk-offset: {$offset};" : null;

    $attrs = $attributes->class([$classes]);
    if ($style !== null) {
        $attrs = $attrs->merge(['style' => $style]);
    }
@endphp

{{-- The wrapper is `relative` and shrink-wraps its target; the badge is
     absolutely placed against that box. This exists so developers stop
     hand-rolling absolute positioning with negative corner offsets on every
     bell and avatar — the exact raw-div pattern WireKit is here to replace.

     NOTE: do not write literal Tailwind class names in this comment. Tailwind's
     @source scans this file as plain text — comments included — and would
     COMPILE any class named here into the bundle as a selector nothing emits.
     The drift auditor catches exactly that. --}}
<span data-wk-indicator data-position="{{ $positionValue }}" {{ $attrs }}>
    {{ $slot }}

    @isset($badge)
        {{-- The badge sits OUTSIDE the target's flow, so it never resizes it.
             Whatever is placed here carries its own meaning: a count must name
             itself ("3 unread"), and a purely decorative dot must be
             aria-hidden with the meaning stated elsewhere. --}}
        <span data-wk-indicator-badge>{{ $badge }}</span>
    @endisset
</span>