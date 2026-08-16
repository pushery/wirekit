{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'legend' => null,
    'hint' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('field.set', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // <fieldset> is the WCAG-recommended grouping container for related controls
    // (radio groups, checkbox groups, address blocks). The <legend> is its group
    // label, announced by screen readers before each control in the set.
    //
    // We reset the native fieldset chrome (border / padding / margin) and provide
    // our own spacing. `min-w-0` defeats the fieldset's intrinsic `min-width: min-content`
    // quirk that otherwise prevents it from shrinking inside flex/grid layouts.
    $classes = WireKit::resolveClasses('field.set', 'base', 'min-w-0 border-0 p-0 m-0', $scope);
@endphp

<fieldset {{ $attributes->class([$classes]) }}>
    @if($legend)
        <legend class="mb-1 text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $legend }}</legend>
    @endif
    @if($hint)
        <p class="mb-3 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    {{-- Grouped controls. The space-y gap keeps the fields evenly spaced. --}}
    <div class="space-y-3">
        {{ $slot }}
    </div>
</fieldset>
