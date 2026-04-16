@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('dropdown.trigger', 'base', '', $scope);
@endphp

{{-- Dropdown trigger — wraps the button/link that opens the menu --}}
<div
    x-ref="trigger"
    x-on:click="toggle()"
    aria-haspopup="menu"
    x-bind:aria-expanded="open"
    x-bind:aria-controls="$el.closest('[data-wk-panel-id]')?.dataset.wkPanelId"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
