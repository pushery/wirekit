{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // Accessible name for the group ("Text alignment", "Save options").
    'label' => null,
    // horizontal (default) welds along the line; vertical welds top-to-bottom.
    'orientation' => 'horizontal',
    // sm | md | lg | xl | 2xl — below that width the group stacks: it welds on the block axis instead
    // of the inline one, so a row whose labels fit on a desktop does not run off a phone. Only
    // meaningful on a horizontal group; a vertical one already stacks at every width.
    'stackBelow' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('button-group', $attributes->getAttributes());

    $orientationValue = in_array($orientation, ['horizontal', 'vertical'], true)
        ? $orientation
        : WireKit::validateProp('button-group', 'orientation', $orientation, ['horizontal', 'vertical']);

    // A `match` (not a ternary) so the drift auditor can statically harvest the
    // class strings.
    $orientationClasses = match ($orientationValue) {
        'vertical' => 'flex-col items-stretch',
        default => 'flex-row items-center',
    };

    // `stackBelow` names the width below which the group becomes a column, through
    // `[data-wk-button-group][data-stack-below="…"]` in dist/wirekit.css, which also stretches the
    // stacked children to one width. An unknown value is reported through the strictness gate and
    // stacks nothing; a vertical group drops it, because it already stacks at every width.
    $stackBelow = filled($stackBelow) ? (string) $stackBelow : null;

    if ($stackBelow !== null && ! in_array($stackBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('button-group', 'stackBelow', $stackBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
        $stackBelow = null;
    }

    if ($orientationValue === 'vertical') {
        $stackBelow = null;
    }

    $classes = WireKit::resolveClasses('button-group', 'base', implode(' ', [
        'inline-flex',
        $orientationClasses,
    ]), $scope);
@endphp

{{-- role="group" + a name is the whole a11y contract here.

     Deliberately no roving tabindex: this is a visual weld, so every control
     inside stays individually Tab-reachable — the behavior developers expect
     from a button group. A roving keyboard model belongs to
     <x-wirekit::toolbar>, and duplicating it here would give two components
     two different answers for the same question. --}}
<div
    @unless($attributes->has('role')) role="group" @endunless
    @if($label) @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($label) }}" @endunless @endif
    data-wk-button-group
    data-orientation="{{ $orientationValue }}"
    @if($stackBelow) data-stack-below="{{ $stackBelow }}" @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{ $slot }}
</div>
