{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'ratio' => '16/9',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('aspect-ratio', $attributes->getAttributes());

    // Parse ratio: accepts "16/9", "4/3", "1/1", or a numeric value
    $aspectValue = is_numeric($ratio) ? $ratio : $ratio;

    $classes = WireKit::resolveClasses('aspect-ratio', 'base', implode(' ', [
        'relative overflow-hidden',
    ]), $scope);
@endphp

{{-- The ratio goes THROUGH the attribute bag, never beside it. Written as a
     second literal style= it produced two style attributes on one element, and
     a browser keeps the first — so any caller who styled the box (a background,
     a radius) silently took the ratio away, which is the one thing this
     component is for. merge() folds them into one declaration list with the
     caller last, so a caller who really means to override the ratio still can.
     Pinned by a guard in the package's own suite. --}}
<div {{ $attributes->merge(['style' => 'aspect-ratio: '.$aspectValue])->class([$classes]) }}>
    {{ $slot }}
</div>
