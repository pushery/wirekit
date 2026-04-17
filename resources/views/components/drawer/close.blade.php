@props([
    'scope' => null,
])

{{-- Drawer close button — delegates to shared overlay-close partial --}}
@include('wirekit::components.partials.overlay-close', ['component' => 'drawer', 'scope' => $scope])
