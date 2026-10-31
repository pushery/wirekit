{{-- optimistic-ui: n/a — client-only
     The modal's close button, rendered by the shared overlay-close partial. It closes. --}}
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
    \Pushery\WireKit\WireKit::warnUnknownProps('modal.close', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an unbound attribute to a string, and 'false' is truthy, so `dismiss="false"`
    // would otherwise make a Cancel of a close.
    $dismiss = BooleanProp::from($dismiss, false, 'modal.close', 'dismiss');
@endphp

{{-- Modal close button — delegates to shared overlay-close partial --}}
@include('wirekit::components.partials.overlay-close', ['component' => 'modal', 'scope' => $scope, 'dismiss' => $dismiss])
