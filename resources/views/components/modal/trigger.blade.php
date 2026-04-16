@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('modal.trigger', 'base', '', $scope);
@endphp

{{-- Modal trigger — opens the parent modal when clicked --}}
<div
    x-on:click="show()"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
