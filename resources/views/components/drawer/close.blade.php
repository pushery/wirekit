{{-- optimistic-ui: n/a — client-only
     The drawer's close button, rendered by the shared overlay-close partial. It closes. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('drawer.close', $attributes->getAttributes());
@endphp

{{-- Drawer close button — delegates to shared overlay-close partial --}}
@include('wirekit::components.partials.overlay-close', ['component' => 'drawer', 'scope' => $scope])
