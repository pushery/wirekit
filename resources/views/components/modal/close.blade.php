@props([
    'scope' => null,
])

{{-- Modal close button — delegates to shared overlay-close partial --}}
@include('wirekit::components.partials.overlay-close', ['component' => 'modal', 'scope' => $scope])
