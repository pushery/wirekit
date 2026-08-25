{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'inline' => false,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('center', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $inline = BooleanProp::from($inline, false);

    // Default to `flex w-full` so the Center fills its parent and the
    // centering is actually visible (without `w-full`, a bare block-level
    // `display:flex` div in some prose / preview wrappers collapses to its
    // intrinsic content width — defeating the component's purpose).
    // Inline mode keeps the natural `inline-flex` shrink-to-content sizing
    // since it's used for inline badges / chips inside text flow.
    $classes = WireKit::resolveClasses('center', 'base', implode(' ', [
        $inline ? 'inline-flex' : 'flex w-full',
        'items-center justify-center',
    ]), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('center', (string) $as);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
