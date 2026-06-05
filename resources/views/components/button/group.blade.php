@props([
    // 'horizontal' (default) joins children left-to-right; 'vertical' stacks them.
    // Literal default (a sub-component, so it does not dotted-read its own config
    // — mirrors avatar.group / accordion.item).
    'orientation' => 'horizontal',
    // Accessible name for the group (e.g. "View mode"). Recommended when the
    // group acts as a single control (segmented toggle, pagination cluster).
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $orientation = $orientation === 'vertical' ? 'vertical' : 'horizontal';

    // Joining (squared inner radii + collapsed 1px seam + z-index lift on the
    // active child) lives in the .wk-button-group CSS class (dist/wirekit.css),
    // driven by the data-orientation attribute. RTL-safe (logical properties).
    $classes = WireKit::resolveClasses('button.group', 'base', 'wk-button-group', $scope);
@endphp

<div
    role="group"
    @if($label) aria-label="{{ $label }}" @endif
    data-orientation="{{ $orientation }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
