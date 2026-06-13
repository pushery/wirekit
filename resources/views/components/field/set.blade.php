@props([
    'legend' => null,
    'hint' => null,
    'scope' => null,
])

@php
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
