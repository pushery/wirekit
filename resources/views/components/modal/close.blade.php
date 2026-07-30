{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

{{-- Modal close button — delegates to shared overlay-close partial --}}
@include('wirekit::components.partials.overlay-close', ['component' => 'modal', 'scope' => $scope])
