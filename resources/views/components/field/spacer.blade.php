{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // The other half of `reserve-message`, and the half that was missing.
    //
    // `reserve-message` holds the space BELOW a control so a field does not shove its
    // neighbors down the moment validation fires. That fixes the bottom edge. The top
    // edge has the same problem and no answer: a button or a plain block sitting beside a
    // labeled field starts at the container's top while the field's control starts one
    // label-height lower, so two things that belong on one line are not on one line.
    //
    // The usual reaches both fail for reasons worth stating, because both look like they
    // should work. `align-items: end` aligns the OUTER boxes, and the control is two levels
    // inside one of them — so a field with a two-line label pushes its neighbor down by a
    // line it does not have. A hand-written `<span>` copying the label's classes drifts the
    // first time a token changes, and it is invisible drift: nothing renders wrong, the two
    // elements simply stop being the same height.
    //
    // So this renders the REAL label with a non-breaking space. Whatever the label is —
    // font, line-height, margin, the token behind any of them — this is exactly as tall,
    // because it IS one.
    $classes = WireKit::resolveClasses('field.spacer', 'base', '', $scope);
@endphp

{{-- aria-hidden: there is nothing here to read. A screen reader that announced an empty
     label would be describing a layout decision as if it were content. --}}
{{-- The no-break space is passed as a slot VARIABLE, not written as `&nbsp;` in the
     markup. A component slot is escaped on the way through, so the literal entity arrives
     as the six visible characters "&nbsp;" — a label reading that instead of holding a
     blank line, which is worse than the misalignment it was meant to fix. --}}
@php($wkSpacerBlank = "\u{00A0}")
<x-wirekit::label aria-hidden="true" {{ $attributes->class([$classes]) }}>{{ $wkSpacerBlank }}</x-wirekit::label>
