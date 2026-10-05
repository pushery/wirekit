{{-- optimistic-ui: n/a — client-only
     The drawer's close button, rendered by the shared overlay-close partial. It closes. --}}
@props([
    'scope' => null,
    // A Cancel: closes as a dismissal and announces it with `via: 'cancel'`, where the
    // default closes the way a finished action does and announces nothing.
    'dismiss' => false,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('drawer.close', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an unbound attribute to a string, and 'false' is truthy, so `dismiss="false"`
    // would otherwise make a Cancel of a close.
    $dismiss = BooleanProp::from($dismiss, false);
@endphp

{{-- Drawer close button — delegates to shared overlay-close partial --}}
@include('wirekit::components.partials.overlay-close', ['component' => 'drawer', 'scope' => $scope, 'dismiss' => $dismiss])
