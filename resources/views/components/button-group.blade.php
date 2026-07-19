@props([
    // Accessible name for the group ("Text alignment", "Save options").
    'label' => null,
    // horizontal (default) welds left-to-right; vertical welds top-to-bottom.
    'orientation' => 'horizontal',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $orientationValue = in_array($orientation, ['horizontal', 'vertical'], true)
        ? $orientation
        : WireKit::validateProp('button-group', 'orientation', $orientation, ['horizontal', 'vertical']);

    // A `match` (not a ternary) so the drift auditor can statically harvest the
    // class strings.
    $orientationClasses = match ($orientationValue) {
        'vertical' => 'flex-col items-stretch',
        default => 'flex-row items-center',
    };

    $classes = WireKit::resolveClasses('button-group', 'base', implode(' ', [
        'inline-flex',
        $orientationClasses,
    ]), $scope);
@endphp

{{-- role="group" + a name is the whole a11y contract here.

     Deliberately NO roving tabindex: this is a VISUAL weld, so every control
     inside stays individually Tab-reachable — the behavior developers expect
     from a button group. A roving keyboard model belongs to
     <x-wirekit::toolbar>, and duplicating it here would give two components
     two different answers for the same question. --}}
<div
    role="group"
    @if($label) aria-label="{{ $label }}" @endif
    data-wk-button-group
    data-orientation="{{ $orientationValue }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
